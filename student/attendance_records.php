<?php
/**
 * Student Attendance History
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
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$scans = [];
try {
    $stmt = $conn->prepare("
        SELECT a.scan_type, a.scanned_at,
               e.name as event_name, e.event_date, e.venue
        FROM attendance_scans a
        JOIN qr_tokens q ON a.qr_token_id = q.id
        JOIN event_registrations r ON q.registration_id = r.id
        JOIN events e ON r.event_id = e.id
        WHERE r.student_id = ?
        ORDER BY a.scanned_at DESC
    ");
    $stmt->execute([$student['id']]);
    $scans = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch student scans: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - Accolades Connect</title>
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
                <li><a href="<?php echo BASE_URL; ?>student/attendance_records.php" class="nav-link active">My Attendance</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/certificates.php" class="nav-link">My Certificates</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/profile.php" class="nav-link">My Profile</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:14px; height:14px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <div style="margin-bottom: 2rem;">
            <h2>My Attendance Logs</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Review your check-in records for department events.</p>
        </div>

        <?php display_flash_message(); ?>

        <div class="card">
            <?php if (empty($scans)): ?>
                <div style="text-align: center; padding: 3rem 1rem;">
                    <i data-lucide="calendar-x" style="width: 40px; height: 40px; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <p style="color: var(--text-muted);">No attendance check-ins recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Event Date</th>
                                <th>Scan Type</th>
                                <th>Checked In At</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scans as $scan): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo e($scan['event_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($scan['event_date'])); ?></td>
                                    <td style="text-transform: capitalize;"><?php echo e($scan['scan_type']); ?> Scan</td>
                                    <td><?php echo date('d M Y, h:i A', strtotime($scan['scanned_at'])); ?></td>
                                    <td>
                                        <span class="badge badge-approved">
                                            <i data-lucide="check" style="width:10px; height:10px; display:inline-block;"></i> Present
                                        </span>
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
