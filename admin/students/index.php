<?php
/**
 * Admin: Student Profiles Management Queue
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Access Control
require_role('admin');

$errors = [];
$success = null;

// Handle Actions (Approve, Suspend, Activate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_post_request();

    $action = trim($_POST['action']);
    $user_id = intval($_POST['target_user_id'] ?? 0);

    if ($user_id > 0) {
        try {
            $stmt = $conn->prepare("SELECT email, status FROM users WHERE id = ? AND role = 'student'");
            $stmt->execute([$user_id]);
            $target_user = $stmt->fetch();

            if (!$target_user) {
                $errors[] = "Target student account not found.";
            } else {
                if ($action === 'approve') {
                    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                    $stmt->execute([$user_id]);
                    log_activity($_SESSION['user_id'], 'student_approved', "Approved student account ID: $user_id, Email: {$target_user['email']}");
                    set_flash_message('success', "Student account approved successfully.");
                } elseif ($action === 'suspend') {
                    $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
                    $stmt->execute([$user_id]);
                    log_activity($_SESSION['user_id'], 'student_suspended', "Suspended student account ID: $user_id, Email: {$target_user['email']}");
                    set_flash_message('success', "Student account suspended.");
                } elseif ($action === 'activate') {
                    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                    $stmt->execute([$user_id]);
                    log_activity($_SESSION['user_id'], 'student_activated', "Re-activated student account ID: $user_id, Email: {$target_user['email']}");
                    set_flash_message('success', "Student account activated.");
                } elseif ($action === 'delete') {
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    log_activity($_SESSION['user_id'], 'student_deleted', "Deleted student account ID: $user_id, Email: {$target_user['email']}");
                    set_flash_message('success', "Student account deleted successfully.");
                }
                
                header("Location: " . BASE_URL . "admin/students/index.php");
                exit();
            }
        } catch (Exception $e) {
            error_log("Failed to process student action: " . $e->getMessage());
            $errors[] = "Database operation failed. Please try again.";
        }
    }
}

// Filters setup
$search = trim($_GET['search'] ?? '');
$batch_filter = intval($_GET['batch_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? '');

// Fetch Batches for filters
$batches = [];
try {
    $batches = $conn->query("SELECT * FROM batches ORDER BY name DESC")->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch batches in students list: " . $e->getMessage());
}

// Build Query
$query_str = "
    SELECT s.*, u.email, u.status, b.name as batch_name 
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN batches b ON s.batch_id = b.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query_str .= " AND (s.name LIKE ? OR s.class_roll LIKE ? OR s.university_roll LIKE ? OR u.email LIKE ?)";
    $search_val = "%$search%";
    $params = array_merge($params, [$search_val, $search_val, $search_val, $search_val]);
}

if ($batch_filter > 0) {
    $query_str .= " AND s.batch_id = ?";
    $params[] = $batch_filter;
}

if (!empty($status_filter)) {
    $query_str .= " AND u.status = ?";
    $params[] = $status_filter;
}

$query_str .= " ORDER BY u.status = 'pending_approval' DESC, s.name ASC";

$students = [];
try {
    $stmt = $conn->prepare($query_str);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch students with filters: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Accolades Connect</title>
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
                <li><a href="<?php echo BASE_URL; ?>admin/students/index.php" class="nav-link active">Students</a></li>
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
            <h2>Manage Students</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Review student details, approve pending accounts, and lock/unlock access.</p>
        </div>

        <?php display_flash_message(); ?>

        <!-- Search and Filter Bar -->
        <div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
            <form action="<?php echo BASE_URL; ?>admin/students/index.php" method="GET" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: flex-end;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="search">Search Name / Roll / Email</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Search..." value="<?php echo e($search); ?>">
                </div>

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
                        <option value="pending_otp" <?php echo $status_filter === 'pending_otp' ? 'selected' : ''; ?>>Pending OTP</option>
                        <option value="pending_approval" <?php echo $status_filter === 'pending_approval' ? 'selected' : ''; ?>>Pending Approval</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.25rem;">
                        <i data-lucide="search" style="width: 16px; height: 16px;"></i>
                    </button>
                    <a href="<?php echo BASE_URL; ?>admin/students/index.php" class="btn btn-secondary" style="padding: 0.75rem 1.25rem;" title="Reset Filters">
                        <i data-lucide="refresh-cw" style="width: 16px; height: 16px;"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Student Listing Queue -->
        <div class="card">
            <?php if (empty($students)): ?>
                <div style="text-align: center; padding: 4rem 1rem; color: var(--text-muted);">
                    <i data-lucide="users" style="width: 40px; height: 40px; margin-bottom: 1rem;"></i>
                    <p>No student records found matching the criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Name & Email</th>
                                <th>Batch & Rolls</th>
                                <th>Contacts</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $stud): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($stud['profile_photo']) && file_exists(UPLOAD_PATH . '/profile_photos/' . $stud['profile_photo'])): ?>
                                            <img src="<?php echo BASE_URL; ?>uploads/profile_photos/<?php echo e($stud['profile_photo']); ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid var(--primary);">
                                        <?php else: ?>
                                            <div style="width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(99, 102, 241, 0.2); color: var(--primary); font-weight: bold; font-size: 0.95rem;">
                                                <?php echo strtoupper(substr($stud['name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo e($stud['name']); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($stud['email']); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500; font-size: 0.9rem;"><?php echo e($stud['course'] ?? 'BCA'); ?> - Batch <?php echo e($stud['batch_name'] ?? 'N/A'); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">Class Roll: <?php echo e($stud['class_roll']); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">Uni Roll: <?php echo e($stud['university_roll']); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.85rem;"><i data-lucide="phone" style="width:10px; height:10px; display:inline-block; vertical-align:middle; margin-right: 0.25rem;"></i> <?php echo e($stud['contact_number']); ?></div>
                                        <div style="font-size: 0.85rem; color: #34d399;"><i data-lucide="message-square" style="width:10px; height:10px; display:inline-block; vertical-align:middle; margin-right: 0.25rem;"></i> <?php echo e($stud['whatsapp_number']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo ($stud['status'] === 'active' ? 'approved' : ($stud['status'] === 'suspended' ? 'rejected' : 'pending')); ?>">
                                            <?php echo str_replace('_', ' ', $stud['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                                            <?php if ($stud['status'] === 'pending_approval'): ?>
                                                <form action="<?php echo BASE_URL; ?>admin/students/index.php" method="POST" onsubmit="return confirm('Are you sure you want to approve this student?');">
                                                    <?php csrf_input(); ?>
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="target_user_id" value="<?php echo $stud['user_id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i data-lucide="check"></i> Approve
                                                    </button>
                                                </form>
                                            <?php elseif ($stud['status'] === 'active'): ?>
                                                <form action="<?php echo BASE_URL; ?>admin/students/index.php" method="POST" onsubmit="return confirm('Are you sure you want to suspend this student account?');">
                                                    <?php csrf_input(); ?>
                                                    <input type="hidden" name="action" value="suspend">
                                                    <input type="hidden" name="target_user_id" value="<?php echo $stud['user_id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i data-lucide="user-x"></i> Suspend
                                                    </button>
                                                </form>
                                            <?php elseif ($stud['status'] === 'suspended'): ?>
                                                <form action="<?php echo BASE_URL; ?>admin/students/index.php" method="POST" onsubmit="return confirm('Re-activate this account?');">
                                                    <?php csrf_input(); ?>
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="target_user_id" value="<?php echo $stud['user_id']; ?>">
                                                    <button type="submit" class="btn btn-accent btn-sm">
                                                        <i data-lucide="user-check"></i> Activate
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Delete Student Button -->
                                            <form action="<?php echo BASE_URL; ?>admin/students/index.php" method="POST" onsubmit="return confirm('WARNING: Permanently delete this student account and all their registrations, payments, and QR scans? This action cannot be undone.');">
                                                <?php csrf_input(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="target_user_id" value="<?php echo $stud['user_id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete Student Account" style="background: rgba(244, 63, 94, 0.1); border-color: rgba(244, 63, 94, 0.2); color: var(--danger);">
                                                    <i data-lucide="trash-2"></i> Delete
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
