<?php
/**
 * Admin: Gallery Management Portal
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Access Control
require_role('admin');

$errors = [];
$success = null;
$gallery_dir = UPLOAD_PATH . '/gallery_photos';

// Handle file upload or delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_request();

    $action = trim($_POST['action'] ?? '');

    if ($action === 'upload') {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_res = handle_file_upload($_FILES['photo'], 'gallery_photos', ['jpg', 'jpeg', 'png', 'webp'], 5242880); // 5MB limit
            if ($upload_res['success']) {
                log_activity($_SESSION['user_id'], 'gallery_photo_uploaded', "Uploaded photo: " . $upload_res['filename']);
                set_flash_message('success', 'Photo uploaded successfully to highlights gallery.');
                header("Location: " . BASE_URL . "admin/gallery.php");
                exit();
            } else {
                $errors[] = $upload_res['message'];
            }
        } else {
            $errors[] = 'Please select a valid image file to upload.';
        }
    } elseif ($action === 'delete') {
        $filename = trim($_POST['filename'] ?? '');
        // Clean filename for safety (prevent directory traversal)
        $filename = basename($filename);
        
        if (!empty($filename)) {
            $filepath = $gallery_dir . '/' . $filename;
            if (file_exists($filepath)) {
                if (unlink($filepath)) {
                    log_activity($_SESSION['user_id'], 'gallery_photo_deleted', "Deleted photo: " . $filename);
                    set_flash_message('success', 'Photo deleted successfully.');
                } else {
                    $errors[] = 'Failed to delete file from directory. Check server permissions.';
                }
            } else {
                $errors[] = 'File not found.';
            }
            header("Location: " . BASE_URL . "admin/gallery.php");
            exit();
        } else {
            $errors[] = 'Invalid file deletion request.';
        }
    }
}

// Read photos from directory
$photos = [];
if (file_exists($gallery_dir)) {
    $files = scandir($gallery_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'])) {
            $photos[] = [
                'name' => $file,
                'time' => filemtime($gallery_dir . '/' . $file),
                'url' => BASE_URL . 'uploads/gallery_photos/' . $file
            ];
        }
    }
    // Sort by modification time DESC (newest first)
    usort($photos, function($a, $b) {
        return $b['time'] - $a['time'];
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Gallery - Accolades Connect</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="brand">
                <i data-lucide="shield-alert"></i>
                <span>Accolades - Admin</span>
            </a>
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/students/index.php" class="nav-link">Students</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/batches/index.php" class="nav-link">Batches</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/events/index.php" class="nav-link">Events</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/payments/index.php" class="nav-link">Payments</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/attendance/index.php" class="nav-link">Attendance</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/scanner_users/index.php" class="nav-link">Scanners</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/certificates/index.php" class="nav-link">Certificates</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/reports/index.php" class="nav-link">Reports</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/logs/index.php" class="nav-link">Logs</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:12px; height:12px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <div style="margin-bottom: 2rem;">
            <h2>Manage Gallery Highlights</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Upload and delete student portal homepage showcase photos. (Allowed formats: JPG, PNG, WEBP. Max size: 5MB)</p>
        </div>

        <?php display_flash_message(); ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger show-alert-anim">
                <i data-lucide="alert-triangle" class="alert-icon"></i>
                <div class="alert-content">
                    <ul style="padding-left: 1rem; margin: 0;">
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo e($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="dashboard-panel">
            <!-- Left Side: Gallery Items Grid -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3>Uploaded Gallery Photos (<?php echo count($photos); ?>)</h3>
                    </div>
                    
                    <?php if (empty($photos)): ?>
                        <div style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                            <i data-lucide="image" style="width: 48px; height: 48px; opacity: 0.3; margin-bottom: 1rem; display: block; margin-left: auto; margin-right: auto;"></i>
                            <p>No photos uploaded yet. Use the panel on the right to upload high-quality event photos.</p>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1rem; padding: 1rem 0;">
                            <?php foreach ($photos as $photo): ?>
                                <div class="gallery-admin-card" style="border: 1px solid var(--border-color); border-radius: var(--radius-sm); overflow: hidden; background: rgba(255, 255, 255, 0.02); display: flex; flex-direction: column; transition: all 0.2s ease;">
                                    <div style="position: relative; width: 100%; height: 120px; overflow: hidden;">
                                        <img src="<?php echo $photo['url']; ?>" alt="Highlight Photo" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                                    </div>
                                    <div style="padding: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem; flex-grow: 1; justify-content: space-between;">
                                        <div style="font-size: 0.75rem; color: var(--text-muted); word-break: break-all;" title="<?php echo e($photo['name']); ?>">
                                            <?php 
                                                $display_name = strlen($photo['name']) > 24 ? substr($photo['name'], 0, 21) . '...' : $photo['name'];
                                                echo e($display_name); 
                                            ?>
                                        </div>
                                        <form action="<?php echo BASE_URL; ?>admin/gallery.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this photo from the highlights slider?');" style="margin: 0;">
                                            <?php csrf_input(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="filename" value="<?php echo e($photo['name']); ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" style="width: 100%; justify-content: center; display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.4rem 0.5rem;">
                                                <i data-lucide="trash-2" style="width: 12px; height: 12px;"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side: Upload Form Widget -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3>Upload New Highlight Photo</h3>
                    </div>

                    <form action="<?php echo BASE_URL; ?>admin/gallery.php" method="POST" enctype="multipart/form-data">
                        <?php csrf_input(); ?>
                        <input type="hidden" name="action" value="upload">

                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label" for="photo">Select Photo File</label>
                            <input type="file" id="photo" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.webp" required style="padding: 0.5rem;">
                            <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.4rem;">Allowed formats: JPG, JPEG, PNG, WEBP. Recommended aspect ratio is 16:9 for the home slider.</small>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i data-lucide="upload-cloud"></i> Upload Photo
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
