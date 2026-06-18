<?php
/**
 * Request Password Reset Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/mailer.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_request();

    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address format.";
    } else {
        try {
            $email = strtolower($email);
            // Check if user exists
            $stmt = $conn->prepare("SELECT id, status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = "No account found with this email address.";
            } elseif ($user['status'] === 'suspended') {
                $error = "This account is suspended. Please contact the administrator.";
            } else {
                // Generate OTP
                $otp = generate_otp();
                $otp_expiry = date('Y-m-d H:i:s', strtotime('+2 minutes'));

                // Save OTP to user record
                $stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?");
                $stmt->execute([$otp, $otp_expiry, $user['id']]);

                // Send email
                if (send_password_reset_email($email, $otp)) {
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_user_id'] = $user['id'];
                    set_flash_message('success', 'A password reset code has been sent to your email.');
                    header("Location: " . BASE_URL . "reset_password.php");
                    exit();
                } else {
                    $error = "Failed to send reset code. Please try again later.";
                }
            }
        } catch (Exception $e) {
            error_log("Password reset request error: " . $e->getMessage());
            $error = "An unexpected error occurred. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Accolades Connect</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css?v=1.0.1">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?php echo BASE_URL; ?>index.php" class="brand">
                <i data-lucide="calendar-check"></i>
                <span>Accolades Connect</span>
            </a>
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>index.php" class="nav-link">Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>login.php" class="nav-link">Login</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <div class="auth-wrapper">
            <div class="card auth-card">
                <div class="card-header" style="text-align: center;">
                    <div style="text-align: center; margin-bottom: 1.25rem;">
                        <img src="<?php echo BASE_URL; ?>assets/images/logo_accolades.png" alt="Accolades Logo" style="max-width: 220px; height: auto; background-color: #ffffff; padding: 0.5rem 1.25rem; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); display: inline-block;">
                    </div>
                    <i data-lucide="key-round" style="width: 32px; height: 32px; color: var(--primary); margin-bottom: 0.5rem;"></i>
                    <h2>Forgot Password</h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">Enter your email to receive a password reset code</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger show-alert-anim">
                        <i data-lucide="alert-triangle" class="alert-icon"></i>
                        <div class="alert-content"><?php echo e($error); ?></div>
                    </div>
                <?php endif; ?>

                <form action="<?php echo BASE_URL; ?>forgot_password.php" method="POST">
                    <?php csrf_input(); ?>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="your.name@example.com" required autofocus>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        <i data-lucide="send"></i> Send Reset Code
                    </button>
                </form>

                <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem;">
                    <span style="color: var(--text-muted);">Remembered your password?</span> 
                    <a href="<?php echo BASE_URL; ?>login.php">Login here</a>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
