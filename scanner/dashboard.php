<?php
/**
 * Scanner Dashboard
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Access Control - Allow scanner and admin to access scanning utilities
require_role(['scanner', 'admin']);

// Fetch list of events that are upcoming, active or recently completed
$events = [];
try {
    $stmt = $conn->query("
        SELECT id, name, event_date, venue, scan_start_time, scan_end_time, food_enabled 
        FROM events 
        WHERE status NOT IN ('cancelled') 
        ORDER BY event_date ASC
    ");
    $events = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch scanner events list: " . $e->getMessage());
}

// Fetch scan count for this user today
$scans_today_count = 0;
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM attendance_scans 
        WHERE scanned_by = ? AND DATE(scanned_at) = CURDATE()
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $res = $stmt->fetch();
    $scans_today_count = $res['count'] ?? 0;
} catch (Exception $e) {
    error_log("Failed to fetch scan count: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner Dashboard - Accolades Connect</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?php echo BASE_URL; ?>index.php" class="brand">
                <i data-lucide="scan-face"></i>
                <span>Accolades Connect - Scanner</span>
            </a>
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>scanner/dashboard.php" class="nav-link active">Dashboard</a></li>
                <li><a href="<?php echo BASE_URL; ?>scanner/scan_history.php" class="nav-link">Scan History</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:14px; height:14px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <header style="margin-bottom: 2rem;">
            <h1 style="font-size: 2rem; margin-bottom: 0.25rem;">Scanner Dashboard</h1>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Select an event below to begin camera-based check-in verification.</p>
        </header>

        <?php display_flash_message(); ?>

        <!-- Quick Stats -->
        <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(6, 182, 212, 0.1); color: var(--accent);">
                    <i data-lucide="scan"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $scans_today_count; ?></div>
                    <div class="stat-label">Your Check-ins Today</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                    <i data-lucide="users"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo count($events); ?></div>
                    <div class="stat-label">Total Active/Upcoming Events</div>
                </div>
            </div>
        </div>

        <!-- Start Scan Control -->
        <div class="card" style="margin-top: 2rem; max-width: 650px;">
            <div class="card-header">
                <h3>Launch Ticket Scanner</h3>
            </div>
            
            <?php if (empty($events)): ?>
                <p style="color: var(--text-muted); padding: 1rem 0;">No active events available for scanning.</p>
            <?php else: ?>
                <form action="<?php echo BASE_URL; ?>scanner/scan.php" method="GET">
                    <div class="form-group">
                        <label class="form-label" for="event_id">Select Target Event</label>
                        <select id="event_id" name="event_id" class="form-control" required>
                            <option value="" disabled selected>-- Choose Event --</option>
                            <?php foreach ($events as $event): ?>
                                <?php 
                                    $date_str = date('M d, Y', strtotime($event['event_date']));
                                    $is_today = (date('Y-m-d', strtotime($event['event_date'])) === date('Y-m-d'));
                                    $label_suffix = $is_today ? ' (TODAY)' : '';
                                ?>
                                <option value="<?php echo $event['id']; ?>" data-food-enabled="<?php echo $event['food_enabled'] ? '1' : '0'; ?>">
                                    <?php echo e($event['name']) . ' - ' . $date_str . $label_suffix; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="scan_type">Check-in Scan Type</label>
                        <select id="scan_type" name="scan_type" class="form-control" required>
                            <option value="entry" selected>Entry Scan (Primary Attendance)</option>
                            <option value="food">Food Preference Scan (Veg/Non-Veg Check)</option>
                            <option value="exit">Exit Scan</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        <i data-lucide="camera"></i> Open Camera Scanner
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const eventSelect = document.getElementById('event_id');
            const scanTypeSelect = document.getElementById('scan_type');
            
            if (eventSelect && scanTypeSelect) {
                // Keep a reference to the food option in case we need to re-add it
                const foodOption = scanTypeSelect.querySelector('option[value="food"]');
                
                function updateScanTypes() {
                    const selectedOption = eventSelect.options[eventSelect.selectedIndex];
                    if (!selectedOption || selectedOption.value === "") {
                        // If no event is selected, keep the option visible or reset
                        if (!scanTypeSelect.querySelector('option[value="food"]') && foodOption) {
                            scanTypeSelect.insertBefore(foodOption, scanTypeSelect.options[2] || null);
                        }
                        return;
                    }
                    
                    const foodEnabled = selectedOption.getAttribute('data-food-enabled') === '1';
                    
                    if (foodEnabled) {
                        // Show/ensure food option exists
                        if (!scanTypeSelect.querySelector('option[value="food"]') && foodOption) {
                            // Insert it at index 1
                            scanTypeSelect.insertBefore(foodOption, scanTypeSelect.options[1]);
                        }
                    } else {
                        // Hide/remove food option
                        const currentFoodOption = scanTypeSelect.querySelector('option[value="food"]');
                        if (currentFoodOption) {
                            if (scanTypeSelect.value === 'food') {
                                scanTypeSelect.value = 'entry'; // fallback to entry scan
                            }
                            currentFoodOption.remove();
                        }
                    }
                }
                
                eventSelect.addEventListener('change', updateScanTypes);
                // Run once on load/initial state
                updateScanTypes();
            }
        });
    </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
