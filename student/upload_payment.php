<?php
/**
 * Student Payment Proof Upload
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Access Control
require_role('student');

$student = get_current_student();
$reg_id = intval($_GET['reg_id'] ?? 0);

if (!$student || !$reg_id) {
    set_flash_message('danger', 'Invalid registration identifier.');
    header("Location: " . BASE_URL . "student/dashboard.php");
    exit();
}

// Fetch registration details with event details
$registration = null;
try {
    $stmt = $conn->prepare("
        SELECT r.*, e.name as event_name, e.registration_fee, e.upi_payment_enabled, e.upi_id, e.upi_qr_image, e.cash_payment_enabled
        FROM event_registrations r
        JOIN events e ON r.event_id = e.id
        WHERE r.id = ? AND r.student_id = ?
    ");
    $stmt->execute([$reg_id, $student['id']]);
    $registration = $stmt->fetch();
} catch (Exception $e) {
    error_log("Failed to fetch registration for payment: " . $e->getMessage());
}

if (!$registration) {
    set_flash_message('danger', 'Registration record not found or access denied.');
    header("Location: " . BASE_URL . "student/dashboard.php");
    exit();
}

// Verify registration status allows payment upload
if (!in_array($registration['status'], ['pending_payment', 'rejected'])) {
    set_flash_message('warning', 'This registration does not require payment verification (Status: ' . $registration['status'] . ').');
    header("Location: " . BASE_URL . "student/dashboard.php");
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_request();

    $payment_method = trim($_POST['payment_method'] ?? '');
    
    // Check method validity
    if (!in_array($payment_method, ['upi', 'cash'])) {
        $errors[] = "Please select a valid payment method.";
    }
    
    if ($payment_method === 'upi' && !$registration['upi_payment_enabled']) {
        $errors[] = "UPI payment is not enabled for this event.";
    }
    if ($payment_method === 'cash' && !$registration['cash_payment_enabled']) {
        $errors[] = "Cash payment is not enabled for this event.";
    }

    // Handle payment proof upload
    $proof_filename = null;
    if (empty($errors)) {
        if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = "Please upload a photo of the payment screenshot or cash receipt.";
        } else {
            $upload_res = handle_file_upload($_FILES['payment_proof'], 'payment_proofs');
            if ($upload_res['success']) {
                $proof_filename = $upload_res['filename'];
            } else {
                $errors[] = $upload_res['message'];
            }
        }
    }

    if (empty($errors) && $proof_filename) {
        try {
            $conn->beginTransaction();

            // 1. Delete previous payment records if they exist (for re-uploading rejected ones)
            $stmt = $conn->prepare("SELECT proof_image FROM payments WHERE registration_id = ?");
            $stmt->execute([$reg_id]);
            $old_payments = $stmt->fetchAll();
            foreach ($old_payments as $old_pay) {
                $old_path = UPLOAD_PATH . '/payment_proofs/' . $old_pay['proof_image'];
                if (file_exists($old_path)) {
                    unlink($old_path);
                }
            }
            $stmt = $conn->prepare("DELETE FROM payments WHERE registration_id = ?");
            $stmt->execute([$reg_id]);

            // 2. Insert new payment proof record
            $stmt = $conn->prepare("
                INSERT INTO payments (registration_id, payment_method, proof_image, status) 
                VALUES (?, ?, ?, 'pending')
            ");
            $stmt->execute([$reg_id, $payment_method, $proof_filename]);

            // 3. Update registration status to pending_verification and save method
            $stmt = $conn->prepare("
                UPDATE event_registrations 
                SET status = 'pending_verification', payment_method = ? 
                WHERE id = ?
            ");
            $stmt->execute([$payment_method, $reg_id]);

            // Log activity
            log_activity($student['user_id'], 'payment_proof_uploaded', "Uploaded payment proof ($payment_method) for Registration ID: $reg_id. Amount: ₹{$registration['registration_fee']}");

            $conn->commit();

            set_flash_message('success', 'Payment proof uploaded successfully. The organizer will verify it shortly.');
            header("Location: " . BASE_URL . "student/dashboard.php");
            exit();

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log("Failed to process payment upload: " . $e->getMessage());
            $errors[] = "Failed to save payment records. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Payment Proof - Accolades Connect</title>
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
                <li><a href="<?php echo BASE_URL; ?>student/attendance_records.php" class="nav-link">My Attendance</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/certificates.php" class="nav-link">My Certificates</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/profile.php" class="nav-link">My Profile</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:14px; height:14px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <div style="max-width: 650px; margin: 0 auto;">
            <div style="margin-bottom: 2rem;">
                <h2>Payment Submission</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem;">
                    Submit registration fees for: <strong><?php echo e($registration['event_name']); ?></strong>
                </p>
                <div style="font-size: 1.25rem; font-weight: 700; color: var(--accent); margin-top: 0.5rem;">
                    Payable Amount: ₹<?php echo number_format($registration['registration_fee'], 2); ?>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger show-alert-anim">
                    <i data-lucide="alert-triangle" class="alert-icon"></i>
                    <div class="alert-content">
                        <ul style="padding-left: 1rem; margin: 0;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo e($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <form action="<?php echo BASE_URL; ?>student/upload_payment.php?reg_id=<?php echo $reg_id; ?>" method="POST" enctype="multipart/form-data">
                    <?php csrf_input(); ?>

                    <!-- Select Payment Mode -->
                    <div class="form-group">
                        <label class="form-label">Select Payment Method</label>
                        <div style="display: flex; gap: 1.5rem; margin-top: 0.5rem;">
                            <?php if ($registration['upi_payment_enabled']): ?>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="radio" name="payment_method" value="upi" checked onclick="togglePaymentInstructions('upi')">
                                    <span>UPI Transfer</span>
                                </label>
                            <?php endif; ?>
                            <?php if ($registration['cash_payment_enabled']): ?>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="radio" name="payment_method" value="cash" <?php echo !$registration['upi_payment_enabled'] ? 'checked' : ''; ?> onclick="togglePaymentInstructions('cash')">
                                    <span>Cash Payment</span>
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Instructions Panel for UPI -->
                    <?php if ($registration['upi_payment_enabled']): ?>
                        <div id="upi-instructions" class="form-group" style="padding: 1.5rem; background: rgba(255, 255, 255, 0.02); border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
                            <h4 style="color: var(--primary); margin-bottom: 0.75rem;"><i data-lucide="qr-code" style="width:16px; height:16px; display:inline-block; vertical-align:middle;"></i> UPI Payment QR</h4>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                                Scan the QR below or transfer to UPI ID: <strong style="color: #ffffff;"><?php echo e($registration['upi_id']); ?></strong>.
                            </p>
                            <?php if (!empty($registration['upi_qr_image']) && file_exists(UPLOAD_PATH . '/upi_qr/' . $registration['upi_qr_image'])): ?>
                                <div style="text-align: center; margin-bottom: 1rem;">
                                    <img src="<?php echo BASE_URL; ?>uploads/upi_qr/<?php echo e($registration['upi_qr_image']); ?>" alt="UPI QR Code" style="max-width: 200px; border-radius: var(--radius-sm); border: 4px solid white;">
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; padding: 1rem; color: var(--text-muted); font-size: 0.85rem; border: 1px dashed var(--border-color);">
                                    QR image not uploaded by organizer. Please use UPI ID.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Instructions Panel for Cash -->
                    <div id="cash-instructions" class="form-group" style="padding: 1.5rem; background: rgba(255, 255, 255, 0.02); border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 1.5rem; display: <?php echo !$registration['upi_payment_enabled'] ? 'block' : 'none'; ?>;">
                        <h4 style="color: var(--warning); margin-bottom: 0.75rem;"><i data-lucide="banknote" style="width:16px; height:16px; display:inline-block; vertical-align:middle;"></i> Cash payment procedure</h4>
                        <p style="font-size: 0.85rem; color: var(--text-muted);">
                            1. Visit the department coordinator or organizer. <br>
                            2. Pay the fee in cash and receive the transaction/receipt slip. <br>
                            3. Take a photo of the slip and upload it below.
                        </p>
                    </div>

                    <!-- File Input for Proof -->
                    <div class="form-group">
                        <label class="form-label" for="payment_proof">Upload Proof Image (Screenshot / Receipt Photo)</label>
                        <input type="file" id="payment_proof" name="payment_proof" class="form-control" accept="image/png, image/jpeg, image/jpg" style="padding: 0.4rem;" required>
                        <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">
                            Allowed file formats: PNG, JPG, JPEG. Max size 5MB.
                        </small>
                    </div>

                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="check"></i> Submit Verification
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function togglePaymentInstructions(method) {
            const upiDiv = document.getElementById('upi-instructions');
            const cashDiv = document.getElementById('cash-instructions');
            
            if (method === 'upi') {
                if(upiDiv) upiDiv.style.display = 'block';
                if(cashDiv) cashDiv.style.display = 'none';
            } else if (method === 'cash') {
                if(upiDiv) upiDiv.style.display = 'none';
                if(cashDiv) cashDiv.style.display = 'block';
            }
        }
    </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
