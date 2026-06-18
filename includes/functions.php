<?php
/**
 * System Utility & Helper Functions
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Auto-create uploads directories on include if they do not exist
$required_directories = [
    UPLOAD_PATH,
    UPLOAD_PATH . '/profile_photos',
    UPLOAD_PATH . '/payment_proofs',
    UPLOAD_PATH . '/upi_qr',
    UPLOAD_PATH . '/event_banners',
    UPLOAD_PATH . '/certificate_templates',
    UPLOAD_PATH . '/gallery_photos',
    BASE_PATH . '/qr_codes',
    BASE_PATH . '/exports'
];

foreach ($required_directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

/**
 * HTML Escaping shorthand
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Check if the email belongs to an allowed college domain
 */
function is_valid_college_email($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $parts = explode('@', $email);
    $domain = end($parts);
    return in_array(strtolower($domain), ALLOWED_DOMAINS);
}

/**
 * Generate a 6-digit numeric OTP code
 */
function generate_otp() {
    return strval(rand(100000, 999999));
}

/**
 * Log activity in activity_logs table
 */
function log_activity($user_id, $action, $details = null) {
    global $conn;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, $ip]);
    } catch (Exception $e) {
        // Fail silently so database log issues don't crash page actions
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Handle File Uploads securely
 */
function handle_file_upload($file_array, $target_subfolder, $allowed_types = ['jpg', 'jpeg', 'png'], $max_size = 5242880) { // 5MB
    if (!isset($file_array) || $file_array['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error or no file selected.'];
    }

    $file_size = $file_array['size'];
    $file_tmp = $file_array['tmp_name'];
    $file_name = basename($file_array['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Validate size
    if ($file_size > $max_size) {
        return ['success' => false, 'message' => 'File size exceeds allowed limit (Max: 5MB).'];
    }

    // Validate extension
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file format. Allowed formats: ' . implode(', ', $allowed_types)];
    }

    // Verify actual image type (MIME check)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file_tmp);
    finfo_close($finfo);

    $valid_mimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    if (!in_array($mime, $valid_mimes)) {
        return ['success' => false, 'message' => 'Upload rejected: Invalid image contents.'];
    }

    // Generate unique file name
    $new_file_name = bin2hex(random_bytes(16)) . '.' . $file_ext;
    $target_dir = UPLOAD_PATH . '/' . trim($target_subfolder, '/');
    $target_filepath = $target_dir . '/' . $new_file_name;

    if (move_uploaded_file($file_tmp, $target_filepath)) {
        return ['success' => true, 'filename' => $new_file_name];
    } else {
        return ['success' => false, 'message' => 'Failed to write file to disk. Check directory permissions.'];
    }
}

/**
 * Set flash message in session
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'danger', 'info', 'warning'
        'message' => $message
    ];
}

/**
 * Display flash message inside dashboard
 */
function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        
        $class = 'alert-' . $msg['type'];
        $icon = 'info';
        if ($msg['type'] === 'success') $icon = 'check-circle';
        if ($msg['type'] === 'danger') $icon = 'alert-triangle';
        if ($msg['type'] === 'warning') $icon = 'alert-circle';

        echo '<div class="alert ' . $class . ' show-alert-anim">
                <i data-lucide="' . $icon . '" class="alert-icon"></i>
                <div class="alert-content">' . e($msg['message']) . '</div>
                <button type="button" class="alert-close-btn" onclick="this.parentElement.remove();">&times;</button>
              </div>';
    }
}

/**
 * Programmatically extracts the design preview image from a Canva link,
 * downloads it, and saves it locally under uploads/certificate_templates/
 *
 * @param string $canva_url Canva design shared link
 * @return array ['success' => bool, 'filename' => string|null, 'message' => string|null]
 */
function sync_canva_template($canva_url) {
    if (empty($canva_url)) {
        return ['success' => false, 'message' => 'Canva URL is empty.'];
    }

    // If input is an HTML embed block, extract the Canva URL from src or href
    if (preg_match('/<[^>]+>/', $canva_url)) {
        if (preg_match('/href=["\']([^"\']*canva\.com\/design\/[^"\']*)["\']/i', $canva_url, $url_match) ||
            preg_match('/src=["\']([^"\']*canva\.com\/design\/[^"\']*)["\']/i', $canva_url, $url_match)) {
            $canva_url = html_entity_decode($url_match[1]);
        }
    }

    // Extract design ID from Canva URLs of style /design/DAF0d-i6Huo/...
    // If the URL already contains the design ID directly, no need to resolve redirects.
    $matches = [];
    if (!preg_match('/\/design\/([A-Za-z0-9_-]+)/', $canva_url, $matches)) {
        // Resolve short links / redirects (like canva.link/...)
        $resolved = false;
        
        // 1. Try fast HEAD request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $canva_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request only
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");
        curl_exec($ch);
        $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if (!empty($effective_url) && preg_match('/\/design\/([A-Za-z0-9_-]+)/', $effective_url, $matches)) {
            $canva_url = $effective_url;
            $resolved = true;
        }

        // 2. Fallback to GET request if HEAD failed or was blocked by CDN
        if (!$resolved) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $canva_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_NOBODY, false); // GET request
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; Twitterbot/1.0; +http://dev.twitter.com/cards)");
            curl_exec($ch);
            $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);

            if (!empty($effective_url) && preg_match('/\/design\/([A-Za-z0-9_-]+)/', $effective_url, $matches)) {
                $canva_url = $effective_url;
                $resolved = true;
            }
        }
        
        if (!$resolved) {
            return ['success' => false, 'message' => 'Invalid Canva URL. Make sure it resolves to a page containing "/design/[DESIGN_ID]".'];
        }
    }
    
    $design_id = '';
    $design_token = '';
    if (preg_match('/\/design\/([A-Za-z0-9_-]+)\/([A-Za-z0-9_-]+)/', $canva_url, $path_matches)) {
        $design_id = $path_matches[1];
        $design_token = $path_matches[2];
    } elseif (preg_match('/\/design\/([A-Za-z0-9_-]+)/', $canva_url, $path_matches)) {
        $design_id = $path_matches[1];
    }

    if (empty($design_id)) {
        return ['success' => false, 'message' => 'Invalid Canva URL format.'];
    }

    $embed_url = "https://www.canva.com/design/" . $design_id;
    $view_url = "https://www.canva.com/design/" . $design_id;
    if (!empty($design_token)) {
        $embed_url .= "/" . $design_token;
        $view_url .= "/" . $design_token;
    }
    $embed_url .= "/view?embed";
    $view_url .= "/view";
    
    $thumbnail_url = null;
    
    // 1. Try Canva oEmbed endpoint first
    $oembed_url = "https://api.canva.com/_spi/presentation/_oembed?url=" . urlencode($canva_url);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $oembed_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['thumbnail_url'])) {
            $thumbnail_url = $data['thumbnail_url'];
        }
    }
    
    // Try to scrape the S3 export image URL from the embed page first, or if oEmbed returned a blocked /screen URL
    if (!$thumbnail_url || (strpos($thumbnail_url, 'canva.com/design/') !== false && strpos($thumbnail_url, '/screen') !== false)) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $embed_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");
        
        $html = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200 && $html) {
            if (preg_match('/(https:\/\/document-export\.canva\.com\/[^\s"\']*)/i', $html, $s3_matches)) {
                $thumbnail_url = html_entity_decode($s3_matches[1]);
            }
        }
    }
    
    // 2. If oEmbed and S3 scraping fails, try scraping the embed view page for og:image/twitter:image
    if (!$thumbnail_url) {
        $crawlers = [
            "facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_voiced.php)",
            "Mozilla/5.0 (compatible; Twitterbot/1.0; +http://dev.twitter.com/cards)",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        ];
        
        foreach ($crawlers as $crawler) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $embed_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, $crawler);
            
            $html = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code == 200 && $html) {
                if (preg_match('/<meta\s+property="og:image"\s+content="([^"]+)"/i', $html, $img_matches)) {
                    $thumbnail_url = $img_matches[1];
                    break;
                }
                if (preg_match('/<meta\s+name="twitter:image"\s+content="([^"]+)"/i', $html, $img_matches)) {
                    $thumbnail_url = $img_matches[1];
                    break;
                }
                if (preg_match('/"thumbnail"\s*:\s*"([^"]+)"/i', $html, $img_matches)) {
                    $thumbnail_url = stripcslashes($img_matches[1]);
                    break;
                }
            }
        }
    }
    
    // 3. Fallback to scraping the standard public view URL
    if (!$thumbnail_url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $view_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; Twitterbot/1.0; +http://dev.twitter.com/cards)");
        
        $html = curl_exec($ch);
        curl_close($ch);
        
        if ($html) {
            if (preg_match('/<meta\s+property="og:image"\s+content="([^"]+)"/i', $html, $img_matches)) {
                $thumbnail_url = $img_matches[1];
            } elseif (preg_match('/<meta\s+name="twitter:image"\s+content="([^"]+)"/i', $html, $img_matches)) {
                $thumbnail_url = $img_matches[1];
            }
        }
    }
    
    if (!$thumbnail_url) {
        return [
            'success' => false,
            'message' => 'Could not extract preview image from Canva. Please ensure the design is set to Public (anyone with the link can view).'
        ];
    }
    if ($thumbnail_url) {
        $thumbnail_url = str_replace('?type=thumbnail', '', $thumbnail_url);
    }
    
    // 4. Download the template preview image
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $thumbnail_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");
    
    $image_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code != 200 || !$image_data) {
        // Fall back to using the remote Canva URL directly instead of failing due to Cloudflare 403 blocks
        return [
            'success' => true,
            'filename' => $thumbnail_url,
            'image_url' => $thumbnail_url
        ];
    }
    
    // Extract file extension or default to png
    $ext = 'png';
    $url_path = parse_url($thumbnail_url, PHP_URL_PATH);
    if ($url_path) {
        $path_info = pathinfo($url_path);
        if (isset($path_info['extension'])) {
            $parsed_ext = strtolower($path_info['extension']);
            if (in_array($parsed_ext, ['png', 'jpg', 'jpeg'])) {
                $ext = $parsed_ext;
            }
        }
    }
    
    $filename = 'canva_' . $design_id . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target_dir = UPLOAD_PATH . '/certificate_templates';
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $target_path = $target_dir . '/' . $filename;
    if (file_put_contents($target_path, $image_data) === false) {
        return ['success' => false, 'message' => 'Failed to save template file to storage directory.'];
    }
    
    return [
        'success' => true,
        'filename' => $filename,
        'image_url' => BASE_URL . 'uploads/certificate_templates/' . $filename
    ];
}

/**
 * Settings Table Helper: Retrieve value
 */
function get_setting($key, $default = '') {
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Settings Table Helper: Insert/Update value
 */
function set_setting($key, $value) {
    global $conn;
    try {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to save setting $key: " . $e->getMessage());
        return false;
    }
}



