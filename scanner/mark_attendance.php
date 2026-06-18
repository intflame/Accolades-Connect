<?php
/**
 * AJAX API: Process Scanned QR Token
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Access Control check
if (!is_logged_in() || !in_array($_SESSION['user_role'], ['scanner', 'admin'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access. Please login as scanner or administrator.'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method.'
    ]);
    exit();
}

$token = trim($_POST['token'] ?? '');
$event_id = intval($_POST['event_id'] ?? 0);
$scan_type = trim($_POST['scan_type'] ?? 'entry');

if (empty($token) || !$event_id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required scan parameters (token or event ID).'
    ]);
    exit();
}

if (!in_array($scan_type, ['entry', 'food', 'exit'])) {
    $scan_type = 'entry';
}

try {
    // 1. Fetch QR token and link to student, registration, event and batch
    $stmt = $conn->prepare("
        SELECT q.id as token_id, q.status as token_status,
               r.id as reg_id, r.event_id, r.status as reg_status, r.assigned_role,
               s.id as student_id, s.name as student_name, s.class_roll, s.university_roll, s.food_preference,
               b.name as batch_name,
               e.name as event_name, e.event_date, e.scan_start_time, e.scan_end_time, e.status as event_status, e.food_enabled
        FROM qr_tokens q
        JOIN event_registrations r ON q.registration_id = r.id
        JOIN students s ON r.student_id = s.id
        LEFT JOIN batches b ON s.batch_id = b.id
        JOIN events e ON r.event_id = e.id
        WHERE q.token = ?
    ");
    $stmt->execute([$token]);
    $data = $stmt->fetch();

    // 2. Validate token existence
    if (!$data) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid QR Code. Ticket token not recognized in system.'
        ]);
        exit();
    }

    // 3. Validate event match
    if (intval($data['event_id']) !== $event_id) {
        echo json_encode([
            'status' => 'error',
            'message' => "Wrong Event! This ticket is for: '{$data['event_name']}'."
        ]);
        exit();
    }

    // Validate food status for food scan type
    if ($scan_type === 'food' && !$data['food_enabled']) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Food scan is not enabled/available for this event.'
        ]);
        exit();
    }

    // 4. Validate registration & payment approval status
    if ($data['reg_status'] !== 'approved') {
        echo json_encode([
            'status' => 'error',
            'message' => "Registration issue! Ticket is: " . strtoupper($data['reg_status']) . ". Payment must be approved first."
        ]);
        exit();
    }

    // 5. Validate QR token status
    if ($data['token_status'] === 'disabled') {
        echo json_encode([
            'status' => 'error',
            'message' => 'This ticket has been disabled by the administrator.'
        ]);
        exit();
    }
    if ($data['token_status'] === 'expired') {
        echo json_encode([
            'status' => 'error',
            'message' => 'This QR ticket has expired.'
        ]);
        exit();
    }

    // 6. Validate scan window timing
    $now = time();
    $start_time = strtotime($data['scan_start_time']);
    $end_time = strtotime($data['scan_end_time']);

    if ($now < $start_time) {
        echo json_encode([
            'status' => 'warning',
            'message' => 'Scan window not open yet. Starts at: ' . date('h:i A', $start_time)
        ]);
        exit();
    }
    if ($now > $end_time) {
        echo json_encode([
            'status' => 'warning',
            'message' => 'Scan window closed! Ended at: ' . date('h:i A', $end_time)
        ]);
        exit();
    }

    // 7. Check if already scanned for this specific scan type
    $stmt = $conn->prepare("
        SELECT id, scanned_at 
        FROM attendance_scans 
        WHERE qr_token_id = ? AND scan_type = ?
    ");
    $stmt->execute([$data['token_id'], $scan_type]);
    $existing_scan = $stmt->fetch();

    if ($existing_scan) {
        $scanned_time = date('h:i A', strtotime($existing_scan['scanned_at']));
        echo json_encode([
            'status' => 'warning',
            'message' => "Already Scanned! Present checked in at {$scanned_time}.",
            'student_name' => $data['student_name'],
            'student_roll' => $data['class_roll'],
            'student_batch' => $data['batch_name'],
            'student_role' => $data['assigned_role']
        ]);
        exit();
    }

    // 8. Process check-in (Save scan details)
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        INSERT INTO attendance_scans (qr_token_id, scan_type, scanned_by) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$data['token_id'], $scan_type, $_SESSION['user_id']]);

    // If entry scan, update token status to used
    if ($scan_type === 'entry') {
        $stmt = $conn->prepare("UPDATE qr_tokens SET status = 'used' WHERE id = ?");
        $stmt->execute([$data['token_id']]);
    }

    // Log check-in activity
    log_activity(
        $_SESSION['user_id'], 
        'attendance_marked', 
        "Marked {$scan_type} attendance for student ID: {$data['student_id']}. Reg ID: {$data['reg_id']}"
    );

    $conn->commit();

    // Success response
    echo json_encode([
        'status' => 'success',
        'message' => strtoupper($scan_type) . " check-in marked successfully!",
        'student_name' => $data['student_name'],
        'student_roll' => $data['class_roll'],
        'student_batch' => $data['batch_name'],
        'food_pref' => $data['food_preference'],
        'student_role' => $data['assigned_role']
    ]);
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Failed to process scanned QR code: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database exception processing check-in.'
    ]);
    exit();
}
