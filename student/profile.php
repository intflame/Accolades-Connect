<?php
/**
 * Student Profile Details
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Access Control
require_role('student');

$student = get_current_student();
if (!$student) {
    set_flash_message('danger', 'Unable to retrieve student profile.');
    header("Location: " . BASE_URL . "student/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Accolades Connect</title>
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2>My Profile Details</h2>
                <a href="<?php echo BASE_URL; ?>student/update_profile.php" class="btn btn-secondary btn-sm">
                    <i data-lucide="edit-3"></i> Edit Profile
                </a>
            </div>

            <?php display_flash_message(); ?>

            <div class="card">
                <!-- User Identity Card header -->
                <div class="profile-photo-container">
                    <?php if (!empty($student['profile_photo']) && file_exists(UPLOAD_PATH . '/profile_photos/' . $student['profile_photo'])): ?>
                        <img src="<?php echo BASE_URL; ?>uploads/profile_photos/<?php echo e($student['profile_photo']); ?>" alt="Profile Photo" class="profile-photo-preview">
                    <?php else: ?>
                        <!-- Styled CSS Initial Avatar -->
                        <div class="profile-photo-preview" style="display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%); color: #ffffff; font-size: 2.25rem; font-family: var(--font-heading); font-weight: 700;">
                            <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.25rem;"><?php echo e($student['name']); ?></h3>
                        <span class="badge badge-active">STUDENT Account</span>
                    </div>
                </div>

                <!-- Profile Field Breakdown -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem; margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 2rem;">
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem;">
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: block;">Email Address</span>
                            <strong style="font-size: 1rem; color: #ffffff;"><?php echo e($student['email']); ?></strong>
                            <span style="font-size: 0.75rem; color: var(--success); display: block; margin-top: 0.15rem;"><i data-lucide="check-circle" style="width:10px; height:10px; display:inline-block; vertical-align:middle;"></i> Verified email address</span>
                        </div>
                        
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: block;">Course & Batch</span>
                            <strong style="font-size: 1rem; color: #ffffff;"><?php echo e($student['course'] ?? 'BCA'); ?> - Batch <?php echo e($student['batch_name'] ?? 'N/A'); ?></strong>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem;">
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: block;">Class Roll No.</span>
                            <strong style="font-size: 1rem; color: #ffffff;"><?php echo e($student['class_roll']); ?></strong>
                        </div>
                        
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: block;">University Roll No.</span>
                            <strong style="font-size: 1rem; color: #ffffff;"><?php echo e($student['university_roll']); ?></strong>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem;">
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: block;">Contact Number</span>
                            <strong style="font-size: 1rem; color: #ffffff;"><?php echo e($student['contact_number']); ?></strong>
                        </div>
                        
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: block;">WhatsApp Number</span>
                            <strong style="font-size: 1rem; color: #ffffff;"><?php echo e($student['whatsapp_number']); ?></strong>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem;">
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: block;">Food Preference</span>
                            <strong style="font-size: 1rem; color: #ffffff; text-transform: capitalize;"><?php echo e($student['food_preference']); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Info footer -->
                <div style="margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem; display: flex; gap: 0.5rem; align-items: center; color: var(--text-muted); font-size: 0.8rem;">
                    <i data-lucide="shield-alert" style="width: 14px; height: 14px; color: var(--warning);"></i>
                    <span>Sensitive details (Email, Batch, Roll numbers) are locked and can only be updated by a department coordinator.</span>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
