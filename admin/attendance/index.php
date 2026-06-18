<?php
/**
 * Admin: Real-time Attendance Logs & Reports
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Access Control
require_role('admin');

// Fetch all events for filter
$events = [];
try {
    $events = $conn->query("SELECT id, name, event_date FROM events ORDER BY event_date ASC")->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch events: " . $e->getMessage());
}

// Fetch Batches for filters
$batches = [];
try {
    $batches = $conn->query("SELECT * FROM batches ORDER BY name DESC")->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch batches: " . $e->getMessage());
}

// Active event selection
$event_id = intval($_GET['event_id'] ?? ($events[0]['id'] ?? 0));
$batch_filter = intval($_GET['batch_id'] ?? 0);
$food_filter = trim($_GET['food_preference'] ?? '');
$attendance_filter = trim($_GET['attendance_status'] ?? ''); // 'present', 'absent'
$search = trim($_GET['search'] ?? '');

$event_details = null;
if ($event_id > 0) {
    foreach ($events as $ev) {
        if (intval($ev['id']) === $event_id) {
            $event_details = $ev;
            break;
        }
    }
}

// Build Query
$query_str = "
    SELECT s.id as student_id, s.name as student_name, s.class_roll, s.university_roll, s.food_preference, s.course,
           b.name as batch_name,
           r.id as reg_id, r.status as reg_status,
           a.id as scan_id, a.scan_type, a.scanned_at
    FROM event_registrations r
    JOIN students s ON r.student_id = s.id
    LEFT JOIN batches b ON s.batch_id = b.id
    LEFT JOIN qr_tokens q ON q.registration_id = r.id
    LEFT JOIN attendance_scans a ON a.qr_token_id = q.id AND a.scan_type = 'entry'
    WHERE r.event_id = ? AND r.status = 'approved'
";
$params = [$event_id];

if (!empty($search)) {
    $query_str .= " AND (s.name LIKE ? OR s.class_roll LIKE ? OR s.university_roll LIKE ?)";
    $search_val = "%$search%";
    $params = array_merge($params, [$search_val, $search_val, $search_val]);
}

if ($batch_filter > 0) {
    $query_str .= " AND s.batch_id = ?";
    $params[] = $batch_filter;
}

if (!empty($food_filter)) {
    $query_str .= " AND s.food_preference = ?";
    $params[] = $food_filter;
}

if ($attendance_filter === 'present') {
    $query_str .= " AND a.id IS NOT NULL";
} elseif ($attendance_filter === 'absent') {
    $query_str .= " AND a.id IS NULL";
}

$query_str .= " ORDER BY s.name ASC";

$attendees = [];
$stats = [
    'approved' => 0,
    'present' => 0,
    'absent' => 0,
    'veg' => 0,
    'nonveg' => 0
];

if ($event_id > 0) {
    try {
        // Fetch matching records
        $stmt = $conn->prepare($query_str);
        $stmt->execute($params);
        $attendees = $stmt->fetchAll();

        // Calculate event totals (unfiltered stat calculations)
        $stat_stmt = $conn->prepare("
            SELECT 
                COUNT(r.id) as approved_count,
                SUM(CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN a.id IS NULL THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN s.food_preference = 'veg' THEN 1 ELSE 0 END) as veg_count,
                SUM(CASE WHEN s.food_preference = 'non-veg' THEN 1 ELSE 0 END) as nonveg_count
            FROM event_registrations r
            JOIN students s ON r.student_id = s.id
            LEFT JOIN qr_tokens q ON q.registration_id = r.id
            LEFT JOIN attendance_scans a ON a.qr_token_id = q.id AND a.scan_type = 'entry'
            WHERE r.event_id = ? AND r.status = 'approved'
        ");
        $stat_stmt->execute([$event_id]);
        $res = $stat_stmt->fetch();
        
        $stats['approved'] = $res['approved_count'] ?? 0;
        $stats['present'] = $res['present_count'] ?? 0;
        $stats['absent'] = $res['absent_count'] ?? 0;
        $stats['veg'] = $res['veg_count'] ?? 0;
        $stats['nonveg'] = $res['nonveg_count'] ?? 0;

    } catch (Exception $e) {
        error_log("Failed to fetch attendance logs: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Attendance - Accolades Connect</title>
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
                <li><a href="<?php echo BASE_URL; ?>admin/attendance/index.php" class="nav-link active">Attendance</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/scanner_users/index.php" class="nav-link">Scanners</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/certificates/index.php" class="nav-link">Certificates</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/reports/index.php" class="nav-link">Reports</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/logs/index.php" class="nav-link">Logs</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:12px; height:12px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <h2>Real-time Event Attendance Logs</h2>
                <p style="color: var(--text-muted); font-size: 0.95rem;">Track live student entries, check food tallies, and filter absentees list.</p>
            </div>
            
            <!-- Link to Reports Export page -->
            <a href="<?php echo BASE_URL; ?>admin/reports/index.php?event_id=<?php echo $event_id; ?>" class="btn btn-secondary btn-sm">
                <i data-lucide="file-text"></i> Export Reports
            </a>
        </div>

        <!-- Filter Selector Card -->
        <div class="card" style="padding: 1.25rem; margin-bottom: 2rem;">
            <form action="<?php echo BASE_URL; ?>admin/attendance/index.php" method="GET" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 0.75rem; align-items: flex-end;">
                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="event_id">Select Event</label>
                    <select id="event_id" name="event_id" class="form-control" onchange="this.form.submit()">
                        <?php foreach ($events as $ev): ?>
                            <option value="<?php echo $ev['id']; ?>" <?php echo $event_id == $ev['id'] ? 'selected' : ''; ?>>
                                <?php echo e($ev['name']) . ' (' . date('d M Y', strtotime($ev['event_date'])) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="batch_id">Batch</label>
                    <select id="batch_id" name="batch_id" class="form-control">
                        <option value="0">All Batches</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo $batch['id']; ?>" <?php echo $batch_filter == $batch['id'] ? 'selected' : ''; ?>>
                                Batch <?php echo e($batch['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="food_preference">Food Preference</label>
                    <select id="food_preference" name="food_preference" class="form-control">
                        <option value="">All Preferences</option>
                        <option value="veg" <?php echo $food_filter === 'veg' ? 'selected' : ''; ?>>Veg</option>
                        <option value="non-veg" <?php echo $food_filter === 'non-veg' ? 'selected' : ''; ?>>Non-Veg</option>
                    </select>
                </div>

                <div class="form-group" style="margin:0;">
                    <label class="form-label" for="attendance_status">Attendance</label>
                    <select id="attendance_status" name="attendance_status" class="form-control">
                        <option value="">All Students</option>
                        <option value="present" <?php echo $attendance_filter === 'present' ? 'selected' : ''; ?>>Present (Checked-in)</option>
                        <option value="absent" <?php echo $attendance_filter === 'absent' ? 'selected' : ''; ?>>Absent</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.25rem;">
                    Apply
                </button>
            </form>
        </div>

        <?php if ($event_id > 0 && $event_details): ?>
            <!-- Event Statistics Badges -->
            <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 2rem;">
                <div class="stat-card" style="padding:1rem;">
                    <div class="stat-info">
                        <div class="stat-value" style="font-size: 1.5rem;"><?php echo $stats['approved']; ?></div>
                        <div class="stat-label">Approved Students</div>
                    </div>
                </div>
                <div class="stat-card" style="padding:1rem; border-color: var(--success);">
                    <div class="stat-info">
                        <div class="stat-value" style="font-size: 1.5rem; color: var(--success);"><?php echo $stats['present']; ?></div>
                        <div class="stat-label">Checked-In (Present)</div>
                    </div>
                </div>
                <div class="stat-card" style="padding:1rem; border-color: var(--danger);">
                    <div class="stat-info">
                        <div class="stat-value" style="font-size: 1.5rem; color: var(--danger);"><?php echo $stats['absent']; ?></div>
                        <div class="stat-label">Absent (Pending)</div>
                    </div>
                </div>
                <div class="stat-card" style="padding:1rem; border-color: var(--accent);">
                    <div class="stat-info">
                        <div class="stat-value" style="font-size: 1.5rem; color: var(--accent);">
                            Veg: <?php echo $stats['veg']; ?> | Non: <?php echo $stats['nonveg']; ?>
                        </div>
                        <div class="stat-label">Approved Food Ratio</div>
                    </div>
                </div>
            </div>

            <!-- Attendance Data Table -->
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>Attendance List (<?php echo count($attendees); ?> rows matching filters)</h3>
                    <div style="font-size: 0.85rem; color: var(--text-muted);">Event date: <?php echo date('M d, Y', strtotime($event_details['event_date'])); ?></div>
                </div>

                <?php if (empty($attendees)): ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 3rem 0;">No student records match the select filters.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Roll & Batch</th>
                                    <th>Food Preference</th>
                                    <th>Check-in Status</th>
                                    <th>Scan Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendees as $row): ?>
                                    <tr style="<?php echo $row['scan_id'] ? '' : 'opacity: 0.7;'; ?>">
                                        <td style="font-weight: 600;"><?php echo e($row['student_name']); ?></td>
                                        <td>
                                            <div style="font-weight: 500;"><?php echo e($row['course'] ?? 'BCA'); ?> - Batch <?php echo e($row['batch_name'] ?? 'N/A'); ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);">Roll: <?php echo e($row['class_roll']); ?></div>
                                        </td>
                                        <td>
                                            <span style="text-transform: capitalize; font-size: 0.85rem; padding: 0.15rem 0.5rem; border-radius: 4px; background: <?php echo $row['food_preference'] === 'veg' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(244, 63, 94, 0.1)'; ?>; color: <?php echo $row['food_preference'] === 'veg' ? 'var(--success)' : 'var(--danger)'; ?>;">
                                                <?php echo e($row['food_preference']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($row['scan_id']): ?>
                                                <span class="badge badge-approved" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                                    <i data-lucide="check" style="width:10px; height:10px;"></i> PRESENT
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-rejected" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                                    <i data-lucide="x" style="width:10px; height:10px;"></i> ABSENT
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $row['scanned_at'] ? date('d M Y, h:i A', strtotime($row['scanned_at'])) : '<span style="color: var(--text-muted);">N/A</span>'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card" style="text-align:center; padding: 3rem;">
                <p style="color: var(--text-muted);">No events are currently configured. Please create an event first.</p>
            </div>
        <?php endif; ?>
    </div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
