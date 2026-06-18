<?php
/**
 * AJAX API: Sync Canva Certificate Template on the fly
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Access Control
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

header('Content-Type: application/json');

$canva_url = trim($_GET['url'] ?? '');

if (empty($canva_url)) {
    echo json_encode(['success' => false, 'message' => 'Canva URL is required.']);
    exit();
}

$sync_res = sync_canva_template($canva_url);

echo json_encode($sync_res);
exit();
