<?php
/**
 * Admin: Payments Verification Queue
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Access Control
require_role('admin');

$errors = [];

// Handle Verification Action (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_request();

    $action = trim($_POST['action'] ?? '');
    $payment_id = intval($_POST['payment_id'] ?? 0);

    if ($payment_id > 0) {
        try {
            // Get payment and registration details
            $stmt = $conn->prepare("
                SELECT p.*, r.id as reg_id, r.student_id, r.event_id, e.name as event_name, s.name as student_name, s.user_id as student_user_id
                FROM payments p
                JOIN event_registrations r ON p.registration_id = r.id
                JOIN students s ON r.student_id = s.id
                JOIN events e ON r.event_id = e.id
                WHERE p.id = ?
            ");
            $stmt->execute([$payment_id]);
            $pay_data = $stmt->fetch();

            if (!$pay_data) {
                $errors[] = "Payment transaction record not found.";
            } elseif ($pay_data['status'] !== 'pending') {
                $errors[] = "This transaction has already been processed.";
            } else {
                $conn->beginTransaction();

                if ($action === 'approve') {
                    // 1. Update Payment record to Approved
                    $stmt = $conn->prepare("
                        UPDATE payments 
                        SET status = 'approved', verified_by = ?, verification_time = CURRENT_TIMESTAMP 
                        WHERE id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id'], $payment_id]);

                    // 2. Update Event Registration to Approved
                    $stmt = $conn->prepare("
                        UPDATE event_registrations 
                        SET status = 'approved' 
                        WHERE id = ?
                    ");
                    $stmt->execute([$pay_data['reg_id']]);

                    // 3. Generate Cryptographically Secure QR Token
                    $qr_token = bin2hex(random_bytes(QR_TOKEN_LENGTH));
                    $stmt = $conn->prepare("
                        INSERT INTO qr_tokens (registration_id, token, status) 
                        VALUES (?, ?, 'active')
                    ");
                    $stmt->execute([$pay_data['reg_id'], $qr_token]);

                    // Log activity
                    log_activity($_SESSION['user_id'], 'payment_approved', "Approved payment for student '{$pay_data['student_name']}' on event '{$pay_data['event_name']}'. QR Code Generated.");
                    
                    set_flash_message('success', "Payment approved successfully. QR Ticket unlocked for {$pay_data['student_name']}.");

                } elseif ($action === 'reject') {
                    $reason = trim($_POST['rejection_reason'] ?? '');
                    if (empty($reason)) {
                        $errors[] = "A rejection reason is required to reject a payment.";
                        $conn->rollBack();
                    } else {
                        // 1. Update Payment record to Rejected
                        $stmt = $conn->prepare("
                            UPDATE payments 
                            SET status = 'rejected', verified_by = ?, verification_time = CURRENT_TIMESTAMP, rejection_reason = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$_SESSION['user_id'], $reason, $payment_id]);

                        // 2. Update Registration record to Rejected
                        $stmt = $conn->prepare("
                            UPDATE event_registrations 
                            SET status = 'rejected' 
                            WHERE id = ?
                        ");
                        $stmt->execute([$pay_data['reg_id']]);

                        // Log activity
                        log_activity($_SESSION['user_id'], 'payment_rejected', "Rejected payment for student '{$pay_data['student_name']}' on event '{$pay_data['event_name']}'. Reason: $reason");
                        
                        set_flash_message('warning', "Payment rejected. Notification status updated for {$pay_data['student_name']}.");
                    }
                }

                if (empty($errors)) {
                    $conn->commit();
                    header("Location: " . BASE_URL . "admin/payments/index.php");
                    exit();
                }
            }
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log("Payment verification process failure: " . $e->getMessage());
            $errors[] = "A database error occurred while processing verification.";
        }
    }
}

// Fetch Pending Payments
$pending = [];
try {
    $stmt = $conn->query("
        SELECT p.id as payment_id, p.payment_method, p.proof_image, p.created_at,
               r.id as reg_id,
               s.name as student_name, s.class_roll, s.university_roll, s.course,
               e.name as event_name, e.registration_fee
        FROM payments p
        JOIN event_registrations r ON p.registration_id = r.id
        JOIN students s ON r.student_id = s.id
        JOIN events e ON r.event_id = e.id
        WHERE p.status = 'pending'
        ORDER BY p.created_at ASC
    ");
    $pending = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to load pending payments query: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Payments - Accolades Connect</title>
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
                <li><a href="<?php echo BASE_URL; ?>admin/payments/index.php" class="nav-link active">Payments</a></li>
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
            <h2>Payments Verification Queue</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Review uploaded receipts/screenshots, approve tickets, or log rejection reasons.</p>
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

        <!-- Queue listing -->
        <div class="card">
            <?php if (empty($pending)): ?>
                <div style="text-align: center; padding: 4rem 1rem; color: var(--text-muted);">
                    <i data-lucide="award" style="width: 48px; height: 48px; color: var(--success); margin: 0 auto 1.5rem;"></i>
                    <h3 style="color: #ffffff;">Queue is completely clear!</h3>
                    <p style="margin-top: 0.5rem;">All event registration payments have been verified.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student details</th>
                                <th>Event Details</th>
                                <th>Method & Fee</th>
                                <th>Submitted Time</th>
                                <th>Payment Proof</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending as $pay): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo e($pay['student_name']); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">Course: <?php echo e($pay['course'] ?? 'BCA'); ?> | Class Roll: <?php echo e($pay['class_roll']); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">Uni Roll: <?php echo e($pay['university_roll']); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo e($pay['event_name']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-active" style="font-size: 0.7rem;"><?php echo strtoupper($pay['payment_method']); ?></span>
                                        <div style="font-weight: bold; color: var(--accent); margin-top: 0.25rem;">₹<?php echo number_format($pay['registration_fee'], 2); ?></div>
                                    </td>
                                    <td><?php echo date('d M Y, h:i A', strtotime($pay['created_at'])); ?></td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>uploads/payment_proofs/<?php echo e($pay['proof_image']); ?>" target="_blank" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.8rem;">
                                            <i data-lucide="image" style="width:14px; height:14px;"></i> Open Screenshot
                                        </a>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                            <!-- Approve Button Form -->
                                            <form action="<?php echo BASE_URL; ?>admin/payments/index.php" method="POST" onsubmit="return confirm('Approve payment and unlock QR Code ticket for this student?');" style="margin: 0;">
                                                <?php csrf_input(); ?>
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="payment_id" value="<?php echo $pay['payment_id']; ?>">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i data-lucide="check-circle" style="width:14px; height:14px;"></i> Approve
                                                </button>
                                            </form>

                                            <!-- Reject Form Trigger Button -->
                                            <button type="button" class="btn btn-danger btn-sm" onclick="showRejectionPrompt(<?php echo $pay['payment_id']; ?>, '<?php echo e(addslashes($pay['student_name'])); ?>')">
                                                <i data-lucide="x-circle" style="width:14px; height:14px;"></i> Reject
                                            </button>
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

    <!-- Simple, premium styled Rejection Modal -->
    <div id="rejection-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(9, 13, 22, 0.8); backdrop-filter: blur(8px); z-index: 2000; align-items: center; justify-content: center; padding: 1.5rem;">
        <div class="card" style="max-width: 480px; width: 100%; border-color: var(--danger); box-shadow: 0 10px 40px rgba(244, 63, 94, 0.15);">
            <div class="card-header" style="border-bottom: 1px solid rgba(244, 63, 94, 0.15); margin-bottom: 1.5rem;">
                <h3 style="color: var(--danger);"><i data-lucide="alert-triangle" style="width: 20px; height: 20px; display:inline-block; vertical-align:middle;"></i> Reject Payment Proof</h3>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.25rem;">Student: <strong id="reject-student-name" style="color:#ffffff;"></strong></p>
            </div>
            
            <form action="<?php echo BASE_URL; ?>admin/payments/index.php" method="POST">
                <?php csrf_input(); ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="payment_id" id="reject-payment-id">

                <div class="form-group">
                    <label class="form-label" for="rejection_reason">Specify Reason for Rejection</label>
                    <textarea id="rejection_reason" name="rejection_reason" class="form-control" placeholder="e.g. Screenshot blurred, incorrect amount paid, receipt number not visible." rows="4" required autofocus></textarea>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="hideRejectionPrompt()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Payment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showRejectionPrompt(payId, studentName) {
            document.getElementById('reject-payment-id').value = payId;
            document.getElementById('reject-student-name').textContent = studentName;
            document.getElementById('rejection-modal').style.display = 'flex';
        }

        function hideRejectionPrompt() {
            document.getElementById('rejection-modal').style.display = 'none';
        }
    </script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
