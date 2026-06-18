<?php
/**
 * Authentication and Session Controls
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Require user login, otherwise redirect to landing/login
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash_message('warning', 'Please login to access this page.');
        header("Location: " . BASE_URL . "login.php");
        exit();
    }
    
    // Periodically verify account status in database
    verify_current_user_status();
}

/**
 * Validate that the logged in user matches required role(s)
 */
function require_role($allowed_roles) {
    require_login();
    
    $role = $_SESSION['user_role'] ?? '';
    
    if (is_array($allowed_roles)) {
        if (!in_array($role, $allowed_roles)) {
            redirect_unauthorized($role);
        }
    } else {
        if ($role !== $allowed_roles) {
            redirect_unauthorized($role);
        }
    }
}

/**
 * Redirect user based on role if unauthorized
 */
function redirect_unauthorized($current_role) {
    set_flash_message('danger', 'Access Denied: You do not have permissions for that page.');
    
    switch ($current_role) {
        case 'admin':
            header("Location: " . BASE_URL . "admin/dashboard.php");
            break;
        case 'student':
            header("Location: " . BASE_URL . "student/dashboard.php");
            break;
        case 'scanner':
            header("Location: " . BASE_URL . "scanner/dashboard.php");
            break;
        default:
            header("Location: " . BASE_URL . "login.php");
            break;
    }
    exit();
}

/**
 * Periodically queries db to make sure user wasn't suspended or status changed
 */
function verify_current_user_status() {
    global $conn;
    if (!isset($_SESSION['user_id'])) return;

    try {
        $stmt = $conn->prepare("SELECT status, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            // User deleted
            session_destroy();
            header("Location: " . BASE_URL . "login.php");
            exit();
        }

        if ($user['status'] === 'suspended') {
            session_destroy();
            // Start fresh session to pass message
            session_start();
            set_flash_message('danger', 'Your account has been suspended. Please contact the administrator.');
            header("Location: " . BASE_URL . "login.php");
            exit();
        }

        // Keep session role in sync
        $_SESSION['user_role'] = $user['role'];

    } catch (Exception $e) {
        // Log query issues but do not block session
        error_log("Status check failed: " . $e->getMessage());
    }
}

/**
 * Get active student details linked to current session user
 */
function get_current_student() {
    global $conn;
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
        return null;
    }

    try {
        $stmt = $conn->prepare("
            SELECT s.*, u.email, u.status, b.name as batch_name 
            FROM students s 
            JOIN users u ON s.user_id = u.id 
            LEFT JOIN batches b ON s.batch_id = b.id
            WHERE s.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}
