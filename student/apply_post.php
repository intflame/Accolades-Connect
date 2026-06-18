<?php
/**
 * Event Post Application Processor
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Access Control
require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "student/dashboard.php");
    exit();
}

// Check CSRF
verify_csrf_post_request();

$student = get_current_student();
$reg_id = intval($_POST['reg_id'] ?? 0);
$requested_role = trim($_POST['requested_role'] ?? '');

if (!$student || !$reg_id) {
    set_flash_message('danger', 'Invalid request parameters.');
    header("Location: " . BASE_URL . "student/dashboard.php");
    exit();
}

$valid_roles = ['participant', 'volunteers', 'OC', 'CC'];
if (!in_array($requested_role, $valid_roles)) {
    set_flash_message('danger', 'Invalid role requested.');
    header("Location: " . BASE_URL . "student/dashboard.php");
    exit();
}

try {
    // 1. Fetch registration details and verify ownership
    $stmt = $conn->prepare("
        SELECT r.*, e.name as event_name 
        FROM event_registrations r
        JOIN events e ON r.event_id = e.id
        WHERE r.id = ? AND r.student_id = ?
    ");
    $stmt->execute([$reg_id, $student['id']]);
    $registration = $stmt->fetch();

    if (!$registration) {
        set_flash_message('danger', 'Registration record not found.');
        header("Location: " . BASE_URL . "student/dashboard.php");
        exit();
    }

    // 2. Process based on requested role
    if ($requested_role === 'participant') {
        // Revert back to default
        $stmt = $conn->prepare("
            UPDATE event_registrations 
            SET assigned_role = 'participant', applied_role = NULL, role_status = 'none'
            WHERE id = ?
        ");
        $stmt->execute([$reg_id]);
        
        log_activity($student['user_id'], 'event_role_reset', "Reset event post to default (Participant) for event: {$registration['event_name']}. Reg ID: $reg_id.");
        set_flash_message('success', "Role reset to default Participant for event: {$registration['event_name']}.");
    } else {
        // Apply for CC, OC, or volunteers
        $stmt = $conn->prepare("
            UPDATE event_registrations 
            SET applied_role = ?, role_status = 'pending'
            WHERE id = ?
        ");
        $stmt->execute([$requested_role, $reg_id]);
        
        $role_label = $requested_role;
        if ($requested_role === 'OC') $role_label = 'Organizing Committee (OC)';
        if ($requested_role === 'CC') $role_label = 'Core Committee (CC)';
        if ($requested_role === 'volunteers') $role_label = 'Volunteers';
        
        log_activity($student['user_id'], 'event_role_applied', "Applied for role '{$requested_role}' for event: {$registration['event_name']}. Reg ID: $reg_id.");
        set_flash_message('success', "Successfully applied for the '{$role_label}' post for event: {$registration['event_name']}. The request has been sent to the admin.");
    }

    header("Location: " . BASE_URL . "student/dashboard.php");
    exit();

} catch (Exception $e) {
    error_log("Failed to process event post application: " . $e->getMessage());
    set_flash_message('danger', 'An unexpected error occurred. Please try again.');
    header("Location: " . BASE_URL . "student/dashboard.php");
    exit();
}
