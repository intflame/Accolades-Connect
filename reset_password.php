<?php
/**
 * Reset User Password Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/mailer.php';

// Check session variables
$reset_email = $_SESSION['reset_email'] ?? null;
$reset_user_id = $_SESSION['reset_user_id'] ?? null;

if (!$reset_email || !$reset_user_id) {
    set_flash_message('warning', 'Please request a password reset link first.');
    header("Location: " . BASE_URL . "forgot_password.php");
    exit();
}

$error = null;
$demo_otp = $_SESSION['demo_otp_display']['otp'] ?? null;

// Handle requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_request();

    $action = $_POST['action'] ?? 'reset';

    if ($action === 'resend') {
        try {
            $new_otp = generate_otp();
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+2 minutes'));

            $stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?");
            $stmt->execute([$new_otp, $otp_expiry, $reset_user_id]);

            if (send_password_reset_email($reset_email, $new_otp)) {
                set_flash_message('success', 'A new verification code has been sent to your email.');
                header("Location: " . BASE_URL . "reset_password.php");
                exit();
            } else {
                $error = "Failed to send reset code. Please try again.";
            }
        } catch (Exception $e) {
            error_log("Failed to resend reset OTP: " . $e->getMessage());
            $error = "An unexpected error occurred. Please try again.";
        }
    } else {
        $otp_input = trim($_POST['otp'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($otp_input) || empty($password) || empty($confirm_password)) {
            $error = "All fields are required.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif ($password !== $confirm_password) {
            $error = "Password confirmation does not match.";
        } else {
            try {
                // Fetch user OTP details
                $stmt = $conn->prepare("SELECT otp_code, otp_expiry FROM users WHERE id = ?");
                $stmt->execute([$reset_user_id]);
                $user = $stmt->fetch();

                $current_time = date('Y-m-d H:i:s');

                if (!$user) {
                    $error = "Account not found.";
                } elseif ($user['otp_code'] !== $otp_input) {
                    $error = "The verification code entered is incorrect.";
                } elseif (strtotime($user['otp_expiry']) < strtotime($current_time)) {
                    $error = "This verification code has expired. Please click the resend button to request a new code.";
                } else {
                    // Success: Update password and clear OTP fields
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("
                        UPDATE users 
                        SET password = ?, otp_code = NULL, otp_expiry = NULL 
                        WHERE id = ?
                    ");
                    $stmt->execute([$hashed_password, $reset_user_id]);

                    // Log activity
                    log_activity($reset_user_id, 'password_reset_success', "Password reset successfully for email: $reset_email");

                    // Clear reset session variables
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_user_id']);
                    unset($_SESSION['demo_otp_display']);

                    set_flash_message('success', 'Your password has been reset successfully! You can log in now.');
                    header("Location: " . BASE_URL . "login.php");
                    exit();
                }
            } catch (Exception $e) {
                error_log("Password reset failed: " . $e->getMessage());
                $error = "An unexpected error occurred. Please try again.";
            }
        }
    }
}

// Fetch OTP expiry time to sync countdown
$seconds_left = 0;
try {
    $stmt = $conn->prepare("SELECT otp_expiry FROM users WHERE id = ?");
    $stmt->execute([$reset_user_id]);
    $expiry = $stmt->fetchColumn();
    if ($expiry) {
        $seconds_left = strtotime($expiry) - time();
        if ($seconds_left < 0) {
            $seconds_left = 0;
        }
    }
} catch (Exception $e) {
    error_log("Failed to fetch OTP expiry: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Accolades Connect</title>
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
                    <i data-lucide="shield-alert" style="width: 32px; height: 32px; color: var(--accent); margin-bottom: 0.5rem;"></i>
                    <h2>Reset Password</h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
                        Enter the verification code sent to <strong style="color: #ffffff;"><?php echo e($reset_email); ?></strong> and your new password.
                    </p>
                </div>

                <?php if (DEVELOPMENT_MODE && $demo_otp): ?>
                    <div class="demo-banner">
                        <div>
                            <span class="demo-badge">DEMO MODE</span>
                            <span style="margin-left: 0.5rem;">Reset Code: <strong style="font-size: 1.1rem; color: #ffffff; letter-spacing: 1px;"><?php echo e($demo_otp); ?></strong></span>
                        </div>
                        <i data-lucide="code" style="width: 18px; height: 18px;"></i>
                    </div>
                <?php endif; ?>

                <?php display_flash_message(); ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger show-alert-anim">
                        <i data-lucide="alert-triangle" class="alert-icon"></i>
                        <div class="alert-content"><?php echo e($error); ?></div>
                    </div>
                <?php endif; ?>

                <form action="<?php echo BASE_URL; ?>reset_password.php" method="POST">
                    <?php csrf_input(); ?>
                    <input type="hidden" name="action" value="reset">

                    <div class="form-group">
                        <label class="form-label" for="email_display">Email Address</label>
                        <input type="email" id="email_display" class="form-control" value="<?php echo e($reset_email); ?>" disabled readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="otp" style="text-align: center;">Enter 6-Digit Verification Code</label>
                        <input type="text" id="otp" name="otp" class="form-control" placeholder="000000" maxlength="6" pattern="\d{6}" style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5em; padding-left: 1.5rem;" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">New Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required style="padding-right: 2.75rem;">
                            <button type="button" id="toggle-password" class="password-toggle-btn">
                                <i data-lucide="eye" style="width: 18px; height: 18px;"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" required style="padding-right: 2.75rem;">
                            <button type="button" id="toggle-confirm-password" class="password-toggle-btn">
                                <i data-lucide="eye" style="width: 18px; height: 18px;"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-accent" style="width: 100%; margin-top: 1rem;">
                        <i data-lucide="check-circle"></i> Save New Password
                    </button>
                </form>

                <form action="<?php echo BASE_URL; ?>reset_password.php" method="POST" id="resend-form" style="display:none;">
                    <?php csrf_input(); ?>
                    <input type="hidden" name="action" value="resend">
                </form>

                <div style="text-align: center; margin-top: 1.5rem;">
                    <span id="timer-container" style="font-size: 0.9rem; color: var(--text-muted);">
                        Code valid for: <strong id="countdown-timer" style="color: var(--accent);">02:00</strong>
                    </span>
                    <button type="button" id="btn-resend-otp" class="btn btn-secondary btn-sm" style="display: none; margin: 0 auto;" onclick="document.getElementById('resend-form').submit();">
                        <i data-lucide="refresh-cw" style="width: 14px; height: 14px; display:inline-block; vertical-align:middle; margin-right: 0.25rem;"></i> Resend Code
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle passwords script
        function bindPasswordToggle(buttonId, inputId) {
            const btn = document.getElementById(buttonId);
            const input = document.getElementById(inputId);
            if (btn && input) {
                btn.addEventListener('click', function() {
                    const icon = btn.querySelector('i') || btn.querySelector('svg');
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.setAttribute('data-lucide', 'eye-off');
                    } else {
                        input.type = 'password';
                        icon.setAttribute('data-lucide', 'eye');
                    }
                    lucide.createIcons();
                });
            }
        }
        bindPasswordToggle('toggle-password', 'password');
        bindPasswordToggle('toggle-confirm-password', 'confirm_password');

        // Countdown Timer script
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
