<?php
/**
 * Admin: Certificates Dashboard
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Access Control
require_role('admin');

// Fetch all events with attendance and certificate stats
$events = [];
try {
    $events = $conn->query("
        SELECT e.id, e.name, e.event_date, e.venue, e.certificate_template,
               (SELECT COUNT(DISTINCT r.id) 
                FROM event_registrations r
                JOIN qr_tokens q ON q.registration_id = r.id
                JOIN attendance_scans a ON a.qr_token_id = q.id AND a.scan_type = 'entry'
                WHERE r.event_id = e.id) as attended_count,
               (SELECT COUNT(*) 
                FROM certificates c
                JOIN event_registrations r ON c.registration_id = r.id
                WHERE r.event_id = e.id) as issued_count
        FROM events e
        ORDER BY e.event_date DESC
    ")->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch events for certificates dashboard: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Certificates - Accolades Connect</title>
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
                <li><a href="<?php echo BASE_URL; ?>admin/certificates/index.php" class="nav-link active">Certificates</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/reports/index.php" class="nav-link">Reports</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/logs/index.php" class="nav-link">Logs</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:12px; height:12px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <header style="margin-bottom: 2rem;">
            <h2>MAR Certificate Generation</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Select an event to issue or revoke student certificates. Note that a certificate template is required for issuance.</p>
        </header>

        <?php display_flash_message(); ?>

        <div class="card">
            <?php if (empty($events)): ?>
                <div style="text-align: center; padding: 4rem 1rem; color: var(--text-muted);">
                    <i data-lucide="award" style="width: 40px; height: 40px; margin-bottom: 1rem;"></i>
                    <p>No events found in the database.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Event Details</th>
                                <th>Venue</th>
                                <th>Template</th>
                                <th>Attended Students</th>
                                <th>Certificates Issued</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <?php 
                                $is_remote_template = !empty($event['certificate_template']) && preg_match('/^https?:\/\//i', $event['certificate_template']);
                                $has_template = $is_remote_template || (!empty($event['certificate_template']) && file_exists(UPLOAD_PATH . '/certificate_templates/' . $event['certificate_template']));
                                ?>
                                <tr>
                                    <td style="font-weight: 600;">
                                        <?php echo e($event['name']); ?>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 400; margin-top: 0.25rem;">
                                            Date: <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                        </div>
                                    </td>
                                    <td><?php echo e($event['venue']); ?></td>
                                    <td>
                                         <?php if ($has_template): ?>
                                             <button onclick="openCertificateCustomizer(<?php echo $event['id']; ?>); return false;" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                                 <i data-lucide="sliders" style="width: 14px; height: 14px;"></i> Customize
                                             </button>
                                         <?php else: ?>
                                             <button onclick="openCertificateCustomizer(<?php echo $event['id']; ?>); return false;" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                                 <i data-lucide="plus" style="width: 14px; height: 14px;"></i> Add Template
                                             </button>
                                         <?php endif; ?>
                                     </td>
                                    <td style="font-weight: 500;">
                                        <i data-lucide="users" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 0.25rem; color: var(--text-muted);"></i>
                                        <?php echo $event['attended_count']; ?>
                                    </td>
                                    <td style="font-weight: 500;">
                                        <i data-lucide="award" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: 0.25rem; color: var(--success);"></i>
                                        <?php echo $event['issued_count']; ?> / <?php echo $event['attended_count']; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>admin/certificates/manage.php?event_id=<?php echo $event['id']; ?>" class="btn btn-primary btn-sm">
                                            <i data-lucide="settings" style="width: 14px; height: 14px;"></i> Manage
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

    <script>
        function openCertificateCustomizer(eventId) {
            const url = 'customize.php?event_id=' + eventId;
            const width = 1200;
            const height = 850;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            window.open(url, 'CustomizeCertificate', `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`);
        }
    </script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
