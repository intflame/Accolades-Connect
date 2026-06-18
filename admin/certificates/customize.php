<?php
/**
 * Admin: Customize Certificate Layout popup window
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Access Control
require_role('admin');

$event_id = intval($_GET['event_id'] ?? 0);
if ($event_id <= 0) {
    echo "<h3>Invalid Event ID.</h3>";
    exit();
}

// Fetch event details
try {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
} catch (Exception $e) {
    error_log("Failed to fetch event: " . $e->getMessage());
    $event = false;
}

if (!$event) {
    echo "<h3>Event not found.</h3>";
    exit();
}

$errors = [];
$success_msg = "";

// Decode custom template layout configurations
$layout_config = json_decode($event['certificate_layout_config'] ?? '', true) ?: [];

// Header (Title) Settings
$title_enabled = intval($layout_config['title_enabled'] ?? 1);
$title_top = $layout_config['title_top'] ?? 20;
$title_left = $layout_config['title_left'] ?? 50;
$title_font_size = $layout_config['title_font_size'] ?? 2.2;
$title_color = $layout_config['title_color'] ?? '#b45309';
$title_font = $layout_config['title_font'] ?? 'Helvetica';
$title_align = $layout_config['title_align'] ?? 'center';

// Sub Header (Subtitle) Settings
$subtitle_enabled = intval($layout_config['subtitle_enabled'] ?? 1);
$subtitle_text = $layout_config['subtitle_text'] ?? 'This is proudly presented to';
$subtitle_top = $layout_config['subtitle_top'] ?? 30;
$subtitle_left = $layout_config['subtitle_left'] ?? 50;
$subtitle_font_size = $layout_config['subtitle_font_size'] ?? 1.1;
$subtitle_color = $layout_config['subtitle_color'] ?? '#475569';
$subtitle_font = $layout_config['subtitle_font'] ?? 'Helvetica';
$subtitle_align = $layout_config['subtitle_align'] ?? 'center';

// Name Settings
$name_enabled = intval($layout_config['name_enabled'] ?? 1);
$name_top = $layout_config['name_top'] ?? 48;
$name_left = $layout_config['name_left'] ?? 50;
$name_font_size = $layout_config['name_font_size'] ?? 3.2;
$name_color = $layout_config['name_color'] ?? '#b45309';
$name_font = $layout_config['name_font'] ?? 'GreatVibes';
$name_align = $layout_config['name_align'] ?? 'center';

// Description Settings
$details_enabled = intval($layout_config['details_enabled'] ?? 1);
$details_text_template = $layout_config['details_text_template'] ?? "of {course} (Batch {batch}) for successfully participating as a {role} in the event {event_name}, organized by the Department on {event_date}.";
$details_top = $layout_config['details_top'] ?? 61;
$details_left = $layout_config['details_left'] ?? 50;
$details_font_size = $layout_config['details_font_size'] ?? 0.95;
$details_color = $layout_config['details_color'] ?? '#475569';
$details_font = $layout_config['details_font'] ?? 'Helvetica';
$details_align = $layout_config['details_align'] ?? 'center';

// QR Code Settings
$qr_enabled = $layout_config['qr_enabled'] ?? 0;
$qr_top = $layout_config['qr_top'] ?? 85;
$qr_left = $layout_config['qr_left'] ?? 15;
$qr_size = $layout_config['qr_size'] ?? 20;

// Verification Code Settings (Disabled for customized templates)
$code_enabled = 0;
$code_top = $layout_config['code_top'] ?? 88;
$code_left = $layout_config['code_left'] ?? 50;
$code_color = $layout_config['code_color'] ?? '#0f172a';
$code_align = $layout_config['code_align'] ?? 'center';

// Signatures Settings
$signatures_enabled = $layout_config['signatures_enabled'] ?? 0;
$sig_left_text = $layout_config['sig_left_text'] ?? 'Event Coordinator';
$sig_left_top = $layout_config['sig_left_top'] ?? 80;
$sig_left_left = $layout_config['sig_left_left'] ?? 15;
$sig_right_text = $layout_config['sig_right_text'] ?? 'Head of Department';
$sig_right_top = $layout_config['sig_right_top'] ?? 80;
$sig_right_right = $layout_config['sig_right_right'] ?? 15;
$sig_color = $layout_config['sig_color'] ?? '#475569';
$sig_font = $layout_config['sig_font'] ?? 'Helvetica';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_request();

    $canva_template_link = trim($_POST['canva_template_link'] ?? '');
    $fetched_template_filename = trim($_POST['fetched_template_filename'] ?? '');
    
    // Read Title settings
    $title_enabled = isset($_POST['title_enabled']) ? 1 : 0;
    $certificate_title = trim($_POST['certificate_title'] ?? 'Certificate of Activity');
    $title_top = floatval($_POST['title_top'] ?? 20);
    $title_left = floatval($_POST['title_left'] ?? 50);
    $title_font_size = floatval($_POST['title_font_size'] ?? 2.2);
    $title_color = trim($_POST['title_color'] ?? '#b45309');
    $title_font = trim($_POST['title_font'] ?? 'Helvetica');
    $title_align = trim($_POST['title_align'] ?? 'center');

    // Read Subtitle settings
    $subtitle_enabled = isset($_POST['subtitle_enabled']) ? 1 : 0;
    $subtitle_text = trim($_POST['subtitle_text'] ?? 'This is proudly presented to');
    $subtitle_top = floatval($_POST['subtitle_top'] ?? 30);
    $subtitle_left = floatval($_POST['subtitle_left'] ?? 50);
    $subtitle_font_size = floatval($_POST['subtitle_font_size'] ?? 1.1);
    $subtitle_color = trim($_POST['subtitle_color'] ?? '#475569');
    $subtitle_font = trim($_POST['subtitle_font'] ?? 'Helvetica');
    $subtitle_align = trim($_POST['subtitle_align'] ?? 'center');

    // Read Name settings
    $name_enabled = isset($_POST['name_enabled']) ? 1 : 0;
    $name_top = floatval($_POST['name_top'] ?? 48);
    $name_left = floatval($_POST['name_left'] ?? 50);
    $name_font_size = floatval($_POST['name_font_size'] ?? 3.2);
    $name_color = trim($_POST['name_color'] ?? '#b45309');
    $name_font = trim($_POST['name_font'] ?? 'GreatVibes');
    $name_align = trim($_POST['name_align'] ?? 'center');

    // Read Description settings
    $details_enabled = isset($_POST['details_enabled']) ? 1 : 0;
    $details_text_template = trim($_POST['details_text_template'] ?? '');
    $details_top = floatval($_POST['details_top'] ?? 61);
    $details_left = floatval($_POST['details_left'] ?? 50);
    $details_font_size = floatval($_POST['details_font_size'] ?? 0.95);
    $details_color = trim($_POST['details_color'] ?? '#475569');
    $details_font = trim($_POST['details_font'] ?? 'Helvetica');
    $details_align = trim($_POST['details_align'] ?? 'center');

    // Read QR settings
    $qr_enabled = isset($_POST['qr_enabled']) ? 1 : 0;
    $qr_top = floatval($_POST['qr_top'] ?? 85);
    $qr_left = floatval($_POST['qr_left'] ?? 15);
    $qr_size = floatval($_POST['qr_size'] ?? 20);

    // Verification Code is disabled for custom templates
    $code_enabled = 0;
    $code_top = 88;
    $code_left = 50;
    $code_color = '#0f172a';
    $code_align = 'center';

    // Read Signature settings
    $signatures_enabled = isset($_POST['signatures_enabled']) ? 1 : 0;
    $sig_left_text = trim($_POST['sig_left_text'] ?? 'Event Coordinator');
    $sig_left_top = floatval($_POST['sig_left_top'] ?? 80);
    $sig_left_left = floatval($_POST['sig_left_left'] ?? 15);
    $sig_right_text = trim($_POST['sig_right_text'] ?? 'Head of Department');
    $sig_right_top = floatval($_POST['sig_right_top'] ?? 80);
    $sig_right_right = floatval($_POST['sig_right_right'] ?? 15);
    $sig_color = trim($_POST['sig_color'] ?? '#475569');
    $sig_font = trim($_POST['sig_font'] ?? 'Helvetica');

    $certificate_coordinator = $sig_left_text; // Sync with legacy column
    $certificate_hod = $sig_right_text; // Sync with legacy column
    $certificate_template_filename = $event['certificate_template']; // keep old by default

    // Check if manual file uploaded
    $manual_uploaded = false;
    if (isset($_FILES['manual_template_file']) && $_FILES['manual_template_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['manual_template_file'];
        $tmp_name = $file['tmp_name'];
        $name = basename($file['name']);
        
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'])) {
            $filename = 'manual_' . $event_id . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target_dir = UPLOAD_PATH . '/certificate_templates';
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            $target_path = $target_dir . '/' . $filename;
            
            if (move_uploaded_file($tmp_name, $target_path)) {
                // Delete old file if exists
                if (!empty($event['certificate_template'])) {
                    $old_path = UPLOAD_PATH . '/certificate_templates/' . $event['certificate_template'];
                    if (!preg_match('/^https?:\/\//i', $event['certificate_template']) && file_exists($old_path)) {
                        @unlink($old_path);
                    }
                }
                $certificate_template_filename = $filename;
                $manual_uploaded = true;
            } else {
                $errors[] = "Failed to save the uploaded template file.";
            }
        } else {
            $errors[] = "Invalid file type. Only PDF, PNG, and JPG files are accepted.";
        }
    }

    if (!$manual_uploaded) {
        if (empty($canva_template_link)) {
            // Deleted template link
            if (!empty($event['certificate_template'])) {
                $old_path = UPLOAD_PATH . '/certificate_templates/' . $event['certificate_template'];
                if (!preg_match('/^https?:\/\//i', $event['certificate_template']) && file_exists($old_path)) {
                    @unlink($old_path);
                }
            }
            $certificate_template_filename = null;
        } else {
            // Canva URL exists
            if (!empty($fetched_template_filename) && $fetched_template_filename !== $event['certificate_template']) {
                // Synced via AJAX in browser
                $is_remote_fetched = preg_match('/^https?:\/\//i', $fetched_template_filename);
                if ($is_remote_fetched || file_exists(UPLOAD_PATH . '/certificate_templates/' . $fetched_template_filename)) {
                    if (!empty($event['certificate_template'])) {
                        $old_path = UPLOAD_PATH . '/certificate_templates/' . $event['certificate_template'];
                        if (!preg_match('/^https?:\/\//i', $event['certificate_template']) && file_exists($old_path)) {
                            @unlink($old_path);
                        }
                    }
                    $certificate_template_filename = $fetched_template_filename;
                }
            } elseif ($canva_template_link !== $event['canva_template_link'] || empty($event['certificate_template'])) {
                // Link changed directly or no image downloaded yet
                $sync_res = sync_canva_template($canva_template_link);
                if ($sync_res['success']) {
                    if (!empty($event['certificate_template'])) {
                        $old_path = UPLOAD_PATH . '/certificate_templates/' . $event['certificate_template'];
                        if (!preg_match('/^https?:\/\//i', $event['certificate_template']) && file_exists($old_path)) {
                            @unlink($old_path);
                        }
                    }
                    $certificate_template_filename = $sync_res['filename'];
                } else {
                    $errors[] = "Canva Sync Error: " . $sync_res['message'];
                }
            }
        }
    }

    $layout_config_json = json_encode([
        'title_enabled' => $title_enabled,
        'title_top' => $title_top,
        'title_left' => $title_left,
        'title_font_size' => $title_font_size,
        'title_color' => $title_color,
        'title_font' => $title_font,
        'title_align' => $title_align,
        
        'subtitle_enabled' => $subtitle_enabled,
        'subtitle_text' => $subtitle_text,
        'subtitle_top' => $subtitle_top,
        'subtitle_left' => $subtitle_left,
        'subtitle_font_size' => $subtitle_font_size,
        'subtitle_color' => $subtitle_color,
        'subtitle_font' => $subtitle_font,
        'subtitle_align' => $subtitle_align,
        
        'name_enabled' => $name_enabled,
        'name_top' => $name_top,
        'name_left' => $name_left,
        'name_font_size' => $name_font_size,
        'name_color' => $name_color,
        'name_font' => $name_font,
        'name_align' => $name_align,
        
        'details_enabled' => $details_enabled,
        'details_text_template' => $details_text_template,
        'details_top' => $details_top,
        'details_left' => $details_left,
        'details_font_size' => $details_font_size,
        'details_color' => $details_color,
        'details_font' => $details_font,
        'details_align' => $details_align,
        
        'qr_enabled' => $qr_enabled,
        'qr_top' => $qr_top,
        'qr_left' => $qr_left,
        'qr_size' => $qr_size,
        
        'code_enabled' => $code_enabled,
        'code_top' => $code_top,
        'code_left' => $code_left,
        'code_color' => $code_color,
        'code_align' => $code_align,
        
        'signatures_enabled' => $signatures_enabled,
        'sig_left_text' => $sig_left_text,
        'sig_left_top' => $sig_left_top,
        'sig_left_left' => $sig_left_left,
        'sig_right_text' => $sig_right_text,
        'sig_right_top' => $sig_right_top,
        'sig_right_right' => $sig_right_right,
        'sig_color' => $sig_color,
        'sig_font' => $sig_font
    ]);

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                UPDATE events SET
                    canva_template_link = ?,
                    certificate_template = ?,
                    certificate_title = ?,
                    certificate_coordinator = ?,
                    certificate_hod = ?,
                    certificate_layout_config = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $canva_template_link,
                $certificate_template_filename,
                $certificate_title,
                $certificate_coordinator,
                $certificate_hod,
                $layout_config_json,
                $event_id
            ]);

            log_activity($_SESSION['user_id'], 'event_certificate_customized', "Customized certificate layout for event: {$event['name']} (ID: $event_id)");
            $success_msg = "Certificate layout saved successfully!";

            // Refresh local variables
            $event['canva_template_link'] = $canva_template_link;
            $event['certificate_template'] = $certificate_template_filename;
            $event['certificate_title'] = $certificate_title;
            $event['certificate_coordinator'] = $certificate_coordinator;
            $event['certificate_hod'] = $certificate_hod;
        } catch (Exception $e) {
            error_log("Failed to save certificate layout: " . $e->getMessage());
            $errors[] = "Failed to update layout in database.";
        }
    }
}

// Resolve current saved background template url
$saved_template_url = '';
if (!empty($event['certificate_template'])) {
    if (preg_match('/^https?:\/\//i', $event['certificate_template'])) {
        $saved_template_url = $event['certificate_template'];
    } elseif (file_exists(UPLOAD_PATH . '/certificate_templates/' . $event['certificate_template'])) {
        $saved_template_url = BASE_URL . 'uploads/certificate_templates/' . $event['certificate_template'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customize Certificate: <?php echo e($event['name']); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=Great+Vibes&family=Montserrat:wght@400;500;600;700&family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
    <style>
        :root {
            --cert-font-title: 'Cinzel Decorative', Georgia, serif;
            --cert-font-script: 'Great Vibes', cursive;
            --cert-font-sans: 'Montserrat', sans-serif;
        }

        body {
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            background-color: #0b0f19;
            background-image: 
                radial-gradient(at 0% 0%, rgba(248, 123, 27, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(255, 255, 255, 0.02) 0px, transparent 50%);
        }

        .title-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background: rgba(15, 23, 42, 0.8);
            border-bottom: 1px solid var(--border-color);
            backdrop-filter: blur(8px);
            z-index: 10;
        }

        .workspace {
            display: grid;
            grid-template-columns: 460px 1fr;
            flex-grow: 1;
            height: calc(100vh - 70px);
            overflow: hidden;
        }

        .controls-sidebar {
            background: rgba(17, 24, 39, 0.5);
            border-right: 1px solid var(--border-color);
            padding: 1.5rem;
            overflow-y: auto;
            height: 100%;
        }

        .preview-area {
            display: flex;
            justify-content: center;
            align-items: center;
            background: rgba(0, 0, 0, 0.3);
            padding: 2rem;
            height: 100%;
            overflow: auto;
        }

        /* Certificate Live Preview Canvas */
        .certificate-wrapper {
            position: relative;
            width: 100%;
            max-width: 800px;
            aspect-ratio: 1.414; /* A4 Landscape standard ratio */
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            border-radius: 4px;
            overflow: hidden;
            background-color: #161e2e;
        }

        .certificate-canvas {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 6% 8%;
            text-align: center;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            color: #1e293b;
        }

        /* Font classes matching student certificate viewer */
        .cert-header {
            font-family: var(--cert-font-sans);
            font-size: 0.95rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            margin-bottom: 2%;
            font-weight: 600;
            color: #475569;
        }

        .cert-title {
            font-family: var(--cert-font-title);
            font-size: 2.2rem;
            margin-bottom: 3%;
            color: #0f172a;
            letter-spacing: 0.05em;
        }

        .cert-statement {
            font-family: var(--cert-font-sans);
            font-size: 0.9rem;
            color: #334155;
            margin-bottom: 2%;
            line-height: 1.6;
        }

        .cert-name {
            font-family: var(--cert-font-script);
            font-size: 3.2rem;
            color: #d97706;
            margin: 1.5% 0;
            line-height: 1;
            font-weight: bold;
        }

        .cert-details {
            font-family: var(--cert-font-sans);
            font-size: 0.8rem;
            font-weight: 500;
            color: #334155;
            margin-bottom: 4%;
            max-width: 85%;
            line-height: 1.6;
        }

        .cert-details strong {
            color: #0f172a;
            font-weight: 700;
        }

        .cert-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            width: 100%;
            margin-top: auto;
        }

        .signature-block {
            text-align: center;
            width: 25%;
        }

        .signature-line {
            border-top: 1.5px solid #475569;
            margin-bottom: 0.5rem;
        }

        .signature-title {
            font-family: var(--cert-font-sans);
            font-size: 0.65rem;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .verification-block {
            text-align: center;
            font-family: var(--cert-font-sans);
            font-size: 0.6rem;
            color: #64748b;
        }

        .cert-code {
            font-family: monospace;
            font-size: 0.8rem;
            font-weight: 700;
            color: #0f172a;
            margin-top: 0.15rem;
            letter-spacing: 0.05em;
        }

        /* Form widgets */
        .section-header {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--primary);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.4rem;
            margin: 1.5rem 0 1rem 0;
        }

        .form-group-flex {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group-flex > div {
            flex: 1;
        }

        .slider-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.8rem;
            margin-bottom: 0.75rem;
        }

        .slider-header {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            margin-bottom: 0.3rem;
            color: var(--text-main);
        }

        .slider-header span.val {
            color: var(--primary);
            font-weight: 600;
        }

        /* Tabs Styles */
        .tabs-nav {
            display: flex;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.2rem;
            margin-bottom: 1.25rem;
            gap: 0.25rem;
        }

        .tab-btn {
            flex: 1;
            background: none;
            border: none;
            color: var(--text-muted);
            padding: 0.5rem 0.25rem;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            transition: all 0.15s ease;
        }

        .tab-btn:hover {
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.02);
        }

        .tab-btn.active {
            color: #ffffff;
            background: linear-gradient(135deg, var(--primary) 0%, #f97316 100%);
            box-shadow: 0 4px 10px rgba(248, 123, 27, 0.15);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Content Toggle Cards & Switches */
        .content-toggle-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.01);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
        }

        .content-toggle-card:hover {
            border-color: rgba(248, 123, 27, 0.25);
            background: rgba(255, 255, 255, 0.03);
        }

        .content-toggle-info {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
        }

        .content-toggle-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .content-toggle-desc {
            font-size: 0.65rem;
            color: var(--text-muted);
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 38px;
            height: 20px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider-round {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.08);
            transition: .2s;
            border-radius: 20px;
            border: 1px solid var(--border-color);
        }

        .slider-round:before {
            position: absolute;
            content: "";
            height: 12px;
            width: 12px;
            left: 3px;
            bottom: 3px;
            background-color: #94a3b8;
            transition: .2s;
            border-radius: 50%;
        }

        input:checked + .slider-round {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        input:checked + .slider-round:before {
            transform: translateX(18px);
            background-color: #ffffff;
        }

        /* Alignment Toggle buttons */
        .btn-align-toggle {
            flex: 1;
            border: none;
            background: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.5rem;
            font-size: 0.75rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            transition: all 0.15s ease;
        }

        .btn-align-toggle:hover {
            color: var(--text-main);
            background: rgba(255,255,255,0.02);
        }

        .btn-align-toggle.active {
            color: #ffffff;
            background: rgba(248, 123, 27, 0.2);
            border: 1px solid var(--primary);
        }

        .styling-pane {
            display: none;
        }

        .styling-pane.active {
            display: block;
        }

    </style>
</head>
<body>

    <!-- Title Header Bar -->
    <div class="title-bar">
        <div>
            <h2 style="font-size: 1.35rem; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                <i data-lucide="award" style="color: var(--primary); width: 22px; height: 22px;"></i>
                Certificate Customization Workspace
            </h2>
            <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0.2rem 0 0 0;">
                Event: <strong><?php echo e($event['name']); ?></strong>
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <button type="submit" form="customizer-form" class="btn btn-primary" style="padding: 0.5rem 1.25rem;">
                <i data-lucide="save" style="width: 16px; height: 16px; display:inline-block; vertical-align:middle; margin-right: 0.25rem;"></i> Save Layout
            </button>
            <button onclick="window.close()" class="btn btn-secondary" style="padding: 0.5rem 1.25rem;">
                Close Window
            </button>
        </div>
    </div>

    <!-- Main Workspace Splitter -->
    <div class="workspace">
        <!-- Sliders & Inputs Controls Panel (Left) -->
        <div class="controls-sidebar">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" style="margin-bottom: 1rem; font-size: 0.85rem; padding: 0.75rem 1rem;">
                    <ul style="padding-left: 1rem; margin:0;">
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo e($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success" style="margin-bottom: 1rem; font-size: 0.85rem; padding: 0.75rem 1rem; background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3);">
                    <i data-lucide="check-circle" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 0.25rem;"></i>
                    <?php echo e($success_msg); ?>
                </div>
            <?php endif; ?>

            <form id="customizer-form" method="POST" action="customize.php?event_id=<?php echo $event_id; ?>" enctype="multipart/form-data">
                <?php csrf_input(); ?>

                <!-- TABS NAVIGATION -->
                <div class="tabs-nav">
                    <button type="button" id="tab-btn-1" class="tab-btn active" onclick="switchTab(1)">
                        <i data-lucide="check-square" style="width: 14px; height: 14px;"></i> 1. Select
                    </button>
                    <button type="button" id="tab-btn-2" class="tab-btn" onclick="switchTab(2)">
                        <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i> 2. Write
                    </button>
                    <button type="button" id="tab-btn-3" class="tab-btn" onclick="switchTab(3)">
                        <i data-lucide="sliders" style="width: 14px; height: 14px;"></i> 3. Style
                    </button>
                </div>

                <!-- TAB 1: SELECT CONTENT & TEMPLATE -->
                <div id="tab-pane-1" class="tab-content active">
                    <div class="section-header" style="margin-top: 0; font-size: 0.8rem; margin-bottom: 0.75rem;">Background Template</div>
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Canva Shared Template Link / Embed HTML</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" name="canva_template_link" id="canva_template_link" class="form-control" placeholder="Paste link or full HTML iframe embed code..." value="<?php echo e($event['canva_template_link'] ?? ''); ?>" style="flex-grow: 1; font-size: 0.8rem;">
                            <button type="button" id="btn-sync-canva" class="btn btn-secondary" onclick="syncCanvaTemplate()" style="padding: 0.5rem 0.8rem; display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.8rem;">
                                <i data-lucide="refresh-cw" style="width:14px; height:14px;"></i> Sync
                            </button>
                        </div>
                        <div id="canva-sync-indicator" style="margin-top: 0.5rem; font-size: 0.8rem; display: none; align-items: center; gap: 0.4rem;"></div>
                        
                        <input type="hidden" id="fetched_template_filename" name="fetched_template_filename" value="<?php echo e($event['certificate_template'] ?? ''); ?>">
                        <input type="hidden" id="fetched_template_url" value="<?php echo e($saved_template_url); ?>">
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Or Upload Background Template (PDF / Image)</label>
                        <input type="file" name="manual_template_file" id="manual_template_file" accept="application/pdf, image/png, image/jpeg, image/jpg" class="form-control" onchange="handleManualTemplateUpload(this)" style="font-size: 0.8rem;">
                        <?php if (!empty($event['certificate_template'])): ?>
                            <div style="margin-top: 0.5rem; font-size: 0.75rem; color: #34d399; display: flex; align-items: center; gap: 0.25rem;">
                                <i data-lucide="file-check" style="width: 14px; height: 14px;"></i>
                                Current: <span style="word-break: break-all;"><?php echo e($event['certificate_template']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="section-header" style="font-size: 0.8rem; margin-bottom: 0.75rem;">Enable/Disable Content Elements</div>
                    
                    <!-- Content Toggle Switch Cards -->
                    <div class="content-toggle-card">
                        <div class="content-toggle-info">
                            <span class="content-toggle-title">Header (Title)</span>
                            <span class="content-toggle-desc">Main top heading statement</span>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="title_enabled" id="title_enabled" value="1" <?php echo $title_enabled ? 'checked' : ''; ?> onchange="updatePreview(); syncWriteTabVisibility();">
                            <span class="slider-round"></span>
                        </label>
                    </div>

                    <div class="content-toggle-card">
                        <div class="content-toggle-info">
                            <span class="content-toggle-title">Sub Header (Subtitle)</span>
                            <span class="content-toggle-desc">Presentation text line</span>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="subtitle_enabled" id="subtitle_enabled" value="1" <?php echo $subtitle_enabled ? 'checked' : ''; ?> onchange="updatePreview(); syncWriteTabVisibility();">
                            <span class="slider-round"></span>
                        </label>
                    </div>

                    <div class="content-toggle-card">
                        <div class="content-toggle-info">
                            <span class="content-toggle-title">Student Name</span>
                            <span class="content-toggle-desc">Overlay of the candidate's name</span>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="name_enabled" id="name_enabled" value="1" <?php echo $name_enabled ? 'checked' : ''; ?> onchange="updatePreview(); syncWriteTabVisibility();">
                            <span class="slider-round"></span>
                        </label>
                    </div>

                    <div class="content-toggle-card">
                        <div class="content-toggle-info">
                            <span class="content-toggle-title">Description</span>
                            <span class="content-toggle-desc">Dynamic details text of the achievement</span>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="details_enabled" id="details_enabled" value="1" <?php echo $details_enabled ? 'checked' : ''; ?> onchange="updatePreview(); syncWriteTabVisibility();">
                            <span class="slider-round"></span>
                        </label>
                    </div>

                    <div class="content-toggle-card">
                        <div class="content-toggle-info">
                            <span class="content-toggle-title">QR Code Overlay</span>
                            <span class="content-toggle-desc">Scannable authentication QR code</span>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="qr_enabled" id="qr_enabled" value="1" <?php echo $qr_enabled ? 'checked' : ''; ?> onchange="updatePreview(); syncWriteTabVisibility();">
                            <span class="slider-round"></span>
                        </label>
                    </div>


                    <div class="content-toggle-card">
                        <div class="content-toggle-info">
                            <span class="content-toggle-title">Signatures Overlay</span>
                            <span class="content-toggle-desc">HOD and Coordinator signature lines</span>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="signatures_enabled" id="signatures_enabled" value="1" <?php echo $signatures_enabled ? 'checked' : ''; ?> onchange="updatePreview(); syncWriteTabVisibility();">
                            <span class="slider-round"></span>
                        </label>
                    </div>
                </div>

                <!-- TAB 2: WRITE CONTENT -->
                <div id="tab-pane-2" class="tab-content">
                    <div class="section-header" style="margin-top: 0; font-size: 0.8rem; margin-bottom: 0.75rem;">Write Content Text</div>
                    
                    <div id="group-write-title" class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Header (Title) Content</label>
                        <input type="text" name="certificate_title" id="certificate_title" class="form-control" value="<?php echo e($event['certificate_title'] ?? 'Certificate of Activity'); ?>" oninput="updatePreview()">
                    </div>

                    <div id="group-write-subtitle" class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Sub Header (Subtitle) Content</label>
                        <input type="text" name="subtitle_text" id="subtitle_text" class="form-control" value="<?php echo e($subtitle_text); ?>" oninput="updatePreview()">
                    </div>

                    <div id="group-write-details" class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Description Template Content</label>
                        <textarea name="details_text_template" id="details_text_template" class="form-control" rows="4" oninput="updatePreview()" style="font-size: 0.8rem; line-height: 1.4; resize: vertical;"><?php echo e($details_text_template); ?></textarea>
                        <small style="color: var(--text-muted); font-size: 0.65rem; display:block; margin-top: 0.25rem;">
                            Variables: <code>{name}</code>, <code>{course}</code>, <code>{batch}</code>, <code>{event_name}</code>, <code>{role}</code>, <code>{event_date}</code>.
                        </small>
                    </div>

                    <div id="group-write-signatures" class="form-group-flex" style="margin-bottom: 1rem;">
                        <div>
                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Left Sig. Title</label>
                            <input type="text" name="sig_left_text" id="sig_left_text" class="form-control" value="<?php echo e($sig_left_text); ?>" oninput="updatePreview()" style="font-size: 0.8rem;">
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Right Sig. Title</label>
                            <input type="text" name="sig_right_text" id="sig_right_text" class="form-control" value="<?php echo e($sig_right_text); ?>" oninput="updatePreview()" style="font-size: 0.8rem;">
                        </div>
                    </div>
                </div>

                <!-- TAB 3: STYLE & POSITION EDITING PANEL -->
                <div id="tab-pane-3" class="tab-content">
                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label class="form-label" style="font-size: 0.85rem; margin-bottom: 0.4rem; font-weight: 600; color: var(--primary);">Choose Element to Style</label>
                        <select id="element-to-style" class="form-control" onchange="switchStylingPane(this.value)" style="background: rgba(15, 23, 42, 0.6); padding: 0.6rem; border-color: rgba(248, 123, 27, 0.3); font-size: 0.85rem; font-weight: 600;">
                            <option value="title">Header (Title)</option>
                            <option value="subtitle">Sub Header (Subtitle)</option>
                            <option value="name">Student Name</option>
                            <option value="details">Description Overlay</option>
                            <option value="qr">QR Code Overlay</option>

                            <option value="signatures">Signatures Overlay</option>
                        </select>
                    </div>

                    <!-- Header Styling Pane -->
                    <div id="styling-pane-title" class="styling-pane active">
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Vertical Position (Top %)</span>
                                <span><span id="val_title_top" class="val"><?php echo $title_top; ?></span>%</span>
                            </div>
                            <input type="range" name="title_top" id="title_top" min="5" max="95" step="0.5" value="<?php echo $title_top; ?>" oninput="updatePreview()" style="width: 100%;">
                        </div>
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Horizontal Position (Center %)</span>
                                <span><span id="val_title_left" class="val"><?php echo $title_left; ?></span>%</span>
                            </div>
                            <input type="range" name="title_left" id="title_left" min="5" max="95" step="0.5" value="<?php echo $title_left; ?>" oninput="updatePreview()" style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Text Alignment</label>
                            <div class="align-btn-group" style="display: flex; gap: 0.25rem; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 0.2rem;">
                                <button type="button" class="btn-align-toggle btn-align-title-left" onclick="setAlignment('title', 'left')" style="flex: 1;"><i data-lucide="align-left" style="width:14px; height:14px;"></i> Left</button>
                                <button type="button" class="btn-align-toggle btn-align-title-center" onclick="setAlignment('title', 'center')" style="flex: 1;"><i data-lucide="align-center" style="width:14px; height:14px;"></i> Center</button>
                                <button type="button" class="btn-align-toggle btn-align-title-right" onclick="setAlignment('title', 'right')" style="flex: 1;"><i data-lucide="align-right" style="width:14px; height:14px;"></i> Right</button>
                            </div>
                            <input type="hidden" name="title_align" id="title_align" value="<?php echo $title_align; ?>">
                        </div>
                        <div class="form-group-flex" style="margin-bottom: 0.75rem;">
                            <div>
                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Font Size (rem)</label>
                                <div class="slider-header" style="margin-bottom: 0.15rem;">
                                    <span>Size:</span>
                                    <span><span id="val_title_font_size" class="val"><?php echo $title_font_size; ?></span>rem</span>
                                </div>
                                <input type="range" name="title_font_size" id="title_font_size" min="0.8" max="5" step="0.1" value="<?php echo $title_font_size; ?>" oninput="updatePreview()" style="width: 100%;">
                            </div>
                            <div>
                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Header Font</label>
                                <select name="title_font" id="title_font" class="form-control" onchange="updatePreview()" style="font-size:0.8rem; padding: 0.35rem 0.5rem;">
                                    <option value="Helvetica" <?php echo $title_font === 'Helvetica' ? 'selected' : ''; ?>>Helvetica</option>
                                    <option value="Times" <?php echo $title_font === 'Times' ? 'selected' : ''; ?>>Times</option>
                                    <option value="Courier" <?php echo $title_font === 'Courier' ? 'selected' : ''; ?>>Courier</option>
                                    <option value="GreatVibes" <?php echo $title_font === 'GreatVibes' ? 'selected' : ''; ?>>Great Vibes</option>
                                </select>
                            </div>
                        </div>
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Font Color</span>
                            </div>
                            <input type="color" name="title_color" id="title_color" value="<?php echo $title_color; ?>" oninput="updatePreview()" style="padding: 0; height: 32px; width: 100%; cursor: pointer; border: 1px solid var(--border-color); background: none;">
                        </div>
                    </div>

                    <!-- Sub Header Styling Pane -->
                    <div id="styling-pane-subtitle" class="styling-pane">
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Vertical Position (Top %)</span>
                                <span><span id="val_subtitle_top" class="val"><?php echo $subtitle_top; ?></span>%</span>
                            </div>
                            <input type="range" name="subtitle_top" id="subtitle_top" min="5" max="95" step="0.5" value="<?php echo $subtitle_top; ?>" oninput="updatePreview()" style="width: 100%;">
                        </div>
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Horizontal Position (Center %)</span>
                                <span><span id="val_subtitle_left" class="val"><?php echo $subtitle_left; ?></span>%</span>
                            </div>
                            <input type="range" name="subtitle_left" id="subtitle_left" min="5" max="95" step="0.5" value="<?php echo $subtitle_left; ?>" oninput="updatePreview()" style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Text Alignment</label>
                            <div class="align-btn-group" style="display: flex; gap: 0.25rem; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 0.2rem;">
                                <button type="button" class="btn-align-toggle btn-align-subtitle-left" onclick="setAlignment('subtitle', 'left')" style="flex: 1;"><i data-lucide="align-left" style="width:14px; height:14px;"></i> Left</button>
                                <button type="button" class="btn-align-toggle btn-align-subtitle-center" onclick="setAlignment('subtitle', 'center')" style="flex: 1;"><i data-lucide="align-center" style="width:14px; height:14px;"></i> Center</button>
                                <button type="button" class="btn-align-toggle btn-align-subtitle-right" onclick="setAlignment('subtitle', 'right')" style="flex: 1;"><i data-lucide="align-right" style="width:14px; height:14px;"></i> Right</button>
                            </div>
                            <input type="hidden" name="subtitle_align" id="subtitle_align" value="<?php echo $subtitle_align; ?>">
                        </div>
                        <div class="form-group-flex" style="margin-bottom: 0.75rem;">
                            <div>
                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Font Size (rem)</label>
                                <div class="slider-header" style="margin-bottom: 0.15rem;">
                                    <span>Size:</span>
                                    <span><span id="val_subtitle_font_size" class="val"><?php echo $subtitle_font_size; ?></span>rem</span>
                                </div>
                                <input type="range" name="subtitle_font_size" id="subtitle_font_size" min="0.5" max="3" step="0.05" value="<?php echo $subtitle_font_size; ?>" oninput="updatePreview()" style="width: 100%;">
                            </div>
                            <div>
                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Sub Header Font</label>
                                <select name="subtitle_font" id="subtitle_font" class="form-control" onchange="updatePreview()" style="font-size:0.8rem; padding: 0.35rem 0.5rem;">
                                    <option value="Helvetica" <?php echo $subtitle_font === 'Helvetica' ? 'selected' : ''; ?>>Helvetica</option>
                                    <option value="Times" <?php echo $subtitle_font === 'Times' ? 'selected' : ''; ?>>Times</option>
                                    <option value="Courier" <?php echo $subtitle_font === 'Courier' ? 'selected' : ''; ?>>Courier</option>
                                    <option value="GreatVibes" <?php echo $subtitle_font === 'GreatVibes' ? 'selected' : ''; ?>>Great Vibes</option>
                                </select>
                            </div>
                        </div>
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Font Color</span>
                            </div>
                            <input type="color" name="subtitle_color" id="subtitle_color" value="<?php echo $subtitle_color; ?>" oninput="updatePreview()" style="padding: 0; height: 32px; width: 100%; cursor: pointer; border: 1px solid var(--border-color); background: none;">
                        </div>
                    </div>

                    <!-- Student Name Styling Pane -->
                    <div id="styling-pane-name" class="styling-pane">
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Vertical Position (Top %)</span>
                                <span><span id="val_name_top" class="val"><?php echo $name_top; ?></span>%</span>
                            </div>
                            <input type="range" name="name_top" id="name_top" min="5" max="95" step="0.5" value="<?php echo $name_top; ?>" oninput="updatePreview()" style="width: 100%;">
                        </div>
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Horizontal Position (Center %)</span>
                                <span><span id="val_name_left" class="val"><?php echo $name_left; ?></span>%</span>
                            </div>
                            <input type="range" name="name_left" id="name_left" min="5" max="95" step="0.5" value="<?php echo $name_left; ?>" oninput="updatePreview()" style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Text Alignment</label>
                            <div class="align-btn-group" style="display: flex; gap: 0.25rem; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 0.2rem;">
                                <button type="button" class="btn-align-toggle btn-align-name-left" onclick="setAlignment('name', 'left')" style="flex: 1;"><i data-lucide="align-left" style="width:14px; height:14px;"></i> Left</button>
                                <button type="button" class="btn-align-toggle btn-align-name-center" onclick="setAlignment('name', 'center')" style="flex: 1;"><i data-lucide="align-center" style="width:14px; height:14px;"></i> Center</button>
                                <button type="button" class="btn-align-toggle btn-align-name-right" onclick="setAlignment('name', 'right')" style="flex: 1;"><i data-lucide="align-right" style="width:14px; height:14px;"></i> Right</button>
                            </div>
                            <input type="hidden" name="name_align" id="name_align" value="<?php echo $name_align; ?>">
                        </div>
                        <div class="form-group-flex" style="margin-bottom: 0.75rem;">
                            <div>
                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Font Size (rem)</label>
                                <div class="slider-header" style="margin-bottom: 0.15rem;">
                                    <span>Size:</span>
                                    <span><span id="val_name_font_size" class="val"><?php echo $name_font_size; ?></span>rem</span>
                                </div>
                                <input type="range" name="name_font_size" id="name_font_size" min="1" max="6" step="0.1" value="<?php echo $name_font_size; ?>" oninput="updatePreview()" style="width: 100%;">
                            </div>
                            <div>
                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Font</label>
                                <select name="name_font" id="name_font" class="form-control" onchange="updatePreview()" style="font-size:0.8rem; padding: 0.35rem 0.5rem;">
                                    <option value="GreatVibes" <?php echo $name_font === 'GreatVibes' ? 'selected' : ''; ?>>Great Vibes</option>
                                    <option value="Helvetica" <?php echo $name_font === 'Helvetica' ? 'selected' : ''; ?>>Helvetica</option>
                                    <option value="Times" <?php echo $name_font === 'Times' ? 'selected' : ''; ?>>Times</option>
                                    <option value="Courier" <?php echo $name_font === 'Courier' ? 'selected' : ''; ?>>Courier</option>
                                </select>
                            </div>
                        </div>
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Font Color</span>
                            </div>
                            <input type="color" name="name_color" id="name_color" value="<?php echo $name_color; ?>" oninput="updatePreview()" style="padding: 0; height: 32px; width: 100%; cursor: pointer; border: 1px solid var(--border-color); background: none;">
                        </div>
                    </div>

                    <!-- Description Styling Pane -->
                    <div id="styling-pane-details" class="styling-pane">
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Vertical Position (Top %)</span>
                                <span><span id="val_details_top" class="val"><?php echo $details_top; ?></span>%</span>
                            </div>
                            <input type="range" name="details_top" id="details_top" min="5" max="95" step="0.5" value="<?php echo $details_top; ?>" oninput="updatePreview()" style="width: 100%;">
                        </div>
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Horizontal Position (Center %)</span>
                                <span><span id="val_details_left" class="val"><?php echo $details_left; ?></span>%</span>
                            </div>
                            <input type="range" name="details_left" id="details_left" min="5" max="95" step="0.5" value="<?php echo $details_left; ?>" oninput="updatePreview()" style="width: 100%;">
                        </div>
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Text Alignment</label>
                            <div class="align-btn-group" style="display: flex; gap: 0.25rem; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 0.2rem;">
                                <button type="button" class="btn-align-toggle btn-align-details-left" onclick="setAlignment('details', 'left')" style="flex: 1;"><i data-lucide="align-left" style="width:14px; height:14px;"></i> Left</button>
                                <button type="button" class="btn-align-toggle btn-align-details-center" onclick="setAlignment('details', 'center')" style="flex: 1;"><i data-lucide="align-center" style="width:14px; height:14px;"></i> Center</button>
                                <button type="button" class="btn-align-toggle btn-align-details-right" onclick="setAlignment('details', 'right')" style="flex: 1;"><i data-lucide="align-right" style="width:14px; height:14px;"></i> Right</button>
                            </div>
                            <input type="hidden" name="details_align" id="details_align" value="<?php echo $details_align; ?>">
                        </div>
                        <div class="form-group-flex" style="margin-bottom: 0.75rem;">
                            <div>
                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Font Size (rem)</label>
                                <div class="slider-header" style="margin-bottom: 0.15rem;">
                                    <span>Size:</span>
                                    <span><span id="val_details_font_size" class="val"><?php echo $details_font_size; ?></span>rem</span>
                                </div>
                                <input type="range" name="details_font_size" id="details_font_size" min="0.5" max="3" step="0.05" value="<?php echo $details_font_size; ?>" oninput="updatePreview()" style="width: 100%;">
                            </div>
                            <div>
                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Font</label>
                                <select name="details_font" id="details_font" class="form-control" onchange="updatePreview()" style="font-size:0.8rem; padding: 0.35rem 0.5rem;">
                                    <option value="Helvetica" <?php echo $details_font === 'Helvetica' ? 'selected' : ''; ?>>Helvetica</option>
                                    <option value="Times" <?php echo $details_font === 'Times' ? 'selected' : ''; ?>>Times</option>
                                    <option value="Courier" <?php echo $details_font === 'Courier' ? 'selected' : ''; ?>>Courier</option>
                                    <option value="GreatVibes" <?php echo $details_font === 'GreatVibes' ? 'selected' : ''; ?>>Great Vibes</option>
                                </select>
                            </div>
                        </div>
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Font Color</span>
                            </div>
                            <input type="color" name="details_color" id="details_color" value="<?php echo $details_color; ?>" oninput="updatePreview()" style="padding: 0; height: 32px; width: 100%; cursor: pointer; border: 1px solid var(--border-color); background: none;">
                        </div>
                    </div>

                    <!-- QR Code Styling Pane -->
                    <div id="styling-pane-qr" class="styling-pane">
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>QR Vertical Position (Top %)</span>
                                <span><span id="val_qr_top" class="val"><?php echo $qr_top; ?></span>%</span>
                            </div>
                            <input type="range" name="qr_top" id="qr_top" min="5" max="98" step="0.5" value="<?php echo $qr_top; ?>" oninput="updatePreview()" style="width: 100%;">
                        </div>
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>QR Horizontal Position (Left %)</span>
                                <span><span id="val_qr_left" class="val"><?php echo $qr_left; ?></span>%</span>
                            </div>
                            <input type="range" name="qr_left" id="qr_left" min="5" max="95" step="0.5" value="<?php echo $qr_left; ?>" oninput="updatePreview()" style="width: 100%;">
                        </div>
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>QR Code Size (mm)</span>
                                <span><span id="val_qr_size" class="val"><?php echo $qr_size; ?></span>mm</span>
                            </div>
                            <input type="range" name="qr_size" id="qr_size" min="10" max="50" step="1" value="<?php echo $qr_size; ?>" oninput="updatePreview()" style="width: 100%;">
                        </div>
                    </div>


                    <!-- Signatures Styling Pane -->
                    <div id="styling-pane-signatures" class="styling-pane">
                        <div style="font-size: 0.8rem; color: var(--primary); font-weight: 600; margin-bottom: 0.5rem;">Left Signature (Coordinator)</div>
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Vertical Position (Top %)</span>
                                <span><span id="val_sig_left_top" class="val"><?php echo $sig_left_top; ?></span>%</span>
                            </div>
                            <input type="range" name="sig_left_top" id="sig_left_top" min="50" max="95" step="0.5" value="<?php echo $sig_left_top; ?>" oninput="updatePreview()" style="width: 100%;">
                        </div>
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Left Indent (Left %)</span>
                                <span><span id="val_sig_left_left" class="val"><?php echo $sig_left_left; ?></span>%</span>
                            </div>
                            <input type="range" name="sig_left_left" id="sig_left_left" min="5" max="50" step="0.5" value="<?php echo $sig_left_left; ?>" oninput="updatePreview()" style="width: 100%;">
                        </div>

                        <div style="font-size: 0.8rem; color: var(--primary); font-weight: 600; margin: 1rem 0 0.5rem 0;">Right Signature (HOD)</div>
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Vertical Position (Top %)</span>
                                <span><span id="val_sig_right_top" class="val"><?php echo $sig_right_top; ?></span>%</span>
                            </div>
                            <input type="range" name="sig_right_top" id="sig_right_top" min="50" max="95" step="0.5" value="<?php echo $sig_right_top; ?>" oninput="updatePreview()" style="width: 100%;">
                        </div>
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Right Indent (Right %)</span>
                                <span><span id="val_sig_right_right" class="val"><?php echo $sig_right_right; ?></span>%</span>
                            </div>
                            <input type="range" name="sig_right_right" id="sig_right_right" min="5" max="50" step="0.5" value="<?php echo $sig_right_right; ?>" oninput="updatePreview()" style="width: 100%;">
                        </div>

                        <div style="font-size: 0.8rem; color: var(--primary); font-weight: 600; margin: 1rem 0 0.5rem 0;">Signature Styling</div>
                        <div class="form-group-flex" style="margin-bottom: 0.75rem;">
                            <div>
                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Sig. Font</label>
                                <select name="sig_font" id="sig_font" class="form-control" onchange="updatePreview()" style="font-size:0.8rem; padding: 0.35rem 0.5rem;">
                                    <option value="Helvetica" <?php echo $sig_font === 'Helvetica' ? 'selected' : ''; ?>>Helvetica</option>
                                    <option value="Times" <?php echo $sig_font === 'Times' ? 'selected' : ''; ?>>Times</option>
                                    <option value="Courier" <?php echo $sig_font === 'Courier' ? 'selected' : ''; ?>>Courier</option>
                                </select>
                            </div>
                        </div>
                        <div class="slider-box">
                            <div class="slider-header">
                                <span>Signature Color</span>
                            </div>
                            <input type="color" name="sig_color" id="sig_color" value="<?php echo $sig_color; ?>" oninput="updatePreview()" style="padding: 0; height: 32px; width: 100%; cursor: pointer; border: 1px solid var(--border-color); background: none;">
                        </div>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; padding: 0.75rem;">
                        <i data-lucide="save" style="width: 16px; height: 16px; display:inline-block; vertical-align:middle; margin-right: 0.25rem;"></i> Save Configuration
                    </button>
                </div>
            </form>
        </div>

        <!-- Live Visual WYSIWYG Preview Panel (Right) -->
        <div class="preview-area">
            <div class="certificate-wrapper">
                <div id="cert-preview-card" class="certificate-canvas">
                    <!-- PDF rendering canvas background -->
                    <canvas id="pdf-bg-canvas" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; display: none;"></canvas>
                    
                    <!-- Standard top heading fallback -->
                    <div id="prev-header" class="cert-header" style="display: none;">Department of Computer Application</div>
                    
                    <!-- Absolutely positioned items -->
                    <div id="prev-title" class="cert-title">Certificate of Activity</div>
                    <div id="prev-subtitle" class="cert-statement">This is proudly presented to</div>
                    <div id="prev-name" class="cert-name">Sandip Kumar Dey</div>
                    <div id="prev-details" class="cert-details">
                        of <strong>Computer Application (Batch 2024-2027)</strong> for successfully participating as a 
                        <strong>Coordinator</strong> in the event <strong id="prev-event-name"><?php echo e($event['name']); ?></strong>, 
                        organized by the Department on <strong><?php echo date('F d, Y', strtotime($event['event_date'])); ?></strong>.
                    </div>

                    <!-- Visual QR Overlay Box -->
                    <div id="prev-qr-block" style="display: none; position: absolute; border: 1px solid #1e293b; background: #ffffff; align-items: center; justify-content: center; z-index: 15;">
                        <i data-lucide="qr-code" style="color: #1e293b; width: 80%; height: 80%;"></i>
                    </div>

                    <!-- Signatures & Verification overlay -->
                    <div id="prev-footer" class="cert-footer">
                        <div id="prev-sig-left" class="signature-block">
                            <div class="signature-line sig-line"></div>
                            <div class="signature-title">Event Coordinator</div>
                        </div>


                        <div id="prev-sig-right" class="signature-block">
                            <div class="signature-line sig-line"></div>
                            <div class="signature-title">Head of Department</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Script triggers -->
    <script>
        lucide.createIcons();

        // Switch customizer steps/tabs
        function switchTab(tabNum) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(pane => pane.classList.remove('active'));

            const activeBtn = document.getElementById(`tab-btn-${tabNum}`);
            const activePane = document.getElementById(`tab-pane-${tabNum}`);
            if (activeBtn) activeBtn.classList.add('active');
            if (activePane) activePane.classList.add('active');
        }

        // Switch styling panel elements
        function switchStylingPane(paneId) {
            document.querySelectorAll('.styling-pane').forEach(pane => pane.classList.remove('active'));
            const activePane = document.getElementById(`styling-pane-${paneId}`);
            if (activePane) activePane.classList.add('active');
        }

        // Set alignment setting and highlight active button
        function setAlignment(element, alignment) {
            const input = document.getElementById(`${element}_align`);
            if (input) {
                input.value = alignment;
            }
            updateAlignmentButtons(element, alignment);
            updatePreview();
        }

        // Highlight the alignment buttons
        function updateAlignmentButtons(element, alignment) {
            const btnLeft = document.querySelector(`.btn-align-${element}-left`);
            const btnCenter = document.querySelector(`.btn-align-${element}-center`);
            const btnRight = document.querySelector(`.btn-align-${element}-right`);
            
            if (btnLeft) btnLeft.classList.remove('active');
            if (btnCenter) btnCenter.classList.remove('active');
            if (btnRight) btnRight.classList.remove('active');
            
            if (alignment === 'left' && btnLeft) btnLeft.classList.add('active');
            if (alignment === 'center' && btnCenter) btnCenter.classList.add('active');
            if (alignment === 'right' && btnRight) btnRight.classList.add('active');
        }

        // Hide/Show Write panel fields dynamically based on visibility switches
        function syncWriteTabVisibility() {
            const titleEnabled = document.getElementById('title_enabled').checked;
            const subtitleEnabled = document.getElementById('subtitle_enabled').checked;
            const nameEnabled = document.getElementById('name_enabled').checked;
            const detailsEnabled = document.getElementById('details_enabled').checked;
            const signaturesEnabled = document.getElementById('signatures_enabled').checked;

            const groupTitle = document.getElementById('group-write-title');
            if (groupTitle) groupTitle.style.display = titleEnabled ? 'block' : 'none';

            const groupSubtitle = document.getElementById('group-write-subtitle');
            if (groupSubtitle) groupSubtitle.style.display = subtitleEnabled ? 'block' : 'none';

            const groupDetails = document.getElementById('group-write-details');
            if (groupDetails) groupDetails.style.display = detailsEnabled ? 'block' : 'none';

            const groupSigs = document.getElementById('group-write-signatures');
            if (groupSigs) groupSigs.style.display = signaturesEnabled ? 'flex' : 'none';
        }

        function handleManualTemplateUpload(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileType = file.type;
                
                if (fileType.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('fetched_template_url').value = e.target.result;
                        updatePreview();
                    };
                    reader.readAsDataURL(file);
                } else if (fileType === 'application/pdf') {
                    const fileUrl = URL.createObjectURL(file);
                    document.getElementById('fetched_template_url').value = fileUrl;
                    updatePreview();
                }
            }
        }

        let pdfjsLib = window['pdfjs-dist/build/pdf'];
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

        function renderPdfBackground(url) {
            const canvas = document.getElementById('pdf-bg-canvas');
            if (!canvas) return;
            
            canvas.style.display = 'block';
            
            pdfjsLib.getDocument(url).promise.then(function(pdf) {
                pdf.getPage(1).then(function(page) {
                    const viewport = page.getViewport({scale: 1.5});
                    const context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    
                    const renderContext = {
                        canvasContext: context,
                        viewport: viewport
                    };
                    page.render(renderContext);
                });
            }).catch(function(error) {
                console.error("Error rendering PDF template preview: ", error);
            });
        }

        async function syncCanvaTemplate() {
            const linkInput = document.getElementById('canva_template_link');
            const syncBtn = document.getElementById('btn-sync-canva');
            const indicator = document.getElementById('canva-sync-indicator');
            const fetchedFilename = document.getElementById('fetched_template_filename');
            const fetchedUrl = document.getElementById('fetched_template_url');
            
            const url = linkInput.value.trim();
            if (!url) {
                alert("Please enter a Canva Template / Design Link first.");
                return;
            }

            indicator.style.display = 'inline-flex';
            indicator.style.color = 'var(--text-muted)';
            indicator.innerHTML = '<i data-lucide="loader-2" class="spin" style="width:14px; height:14px; display:inline-block; vertical-align:middle; animation: spin 1s linear infinite;"></i> Syncing design from Canva...';
            lucide.createIcons();
            syncBtn.disabled = true;

            try {
                const response = await fetch('ajax_sync_canva.php?url=' + encodeURIComponent(url));
                const data = await response.json();
                
                if (data.success) {
                    fetchedFilename.value = data.filename;
                    fetchedUrl.value = data.image_url;
                    
                    indicator.style.color = 'var(--success)';
                    indicator.innerHTML = '<i data-lucide="check" style="width:14px; height:14px; display:inline-block; vertical-align:middle;"></i> Synced successfully!';
                    lucide.createIcons();
                    
                    updatePreview();
                } else {
                    indicator.style.color = 'var(--danger)';
                    indicator.innerHTML = '<i data-lucide="x-circle" style="width:14px; height:14px; display:inline-block; vertical-align:middle;"></i> ' + data.message;
                    lucide.createIcons();
                }
            } catch (err) {
                indicator.style.color = 'var(--danger)';
                indicator.innerHTML = '<i data-lucide="x-circle" style="width:14px; height:14px; display:inline-block; vertical-align:middle;"></i> Network error.';
                lucide.createIcons();
            } finally {
                syncBtn.disabled = false;
            }
        }

        // Add keyframes style dynamically
        if (!document.getElementById('spin-keyframe-style')) {
            const style = document.createElement('style');
            style.id = 'spin-keyframe-style';
            style.innerHTML = `@keyframes spin { 100% { transform: rotate(360deg); } } .spin { animation: spin 1s linear infinite; }`;
            document.head.appendChild(style);
        }

        function updatePreview() {
            // Read UI Text inputs
            const titleEnabled = document.getElementById('title_enabled').checked;
            const titleText = document.getElementById('certificate_title').value || 'Certificate of Activity';
            const titleTop = document.getElementById('title_top').value;
            const titleLeft = document.getElementById('title_left').value;
            const titleFontSize = document.getElementById('title_font_size').value;
            const titleColor = document.getElementById('title_color').value;
            const titleFont = document.getElementById('title_font').value;
            const titleAlign = document.getElementById('title_align').value;

            const subtitleEnabled = document.getElementById('subtitle_enabled').checked;
            const subtitleText = document.getElementById('subtitle_text').value || 'This is proudly presented to';
            const subtitleTop = document.getElementById('subtitle_top').value;
            const subtitleLeft = document.getElementById('subtitle_left').value;
            const subtitleFontSize = document.getElementById('subtitle_font_size').value;
            const subtitleColor = document.getElementById('subtitle_color').value;
            const subtitleFont = document.getElementById('subtitle_font').value;
            const subtitleAlign = document.getElementById('subtitle_align').value;

            const nameEnabled = document.getElementById('name_enabled').checked;
            const nameTop = document.getElementById('name_top').value;
            const nameLeft = document.getElementById('name_left').value;
            const nameFontSize = document.getElementById('name_font_size').value;
            const nameColor = document.getElementById('name_color').value;
            const nameFont = document.getElementById('name_font').value;
            const nameAlign = document.getElementById('name_align').value;

            const detailsEnabled = document.getElementById('details_enabled').checked;
            const detailsTemplate = document.getElementById('details_text_template').value;
            const detailsTop = document.getElementById('details_top').value;
            const detailsLeft = document.getElementById('details_left').value;
            const detailsFontSize = document.getElementById('details_font_size').value;
            const detailsColor = document.getElementById('details_color').value;
            const detailsFont = document.getElementById('details_font').value;
            const detailsAlign = document.getElementById('details_align').value;

            const qrEnabled = document.getElementById('qr_enabled').checked;
            const qrTop = document.getElementById('qr_top').value;
            const qrLeft = document.getElementById('qr_left').value;
            const qrSize = document.getElementById('qr_size').value;

            const codeEnabled = false;
            const codeTop = 88;
            const codeLeft = 50;
            const codeColor = '#0f172a';
            const codeAlign = 'center';

            const sigsEnabled = document.getElementById('signatures_enabled').checked;
            const sigLeftText = document.getElementById('sig_left_text').value || 'Event Coordinator';
            const sigRightText = document.getElementById('sig_right_text').value || 'Head of Department';
            const sigLeftTop = document.getElementById('sig_left_top').value;
            const sigLeftLeft = document.getElementById('sig_left_left').value;
            const sigRightTop = document.getElementById('sig_right_top').value;
            const sigRightRight = document.getElementById('sig_right_right').value;
            const sigColor = document.getElementById('sig_color').value;
            const sigFont = document.getElementById('sig_font').value;

            // Update display numeric labels
            document.getElementById('val_title_top').textContent = titleTop;
            document.getElementById('val_title_left').textContent = titleLeft;
            document.getElementById('val_title_font_size').textContent = titleFontSize;
            document.getElementById('val_subtitle_top').textContent = subtitleTop;
            document.getElementById('val_subtitle_left').textContent = subtitleLeft;
            document.getElementById('val_subtitle_font_size').textContent = subtitleFontSize;
            document.getElementById('val_name_top').textContent = nameTop;
            document.getElementById('val_name_left').textContent = nameLeft;
            document.getElementById('val_name_font_size').textContent = nameFontSize;
            document.getElementById('val_details_top').textContent = detailsTop;
            document.getElementById('val_details_left').textContent = detailsLeft;
            document.getElementById('val_details_font_size').textContent = detailsFontSize;
            document.getElementById('val_qr_top').textContent = qrTop;
            document.getElementById('val_qr_left').textContent = qrLeft;
            document.getElementById('val_qr_size').textContent = qrSize;

            document.getElementById('val_sig_left_top').textContent = sigLeftTop;
            document.getElementById('val_sig_left_left').textContent = sigLeftLeft;
            document.getElementById('val_sig_right_top').textContent = sigRightTop;
            document.getElementById('val_sig_right_right').textContent = sigRightRight;

            // Apply font mappings
            const fontMapping = {
                'Helvetica': "'Montserrat', sans-serif",
                'Times': "Georgia, serif",
                'Courier': "monospace",
                'GreatVibes': "'Great Vibes', cursive"
            };

            const card = document.getElementById('cert-preview-card');
            if (!card) return;

            // Elements references
            const prevTitle = document.getElementById('prev-title');
            const prevSubtitle = document.getElementById('prev-subtitle');
            const prevName = document.getElementById('prev-name');
            const prevDetails = document.getElementById('prev-details');
            const prevFooter = document.getElementById('prev-footer');

            const prevSigLeft = document.getElementById('prev-sig-left');
            const prevSigRight = document.getElementById('prev-sig-right');
            const prevQrBlock = document.getElementById('prev-qr-block');

            // Apply Background
            const bgUrl = document.getElementById('fetched_template_url').value;
            const pdfCanvas = document.getElementById('pdf-bg-canvas');
            if (bgUrl) {
                const isPdf = bgUrl.toLowerCase().endsWith('.pdf') || bgUrl.startsWith('blob:');
                if (isPdf) {
                    card.style.backgroundImage = 'none';
                    card.style.background = '#0f172a';
                    card.style.border = 'none';
                    const placeholder = document.getElementById('prev-placeholder-text');
                    if (placeholder) placeholder.style.display = 'none';
                    
                    renderPdfBackground(bgUrl);
                } else {
                    if (pdfCanvas) pdfCanvas.style.display = 'none';
                    card.style.backgroundImage = "url('" + bgUrl + "')";
                    card.style.backgroundSize = 'cover';
                    card.style.backgroundPosition = 'center';
                    card.style.border = 'none';
                    const placeholder = document.getElementById('prev-placeholder-text');
                    if (placeholder) placeholder.style.display = 'none';
                }
            } else {
                if (pdfCanvas) pdfCanvas.style.display = 'none';
                card.style.backgroundImage = 'none';
                card.style.background = '#111827';
                card.style.border = '2px dashed var(--border-color)';
                let placeholder = document.getElementById('prev-placeholder-text');
                if (!placeholder) {
                    placeholder = document.createElement('div');
                    placeholder.id = 'prev-placeholder-text';
                    placeholder.style.position = 'absolute';
                    placeholder.style.top = '40%';
                    placeholder.style.left = '50%';
                    placeholder.style.transform = 'translate(-50%, -50%)';
                    placeholder.style.color = 'var(--text-muted)';
                    placeholder.style.fontSize = '0.95rem';
                    placeholder.style.textAlign = 'center';
                    placeholder.style.width = '80%';
                    placeholder.innerHTML = '<i data-lucide="image" style="width:36px; height:36px; margin: 0 auto 0.5rem; display:block; color:var(--primary);"></i> Paste Canva URL or Upload Template to overlay layout config.';
                    card.appendChild(placeholder);
                    lucide.createIcons();
                } else {
                    placeholder.style.display = 'block';
                    placeholder.style.color = 'var(--text-muted)';
                    placeholder.style.innerHTML = '<i data-lucide="image" style="width:36px; height:36px; margin: 0 auto 0.5rem; display:block; color:var(--primary);"></i> Paste Canva URL or Upload Template to overlay layout config.';
                    lucide.createIcons();
                }
            }

            // Draw default standard headers
            document.getElementById('prev-header').style.display = 'none';

            // Alignment positioning helper
            const applyTextPositionAndAlign = (elem, topPercent, leftPercent, alignMode, sizeRem, colorHex, fontKey, isTitleBlock = false) => {
                if (!elem) return;
                elem.style.position = 'absolute';
                elem.style.top = topPercent + '%';
                elem.style.left = leftPercent + '%';
                elem.style.color = colorHex;
                elem.style.fontSize = (sizeRem * 0.45) + 'rem';
                elem.style.fontFamily = fontMapping[fontKey];
                elem.style.margin = '0';
                elem.style.zIndex = '5';
                
                // Set widths based on whether it is standard line or description block
                elem.style.width = isTitleBlock ? '70%' : '80%';

                if (alignMode === 'left') {
                    elem.style.transform = 'translate(0, -50%)';
                    elem.style.textAlign = 'left';
                } else if (alignMode === 'right') {
                    elem.style.transform = 'translate(-100%, -50%)';
                    elem.style.textAlign = 'right';
                } else { // center
                    elem.style.transform = 'translate(-50%, -50%)';
                    elem.style.textAlign = 'center';
                }
            };

            // Header (Title)
            if (titleEnabled) {
                prevTitle.style.display = 'block';
                prevTitle.textContent = titleText;
                applyTextPositionAndAlign(prevTitle, titleTop, titleLeft, titleAlign, titleFontSize, titleColor, titleFont, true);
            } else {
                prevTitle.style.display = 'none';
            }

            // Subtitle (Sub Header)
            if (subtitleEnabled) {
                prevSubtitle.style.display = 'block';
                prevSubtitle.textContent = subtitleText;
                applyTextPositionAndAlign(prevSubtitle, subtitleTop, subtitleLeft, subtitleAlign, subtitleFontSize, subtitleColor, subtitleFont, true);
            } else {
                prevSubtitle.style.display = 'none';
            }

            // Name
            if (nameEnabled) {
                prevName.style.display = 'block';
                applyTextPositionAndAlign(prevName, nameTop, nameLeft, nameAlign, nameFontSize, nameColor, nameFont, true);
            } else {
                prevName.style.display = 'none';
            }

            // Details (Description)
            if (detailsEnabled) {
                prevDetails.style.display = 'block';
                let detailsParsed = detailsTemplate
                    .replace(/{name}/g, "Sandip Kumar Dey")
                    .replace(/{course}/g, "Computer Application")
                    .replace(/{batch}/g, "2024-2027")
                    .replace(/{event_name}/g, document.getElementById('prev-event-name').textContent)
                    .replace(/{role}/g, "Coordinator")
                    .replace(/{event_date}/g, "<?php echo date('F d, Y', strtotime($event['event_date'])); ?>");

                prevDetails.innerHTML = detailsParsed;
                applyTextPositionAndAlign(prevDetails, detailsTop, detailsLeft, detailsAlign, detailsFontSize, detailsColor, detailsFont, false);
            } else {
                prevDetails.style.display = 'none';
            }

            // QR Code block
            if (qrEnabled) {
                prevQrBlock.style.display = 'flex';
                prevQrBlock.style.top = qrTop + '%';
                prevQrBlock.style.left = qrLeft + '%';
                prevQrBlock.style.transform = 'translate(-50%, -50%)';
                const qrPxSize = qrSize * 2.7; // mm scaling factor
                prevQrBlock.style.width = qrPxSize + 'px';
                prevQrBlock.style.height = qrPxSize + 'px';
                prevQrBlock.style.zIndex = '5';
            } else {
                prevQrBlock.style.display = 'none';
            }

            // Verification Code text block
            prevFooter.style.position = 'absolute';
            prevFooter.style.top = '0';
            prevFooter.style.left = '0';
            prevFooter.style.width = '100%';
            prevFooter.style.height = '100%';
            prevFooter.style.pointerEvents = 'none';
            prevFooter.style.zIndex = '5';



            // System Signatures positioning
            if (sigsEnabled) {
                prevSigLeft.style.display = 'block';
                prevSigLeft.style.position = 'absolute';
                prevSigLeft.style.top = sigLeftTop + '%';
                prevSigLeft.style.left = sigLeftLeft + '%';
                prevSigLeft.style.transform = 'translate(-50%, -50%)';
                prevSigLeft.style.color = sigColor;
                prevSigLeft.style.fontFamily = fontMapping[sigFont];
                prevSigLeft.querySelector('.sig-line').style.borderColor = sigColor;
                prevSigLeft.querySelector('.signature-title').textContent = sigLeftText;

                prevSigRight.style.display = 'block';
                prevSigRight.style.position = 'absolute';
                prevSigRight.style.top = sigRightTop + '%';
                prevSigRight.style.right = sigRightRight + '%';
                prevSigRight.style.transform = 'translate(50%, -50%)';
                prevSigRight.style.color = sigColor;
                prevSigRight.style.fontFamily = fontMapping[sigFont];
                prevSigRight.querySelector('.sig-line').style.borderColor = sigColor;
                prevSigRight.querySelector('.signature-title').textContent = sigRightText;
                prevSigRight.style.left = 'auto';
            } else {
                prevSigLeft.style.display = 'none';
                prevSigRight.style.display = 'none';
            }
        }

        // Initialize state on load
        updateAlignmentButtons('title', document.getElementById('title_align').value);
        updateAlignmentButtons('subtitle', document.getElementById('subtitle_align').value);
        updateAlignmentButtons('name', document.getElementById('name_align').value);
        updateAlignmentButtons('details', document.getElementById('details_align').value);


        syncWriteTabVisibility();
        updatePreview();
    </script>
</body>
</html>
