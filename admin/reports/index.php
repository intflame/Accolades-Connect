<?php
/**
 * Admin: Reports Generation & CSV Export Engine
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Access Control
require_role('admin');

$export = trim($_GET['export'] ?? '');
$type = trim($_GET['type'] ?? '');
$event_id = intval($_GET['event_id'] ?? 0);

// --- CSV Export Engine Handler ---
if ($export === 'csv' && !empty($type)) {
    try {
        $filename = "report_" . $type;
        $headers = [];
        $data = [];

        // Definition of all possible columns and their index mappings for each report type
        $available_cols = [
            'students' => [
                0 => 'Student Name',
                1 => 'Email',
                2 => 'Course',
                3 => 'Batch',
                4 => 'Class Roll',
                5 => 'University Roll',
                6 => 'Contact No.',
                7 => 'WhatsApp No.',
                8 => 'Food Preference',
                9 => 'Status'
            ],
            'registrations' => [
                0 => 'Student Name',
                1 => 'Course',
                2 => 'Batch',
                3 => 'Class Roll',
                4 => 'University Roll',
                5 => 'Email',
                6 => 'Payment Method',
                7 => 'Payment Status',
                8 => 'Registration Status',
                9 => 'Event Role',
                10 => 'Contact No.'
            ],
            'attendance' => [
                0 => 'Student Name',
                1 => 'Course',
                2 => 'Batch',
                3 => 'Class Roll',
                4 => 'University Roll',
                5 => 'Email',
                6 => 'Check-in Type',
                7 => 'Scanned Time',
                8 => 'Contact No.'
            ],
            'absentees' => [
                0 => 'Student Name',
                1 => 'Course',
                2 => 'Batch',
                3 => 'Class Roll',
                4 => 'University Roll',
                5 => 'Email',
                6 => 'Contact No.',
                7 => 'Food Preference'
            ],
            'food' => [
                0 => 'Student Name',
                1 => 'Course',
                2 => 'Batch',
                3 => 'Class Roll',
                4 => 'Food Preference',
                5 => 'Check-in (Present)',
                6 => 'Contact No.'
            ]
        ];

        if (!array_key_exists($type, $available_cols)) {
            throw new Exception("Invalid report type.");
        }

        // Parse selected columns, defaulting to all if none are provided
        $selected_indexes = isset($_GET['columns']) && is_array($_GET['columns']) ? array_map('intval', $_GET['columns']) : [];

        if (empty($selected_indexes)) {
            $selected_indexes = array_keys($available_cols[$type]);
        } else {
            $selected_indexes = array_intersect($selected_indexes, array_keys($available_cols[$type]));
            if (empty($selected_indexes)) {
                throw new Exception("Please select at least one column to export.");
            }
        }

        // Sort indexes to keep consistent database order
        sort($selected_indexes);

        // Build output headers
        foreach ($selected_indexes as $idx) {
            $headers[] = $available_cols[$type][$idx];
        }

        if ($type === 'students') {
            $filename .= "_" . date('Ymd_His') . ".csv";
            
            $stmt = $conn->query("
                SELECT s.name, u.email, s.course, b.name as batch_name, s.class_roll, s.university_roll, s.contact_number, s.whatsapp_number, s.food_preference, u.status
                FROM students s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN batches b ON s.batch_id = b.id
                ORDER BY s.name ASC
            ");
            $raw_data = $stmt->fetchAll(PDO::FETCH_NUM);
            foreach ($raw_data as $row) {
                $filtered_row = [];
                foreach ($selected_indexes as $idx) {
                    $filtered_row[] = $row[$idx];
                }
                $data[] = $filtered_row;
            }

        } elseif ($type === 'registrations') {
            if (!$event_id) throw new Exception("Event ID is required for registration reports.");
            
            // Get event name
            $ev_name = $conn->query("SELECT name FROM events WHERE id = $event_id")->fetchColumn();
            $filename .= "_" . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $ev_name)) . "_" . date('Ymd') . ".csv";
            
            $stmt = $conn->prepare("
                SELECT s.name, s.course, b.name as batch, s.class_roll, s.university_roll, u.email, r.payment_method, IFNULL(p.status, 'unpaid'), r.status, r.assigned_role, s.contact_number
                FROM event_registrations r
                JOIN students s ON r.student_id = s.id
                JOIN users u ON s.user_id = u.id
                LEFT JOIN batches b ON s.batch_id = b.id
                LEFT JOIN payments p ON p.registration_id = r.id
                WHERE r.event_id = ?
                ORDER BY s.name ASC
            ");
            $stmt->execute([$event_id]);
            $raw_data = $stmt->fetchAll(PDO::FETCH_NUM);
            foreach ($raw_data as $row) {
                $role = $row[9];
                if ($role === 'volunteers') {
                    $row[9] = 'Volunteer';
                } elseif ($role === 'OC') {
                    $row[9] = 'Organizing Committee (OC)';
                } elseif ($role === 'CC') {
                    $row[9] = 'Core Committee (CC)';
                } else {
                    $row[9] = 'Participant';
                }
                
                $filtered_row = [];
                foreach ($selected_indexes as $idx) {
                    $filtered_row[] = $row[$idx];
                }
                $data[] = $filtered_row;
            }

        } elseif ($type === 'attendance') {
            if (!$event_id) throw new Exception("Event ID is required for attendance reports.");
            
            $ev_name = $conn->query("SELECT name FROM events WHERE id = $event_id")->fetchColumn();
            $filename .= "_" . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $ev_name)) . "_" . date('Ymd') . ".csv";
            
            $stmt = $conn->prepare("
                SELECT s.name, s.course, b.name as batch, s.class_roll, s.university_roll, u.email, a.scan_type, a.scanned_at, s.contact_number
                FROM attendance_scans a
                JOIN qr_tokens q ON a.qr_token_id = q.id
                JOIN event_registrations r ON q.registration_id = r.id
                JOIN students s ON r.student_id = s.id
                JOIN users u ON s.user_id = u.id
                LEFT JOIN batches b ON s.batch_id = b.id
                WHERE r.event_id = ?
                ORDER BY a.scanned_at ASC
            ");
            $stmt->execute([$event_id]);
            $raw_data = $stmt->fetchAll(PDO::FETCH_NUM);
            foreach ($raw_data as $row) {
                $filtered_row = [];
                foreach ($selected_indexes as $idx) {
                    $filtered_row[] = $row[$idx];
                }
                $data[] = $filtered_row;
            }

        } elseif ($type === 'absentees') {
            if (!$event_id) throw new Exception("Event ID is required for absentees reports.");
            
            $ev_name = $conn->query("SELECT name FROM events WHERE id = $event_id")->fetchColumn();
            $filename .= "_absent_" . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $ev_name)) . "_" . date('Ymd') . ".csv";
            
            $stmt = $conn->prepare("
                SELECT s.name, s.course, b.name as batch, s.class_roll, s.university_roll, u.email, s.contact_number, s.food_preference
                FROM event_registrations r
                JOIN students s ON r.student_id = s.id
                JOIN users u ON s.user_id = u.id
                LEFT JOIN batches b ON s.batch_id = b.id
                LEFT JOIN qr_tokens q ON q.registration_id = r.id
                LEFT JOIN attendance_scans a ON a.qr_token_id = q.id AND a.scan_type = 'entry'
                WHERE r.event_id = ? AND r.status = 'approved' AND a.id IS NULL
                ORDER BY s.name ASC
            ");
            $stmt->execute([$event_id]);
            $raw_data = $stmt->fetchAll(PDO::FETCH_NUM);
            foreach ($raw_data as $row) {
                $filtered_row = [];
                foreach ($selected_indexes as $idx) {
                    $filtered_row[] = $row[$idx];
                }
                $data[] = $filtered_row;
            }

        } elseif ($type === 'food') {
            if (!$event_id) throw new Exception("Event ID is required for food reports.");
            
            $ev_name = $conn->query("SELECT name FROM events WHERE id = $event_id")->fetchColumn();
            $filename .= "_food_" . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $ev_name)) . "_" . date('Ymd') . ".csv";
            
            $stmt = $conn->prepare("
                SELECT s.name, s.course, b.name as batch, s.class_roll, s.food_preference, IF(a.id IS NOT NULL, 'PRESENT', 'ABSENT'), s.contact_number
                FROM event_registrations r
                JOIN students s ON r.student_id = s.id
                LEFT JOIN batches b ON s.batch_id = b.id
                LEFT JOIN qr_tokens q ON q.registration_id = r.id
                LEFT JOIN attendance_scans a ON a.qr_token_id = q.id AND a.scan_type = 'entry'
                WHERE r.event_id = ? AND r.status = 'approved'
                ORDER BY s.food_preference ASC, s.name ASC
            ");
            $stmt->execute([$event_id]);
            $raw_data = $stmt->fetchAll(PDO::FETCH_NUM);
            foreach ($raw_data as $row) {
                $filtered_row = [];
                foreach ($selected_indexes as $idx) {
                    $filtered_row[] = $row[$idx];
                }
                $data[] = $filtered_row;
            }
        }

        // Set download headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Output CSV Header
        fputcsv($output, $headers);
        
        // Output CSV rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        
        // Log report export activity
        log_activity($_SESSION['user_id'], 'report_exported', "Exported report '{$type}' to CSV. Event ID: $event_id");
        exit();

    } catch (Exception $e) {
        set_flash_message('danger', 'Export failed: ' . $e->getMessage());
        header("Location: " . BASE_URL . "admin/reports/index.php");
        exit();
    }
}

// Fetch all events for report configuration selection
$events = [];
try {
    $events = $conn->query("SELECT id, name, event_date FROM events ORDER BY event_date ASC")->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch events: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Accolades Connect</title>
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
                <li><a href="<?php echo BASE_URL; ?>admin/certificates/index.php" class="nav-link">Certificates</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/reports/index.php" class="nav-link active">Reports</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/logs/index.php" class="nav-link">Logs</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:12px; height:12px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <div style="margin-bottom: 2.5rem;">
            <h2>Generate Operational Reports</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Export CSV formats for registration queues, food preferences, and attendance history logs.</p>
        </div>

        <?php display_flash_message(); ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
            
            <!-- Student Master Report card -->
            <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                <form method="GET" action="<?php echo BASE_URL; ?>admin/reports/index.php" style="display: flex; flex-direction: column; height: 100%; justify-content: space-between;">
                    <input type="hidden" name="export" value="csv">
                    <input type="hidden" name="type" value="students">
                    <div>
                        <h3 style="display: flex; align-items: center; gap: 0.5rem; color: var(--primary);"><i data-lucide="users"></i> Student Master Report</h3>
                        <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.75rem; margin-bottom: 1.5rem;">
                            Export detailed profile records of all registered students. Choose which details to include below.
                        </p>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                <label class="form-label" style="margin-bottom: 0; font-weight: 600;">Select Details to Include</label>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleSelectAll('students-cols')" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                    Toggle All
                                </button>
                            </div>
                            <div class="students-cols" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.6rem; background: var(--bg-input); padding: 0.85rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; cursor: pointer; color: var(--text-main);">
                                    <input type="checkbox" name="columns[]" value="0" checked> Student Name
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; cursor: pointer; color: var(--text-main);">
                                    <input type="checkbox" name="columns[]" value="1" checked> Email
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; cursor: pointer; color: var(--text-main);">
                                    <input type="checkbox" name="columns[]" value="2" checked> Course
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; cursor: pointer; color: var(--text-main);">
                                    <input type="checkbox" name="columns[]" value="3" checked> Batch
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; cursor: pointer; color: var(--text-main);">
                                    <input type="checkbox" name="columns[]" value="4" checked> Class Roll
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; cursor: pointer; color: var(--text-main);">
                                    <input type="checkbox" name="columns[]" value="5" checked> University Roll
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; cursor: pointer; color: var(--text-main);">
                                    <input type="checkbox" name="columns[]" value="6" checked> Contact No.
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; cursor: pointer; color: var(--text-main);">
                                    <input type="checkbox" name="columns[]" value="7" checked> WhatsApp No.
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; cursor: pointer; color: var(--text-main);">
                                    <input type="checkbox" name="columns[]" value="8" checked> Food Pref.
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; cursor: pointer; color: var(--text-main);">
                                    <input type="checkbox" name="columns[]" value="9" checked> Status
                                </label>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i data-lucide="download"></i> Download CSV Report
                        </button>
                    </div>
                </form>
            </div>

            <!-- Event-specific report settings widget -->
            <div class="card">
                <form id="event-report-form" method="GET" action="<?php echo BASE_URL; ?>admin/reports/index.php" style="display: flex; flex-direction: column; height: 100%;">
                    <input type="hidden" name="export" value="csv">
                    
                    <h3 style="display: flex; align-items: center; gap: 0.5rem; color: var(--accent); margin-bottom: 1rem;"><i data-lucide="calendar"></i> Event Specific Reports</h3>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">
                        Select an event, report type, and choose which details to include in the exported report.
                    </p>

                    <?php if (empty($events)): ?>
                        <p style="color: var(--danger); font-size: 0.85rem;">Please configure at least one event first.</p>
                    <?php else: ?>
                        <div class="form-group">
                            <label class="form-label" for="event-select">Select Target Event</label>
                            <select id="event-select" name="event_id" class="form-control">
                                <?php foreach ($events as $ev): ?>
                                    <option value="<?php echo $ev['id']; ?>" <?php echo $event_id == $ev['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($ev['name']) . ' (' . date('d M Y', strtotime($ev['event_date'])) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="type-select">Select Report Type</label>
                            <select id="type-select" name="type" class="form-control" onchange="updateColumnSelectors(this.value)">
                                <option value="registrations">Registrants & Payments Report</option>
                                <option value="attendance">Checked-in Attendees List</option>
                                <option value="absentees">Absentees List (Approved but Absent)</option>
                                <option value="food">Food Preference Tally List</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                <label class="form-label" style="margin-bottom: 0; font-weight: 600;">Select Details to Include</label>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleSelectAll('event-cols-container')" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                    Toggle All
                                </button>
                            </div>
                            <!-- Column Selectors Container -->
                            <div id="event-cols-container" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.6rem; background: var(--bg-input); padding: 0.85rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                                <!-- Will be dynamically updated by JS based on selected type -->
                            </div>
                        </div>

                        <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                            <button type="submit" class="btn btn-accent" style="width: 100%;">
                                <i data-lucide="download"></i> Download CSV Report
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Script to dynamically build columns based on report type -->
    <script>
        const BASE_URL = "<?php echo BASE_URL; ?>";

        const columnsConfig = {
            registrations: [
                { id: 0, name: 'Student Name' },
                { id: 1, name: 'Course' },
                { id: 2, name: 'Batch' },
                { id: 3, name: 'Class Roll' },
                { id: 4, name: 'University Roll' },
                { id: 5, name: 'Email' },
                { id: 6, name: 'Payment Method' },
                { id: 7, name: 'Payment Status' },
                { id: 8, name: 'Registration Status' },
                { id: 9, name: 'Event Role' },
                { id: 10, name: 'Contact No.' }
            ],
            attendance: [
                { id: 0, name: 'Student Name' },
                { id: 1, name: 'Course' },
                { id: 2, name: 'Batch' },
                { id: 3, name: 'Class Roll' },
                { id: 4, name: 'University Roll' },
                { id: 5, name: 'Email' },
                { id: 6, name: 'Check-in Type' },
                { id: 7, name: 'Scanned Time' },
                { id: 8, name: 'Contact No.' }
            ],
            absentees: [
                { id: 0, name: 'Student Name' },
                { id: 1, name: 'Course' },
                { id: 2, name: 'Batch' },
                { id: 3, name: 'Class Roll' },
                { id: 4, name: 'University Roll' },
                { id: 5, name: 'Email' },
                { id: 6, name: 'Contact No.' },
                { id: 7, name: 'Food Pref.' }
            ],
            food: [
                { id: 0, name: 'Student Name' },
                { id: 1, name: 'Course' },
                { id: 2, name: 'Batch' },
                { id: 3, name: 'Class Roll' },
                { id: 4, name: 'Food Pref.' },
                { id: 5, name: 'Check-in (Present)' },
                { id: 6, name: 'Contact No.' }
            ]
        };

        function updateColumnSelectors(reportType) {
            const container = document.getElementById('event-cols-container');
            if (!container) return;
            
            container.innerHTML = '';
            const cols = columnsConfig[reportType] || [];
            cols.forEach(col => {
                const label = document.createElement('label');
                label.style.display = 'flex';
                label.style.alignItems = 'center';
                label.style.gap = '0.5rem';
                label.style.fontSize = '0.85rem';
                label.style.cursor = 'pointer';
                label.style.color = 'var(--text-main)';
                
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.name = 'columns[]';
                input.value = col.id;
                input.checked = true;
                
                label.appendChild(input);
                label.appendChild(document.createTextNode(' ' + col.name));
                container.appendChild(label);
            });
        }

        function toggleSelectAll(containerClassOrId) {
            const container = document.getElementById(containerClassOrId) || document.querySelector('.' + containerClassOrId);
            if (!container) return;
            const checkboxes = container.querySelectorAll('input[type="checkbox"]');
            if (checkboxes.length === 0) return;
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
        }

        // Initialize selectors on load
        document.addEventListener("DOMContentLoaded", function() {
            const typeSelect = document.getElementById('type-select');
            if (typeSelect) {
                updateColumnSelectors(typeSelect.value);
            }
        });
    </script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
