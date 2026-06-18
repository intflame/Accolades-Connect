<?php
/**
 * Admin: Batch Management CRUD Portal
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

// Handle Add/Edit Batch Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_request();

    $action = trim($_POST['action'] ?? '');
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            $errors[] = "Batch name cannot be empty.";
        } else {
            try {
                // Check duplicate
                $stmt = $conn->prepare("SELECT id FROM batches WHERE name = ?");
                $stmt->execute([$name]);
                if ($stmt->fetch()) {
                    $errors[] = "Batch '{$name}' already exists.";
                } else {
                    $stmt = $conn->prepare("INSERT INTO batches (name) VALUES (?)");
                    $stmt->execute([$name]);
                    log_activity($_SESSION['user_id'], 'batch_created', "Created batch: $name");
                    set_flash_message('success', "Batch '{$name}' added successfully.");
                    header("Location: " . BASE_URL . "admin/batches/index.php");
                    exit();
                }
            } catch (Exception $e) {
                error_log("Failed to add batch: " . $e->getMessage());
                $errors[] = "Database insert failed.";
            }
        }
    } elseif ($action === 'delete') {
        $batch_id = intval($_POST['batch_id'] ?? 0);
        if ($batch_id > 0) {
            try {
                // Get batch name before deletion for logging
                $stmt = $conn->prepare("SELECT name FROM batches WHERE id = ?");
                $stmt->execute([$batch_id]);
                $batch_name = $stmt->fetchColumn();

                if ($batch_name) {
                    $stmt = $conn->prepare("DELETE FROM batches WHERE id = ?");
                    $stmt->execute([$batch_id]);
                    log_activity($_SESSION['user_id'], 'batch_deleted', "Deleted batch: $batch_name");
                    set_flash_message('success', "Batch '{$batch_name}' deleted successfully. Students in this batch will be set to N/A.");
                    header("Location: " . BASE_URL . "admin/batches/index.php");
                    exit();
                }
            } catch (Exception $e) {
                error_log("Failed to delete batch: " . $e->getMessage());
                $errors[] = "Failed to delete batch. It may be currently referenced by student records.";
            }
        }
    }
}

// Fetch all batches
$batches = [];
try {
    $batches = $conn->query("
        SELECT b.*, COUNT(s.id) as student_count 
        FROM batches b 
        LEFT JOIN students s ON b.id = s.batch_id 
        GROUP BY b.id 
        ORDER BY b.name DESC
    ")->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch batches in admin CRUD: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Batches - Accolades Connect</title>
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
                <li><a href="<?php echo BASE_URL; ?>admin/batches/index.php" class="nav-link active">Batches</a></li>
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
            <h2>Manage Batches</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Add or remove academic batches cohorts used for student profile categorization.</p>
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
            <!-- Left Side: Add Batch Form -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3>Active Batches List</h3>
                    </div>

                    <?php if (empty($batches)): ?>
                        <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">No batches defined yet. Use the panel on the right to add one.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Batch Name</th>
                                        <th>Enrolled Students</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($batches as $batch): ?>
                                        <tr>
                                            <td><?php echo $batch['id']; ?></td>
                                            <td style="font-weight: 600;"><?php echo e($batch['name']); ?></td>
                                            <td><?php echo $batch['student_count']; ?> Student(s)</td>
                                            <td>
                                                <form action="<?php echo BASE_URL; ?>admin/batches/index.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this batch? It will reset batch classifications for students enrolled in it.');" style="display:inline;">
                                                    <?php csrf_input(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="batch_id" value="<?php echo $batch['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" <?php echo $batch['student_count'] > 0 ? 'disabled title="Cannot delete batch with enrolled students."' : ''; ?>>
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

            <!-- Right Side: Add Batch Widget -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <h3>Add New Academic Batch</h3>
                    </div>

                    <form action="<?php echo BASE_URL; ?>admin/batches/index.php" method="POST">
                        <?php csrf_input(); ?>
                        <input type="hidden" name="action" value="add">

                        <div class="form-group">
                            <label class="form-label" for="name">Batch Cohort Name</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="e.g. 2023-2027" required>
                            <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">Usually formatted as START_YEAR-END_YEAR (e.g. 2024-2028).</small>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                            <i data-lucide="plus-circle"></i> Create Batch
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
