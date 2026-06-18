<?php
/**
 * Event Registration Action Processor
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Access Control
require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "student/events.php");
    exit();
}

// Check CSRF
verify_csrf_post_request();

$student = get_current_student();
$event_id = intval($_POST['event_id'] ?? 0);

if (!$student || !$event_id) {
    set_flash_message('danger', 'Invalid registration parameters.');
    header("Location: " . BASE_URL . "student/events.php");
    exit();
}

try {
    // 1. Fetch Event details
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        set_flash_message('danger', 'Event not found.');
        header("Location: " . BASE_URL . "student/events.php");
        exit();
    }

    // 2. Validate Event state
    if ($event['status'] !== 'registration_open') {
        set_flash_message('danger', 'Registrations are currently closed for this event.');
        header("Location: " . BASE_URL . "student/events.php");
        exit();
    }

    if (strtotime($event['registration_deadline']) < time()) {
        set_flash_message('danger', 'Registration deadline has already passed.');
        header("Location: " . BASE_URL . "student/events.php");
        exit();
    }

    // 3. Check duplicate registration
    $stmt = $conn->prepare("SELECT id FROM event_registrations WHERE event_id = ? AND student_id = ?");
    $stmt->execute([$event_id, $student['id']]);
    if ($stmt->fetch()) {
        set_flash_message('warning', 'You are already registered for this event.');
        header("Location: " . BASE_URL . "student/dashboard.php");
        exit();
    }

    // 4. Determine initial status based on fee
    $initial_status = 'pending_payment';
    $is_free = ($event['registration_fee'] == 0);
    
    if ($is_free) {
        $initial_status = 'approved';
    }

    // 5. Begin Transaction
    $conn->beginTransaction();

    // Insert Registration
    $stmt = $conn->prepare("
        INSERT INTO event_registrations (event_id, student_id, status, payment_method) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$event_id, $student['id'], $initial_status, $is_free ? 'cash' : null]);
    $reg_id = $conn->lastInsertId();

    // 6. Generate QR token if it's FREE (since approved instantly)
    if ($is_free) {
        $token = bin2hex(random_bytes(QR_TOKEN_LENGTH));
        $stmt = $conn->prepare("
            INSERT INTO qr_tokens (registration_id, token, status) 
            VALUES (?, ?, 'active')
        ");
        $stmt->execute([$reg_id, $token]);
        
        log_activity($student['user_id'], 'event_registered_free', "Registered for free event: {$event['name']}. Registration ID: $reg_id. QR Generated.");
        set_flash_message('success', "Registration successful! Since this is a free event, your ticket is approved and QR code is ready.");
    } else {
        log_activity($student['user_id'], 'event_registered_paid', "Registered for paid event: {$event['name']}. Registration ID: $reg_id. Status: Pending Payment.");
        set_flash_message('success', "Successfully registered for {$event['name']}. Please upload your payment screenshot to complete validation.");
    }

    $conn->commit();

    if ($is_free) {
        header("Location: " . BASE_URL . "student/dashboard.php");
    } else {
        header("Location: " . BASE_URL . "student/upload_payment.php?reg_id=" . $reg_id);
    }
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Event registration processor failed: " . $e->getMessage());
    set_flash_message('danger', 'An unexpected error occurred. Please try again.');
    header("Location: " . BASE_URL . "student/events.php");
    exit();
}
