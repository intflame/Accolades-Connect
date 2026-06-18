<?php
/**
 * Student QR Code Ticket Page
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Access Control
require_role('student');

$student = get_current_student();
$reg_id = intval($_GET['reg_id'] ?? 0);

if (!$student || !$reg_id) {
    set_flash_message('danger', 'Invalid registration parameters.');
    header("Location: " . BASE_URL . "student/dashboard.php");
    exit();
}

// Fetch registration, event, and QR token details
$data = null;
try {
    $stmt = $conn->prepare("
        SELECT r.id as reg_id, r.status as reg_status,
               e.name as event_name, e.event_date, e.venue, e.status as event_status,
               q.token, q.status as qr_status
        FROM event_registrations r
        JOIN events e ON r.event_id = e.id
        LEFT JOIN qr_tokens q ON q.registration_id = r.id
        WHERE r.id = ? AND r.student_id = ?
    ");
    $stmt->execute([$reg_id, $student['id']]);
    $data = $stmt->fetch();
} catch (Exception $e) {
    error_log("Failed to fetch QR ticket: " . $e->getMessage());
}

if (!$data) {
    set_flash_message('danger', 'Registration record not found.');
    header("Location: " . BASE_URL . "student/dashboard.php");
    exit();
}

// CRITICAL SECURITY RULE: Never generate/show QR code before registration & payment is approved
$is_approved = ($data['reg_status'] === 'approved');
$has_token = !empty($data['token']);
$is_valid_event = ($data['event_status'] !== 'completed');

$qr_ready = ($is_approved && $has_token && $is_valid_event);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My QR Ticket - Accolades Connect</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css">
    <!-- Load QR Code JS client side generator -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
        <div style="max-width: 550px; margin: 0 auto; text-align: center;">
            <div style="margin-bottom: 2rem;">
                <a href="<?php echo BASE_URL; ?>student/dashboard.php" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem;">
                    <i data-lucide="arrow-left" style="width:16px; height:16px;"></i> Return to Dashboard
                </a>
                <h2>Event Entry Ticket</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Present this QR code at the registration desk on the event day.</p>
            </div>

            <?php if ($qr_ready): ?>
                <div class="card" style="padding: 2.5rem 2rem;">
                    <h3 style="margin-bottom: 0.5rem; color: #ffffff;"><?php echo e($data['event_name']); ?></h3>
                    <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">
                        <span><?php echo date('F d, Y', strtotime($data['event_date'])); ?></span> &bull; 
                        <span><?php echo e($data['venue']); ?></span>
                    </div>

                    <!-- Clean QR Render Holder -->
                    <div class="qr-render-box">
                        <div id="qrcode"></div>
                    </div>

                    <!-- Token Status Badge -->
                    <div style="margin-top: 1rem;">
                        <span class="badge badge-<?php echo $data['qr_status']; ?>">
                            Ticket Status: <?php echo strtoupper($data['qr_status']); ?>
                        </span>
                    </div>

                    <div style="margin-top: 1.5rem; padding: 1rem; border-radius: var(--radius-sm); background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); text-align: left; font-size: 0.8rem; color: var(--text-muted); display: flex; gap: 0.5rem;">
                        <i data-lucide="shield" style="width:16px; height:16px; color: var(--primary); flex-shrink: 0; margin-top: 2px;"></i>
                        <div>
                            <strong>Security Verification Enabled:</strong> This ticket contains a secure random cryptographic token. It contains no personal records and expires automatically once processed.
                        </div>
                    </div>
                </div>

                <!-- Client-Side QRCode.js rendering script -->
                <script>
                    window.addEventListener('DOMContentLoaded', (event) => {
                        const tokenStr = "<?php echo $data['token']; ?>";
                        new QRCode(document.getElementById("qrcode"), {
                            text: tokenStr,
                            width: 192,
                            height: 192,
                            colorDark : "#090d16",
                            colorLight : "#ffffff",
                            correctLevel : QRCode.CorrectLevel.M
                        });
                    });
                </script>
            <?php else: ?>
                <!-- Security blocking message -->
                <div class="card" style="padding: 3rem 2rem;">
                    <i data-lucide="shield-alert" style="width: 48px; height: 48px; color: var(--danger); margin: 0 auto 1.5rem;"></i>
                    <h3 style="color: var(--danger); margin-bottom: 0.5rem;">QR Ticket Locked</h3>
                    
                    <?php if ($data['reg_status'] === 'pending_payment'): ?>
                        <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem;">
                            Your payment has not been submitted yet. Please submit the registration fee screenshot to activate your ticket.
                        </p>
                        <a href="<?php echo BASE_URL; ?>student/upload_payment.php?reg_id=<?php echo $data['reg_id']; ?>" class="btn btn-primary">
                            <i data-lucide="upload-cloud"></i> Upload Payment Proof
                        </a>
                    <?php elseif ($data['reg_status'] === 'pending_verification'): ?>
                        <p style="color: var(--text-muted); font-size: 0.95rem;">
                            Your payment proof has been uploaded and is waiting verification by the department coordinator. Once approved, the QR code will unlock.
                        </p>
                    <?php elseif ($data['reg_status'] === 'rejected'): ?>
                        <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem;">
                            Your payment proof was rejected. Please review the comments and upload again.
                        </p>
                        <a href="<?php echo BASE_URL; ?>student/upload_payment.php?reg_id=<?php echo $data['reg_id']; ?>" class="btn btn-primary">
                            <i data-lucide="upload-cloud"></i> Upload Proof Again
                        </a>
                    <?php else: ?>
                        <p style="color: var(--text-muted); font-size: 0.95rem;">
                            This ticket is invalid, expired, or the event has been completed.
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
