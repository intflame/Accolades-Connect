<?php
/**
 * Admin Operational Dashboard
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Access Control
require_role('admin');

// Fetch summary metrics
$counts = [
    'students' => 0,
    'pending_students' => 0,
    'events' => 0,
    'pending_payments' => 0
];

try {
    // Total & Pending Students
    $counts['students'] = $conn->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $counts['pending_students'] = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'pending_approval'")->fetchColumn();
    
    // Total Events
    $counts['events'] = $conn->query("SELECT COUNT(*) FROM events")->fetchColumn();
    
    // Pending Payment Verifications
    $counts['pending_payments'] = $conn->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
} catch (Exception $e) {
    error_log("Failed to fetch dashboard metrics: " . $e->getMessage());
}

// Fetch pending payments list
$pending_payments = [];
try {
    $stmt = $conn->query("
        SELECT p.id as payment_id, p.payment_method, p.proof_image, p.created_at,
               r.id as reg_id, r.event_id,
               s.name as student_name, s.class_roll, s.course,
               e.name as event_name, e.registration_fee
        FROM payments p
        JOIN event_registrations r ON p.registration_id = r.id
        JOIN students s ON r.student_id = s.id
        JOIN events e ON r.event_id = e.id
        WHERE p.status = 'pending'
        ORDER BY p.created_at ASC
        LIMIT 5
    ");
    $pending_payments = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch pending payments: " . $e->getMessage());
}

// Fetch active events summaries
$active_events = [];
try {
    $stmt = $conn->query("
        SELECT e.*, COUNT(r.id) as reg_count 
        FROM events e 
        LEFT JOIN event_registrations r ON e.id = r.event_id 
        WHERE e.status IN ('upcoming', 'registration_open')
        GROUP BY e.id 
        ORDER BY e.event_date ASC
    ");
    $active_events = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch active events: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Accolades Connect</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css">
</head>
<body>
    <!-- Premium Sidebar or Header Navbar for Admin -->
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="brand">
                <i data-lucide="shield-alert"></i>
                <span>Accolades - Admin</span>
            </a>
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="nav-link active">Dashboard</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/students/index.php" class="nav-link">Students</a></li>
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
        <header style="margin-bottom: 2rem;">
            <h1 style="font-size: 2rem; margin-bottom: 0.25rem;">Admin Dashboard Overview</h1>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Manage students, verify transactions, configuration setups, and audit check-ins.</p>
        </header>

        <?php display_flash_message(); ?>

        <!-- Quick Summary Cards Grid -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: var(--primary);">
                    <i data-lucide="users"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $counts['students']; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>

            <div class="stat-card" style="border-color: <?php echo $counts['pending_students'] > 0 ? 'var(--warning)' : 'var(--glass-border)'; ?>;">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                    <i data-lucide="user-check"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $counts['pending_students']; ?></div>
                    <div class="stat-label">Pending Approvals</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(6, 182, 212, 0.1); color: var(--accent);">
                    <i data-lucide="calendar"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $counts['events']; ?></div>
                    <div class="stat-label">Events Managed</div>
                </div>
            </div>

            <div class="stat-card" style="border-color: <?php echo $counts['pending_payments'] > 0 ? 'var(--danger)' : 'var(--glass-border)'; ?>;">
                <div class="stat-icon" style="background: rgba(244, 63, 94, 0.1); color: var(--danger);">
                    <i data-lucide="banknote"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $counts['pending_payments']; ?></div>
                    <div class="stat-label">Pending Payments</div>
                </div>
            </div>
        </div>

        <div class="dashboard-panel">
            <!-- Left Side: Pending Verification Queue -->
            <div>
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3>Pending Payments Verification</h3>
                        <a href="<?php echo BASE_URL; ?>admin/payments/index.php" style="font-size: 0.85rem; font-weight: 600;">View Queue &rarr;</a>
                    </div>

                    <?php if (empty($pending_payments)): ?>
                        <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                            <i data-lucide="check-square" style="width: 32px; height: 32px; margin-bottom: 0.75rem;"></i>
                            <p>No payments awaiting verification.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Event Details</th>
                                        <th>Amount</th>
                                        <th>Proof File</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_payments as $pay): ?>
                                        <tr>
                                            <td style="font-weight: 600;">
                                                <?php echo e($pay['student_name']); ?>
                                                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 400;"><?php echo e($pay['course'] ?? 'BCA'); ?> | <?php echo e($pay['class_roll']); ?></div>
                                            </td>
                                            <td><?php echo e($pay['event_name']); ?></td>
                                            <td style="font-weight: bold; color: var(--accent);">₹<?php echo number_format($pay['registration_fee'], 2); ?></td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>uploads/payment_proofs/<?php echo e($pay['proof_image']); ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.85rem;">
                                                    <i data-lucide="image" style="width: 14px; height: 14px;"></i> View Proof
                                                </a>
                                            </td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>admin/payments/index.php" class="btn btn-primary btn-sm">Verify</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side: Active Events Status -->
            <div>
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3>Active Events</h3>
                        <a href="<?php echo BASE_URL; ?>admin/events/index.php" style="font-size: 0.85rem; font-weight: 600;">Manage &rarr;</a>
                    </div>

                    <?php if (empty($active_events)): ?>
                        <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">No active events scheduled.</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                            <?php foreach ($active_events as $event): ?>
                                <div style="padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: rgba(255, 255, 255, 0.01);">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                        <h4 style="font-size: 1rem; margin: 0; color: #ffffff;"><?php echo e($event['name']); ?></h4>
                                        <span class="badge badge-approved" style="font-size: 0.65rem; padding: 0.15rem 0.4rem;"><?php echo $event['reg_count']; ?> Registered</span>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem;">
                                        <i data-lucide="calendar" style="width: 12px; height: 12px;"></i>
                                        <span><?php echo date('M d, Y', strtotime($event['event_date'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
