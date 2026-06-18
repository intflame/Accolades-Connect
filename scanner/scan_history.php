<?php
/**
 * Scanner Check-in Log History
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Access Control
require_role(['scanner', 'admin']);

$scans = [];
try {
    $stmt = $conn->prepare("
        SELECT a.scan_type, a.scanned_at,
               s.name as student_name, s.class_roll,
               e.name as event_name
        FROM attendance_scans a
        JOIN qr_tokens q ON a.qr_token_id = q.id
        JOIN event_registrations r ON q.registration_id = r.id
        JOIN students s ON r.student_id = s.id
        JOIN events e ON r.event_id = e.id
        WHERE a.scanned_by = ?
        ORDER BY a.scanned_at DESC
        LIMIT 50
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $scans = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch scanner logs: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan History - Accolades Connect</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?php echo BASE_URL; ?>scanner/dashboard.php" class="brand">
                <i data-lucide="scan-face"></i>
                <span>Scanner Panel</span>
            </a>
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>scanner/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="<?php echo BASE_URL; ?>scanner/scan_history.php" class="nav-link active">Scan History</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:14px; height:14px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <h2>Your Scan Activity History</h2>
                <p style="color: var(--text-muted); font-size: 0.95rem;">Review recent QR check-ins processed under your session.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>scanner/dashboard.php" class="btn btn-primary btn-sm">
                <i data-lucide="scan"></i> Launch Scanner
            </a>
        </div>

        <?php display_flash_message(); ?>

        <div class="card">
            <?php if (empty($scans)): ?>
                <div style="text-align: center; padding: 4rem 1rem;">
                    <i data-lucide="history" style="width: 40px; height: 40px; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <p style="color: var(--text-muted);">You have not processed any QR scans in this session yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Roll Number</th>
                                <th>Event Name</th>
                                <th>Scan Type</th>
                                <th>Processed Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scans as $scan): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo e($scan['student_name']); ?></td>
                                    <td><?php echo e($scan['class_roll']); ?></td>
                                    <td><?php echo e($scan['event_name']); ?></td>
                                    <td style="text-transform: capitalize;">
                                        <span style="font-weight: 500;"><?php echo e($scan['scan_type']); ?></span>
                                    </td>
                                    <td><?php echo date('d M Y, h:i:s A', strtotime($scan['scanned_at'])); ?></td>
                                    <td>
                                        <span class="badge badge-approved" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <i data-lucide="check" style="width:10px; height:10px;"></i> Verified
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
