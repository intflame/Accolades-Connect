<?php
/**
 * OTP Verification Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

// Check if verification session exists
$verify_id = $_SESSION['verify_user_id'] ?? null;
$verify_email = $_SESSION['verify_email'] ?? null;

if (!$verify_id || !$verify_email) {
    set_flash_message('warning', 'Please register or request a verification link first.');
    header("Location: " . BASE_URL . "register.php");
    exit();
}

$error = null;
$demo_otp = $_SESSION['demo_otp_display']['otp'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_request();

    $action = $_POST['action'] ?? 'verify';

    if ($action === 'resend') {
        try {
            $new_otp = generate_otp();
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+2 minutes'));

            $stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ? AND status = 'pending_otp'");
            $stmt->execute([$new_otp, $otp_expiry, $verify_id]);

            require_once __DIR__ . '/includes/mailer.php';
            if (send_otp_email($verify_email, $new_otp)) {
                set_flash_message('success', 'A new OTP verification code has been sent to your email.');
                header("Location: " . BASE_URL . "verify_email.php");
                exit();
            } else {
                $error = "Failed to send verification email. Please try again.";
            }
        } catch (Exception $e) {
            error_log("Failed to resend registration OTP: " . $e->getMessage());
            $error = "An unexpected error occurred. Please try again.";
        }
    } else {
        $otp_input = trim($_POST['otp'] ?? '');

        if (empty($otp_input)) {
            $error = "OTP code is required.";
        } else {
            try {
                // Fetch OTP details from database
                $stmt = $conn->prepare("SELECT otp_code, otp_expiry, status FROM users WHERE id = ?");
                $stmt->execute([$verify_id]);
                $user = $stmt->fetch();

                if (!$user) {
                    $error = "Account not found.";
                } elseif ($user['status'] !== 'pending_otp') {
                    $error = "Account email has already been verified.";
                } else {
                    $current_time = date('Y-m-d H:i:s');
                    
                    // Validate match and expiry
                    if ($user['otp_code'] !== $otp_input) {
                        $error = "The OTP code entered is incorrect.";
                    } elseif (strtotime($user['otp_expiry']) < strtotime($current_time)) {
                        $error = "This OTP code has expired. Please click the Resend button below to request a new code.";
                    } else {
                        // Verification success: Update status to active directly
                        $stmt = $conn->prepare("
                            UPDATE users 
                            SET status = 'active', otp_code = NULL, otp_expiry = NULL 
                            WHERE id = ?
                        ");
                        $stmt->execute([$verify_id]);

                        // Log activity
                        log_activity($verify_id, 'email_verified', "Student email ($verify_email) verified successfully. Status changed to active.");

                        // Clear verification session variables
                        unset($_SESSION['verify_user_id']);
                        unset($_SESSION['verify_email']);
                        unset($_SESSION['demo_otp_display']);

                        set_flash_message('success', 'Email verification successful! Your account is now active. You can log in now.');
                        header("Location: " . BASE_URL . "login.php");
                        exit();
                    }
                }
            } catch (Exception $e) {
                error_log("OTP verification failed: " . $e->getMessage());
                $error = "An unexpected error occurred. Please try again.";
            }
        }
    }
}

// Fetch OTP expiry time to calculate countdown seconds
$seconds_left = 0;
try {
    $stmt = $conn->prepare("SELECT otp_expiry FROM users WHERE id = ? AND status = 'pending_otp'");
    $stmt->execute([$verify_id]);
    $expiry = $stmt->fetchColumn();
    if ($expiry) {
        $seconds_left = strtotime($expiry) - time();
        if ($seconds_left < 0) {
            $seconds_left = 0;
        }
    }
} catch (Exception $e) {
    error_log("Failed to fetch registration OTP expiry: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - Accolades Connect</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css?v=1.0.1">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?php echo BASE_URL; ?>index.php" class="brand">
                <i data-lucide="calendar-check"></i>
                <span>Accolades Connect</span>
            </a>
        </div>
    </nav>

    <div class="container main-content">
        <div class="auth-wrapper">
            <div class="card auth-card">
                <div class="card-header" style="text-align: center;">
                    <div style="text-align: center; margin-bottom: 1.25rem;">
                        <img src="<?php echo BASE_URL; ?>assets/images/logo_accolades.png" alt="Accolades Logo" style="max-width: 220px; height: auto; background-color: #ffffff; padding: 0.5rem 1.25rem; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); display: inline-block;">
                    </div>
                    <i data-lucide="mail-check" style="width: 32px; height: 32px; color: var(--accent); margin-bottom: 0.5rem;"></i>
                    <h2>Email Verification</h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
                        We have sent a 6-digit verification code to <strong style="color: #ffffff;"><?php echo e($verify_email); ?></strong>
                    </p>
                </div>

                <!-- Premium Dev-Mode Helper Alert -->
                <?php if (DEVELOPMENT_MODE && $demo_otp): ?>
                    <div class="demo-banner">
                        <div>
                            <span class="demo-badge">DEMO MODE</span>
                            <span style="margin-left: 0.5rem;">Simulated OTP Code: <strong style="font-size: 1.1rem; color: #ffffff; letter-spacing: 1px;"><?php echo e($demo_otp); ?></strong></span>
                        </div>
                        <i data-lucide="code" style="width: 18px; height: 18px;"></i>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger show-alert-anim">
                        <i data-lucide="alert-triangle" class="alert-icon"></i>
                        <div class="alert-content"><?php echo e($error); ?></div>
                    </div>
                <?php endif; ?>

                <form action="<?php echo BASE_URL; ?>verify_email.php" method="POST">
                    <?php csrf_input(); ?>
                    <input type="hidden" name="action" value="verify">

                    <div class="form-group">
                        <label class="form-label" for="otp" style="text-align: center;">Enter 6-Digit OTP Code</label>
                        <input type="text" id="otp" name="otp" class="form-control" placeholder="000000" maxlength="6" pattern="\d{6}" style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5em; padding-left: 1.5rem;" required autofocus>
                    </div>

                    <button type="submit" class="btn btn-accent" style="width: 100%; margin-top: 1rem;">
                        <i data-lucide="check-circle"></i> Verify Code
                    </button>
                </form>

                <form action="<?php echo BASE_URL; ?>verify_email.php" method="POST" id="resend-form" style="display:none;">
                    <?php csrf_input(); ?>
                    <input type="hidden" name="action" value="resend">
                </form>

                <div style="text-align: center; margin-top: 1.5rem;">
                    <span id="timer-container" style="font-size: 0.9rem; color: var(--text-muted);">
                        OTP valid for: <strong id="countdown-timer" style="color: var(--accent);">02:00</strong>
                    </span>
                    <button type="button" id="btn-resend-otp" class="btn btn-secondary btn-sm" style="display: none; margin: 0 auto;" onclick="document.getElementById('resend-form').submit();">
                        <i data-lucide="refresh-cw" style="width: 14px; height: 14px; display:inline-block; vertical-align:middle; margin-right: 0.25rem;"></i> Resend OTP
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let secondsLeft = <?php echo intval($seconds_left); ?>;
            const timerContainer = document.getElementById('timer-container');
            const countdownTimer = document.getElementById('countdown-timer');
            const btnResend = document.getElementById('btn-resend-otp');

            function updateTimerDisplay() {
                if (secondsLeft <= 0) {
                    timerContainer.style.display = 'none';
                    btnResend.style.display = 'inline-flex';
                } else {
                    const minutes = Math.floor(secondsLeft / 60);
                    const seconds = secondsLeft % 60;
                    countdownTimer.textContent = 
                        (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
                    secondsLeft--;
                    setTimeout(updateTimerDisplay, 1000);
                }
            }

            updateTimerDisplay();
        });
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
