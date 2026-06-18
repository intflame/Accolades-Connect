<?php
/**
 * Admin: Manage Events Portal
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Access Control
require_role('admin');

$errors = [];

// Handle Quick Status Update or Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_request();

    $action = trim($_POST['action'] ?? '');
    $event_id = intval($_POST['event_id'] ?? 0);

    if ($event_id > 0) {
        try {
            // Check existence
            $stmt = $conn->prepare("SELECT name, upi_qr_image FROM events WHERE id = ?");
            $stmt->execute([$event_id]);
            $event = $stmt->fetch();

            if (!$event) {
                $errors[] = "Event record not found.";
            } else {
                if ($action === 'change_status') {
                    $new_status = trim($_POST['status'] ?? '');
                    if (in_array($new_status, ['upcoming', 'registration_open', 'registration_closed', 'completed', 'cancelled'])) {
                        $stmt = $conn->prepare("UPDATE events SET status = ? WHERE id = ?");
                        $stmt->execute([$new_status, $event_id]);
                        log_activity($_SESSION['user_id'], 'event_status_updated', "Updated event status of '{$event['name']}' to: $new_status");
                        set_flash_message('success', "Event '{$event['name']}' status updated to " . str_replace('_', ' ', $new_status));
                    }
                } elseif ($action === 'delete') {
                    // Delete QR image files if they exist
                    if (!empty($event['upi_qr_image'])) {
                        $qr_path = UPLOAD_PATH . '/upi_qr/' . $event['upi_qr_image'];
                        if (file_exists($qr_path)) {
                            unlink($qr_path);
                        }
                    }
                    
                    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
                    $stmt->execute([$event_id]);
                    log_activity($_SESSION['user_id'], 'event_deleted', "Deleted event: {$event['name']}");
                    set_flash_message('success', "Event '{$event['name']}' has been permanently deleted.");
                }
                
                header("Location: " . BASE_URL . "admin/events/index.php");
                exit();
            }
        } catch (Exception $e) {
            error_log("Failed to process event actions: " . $e->getMessage());
            $errors[] = "Database operation failed. Ensure no students have active registration scans before deleting.";
        }
    }
}

// Fetch all events with registration statistics
$events = [];
try {
    $events = $conn->query("
        SELECT e.*, 
               COUNT(r.id) as total_regs,
               SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved_regs
        FROM events e
        LEFT JOIN event_registrations r ON e.id = r.event_id
        GROUP BY e.id
        ORDER BY e.event_date ASC
    ")->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch events: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Accolades Connect</title>
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
                <li><a href="<?php echo BASE_URL; ?>admin/events/index.php" class="nav-link active">Events</a></li>
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <h2>Manage Events</h2>
                <p style="color: var(--text-muted); font-size: 0.95rem;">Configure departmental events, fees, deadline periods, and view registrations.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>admin/events/create.php" class="btn btn-primary btn-sm">
                <i data-lucide="calendar-plus"></i> Create New Event
            </a>
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

        <!-- Events Listing -->
        <div class="card">
            <?php if (empty($events)): ?>
                <div style="text-align: center; padding: 4rem 1rem; color: var(--text-muted);">
                    <i data-lucide="calendar" style="width: 40px; height: 40px; margin-bottom: 1rem;"></i>
                    <p>No events have been created yet.</p>
                    <a href="<?php echo BASE_URL; ?>admin/events/create.php" style="display: inline-block; margin-top: 1rem; font-weight: 600;">Create Event Now &rarr;</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Event Details</th>
                                <th>Venue</th>
                                <th>Fee</th>
                                <th>Deadlines & Scanning Time</th>
                                <th>Registrations</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td style="font-weight: 600; font-size: 1rem;">
                                        <?php echo e($event['name']); ?>
                                        <?php if (!empty($event['description'])): ?>
                                            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 400; margin-top: 0.25rem; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo e($event['description']); ?>">
                                                <?php echo e($event['description']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 400; margin-top: 0.25rem;">
                                            Date: <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                        </div>
                                    </td>
                                    <td><?php echo e($event['venue']); ?></td>
                                    <td style="font-weight: bold; color: var(--accent);">
                                        <?php echo $event['registration_fee'] > 0 ? '₹' . number_format($event['registration_fee'], 2) : 'Free'; ?>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);">
                                            <strong>Deadline:</strong> <?php echo date('d M, h:i A', strtotime($event['registration_deadline'])); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                                            <strong>Scan:</strong> <?php echo date('h:i A', strtotime($event['scan_start_time'])); ?> - <?php echo date('h:i A', strtotime($event['scan_end_time'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.9rem;">
                                            <a href="<?php echo BASE_URL; ?>admin/registrations/index.php?event_id=<?php echo $event['id']; ?>" style="font-weight: 600;">
                                                <?php echo $event['total_regs']; ?> Total
                                            </a>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--success);"><?php echo $event['approved_regs']; ?> Verified</div>
                                    </td>
                                    <td>
                                        <!-- Quick Status Changer Form -->
                                        <form action="<?php echo BASE_URL; ?>admin/events/index.php" method="POST" style="margin:0;">
                                            <?php csrf_input(); ?>
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                            <select name="status" class="form-control" style="font-size: 0.8rem; padding: 0.25rem 0.5rem; width: auto;" onchange="this.form.submit()">
                                                <option value="upcoming" <?php echo $event['status'] === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                                <option value="registration_open" <?php echo $event['status'] === 'registration_open' ? 'selected' : ''; ?>>Open</option>
                                                <option value="registration_closed" <?php echo $event['status'] === 'registration_closed' ? 'selected' : ''; ?>>Closed</option>
                                                <option value="completed" <?php echo $event['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $event['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.25rem;">
                                            <a href="<?php echo BASE_URL; ?>admin/registrations/index.php?event_id=<?php echo $event['id']; ?>" class="btn btn-secondary btn-sm" title="View Registered Students">
                                                <i data-lucide="users" style="width: 14px; height: 14px;"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>admin/events/edit.php?event_id=<?php echo $event['id']; ?>" class="btn btn-accent btn-sm" title="Edit Event Settings">
                                                <i data-lucide="edit-3" style="width: 14px; height: 14px;"></i>
                                            </a>
                                            <form action="<?php echo BASE_URL; ?>admin/events/index.php" method="POST" onsubmit="return confirm('Permanently delete this event? This will also remove registrations, payments and QR tokens for it.');">
                                                <?php csrf_input(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete Event">
                                                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
