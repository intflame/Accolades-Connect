<?php
/**
 * Real-time Camera QR Scanner
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Access Control
require_role(['scanner', 'admin']);

$event_id = intval($_GET['event_id'] ?? 0);
$scan_type = trim($_GET['scan_type'] ?? 'entry');

if (!in_array($scan_type, ['entry', 'food', 'exit'])) {
    $scan_type = 'entry';
}

$event = null;
try {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
} catch (Exception $e) {
    error_log("Scanner failed to fetch event: " . $e->getMessage());
}

if (!$event) {
    set_flash_message('danger', 'Selected event was not found.');
    header("Location: " . BASE_URL . "scanner/dashboard.php");
    exit();
}

if ($scan_type === 'food' && !$event['food_enabled']) {
    set_flash_message('danger', 'Food scanning is not enabled for this event.');
    header("Location: " . BASE_URL . "scanner/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camera QR Scanner - Accolades Connect</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css">
    <!-- Load html5-qrcode browser scanner library -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?php echo BASE_URL; ?>scanner/dashboard.php" class="brand">
                <i data-lucide="scan-face"></i>
                <span>Scanner Panel</span>
            </a>
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>scanner/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="<?php echo BASE_URL; ?>scanner/scan_history.php" class="nav-link">Scan History</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:14px; height:14px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <div style="max-width: 600px; margin: 0 auto;">
            <!-- Event & Session Info Card -->
            <div style="margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                <a href="<?php echo BASE_URL; ?>scanner/dashboard.php" class="btn btn-secondary btn-sm">
                    <i data-lucide="arrow-left"></i> Change Event
                </a>
                <span class="badge badge-active" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; text-transform: uppercase;">
                    Type: <?php echo e($scan_type); ?> SCAN
                </span>
            </div>

            <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
                <h3 style="font-size: 1.2rem; margin-bottom: 0.25rem;"><?php echo e($event['name']); ?></h3>
                <div style="font-size: 0.85rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 0.25rem;">
                    <div><i data-lucide="map-pin" style="width:12px; height:12px; display:inline-block; vertical-align:middle;"></i> <?php echo e($event['venue']); ?></div>
                    <div><i data-lucide="clock" style="width:12px; height:12px; display:inline-block; vertical-align:middle;"></i> Scan Window: <?php echo date('h:i A', strtotime($event['scan_start_time'])); ?> - <?php echo date('h:i A', strtotime($event['scan_end_time'])); ?></div>
                </div>
            </div>

            <!-- Scanner Viewport and Visual Laser Line -->
            <div class="scanner-viewport">
                <div class="scanner-laser"></div>
                <div id="reader" style="width: 100%; height: 100%; border: none;"></div>
            </div>

            <!-- Control buttons -->
            <div style="display: flex; gap: 1rem; justify-content: center; margin-bottom: 1.5rem;">
                <button id="btn-toggle-scan" class="btn btn-danger" onclick="toggleScanner()" style="width: 100%;">
                    <i data-lucide="video-off"></i> Pause Camera
                </button>
            </div>

            <!-- Dynamic Result/Status Display Panel -->
            <div id="scan-result-container" style="display: none;" class="show-alert-anim">
                <div id="scan-result-card" class="alert">
                    <i id="scan-result-icon" data-lucide="info" class="alert-icon"></i>
                    <div class="alert-content">
                        <strong id="scan-result-title" style="display: block; font-size: 1rem; margin-bottom: 0.25rem;">Checking...</strong>
                        <span id="scan-result-message">Processing scanned ticket code.</span>
                    </div>
                </div>
            </div>

            <!-- Hidden inputs to supply token processing -->
            <input type="hidden" id="event_id" value="<?php echo $event_id; ?>">
            <input type="hidden" id="scan_type" value="<?php echo $scan_type; ?>">
        </div>
    </div>

    <!-- Scanner script logic -->
    <script>
        const BASE_URL = "<?php echo BASE_URL; ?>";
        let html5QrcodeScanner = null;
        let isScanning = false;
        let lastScannedToken = "";
        let scanCooldown = false;

        document.addEventListener("DOMContentLoaded", function() {
            startScanning();
        });

        function startScanning() {
            if (scanCooldown) return;
            
            isScanning = true;
            document.getElementById('btn-toggle-scan').innerHTML = '<i data-lucide="video-off"></i> Pause Camera';
            document.getElementById('btn-toggle-scan').className = 'btn btn-danger';
            if (window.lucide) window.lucide.createIcons();

            // Initialize scanner
            html5QrcodeScanner = new Html5Qrcode("reader");
            
            const config = { 
                fps: 10, 
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0 
            };

            // Start QR Scanner on back camera by default
            html5QrcodeScanner.start(
                { facingMode: "environment" }, 
                config, 
                onScanSuccess
            ).catch(err => {
                console.error("Camera access error:", err);
                showLocalError("Camera Error", "Could not start camera. Make sure website has camera permissions.");
            });
        }

        function stopScanning() {
            isScanning = false;
            document.getElementById('btn-toggle-scan').innerHTML = '<i data-lucide="video"></i> Resume Camera';
            document.getElementById('btn-toggle-scan').className = 'btn btn-success';
            if (window.lucide) window.lucide.createIcons();

            if (html5QrcodeScanner) {
                html5QrcodeScanner.stop().then(() => {
                    html5QrcodeScanner = null;
                }).catch(err => {
                    console.error("Failed to stop scanner: ", err);
                });
            }
        }

        function toggleScanner() {
            if (isScanning) {
                stopScanning();
            } else {
                startScanning();
            }
        }

        function onScanSuccess(decodedText, decodedResult) {
            // Check cooldown (prevents rapid multi-scanning of same QR)
            if (scanCooldown) return;
            
            // Text inside QR code is the secure token string
            const scannedToken = decodedText.trim();
            
            // Trigger API post request
            processScannedToken(scannedToken);
        }

        function processScannedToken(token) {
            scanCooldown = true;
            
            // Visual feedback - Show loading state
            showLoadingState();

            // Vibrate device briefly (if supported)
            if (navigator.vibrate) {
                navigator.vibrate(100);
            }

            const eventId = document.getElementById('event_id').value;
            const scanType = document.getElementById('scan_type').value;

            // Make POST request to mark_attendance.php API
            fetch(BASE_URL + 'scanner/mark_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'token': token,
                    'event_id': eventId,
                    'scan_type': scanType
                })
            })
            .then(response => response.json())
            .then(data => {
                displayScanResult(data);
                
                // Set cooldown: Wait 3 seconds before next scan is active
                setTimeout(() => {
                    scanCooldown = false;
                }, 3000);
            })
            .catch(error => {
                console.error("Fetch Scan API error:", error);
                showLocalError("Connection Error", "Failed to communicate with database server.");
                scanCooldown = false;
            });
        }

        function showLoadingState() {
            const container = document.getElementById('scan-result-container');
            const alertCard = document.getElementById('scan-result-card');
            const title = document.getElementById('scan-result-title');
            const message = document.getElementById('scan-result-message');
            const icon = document.getElementById('scan-result-icon');

            container.style.display = 'block';
            alertCard.className = 'alert alert-info';
            title.textContent = 'Processing Code...';
            message.textContent = 'Verifying database records.';
            icon.setAttribute('data-lucide', 'loader');
            if (window.lucide) window.lucide.createIcons();
        }

        function displayScanResult(res) {
            const container = document.getElementById('scan-result-container');
            const alertCard = document.getElementById('scan-result-card');
            const title = document.getElementById('scan-result-title');
            const message = document.getElementById('scan-result-message');
            const icon = document.getElementById('scan-result-icon');

            container.style.display = 'block';
            
            // Map JSON status to alert types
            if (res.status === 'success') {
                alertCard.className = 'alert alert-success';
                
                let roleText = '';
                if (res.student_role && res.student_role !== 'participant') {
                    if (res.student_role === 'volunteers') roleText = ' (Volunteer)';
                    else if (res.student_role === 'OC') roleText = ' (OC)';
                    else if (res.student_role === 'CC') roleText = ' (CC)';
                }
                title.textContent = 'Present: ' + res.student_name + roleText;
                
                let details = `Roll: ${res.student_roll} | Batch: ${res.student_batch}`;
                if (res.food_pref) {
                    details += ` | Food: ${res.food_pref.toUpperCase()}`;
                }
                message.innerHTML = `${details}<br><strong style="color: #ffffff;">${res.message}</strong>`;
                icon.setAttribute('data-lucide', 'check-circle');
                
                // Play Success sound
                playAudioNotification(true);
            } else {
                // error, warning
                alertCard.className = res.status === 'warning' ? 'alert alert-warning' : 'alert alert-danger';
                
                if (res.status === 'warning' && res.student_name) {
                    let roleText = '';
                    if (res.student_role && res.student_role !== 'participant') {
                        if (res.student_role === 'volunteers') roleText = ' (Volunteer)';
                        else if (res.student_role === 'OC') roleText = ' (OC)';
                        else if (res.student_role === 'CC') roleText = ' (CC)';
                    }
                    title.textContent = 'Warning: ' + res.student_name + roleText;
                    message.innerHTML = `Roll: ${res.student_roll} | Batch: ${res.student_batch}<br><strong style="color: #ffffff;">${res.message}</strong>`;
                } else {
                    title.textContent = 'Invalid Ticket';
                    message.textContent = res.message;
                }
                icon.setAttribute('data-lucide', 'alert-triangle');
                
                // Play Fail sound
                playAudioNotification(false);
            }
            if (window.lucide) window.lucide.createIcons();
        }

        function showLocalError(errorTitle, errorMessage) {
            const container = document.getElementById('scan-result-container');
            const alertCard = document.getElementById('scan-result-card');
            const title = document.getElementById('scan-result-title');
            const message = document.getElementById('scan-result-message');
            const icon = document.getElementById('scan-result-icon');

            container.style.display = 'block';
            alertCard.className = 'alert alert-danger';
            title.textContent = errorTitle;
            message.textContent = errorMessage;
            icon.setAttribute('data-lucide', 'alert-triangle');
            if (window.lucide) window.lucide.createIcons();
        }

        // Web Audio API to play a premium synth beep/boop notification sound based on scan result!
        function playAudioNotification(isSuccess) {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                
                if (isSuccess) {
                    // Quick high double beep for success
                    oscillator.type = 'sine';
                    oscillator.frequency.setValueAtTime(660, audioCtx.currentTime); // Mi
                    gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
                    oscillator.start();
                    oscillator.stop(audioCtx.currentTime + 0.08);
                    
                    setTimeout(() => {
                        const osc2 = audioCtx.createOscillator();
                        const gain2 = audioCtx.createGain();
                        osc2.connect(gain2);
                        gain2.connect(audioCtx.destination);
                        osc2.type = 'sine';
                        osc2.frequency.setValueAtTime(880, audioCtx.currentTime); // La
                        gain2.gain.setValueAtTime(0.1, audioCtx.currentTime);
                        osc2.start();
                        osc2.stop(audioCtx.currentTime + 0.12);
                    }, 100);
                } else {
                    // Deep error buzz
                    oscillator.type = 'sawtooth';
                    oscillator.frequency.setValueAtTime(150, audioCtx.currentTime);
                    gainNode.gain.setValueAtTime(0.15, audioCtx.currentTime);
                    oscillator.start();
                    oscillator.stop(audioCtx.currentTime + 0.35);
                }
            } catch (err) {
                console.log("Audio not supported or blocked by browser policy.");
            }
        }
    </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
