<?php
/**
 * Admin: Event Registrants List Details
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Access Control
require_role('admin');

$event_id = intval($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
$batch_filter = intval($_GET['batch_id'] ?? $_POST['batch_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? $_POST['status'] ?? '');
$role_filter = trim($_GET['role'] ?? $_POST['role'] ?? '');

if (!$event_id) {
    set_flash_message('warning', 'Please select an event to view registrations.');
    header("Location: " . BASE_URL . "admin/events/index.php");
    exit();
}

// Handle Event Role Verification Action (Approve / Reject Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_request();

    $action = trim($_POST['action'] ?? '');
    $reg_id = intval($_POST['reg_id'] ?? 0);

    if ($reg_id > 0 && in_array($action, ['approve_role', 'reject_role'])) {
        try {
            // Get registration & student details
            $stmt = $conn->prepare("
                SELECT r.*, e.name as event_name, s.name as student_name, s.user_id as student_user_id
                FROM event_registrations r
                JOIN students s ON r.student_id = s.id
                JOIN events e ON r.event_id = e.id
                WHERE r.id = ?
            ");
            $stmt->execute([$reg_id]);
            $reg_data = $stmt->fetch();

            if (!$reg_data) {
                set_flash_message('danger', "Registration record not found.");
            } elseif ($reg_data['role_status'] !== 'pending') {
                set_flash_message('warning', "This role request has already been processed or is not pending.");
            } else {
                $conn->beginTransaction();

                if ($action === 'approve_role') {
                    // Update role and status
                    $stmt = $conn->prepare("
                        UPDATE event_registrations 
                        SET assigned_role = applied_role, role_status = 'approved' 
                        WHERE id = ?
                    ");
                    $stmt->execute([$reg_id]);

                    $role_lbl = $reg_data['applied_role'];
                    if ($role_lbl === 'volunteers') $role_lbl = 'Volunteer';
                    elseif ($role_lbl === 'OC') $role_lbl = 'Organizing Committee (OC)';
                    elseif ($role_lbl === 'CC') $role_lbl = 'Core Committee (CC)';

                    log_activity($_SESSION['user_id'], 'event_role_approved', "Approved '{$reg_data['applied_role']}' role for student '{$reg_data['student_name']}' on event '{$reg_data['event_name']}'.");
                    set_flash_message('success', "Approved request: {$reg_data['student_name']} is now a {$role_lbl} for the event.");

                } elseif ($action === 'reject_role') {
                    // Rejection: stays as default
                    $stmt = $conn->prepare("
                        UPDATE event_registrations 
                        SET role_status = 'rejected' 
                        WHERE id = ?
                    ");
                    $stmt->execute([$reg_id]);

                    log_activity($_SESSION['user_id'], 'event_role_rejected', "Rejected '{$reg_data['applied_role']}' role request for student '{$reg_data['student_name']}' on event '{$reg_data['event_name']}'.");
                    set_flash_message('warning', "Rejected role request for {$reg_data['student_name']}. The post remains as default 'Participant'.");
                }

                $conn->commit();
            }
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log("Event role approval/rejection process failure: " . $e->getMessage());
            set_flash_message('danger', "A database error occurred while processing the request.");
        }
    }
    header("Location: " . BASE_URL . "admin/registrations/index.php?event_id=" . $event_id . "&batch_id=" . $batch_filter . "&status=" . urlencode($status_filter) . "&role=" . urlencode($role_filter));
    exit();
}

// Fetch event details
$event = null;
try {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
} catch (Exception $e) {
    error_log("Failed to fetch event: " . $e->getMessage());
}

if (!$event) {
    set_flash_message('danger', 'Event not found.');
    header("Location: " . BASE_URL . "admin/events/index.php");
    exit();
}

// Fetch Batches for filters
$batches = [];
try {
    $batches = $conn->query("SELECT * FROM batches ORDER BY name DESC")->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch batches: " . $e->getMessage());
}

// Build query for registrations
$query_str = "
    SELECT r.id as reg_id, r.status as reg_status, r.payment_method, r.created_at as reg_date,
           r.assigned_role, r.applied_role, r.role_status,
           s.name as student_name, s.class_roll, s.university_roll, s.food_preference, s.course,
           b.name as batch_name,
           p.status as payment_status
    FROM event_registrations r
    JOIN students s ON r.student_id = s.id
    LEFT JOIN batches b ON s.batch_id = b.id
    LEFT JOIN payments p ON p.registration_id = r.id
    WHERE r.event_id = ?
";
$params = [$event_id];

if ($batch_filter > 0) {
    $query_str .= " AND s.batch_id = ?";
    $params[] = $batch_filter;
}

if (!empty($status_filter)) {
    $query_str .= " AND r.status = ?";
    $params[] = $status_filter;
}

if (!empty($role_filter)) {
    if ($role_filter === 'pending_request') {
        $query_str .= " AND r.role_status = 'pending'";
    } else {
        $query_str .= " AND r.assigned_role = ?";
        $params[] = $role_filter;
    }
}

$query_str .= " ORDER BY s.name ASC";

$registrations = [];
try {
    $stmt = $conn->prepare($query_str);
    $stmt->execute($params);
    $registrations = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch registrations: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrations: <?php echo e($event['name']); ?> - Accolades Connect</title>
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
                <li><a href="<?php echo BASE_URL; ?>admin/reports/index.php" class="nav-link">Reports</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/logs/index.php" class="nav-link">Logs</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:12px; height:12px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <a href="<?php echo BASE_URL; ?>admin/events/index.php" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.5rem;">
                    <i data-lucide="arrow-left" style="width:14px; height:14px;"></i> Return to Events
                </a>
                <h2>Event Registrations List</h2>
                <p style="color: var(--accent); font-weight: 600; font-size: 1.15rem; margin-top: 0.25rem;"><?php echo e($event['name']); ?></p>
            </div>
            
            <span style="color: var(--text-muted); font-size: 0.9rem; font-weight: 500; align-self: flex-end;">
                Total Registrants: <strong><?php echo count($registrations); ?></strong>
            </span>
        </div>

        <?php display_flash_message(); ?>

        <!-- Filters Form -->
        <div class="card" style="padding: 1.25rem; margin-bottom: 2rem;">
            <form action="<?php echo BASE_URL; ?>admin/registrations/index.php" method="GET" style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 1rem; align-items: flex-end;">
                <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">

                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="batch_id">Filter Batch</label>
                    <select id="batch_id" name="batch_id" class="form-control">
                        <option value="0">All Batches</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo $batch['id']; ?>" <?php echo $batch_filter == $batch['id'] ? 'selected' : ''; ?>>
                                Batch <?php echo e($batch['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="status">Filter Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="pending_payment" <?php echo $status_filter === 'pending_payment' ? 'selected' : ''; ?>>Pending Payment</option>
                        <option value="pending_verification" <?php echo $status_filter === 'pending_verification' ? 'selected' : ''; ?>>Pending Verification</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="role">Filter Role / Post</label>
                    <select id="role" name="role" class="form-control">
                        <option value="">All Roles</option>
                        <option value="participant" <?php echo $role_filter === 'participant' ? 'selected' : ''; ?>>Participant</option>
                        <option value="volunteers" <?php echo $role_filter === 'volunteers' ? 'selected' : ''; ?>>Volunteers</option>
                        <option value="OC" <?php echo $role_filter === 'OC' ? 'selected' : ''; ?>>OC</option>
                        <option value="CC" <?php echo $role_filter === 'CC' ? 'selected' : ''; ?>>CC</option>
                        <option value="pending_request" <?php echo $role_filter === 'pending_request' ? 'selected' : ''; ?>>Pending Role Requests</option>
                    </select>
                </div>

                <div></div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.25rem;">
                        Filter
                    </button>
                    <a href="<?php echo BASE_URL; ?>admin/registrations/index.php?event_id=<?php echo $event_id; ?>" class="btn btn-secondary" style="padding: 0.75rem 1.25rem;">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Registrants Table -->
        <div class="card">
            <?php if (empty($registrations)): ?>
                <div style="text-align: center; padding: 4rem 1rem; color: var(--text-muted);">
                    <i data-lucide="user-x" style="width: 40px; height: 40px; margin-bottom: 1rem;"></i>
                    <p>No student registrations recorded for this event yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Roll & Batch</th>
                                <th>Food Preference</th>
                                <th>Payment Method</th>
                                <th>Payment status</th>
                                <th>Registration Status</th>
                                <th>Event Role / Post</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrations as $reg): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo e($reg['student_name']); ?></td>
                                    <td>
                                        <div style="font-weight: 500;"><?php echo e($reg['course'] ?? 'BCA'); ?> - Batch <?php echo e($reg['batch_name'] ?? 'N/A'); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">Roll: <?php echo e($reg['class_roll']); ?></div>
                                    </td>
                                    <td style="text-transform: capitalize;"><?php echo e($reg['food_preference']); ?></td>
                                    <td style="text-transform: uppercase; font-size: 0.85rem; font-weight: 500;">
                                        <?php echo $reg['payment_method'] ?? '<span style="color: var(--text-muted);">N/A</span>'; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $reg['payment_status'] ?? 'pending'; ?>">
                                            <?php echo $reg['payment_status'] ? strtoupper($reg['payment_status']) : 'UNPAID'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $reg['reg_status']; ?>">
                                            <?php echo str_replace('_', ' ', $reg['reg_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                            <div>
                                                <?php 
                                                $assigned = $reg['assigned_role'];
                                                if ($assigned === 'volunteers') {
                                                    echo '<span class="badge badge-approved" style="background: var(--success); color: #ffffff;">Volunteer</span>';
                                                } elseif ($assigned === 'OC') {
                                                    echo '<span class="badge badge-approved" style="background: var(--success); color: #ffffff;">Organizing Committee (OC)</span>';
                                                } elseif ($assigned === 'CC') {
                                                    echo '<span class="badge badge-approved" style="background: var(--success); color: #ffffff;">Core Committee (CC)</span>';
                                                } else {
                                                    echo '<span style="color: var(--text-muted);">Participant</span>';
                                                }
                                                ?>
                                            </div>
                                            <?php if ($reg['role_status'] === 'pending'): ?>
                                                <div style="font-size: 0.8rem; font-weight: 600; color: var(--warning); margin-top: 0.25rem;">
                                                    Requested: <?php 
                                                    $app = $reg['applied_role'];
                                                    if ($app === 'volunteers') echo 'Volunteer';
                                                    elseif ($app === 'OC') echo 'OC';
                                                    elseif ($app === 'CC') echo 'CC';
                                                    ?>
                                                </div>
                                                <div style="display: flex; gap: 0.25rem; margin-top: 0.25rem;">
                                                    <form action="" method="POST" style="margin: 0;" onsubmit="return confirm('Approve this role request?');">
                                                        <?php csrf_input(); ?>
                                                        <input type="hidden" name="action" value="approve_role">
                                                        <input type="hidden" name="reg_id" value="<?php echo $reg['reg_id']; ?>">
                                                        <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                                                        <button type="submit" class="btn btn-success btn-sm" style="padding: 0.15rem 0.35rem; font-size: 0.7rem;">
                                                            Approve
                                                        </button>
                                                    </form>
                                                    <form action="" method="POST" style="margin: 0;" onsubmit="return confirm('Reject this role request?');">
                                                        <?php csrf_input(); ?>
                                                        <input type="hidden" name="action" value="reject_role">
                                                        <input type="hidden" name="reg_id" value="<?php echo $reg['reg_id']; ?>">
                                                        <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.15rem 0.35rem; font-size: 0.7rem;">
                                                            Reject
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php elseif ($reg['role_status'] === 'rejected'): ?>
                                                <div style="font-size: 0.75rem; color: var(--danger); font-style: italic;">
                                                    Requested <?php echo strtoupper($reg['applied_role']); ?> (Rejected)
                                                </div>
                                            <?php endif; ?>
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
