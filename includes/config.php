<?php
/**
 * Application Configuration
 */




// Dynamically detect base URL path (handles subdirectory hosting on XAMPP and root domains)
$doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$dir_path = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$base_path = '/';
if (!empty($doc_root) && strpos($dir_path, $doc_root) === 0) {
    $sub_folder = substr($dir_path, strlen($doc_root));
    $base_path = '/' . trim($sub_folder, '/') . '/';
    if ($base_path === '//') $base_path = '/';
}
define('BASE_URL', $base_path);

// Define application base paths
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dept_event_attendance');

// Security & Domain Restriction
define('ALLOWED_DOMAINS', ['teamfuture.in']);

// Development Mode Settings
// If true, SMTP email failures won't block registration, and OTP codes will be displayed on-screen or logged.
define('DEVELOPMENT_MODE', false);
define('OTP_LOG_FILE', UPLOAD_PATH . '/otp_log.txt');

// SMTP Settings (For PHPMailer / Production)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // 'ssl' or 'tls'
define('SMTP_USER', 'sandipdutta41862@gmail.com');
define('SMTP_PASS', 'dydk wgee xtkj emij');
define('SMTP_FROM_EMAIL', 'sandipdutta41862@gmail.com');
define('SMTP_FROM_NAME', 'Accolades Connect');

// QR Token Settings
define('QR_TOKEN_LENGTH', 32); // Creates 64 character hex string

// Timezone Setup
date_default_timezone_set('Asia/Kolkata'); // Setting appropriate Indian Timezone by default or can adjust.
