<?php
/**
 * User Login Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'admin') header("Location: " . BASE_URL . "admin/dashboard.php");
    elseif ($role === 'student') header("Location: " . BASE_URL . "student/dashboard.php");
    elseif ($role === 'scanner') header("Location: " . BASE_URL . "scanner/dashboard.php");
    else header("Location: " . BASE_URL . "index.php");
    exit();
}

$error = null;
$redirect = trim($_GET['redirect'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_request();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                
                // Account state checks
                if ($user['status'] === 'pending_approval') {
                    $error = "Your account is verified but pending Admin approval. Please check back later.";
                } elseif ($user['status'] === 'suspended') {
                    $error = "Your account has been suspended. Please contact the administrator.";
                } elseif ($user['status'] === 'active') {
                    // Start authenticated session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_email'] = $user['email'];

                    // Fetch student name if student
                    if ($user['role'] === 'student') {
                        $s_stmt = $conn->prepare("SELECT name FROM students WHERE user_id = ?");
                        $s_stmt->execute([$user['id']]);
                        $student = $s_stmt->fetch();
                        $_SESSION['student_name'] = $student['name'] ?? 'Student';
                    }

                    // Log activity
                    log_activity($user['id'], 'login_success', "User logged in with role: " . $user['role']);

                    // Redirect based on role or request redirect (students always go direct to dashboard.php)
                    if ($user['role'] === 'student') {
                        header("Location: " . BASE_URL . "student/dashboard.php");
                    } elseif (!empty($redirect) && strpos($redirect, '..') === false) {
                        header("Location: /" . ltrim($redirect, '/'));
                    } else {
                        if ($user['role'] === 'admin') {
                            header("Location: " . BASE_URL . "admin/dashboard.php");
                        } elseif ($user['role'] === 'scanner') {
                            header("Location: " . BASE_URL . "scanner/dashboard.php");
                        } else {
                            header("Location: " . BASE_URL . "index.php");
                        }
                    }
                    exit();
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Invalid email or password.";
                // Log failed attempt if user exists (to prevent brute forcing silently)
                if ($user) {
                    log_activity($user['id'], 'login_failed', "Failed login attempt for email: $email");
                }
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
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
    <title>Login - Accolades Connect</title>
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
                <li><a href="<?php echo BASE_URL; ?>register.php" class="nav-link">Register</a></li>
                <li><a href="<?php echo BASE_URL; ?>login.php" class="btn btn-primary btn-sm">Login</a></li>
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
                    <i data-lucide="log-in" style="width: 32px; height: 32px; color: var(--primary); margin-bottom: 0.5rem;"></i>
                    <h2>Welcome Back</h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">Sign in to access your portal</p>
                </div>

                <?php display_flash_message(); ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger show-alert-anim">
                        <i data-lucide="alert-triangle" class="alert-icon"></i>
                        <div class="alert-content"><?php echo e($error); ?></div>
                    </div>
                <?php endif; ?>

                <form action="<?php echo BASE_URL; ?>login.php<?php echo !empty($redirect) ? '?redirect=' . urlencode($redirect) : ''; ?>" method="POST">
                    <?php csrf_input(); ?>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="your.name@example.com" required autofocus>
                    </div>

                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <label class="form-label" for="password" style="margin-bottom: 0;">Password</label>
                            <a href="<?php echo BASE_URL; ?>forgot_password.php" style="font-size: 0.8rem; font-weight: 500;">Forgot Password?</a>
                        </div>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required style="padding-right: 2.75rem;">
                            <button type="button" id="toggle-password" class="password-toggle-btn">
                                <i data-lucide="eye" style="width: 18px; height: 18px;"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        <i data-lucide="shield-check"></i> Authenticate
                    </button>
                </form>

                <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem;">
                    <span style="color: var(--text-muted);">New student?</span> 
                    <a href="<?php echo BASE_URL; ?>register.php">Create an account</a>
                </div>
                
                <!-- Helper card for testing -->
                <div style="margin-top: 2rem; padding: 1rem; border-radius: var(--radius-sm); background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); font-size: 0.8rem; color: var(--text-muted);">
                    <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem; font-weight: 600; color: #ffffff;">
                        <i data-lucide="info" style="width: 14px; height: 14px;"></i>
                        <span>Default Demo Credentials:</span>
                    </div>
                    <ul style="padding-left: 1rem; list-style-type: disc;">
                        <li><strong>login with your registered email</strong></li>
                        <li><strong>For any issue contact with admin</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('toggle-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i') || this.querySelector('svg');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                passwordInput.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        });
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
