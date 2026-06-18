<?php
/**
 * CSRF Protection Security Token Provider
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate secure token and store in session
 */
function get_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output CSRF hidden input field for forms
 */
function csrf_input() {
    $token = get_csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Validate submitted token against session token
 */
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Automatically validate POST requests (helper)
 */
function verify_csrf_post_request() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        if (!validate_csrf_token($token)) {
            // Log CSRF failure
            if (function_exists('log_activity')) {
                log_activity(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null, 'csrf_violation', 'CSRF validation failed for POST request.');
            }
            http_response_code(403);
            die("Security Error: Invalid CSRF Token.");
        }
    }
}
