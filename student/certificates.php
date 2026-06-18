<?php
/**
 * Student Certificates Dashboard
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
    set_flash_message('danger', 'Profile records missing. Please contact administrator.');
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// Fetch all certificates earned by the student
$certificates = [];
try {
    $stmt = $conn->prepare("
        SELECT c.id as certificate_id, c.certificate_code, c.issued_at,
               e.id as event_id, e.name as event_name, e.event_date, e.venue
        FROM certificates c
        JOIN event_registrations r ON c.registration_id = r.id
        JOIN events e ON r.event_id = e.id
        WHERE r.student_id = ?
        ORDER BY c.issued_at DESC
    ");
    $stmt->execute([$student['id']]);
    $certificates = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch student certificates: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Certificates - Accolades Connect</title>
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
                <li><a href="<?php echo BASE_URL; ?>student/certificates.php" class="nav-link active">My Certificates</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/profile.php" class="nav-link">My Profile</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:14px; height:14px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <header style="margin-bottom: 2rem;">
            <h1 style="font-size: 2rem; margin-bottom: 0.25rem;">My Earned Certificates</h1>
            <p style="color: var(--text-muted); font-size: 0.95rem;">View and print your Co-Curricular & MAR activity certificates.</p>
        </header>

        <?php display_flash_message(); ?>

        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3>Certificates List</h3>
                <i data-lucide="award" style="color: var(--success); width: 22px; height: 22px;"></i>
            </div>

            <?php if (empty($certificates)): ?>
                <div style="text-align: center; padding: 4rem 1rem; color: var(--text-muted);">
                    <i data-lucide="award" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1.5rem; opacity: 0.5;"></i>
                    <p style="font-size: 1.05rem; margin-bottom: 0.5rem;">No certificates issued yet.</p>
                    <p style="font-size: 0.85rem;">Certificates are generated once your event attendance has been scanned and verified by the event coordinator.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Date & Venue</th>
                                <th>Certificate Code</th>
                                <th>Issue Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($certificates as $cert): ?>
                                <tr>
                                    <td style="font-weight: 600;">
                                        <?php echo e($cert['event_name']); ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;"><?php echo date('M d, Y', strtotime($cert['event_date'])); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($cert['venue']); ?></div>
                                    </td>
                                    <td>
                                        <code style="font-family: monospace; font-size: 0.9rem; color: var(--accent); font-weight: bold;"><?php echo e($cert['certificate_code']); ?></code>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($cert['issued_at'])); ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>student/view_certificate.php?id=<?php echo $cert['certificate_id']; ?>" target="_blank" class="btn btn-success btn-sm" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <i data-lucide="eye" style="width: 14px; height: 14px;"></i> View & Print
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
