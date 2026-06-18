<?php
/**
 * Admin: Scanner User Management CRUD Portal
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Access Control
require_role('admin');

$errors = [];

// Handle Form Submission (Create / Delete Scanner Account)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_request();

    $action = trim($_POST['action'] ?? '');
    
    if ($action === 'create') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($email)) $errors[] = "Scanner Email is required.";
        if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters long.";
        if ($password !== $confirm_password) $errors[] = "Password confirmation does not match.";

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email format.";
        }

        if (empty($errors)) {
            try {
                // Check if user already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = "An account with email '{$email}' already exists.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    // Insert scanner user with active status immediately (skipping verification)
                    $stmt = $conn->prepare("
                        INSERT INTO users (email, password, role, status) 
                        VALUES (?, ?, 'scanner', 'active')
                    ");
                    $stmt->execute([$email, $hashed_password]);
                    
                    log_activity($_SESSION['user_id'], 'scanner_created', "Created scanner account: $email");
                    set_flash_message('success', "Scanner account '{$email}' created successfully.");
                    header("Location: " . BASE_URL . "admin/scanner_users/index.php");
                    exit();
                }
            } catch (Exception $e) {
                error_log("Failed to create scanner account: " . $e->getMessage());
                $errors[] = "Database operation failed. Please try again.";
            }
        }
    } elseif ($action === 'delete') {
        $target_user_id = intval($_POST['target_user_id'] ?? 0);
        
        // Prevent deleting self (just in case they somehow request it, although they are admin)
        if ($target_user_id === $_SESSION['user_id']) {
            $errors[] = "You cannot delete your own account.";
        } elseif ($target_user_id > 0) {
            try {
                $stmt = $conn->prepare("SELECT email FROM users WHERE id = ? AND role = 'scanner'");
                $stmt->execute([$target_user_id]);
                $scanner_email = $stmt->fetchColumn();

                if ($scanner_email) {
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$target_user_id]);
                    log_activity($_SESSION['user_id'], 'scanner_deleted', "Deleted scanner account: $scanner_email");
                    set_flash_message('success', "Scanner account '{$scanner_email}' deleted successfully.");
                    header("Location: " . BASE_URL . "admin/scanner_users/index.php");
                    exit();
                } else {
                    $errors[] = "Scanner user not found.";
                }
            } catch (Exception $e) {
                error_log("Failed to delete scanner user: " . $e->getMessage());
                $errors[] = "Failed to delete scanner. It may have check-in scan records in database.";
            }
        }
    }
}

// Fetch all scanner accounts
$scanners = [];
try {
    $scanners = $conn->query("
        SELECT id, email, status, created_at 
        FROM users 
        WHERE role = 'scanner' 
        ORDER BY created_at DESC
    ")->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch scanner users: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Scanners - Accolades Connect</title>
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
                <li><a href="<?php echo BASE_URL; ?>admin/scanner_users/index.php" class="nav-link active">Scanners</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/certificates/index.php" class="nav-link">Certificates</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/reports/index.php" class="nav-link">Reports</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/logs/index.php" class="nav-link">Logs</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:12px; height:12px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <div style="margin-bottom: 2rem;">
            <h2>Manage Scanners</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Configure login credentials for scanners who will verify QR codes at event entry gates.</p>
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
            <!-- Left Side: Scanner List -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3>Registered Scanner Accounts</h3>
                    </div>

                    <?php if (empty($scanners)): ?>
                        <p style="color: var(--text-muted); text-align: center; padding: 2.25rem 0;">No scanner logins created yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Scanner Email</th>
                                        <th>Created Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($scanners as $scan): ?>
                                        <tr>
                                            <td><?php echo $scan['id']; ?></td>
                                            <td style="font-weight: 600;"><?php echo e($scan['email']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($scan['created_at'])); ?></td>
                                            <td>
                                                <span class="badge badge-approved"><?php echo $scan['status']; ?></span>
                                            </td>
                                            <td>
                                                <form action="<?php echo BASE_URL; ?>admin/scanner_users/index.php" method="POST" onsubmit="return confirm('Permanently delete this scanner user?');" style="display:inline;">
                                                    <?php csrf_input(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="target_user_id" value="<?php echo $scan['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i data-lucide="trash-2"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side: Create Scanner Account Form -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3>Create Scanner User</h3>
                    </div>

                    <form action="<?php echo BASE_URL; ?>admin/scanner_users/index.php" method="POST">
                        <?php csrf_input(); ?>
                        <input type="hidden" name="action" value="create">

                        <div class="form-group">
                            <label class="form-label" for="email">Scanner Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" placeholder="scanner1@college.edu.in" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                            <i data-lucide="plus-circle"></i> Create Scanner
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
