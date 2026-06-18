<?php
/**
 * Student Browse Events Page
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Access Control
require_role('student');

$student = get_current_student();
if (!$student) {
    set_flash_message('danger', 'Unable to retrieve student profile.');
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$events = [];
try {
    $stmt = $conn->prepare("
        SELECT e.*, r.id as reg_id, r.status as reg_status, r.assigned_role, r.applied_role, r.role_status
        FROM events e
        LEFT JOIN event_registrations r ON e.id = r.event_id AND r.student_id = ?
        WHERE e.status IN ('upcoming', 'registration_open', 'registration_closed')
        ORDER BY e.event_date ASC
    ");
    $stmt->execute([$student['id']]);
    $events = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch student browse events: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Events - Accolades Connect</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?php echo BASE_URL; ?>index.php" class="brand">
                <i data-lucide="calendar-check"></i>
                <span>Accolades Connect</span>
            </a>
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>student/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/events.php" class="nav-link active">Browse Events</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/attendance_records.php" class="nav-link">My Attendance</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/certificates.php" class="nav-link">My Certificates</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/profile.php" class="nav-link">My Profile</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:14px; height:14px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <div style="margin-bottom: 2.5rem;">
            <h2>Browse Department Events</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Explore upcoming events, registration deadlines, and signup statuses.</p>
        </div>

        <?php display_flash_message(); ?>

        <?php if (empty($events)): ?>
            <div class="card" style="text-align: center; padding: 4rem 2rem;">
                <i data-lucide="calendar" style="width: 48px; height: 48px; color: var(--text-muted); margin: 0 auto 1.5rem;"></i>
                <p style="font-size: 1.1rem; color: var(--text-muted);">No departmental events are scheduled at the moment.</p>
            </div>
        <?php else: ?>
            <div class="landing-cards">
                <?php foreach ($events as $event): ?>
                    <article class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem;">
                                <span class="badge <?php echo $event['status'] === 'registration_open' ? 'badge-approved' : 'badge-pending'; ?>">
                                    <?php echo str_replace('_', ' ', $event['status']); ?>
                                </span>
                                <span style="font-weight: 700; font-size: 1.15rem; color: var(--accent);">
                                    <?php echo $event['registration_fee'] > 0 ? '₹' . number_format($event['registration_fee'], 2) : 'Free'; ?>
                                </span>
                            </div>
                            
                            <h3 style="margin-bottom: 0.75rem; font-size: 1.35rem; color: #ffffff;"><?php echo e($event['name']); ?></h3>
                            
                            <?php if (!empty($event['description'])): ?>
                                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem; line-height: 1.5; word-wrap: break-word;">
                                    <?php echo e(strlen($event['description']) > 150 ? substr($event['description'], 0, 147) . '...' : $event['description']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div style="display: flex; flex-direction: column; gap: 0.5rem; margin: 1.5rem 0; font-size: 0.9rem; color: var(--text-muted);">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i data-lucide="calendar" style="width:16px; height:16px;"></i>
                                    <span>Date: <?php echo date('M d, Y', strtotime($event['event_date'])); ?></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i data-lucide="map-pin" style="width:16px; height:16px;"></i>
                                    <span>Venue: <?php echo e($event['venue']); ?></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i data-lucide="clock" style="width:16px; height:16px;"></i>
                                    <span>Reg Deadline: <?php echo date('M d, Y H:i', strtotime($event['registration_deadline'])); ?></span>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                            <?php if ($event['reg_id']): ?>
                                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                                        <span style="color: var(--text-muted);">Registration Status:</span>
                                        <span class="badge badge-<?php echo $event['reg_status']; ?>"><?php echo $event['reg_status']; ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                                        <span style="color: var(--text-muted);">Event Post:</span>
                                        <span style="font-weight: 500; font-size: 0.85rem; color: #ffffff;">
                                            <?php 
                                            if ($event['role_status'] === 'approved') {
                                                $lbl = $event['assigned_role'];
                                                if ($lbl === 'volunteers') echo 'Volunteer';
                                                elseif ($lbl === 'OC') echo 'OC Members';
                                                elseif ($lbl === 'CC') echo 'CC Members';
                                                else echo 'Participant';
                                            } elseif ($event['role_status'] === 'pending') {
                                                $lbl = $event['applied_role'];
                                                $role_lbl = 'Participant';
                                                if ($lbl === 'volunteers') $role_lbl = 'Volunteer';
                                                elseif ($lbl === 'OC') $role_lbl = 'OC';
                                                elseif ($lbl === 'CC') $role_lbl = 'CC';
                                                echo 'Requested: ' . $role_lbl . ' (Pending)';
                                            } else {
                                                echo 'Participant';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <?php if ($event['reg_status'] === 'approved'): ?>
                                        <a href="<?php echo BASE_URL; ?>student/my_qr.php?reg_id=<?php echo $event['reg_id']; ?>" class="btn btn-success btn-sm" style="width: 100%;">
                                            <i data-lucide="qr-code"></i> View Event QR Code
                                        </a>
                                    <?php elseif ($event['reg_status'] === 'pending_payment' || $event['reg_status'] === 'rejected'): ?>
                                        <?php if ($event['registration_fee'] > 0): ?>
                                            <a href="<?php echo BASE_URL; ?>student/upload_payment.php?reg_id=<?php echo $event['reg_id']; ?>" class="btn btn-primary btn-sm" style="width: 100%;">
                                                <i data-lucide="upload-cloud"></i> Upload Payment Proof
                                            </a>
                                        <?php else: ?>
                                            <span style="font-size: 0.85rem; color: var(--warning); text-align: center; display: block;">Pending organizer approval</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="btn btn-secondary btn-sm" style="width: 100%;">
                                            Go to Dashboard
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <!-- Student hasn't registered yet -->
                                <?php if ($event['status'] === 'registration_open'): ?>
                                    <?php 
                                    $deadline_passed = strtotime($event['registration_deadline']) < time();
                                    if ($deadline_passed): 
                                    ?>
                                        <button class="btn btn-secondary" style="width: 100%;" disabled>
                                            <i data-lucide="clock"></i> Deadline Passed
                                        </button>
                                    <?php else: ?>
                                        <!-- Registration action form -->
                                        <form action="<?php echo BASE_URL; ?>student/register_event.php" method="POST">
                                            <?php csrf_input(); ?>
                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                                <i data-lucide="check-square"></i> Register Now
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($event['status'] === 'registration_closed'): ?>
                                    <button class="btn btn-secondary" style="width: 100%;" disabled>
                                        Registration Closed
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary" style="width: 100%;" disabled>
                                        Coming Soon
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
