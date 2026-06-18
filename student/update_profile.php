<?php
/**
 * Update Student Profile
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Access Control
require_role('student');

$student = get_current_student();
if (!$student) {
    set_flash_message('danger', 'Unable to retrieve student profile.');
    header("Location: " . BASE_URL . "student/dashboard.php");
    exit();
}

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_request();

    $contact = trim($_POST['contact_number'] ?? '');
    $whatsapp = trim($_POST['whatsapp_number'] ?? '');
    $food_pref = trim($_POST['food_preference'] ?? 'veg');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validations
    if (empty($contact)) $errors[] = "Contact number is required.";
    if (empty($whatsapp)) $errors[] = "WhatsApp number is required.";
    if (!in_array($food_pref, ['veg', 'non-veg'])) $food_pref = 'veg';

    // Password validation if filled
    $update_password = false;
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = "New password must be at least 6 characters long.";
        } elseif ($password !== $confirm_password) {
            $errors[] = "Confirm password does not match.";
        } else {
            $update_password = true;
        }
    }

    // Process file upload if selected
    $uploaded_photo = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_res = handle_file_upload($_FILES['profile_photo'], 'profile_photos');
        if ($upload_res['success']) {
            $uploaded_photo = $upload_res['filename'];
        } else {
            $errors[] = $upload_res['message'];
        }
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // 1. Update Student details
            if ($uploaded_photo) {
                // Remove old photo if exists
                if (!empty($student['profile_photo'])) {
                    $old_photo_path = UPLOAD_PATH . '/profile_photos/' . $student['profile_photo'];
                    if (file_exists($old_photo_path)) {
                        unlink($old_photo_path);
                    }
                }
                
                $stmt = $conn->prepare("
                    UPDATE students 
                    SET contact_number = ?, whatsapp_number = ?, food_preference = ?, profile_photo = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$contact, $whatsapp, $food_pref, $uploaded_photo, $student['id']]);
            } else {
                $stmt = $conn->prepare("
                    UPDATE students 
                    SET contact_number = ?, whatsapp_number = ?, food_preference = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$contact, $whatsapp, $food_pref, $student['id']]);
            }

            // 2. Update Password if requested
            if ($update_password) {
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$new_hash, $student['user_id']]);
            }

            // Log activity
            log_activity($student['user_id'], 'profile_updated', "Student updated contact details, preference, or avatar.");

            $conn->commit();

            set_flash_message('success', 'Profile updated successfully.');
            header("Location: " . BASE_URL . "student/profile.php");
            exit();

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log("Failed to update profile: " . $e->getMessage());
            $errors[] = "Database update failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Accolades Connect</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?php echo BASE_URL; ?>index.php" class="brand">
                <i data-lucide="calendar-check"></i>
                <span>Accolades Connect</span>
            </a>
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>student/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/events.php" class="nav-link">Browse Events</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/attendance_records.php" class="nav-link">My Attendance</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/certificates.php" class="nav-link">My Certificates</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/profile.php" class="nav-link active">My Profile</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:14px; height:14px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <div style="max-width: 700px; margin: 0 auto;">
            <div style="margin-bottom: 2rem;">
                <h2>Edit Profile</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Modify your profile details and preferences.</p>
            </div>

            <!-- Display Errors -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger show-alert-anim">
                    <i data-lucide="alert-triangle" class="alert-icon"></i>
                    <div class="alert-content">
                        <ul style="padding-left: 1rem; margin: 0;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo e($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <form action="<?php echo BASE_URL; ?>student/update_profile.php" method="POST" enctype="multipart/form-data">
                    <?php csrf_input(); ?>

                    <!-- Current profile pic preview & edit -->
                    <div class="profile-photo-container">
                        <?php if (!empty($student['profile_photo']) && file_exists(UPLOAD_PATH . '/profile_photos/' . $student['profile_photo'])): ?>
                            <img src="<?php echo BASE_URL; ?>uploads/profile_photos/<?php echo e($student['profile_photo']); ?>" alt="Profile Photo" class="profile-photo-preview">
                        <?php else: ?>
                            <div class="profile-photo-preview" style="display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%); color: #ffffff; font-size: 2.25rem; font-family: var(--font-heading); font-weight: 700;">
                                <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <label class="form-label" for="profile_photo">Upload New Profile Photo</label>
                            <input type="file" id="profile_photo" name="profile_photo" class="form-control" accept="image/png, image/jpeg, image/jpg" style="padding: 0.4rem;">
                            <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">PNG, JPG or JPEG. Max size 5MB.</small>
                        </div>
                    </div>

                    <!-- Sensitive Locked Properties (Read-Only UI) -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" value="<?php echo e($student['name']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="text" class="form-control" value="<?php echo e($student['email']); ?>" disabled>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Course</label>
                            <input type="text" class="form-control" value="<?php echo e($student['course'] ?? 'BCA'); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Batch</label>
                            <input type="text" class="form-control" value="Batch <?php echo e($student['batch_name']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Class Roll No.</label>
                            <input type="text" class="form-control" value="<?php echo e($student['class_roll']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label class="form-label">University Roll No.</label>
                            <input type="text" class="form-control" value="<?php echo e($student['university_roll']); ?>" disabled>
                        </div>
                    </div>

                    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 2rem 0;">

                    <!-- Editable Properties -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="contact_number">Contact Number</label>
                            <input type="tel" id="contact_number" name="contact_number" class="form-control" value="<?php echo e($student['contact_number']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="whatsapp_number">WhatsApp Number</label>
                            <input type="tel" id="whatsapp_number" name="whatsapp_number" class="form-control" value="<?php echo e($student['whatsapp_number']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="food_preference">Food Preference</label>
                            <select id="food_preference" name="food_preference" class="form-control" required>
                                <option value="veg" <?php echo $student['food_preference'] === 'veg' ? 'selected' : ''; ?>>Veg</option>
                                <option value="non-veg" <?php echo $student['food_preference'] === 'non-veg' ? 'selected' : ''; ?>>Non-Veg</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="password">New Password (leave blank to keep current)</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••">
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                        <a href="<?php echo BASE_URL; ?>student/profile.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
