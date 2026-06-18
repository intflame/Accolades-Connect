<?php
/**
 * Admin: Manage Event Certificates
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Access Control
require_role('admin');

$event_id = intval($_GET['event_id'] ?? 0);
if ($event_id <= 0) {
    set_flash_message('danger', 'Invalid Event ID.');
    header("Location: " . BASE_URL . "admin/certificates/index.php");
    exit();
}

// Fetch event details
try {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
} catch (Exception $e) {
    error_log("Failed to fetch event: " . $e->getMessage());
    $event = false;
}

if (!$event) {
    set_flash_message('danger', 'Event not found.');
    header("Location: " . BASE_URL . "admin/certificates/index.php");
    exit();
}

$is_certifier_enabled = !empty($event['certifier_campaign_id']);
$is_remote_template = !empty($event['certificate_template']) && preg_match('/^https?:\/\//i', $event['certificate_template']);
$has_template = $is_remote_template || (!empty($event['certificate_template']) && file_exists(UPLOAD_PATH . '/certificate_templates/' . $event['certificate_template']));
$allow_issuance = $has_template || $is_certifier_enabled;

$errors = [];

// Handle Certificate Issuance / Revocation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_request();

    $action = trim($_POST['action'] ?? '');
    $reg_ids = $_POST['reg_ids'] ?? [];

    if (empty($reg_ids)) {
        $errors[] = "Please select at least one student.";
    } else {
        // Sanitize registration IDs
        $reg_ids = array_map('intval', $reg_ids);
        
        if ($action === 'issue') {
            if (!$allow_issuance) {
                $errors[] = "Cannot issue certificates: A certificate template or Certifier Campaign ID has not been configured for this event.";
            } else {
                $issued_count = 0;
                try {
                    $conn->beginTransaction();
                    
                    // Prepare queries with extra fields for Certifier templates
                    $check_stmt = $conn->prepare("
                        SELECT r.id, s.name, u.email, s.course, b.name as batch_name, r.assigned_role
                        FROM event_registrations r
                        JOIN students s ON r.student_id = s.id
                        JOIN users u ON s.user_id = u.id
                        LEFT JOIN batches b ON s.batch_id = b.id
                        JOIN qr_tokens q ON q.registration_id = r.id
                        JOIN attendance_scans a ON a.qr_token_id = q.id AND a.scan_type = 'entry'
                        WHERE r.id = ? AND r.event_id = ?
                    ");
                    $cert_check = $conn->prepare("SELECT id FROM certificates WHERE registration_id = ?");
                    $insert_stmt = $conn->prepare("
                        INSERT INTO certificates (registration_id, certificate_code, issued_by, certifier_credential_id, certifier_pdf_url)
                        VALUES (?, ?, ?, ?, ?)
                    ");

                    foreach ($reg_ids as $rid) {
                        // Validate student attended and registered
                        $check_stmt->execute([$rid, $event_id]);
                        $student = $check_stmt->fetch();
                        if (!$student) continue;

                        // Check if already issued
                        $cert_check->execute([$rid]);
                        if ($cert_check->fetch()) continue;

                        // Generate unique certificate code
                        $code = "MAR-E" . $event_id . "-" . strtoupper(bin2hex(random_bytes(4)));
                        
                        $certifier_credential_id = null;
                        $certifier_pdf_url = null;

                        if ($is_certifier_enabled) {
                            $role_label = 'Participant';
                            if ($student['assigned_role'] === 'volunteers') {
                                $role_label = 'Volunteer';
                            } elseif ($student['assigned_role'] === 'OC') {
                                $role_label = 'Organizing Committee Member';
                            } elseif ($student['assigned_role'] === 'CC') {
                                $role_label = 'Co-coordinator';
                            }

                            $attributes = [
                                'event_name' => $event['name'],
                                'event_date' => date('F d, Y', strtotime($event['event_date'])),
                                'venue' => $event['venue'],
                                'assigned_role' => $role_label,
                                'course' => $student['course'],
                                'batch' => $student['batch_name'] ?? 'N/A',
                                'certificate_code' => $code
                            ];

                            $api_res = issue_certifier_certificate($event['certifier_campaign_id'], $student['name'], $student['email'], $attributes);
                            if ($api_res['success']) {
                                $certifier_credential_id = $api_res['credential_id'];
                                $certifier_pdf_url = $api_res['pdf_link'];
                            } else {
                                throw new Exception("Certifier API Error for {$student['name']}: " . $api_res['message']);
                            }
                        }

                        $insert_stmt->execute([$rid, $code, $_SESSION['user_id'], $certifier_credential_id, $certifier_pdf_url]);
                        
                        log_activity($_SESSION['user_id'], 'certificate_issued', "Issued certificate {$code} for {$student['name']} in event '{$event['name']}'");
                        $issued_count++;
                    }

                    $conn->commit();
                    set_flash_message('success', "Successfully issued {$issued_count} certificates.");
                    header("Location: " . BASE_URL . "admin/certificates/manage.php?event_id=" . $event_id);
                    exit();

                } catch (Exception $e) {
                    $conn->rollBack();
                    error_log("Failed to issue certificates: " . $e->getMessage());
                    $errors[] = $e->getMessage();
                }
            }
        } elseif ($action === 'revoke') {
            $revoked_count = 0;
            try {
                $conn->beginTransaction();

                $check_stmt = $conn->prepare("
                    SELECT r.id, s.name, c.certificate_code
                    FROM event_registrations r
                    JOIN students s ON r.student_id = s.id
                    JOIN certificates c ON c.registration_id = r.id
                    WHERE r.id = ? AND r.event_id = ?
                ");
                $delete_stmt = $conn->prepare("DELETE FROM certificates WHERE registration_id = ?");

                foreach ($reg_ids as $rid) {
                    $check_stmt->execute([$rid, $event_id]);
                    $record = $check_stmt->fetch();
                    if (!$record) continue;

                    $delete_stmt->execute([$rid]);
                    log_activity($_SESSION['user_id'], 'certificate_revoked', "Revoked certificate {$record['certificate_code']} for {$record['name']} in event '{$event['name']}'");
                    $revoked_count++;
                }

                $conn->commit();
                set_flash_message('success', "Successfully revoked {$revoked_count} certificates.");
                header("Location: " . BASE_URL . "admin/certificates/manage.php?event_id=" . $event_id);
                exit();

            } catch (Exception $e) {
                $conn->rollBack();
                error_log("Failed to revoke certificates: " . $e->getMessage());
                $errors[] = "An error occurred during revocation. Please try again.";
            }
        }
    }
}

// Fetch all attended students for this event
$students = [];
try {
    $stmt = $conn->prepare("
        SELECT r.id as reg_id, s.name as student_name, s.course, b.name as batch_name, s.class_roll, s.university_roll, 
               a.scanned_at, c.id as certificate_id, c.certificate_code, c.issued_at
        FROM event_registrations r
        JOIN students s ON r.student_id = s.id
        LEFT JOIN batches b ON s.batch_id = b.id
        JOIN qr_tokens q ON q.registration_id = r.id
        JOIN attendance_scans a ON a.qr_token_id = q.id AND a.scan_type = 'entry'
        LEFT JOIN certificates c ON c.registration_id = r.id
        WHERE r.event_id = ?
        ORDER BY s.name ASC
    ");
    $stmt->execute([$event_id]);
    $students = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch attended students for event: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Event Certificates - Accolades Connect</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="brand">
                <i data-lucide="shield-alert"></i>
                <span>Accolades - Admin</span>
            </a>
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/students/index.php" class="nav-link">Students</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/batches/index.php" class="nav-link">Batches</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/events/index.php" class="nav-link">Events</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/payments/index.php" class="nav-link">Payments</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/attendance/index.php" class="nav-link">Attendance</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/scanner_users/index.php" class="nav-link">Scanners</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/certificates/index.php" class="nav-link active">Certificates</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/reports/index.php" class="nav-link">Reports</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/logs/index.php" class="nav-link">Logs</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:12px; height:12px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <a href="<?php echo BASE_URL; ?>admin/certificates/index.php" style="font-weight: 500; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.25rem; margin-bottom: 0.5rem;">
                    <i data-lucide="arrow-left" style="width:14px; height:14px;"></i> Back to Certificates Dashboard
                </a>
                <h2>Manage Certificates: <?php echo e($event['name']); ?></h2>
                <p style="color: var(--text-muted); font-size: 0.95rem;">
                    Event Date: <?php echo date('M d, Y', strtotime($event['event_date'])); ?> &bull; Venue: <?php echo e($event['venue']); ?>
                </p>
            </div>
            
            <div>
                <?php if ($has_template): ?>
                    <a href="<?php echo $is_remote_template ? e($event['certificate_template']) : BASE_URL . 'uploads/certificate_templates/' . e($event['certificate_template']); ?>" target="_blank" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                        <i data-lucide="image" style="width: 14px; height: 14px;"></i> Preview Template
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>admin/events/edit.php?event_id=<?php echo $event['id']; ?>" class="btn btn-danger btn-sm" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                        <i data-lucide="upload-cloud" style="width: 14px; height: 14px;"></i> Upload Template
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php display_flash_message(); ?>

        <?php if (!$has_template): ?>
            <div class="alert alert-danger show-alert-anim" style="margin-bottom: 2rem;">
                <i data-lucide="alert-triangle" class="alert-icon"></i>
                <div class="alert-content">
                    <strong>Warning:</strong> No certificate template uploaded yet. You cannot issue certificates until you upload a template in the event settings. 
                    <a href="<?php echo BASE_URL; ?>admin/events/edit.php?event_id=<?php echo $event['id']; ?>" style="color: inherit; text-decoration: underline; font-weight: bold; margin-left: 0.5rem;">Upload Template Now</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger show-alert-anim" style="margin-bottom: 2rem;">
                <i data-lucide="alert-circle" class="alert-icon"></i>
                <div class="alert-content">
                    <ul style="padding-left: 1rem; margin: 0;">
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo e($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 2rem;">
            <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; justify-content: space-between;">
                <!-- Search & Filters -->
                <div style="display: flex; gap: 0.75rem; flex-grow: 1; max-width: 500px;">
                    <div style="position: relative; flex-grow: 1;">
                        <i data-lucide="search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted);"></i>
                        <input type="text" id="search-input" placeholder="Search by name, roll or batch..." class="form-control" style="padding-left: 2.25rem;">
                    </div>
                    <select id="status-filter" class="form-control" style="width: 160px; max-width: 100%;">
                        <option value="all">All Students</option>
                        <option value="issued">Issued</option>
                        <option value="pending">Not Issued</option>
                    </select>
                </div>

                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    Showing <span id="displayed-count"><?php echo count($students); ?></span> of <?php echo count($students); ?> attended students.
                </div>
            </div>

            <?php if (empty($students)): ?>
                <div style="text-align: center; padding: 4rem 1rem; color: var(--text-muted);">
                    <i data-lucide="users" style="width: 40px; height: 40px; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <p>No students have attended this event yet (no entry scans recorded).</p>
                </div>
            <?php else: ?>
                <form id="bulk-form" method="POST" action="<?php echo BASE_URL; ?>admin/certificates/manage.php?event_id=<?php echo $event_id; ?>">
                    <?php csrf_input(); ?>
                    <input type="hidden" name="action" id="bulk-action" value="">

                    <div style="display: flex; gap: 0.75rem; margin-bottom: 1rem;">
                        <button type="button" class="btn btn-success btn-sm" onclick="submitBulk('issue')" <?php echo !$has_template ? 'disabled' : ''; ?> style="display: inline-flex; align-items: center; gap: 0.25rem;">
                            <i data-lucide="check-circle" style="width: 14px; height: 14px;"></i> Issue Selected
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="submitBulk('revoke')" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                            <i data-lucide="x-circle" style="width: 14px; height: 14px;"></i> Revoke Selected
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table" id="students-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px; text-align: center;">
                                        <input type="checkbox" id="select-all" style="transform: scale(1.2); cursor: pointer;">
                                    </th>
                                    <th>Student Details</th>
                                    <th>Batch / Roll</th>
                                    <th>Check-in Time</th>
                                    <th>Certificate Status</th>
                                    <th>Certificate Code</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr class="student-row" data-name="<?php echo e(strtolower($student['student_name'])); ?>" data-roll="<?php echo e(strtolower($student['class_roll'])); ?>" data-batch="<?php echo e(strtolower($student['batch_name'] ?? '')); ?>" data-status="<?php echo $student['certificate_id'] ? 'issued' : 'pending'; ?>">
                                        <td style="text-align: center; vertical-align: middle;">
                                            <input type="checkbox" name="reg_ids[]" value="<?php echo $student['reg_id']; ?>" class="student-checkbox" style="transform: scale(1.2); cursor: pointer;">
                                        </td>
                                        <td style="vertical-align: middle;">
                                            <div style="font-weight: 600;"><?php echo e($student['student_name']); ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo e($student['course']); ?></div>
                                        </td>
                                        <td style="vertical-align: middle;">
                                            <div><?php echo e($student['batch_name'] ?? 'N/A'); ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);">Roll: <?php echo e($student['class_roll']); ?></div>
                                        </td>
                                        <td style="vertical-align: middle; font-size: 0.85rem; color: var(--text-muted);">
                                            <?php echo date('M d, h:i A', strtotime($student['scanned_at'])); ?>
                                        </td>
                                        <td style="vertical-align: middle;">
                                            <?php if ($student['certificate_id']): ?>
                                                <span class="badge badge-approved" style="background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3);">
                                                    Issued
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-pending" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3);">
                                                    Not Issued
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="vertical-align: middle;">
                                            <?php if ($student['certificate_id']): ?>
                                                <code style="font-family: monospace; font-size: 0.85rem; color: var(--accent); font-weight: bold;"><?php echo e($student['certificate_code']); ?></code>
                                                <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
                                                    On: <?php echo date('M d, Y', strtotime($student['issued_at'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 0.85rem;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const searchInput = document.getElementById('search-input');
        const statusFilter = document.getElementById('status-filter');
        const selectAll = document.getElementById('select-all');
        const checkboxes = document.querySelectorAll('.student-checkbox');
        const rows = document.querySelectorAll('.student-row');
        const displayedCount = document.getElementById('displayed-count');

        if (searchInput && statusFilter) {
            function filterTable() {
                const query = searchInput.value.toLowerCase().trim();
                const filterVal = statusFilter.value;
                let count = 0;

                rows.forEach(row => {
                    const name = row.getAttribute('data-name');
                    const roll = row.getAttribute('data-roll');
                    const batch = row.getAttribute('data-batch');
                    const status = row.getAttribute('data-status');

                    const matchesSearch = name.includes(query) || roll.includes(query) || batch.includes(query);
                    const matchesStatus = filterVal === 'all' || status === filterVal;

                    if (matchesSearch && matchesStatus) {
                        row.style.display = '';
                        count++;
                    } else {
                        row.style.display = 'none';
                        // Deselect if hidden
                        const cb = row.querySelector('.student-checkbox');
                        if (cb) cb.checked = false;
                    }
                });

                if (displayedCount) displayedCount.textContent = count;
                updateSelectAllCheckbox();
            }

            searchInput.addEventListener('input', filterTable);
            statusFilter.addEventListener('change', filterTable);
        }

        if (selectAll) {
            selectAll.addEventListener('change', function() {
                const isChecked = this.checked;
                rows.forEach(row => {
                    if (row.style.display !== 'none') {
                        const cb = row.querySelector('.student-checkbox');
                        if (cb) cb.checked = isChecked;
                    }
                });
            });
        }

        checkboxes.forEach(cb => {
            cb.addEventListener('change', updateSelectAllCheckbox);
        });

        function updateSelectAllCheckbox() {
            if (!selectAll) return;
            const visibleCheckboxes = Array.from(rows)
                .filter(row => row.style.display !== 'none')
                .map(row => row.querySelector('.student-checkbox'))
                .filter(Boolean);

            if (visibleCheckboxes.length === 0) {
                selectAll.checked = false;
                return;
            }

            const allChecked = visibleCheckboxes.every(cb => cb.checked);
            selectAll.checked = allChecked;
        }
    });

    function submitBulk(action) {
        const checked = document.querySelectorAll('.student-checkbox:checked');
        if (checked.length === 0) {
            alert('Please select at least one student.');
            return;
        }

        const actionText = action === 'issue' ? 'issue' : 'revoke';
        const msg = action === 'issue' 
            ? `Are you sure you want to generate and issue certificates to the ${checked.length} selected student(s)?` 
            : `Are you sure you want to permanently revoke/delete certificates from the ${checked.length} selected student(s)?`;

        if (confirm(msg)) {
            document.getElementById('bulk-action').value = action;
            document.getElementById('bulk-form').submit();
        }
    }
    </script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
