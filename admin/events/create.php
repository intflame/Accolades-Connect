<?php
/**
 * Admin: Create New Department Event
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Access Control
require_role('admin');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_post_request();

    $event_type = trim($_POST['event_type'] ?? 'unpaid');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $venue = trim($_POST['venue'] ?? '');
    
    if ($event_type === 'paid') {
        $registration_fee = floatval($_POST['registration_fee'] ?? 0.00);
        $upi_payment_enabled = isset($_POST['upi_payment_enabled']) ? 1 : 0;
        $upi_id = trim($_POST['upi_id'] ?? '');
        $cash_payment_enabled = isset($_POST['cash_payment_enabled']) ? 1 : 0;
    } else {
        $registration_fee = 0.00;
        $upi_payment_enabled = 0;
        $upi_id = '';
        $cash_payment_enabled = 0;
    }
    
    $registration_deadline = trim($_POST['registration_deadline'] ?? '');
    $scan_start_time = trim($_POST['scan_start_time'] ?? '');
    $scan_end_time = trim($_POST['scan_end_time'] ?? '');
    $food_enabled = isset($_POST['food_enabled']) ? 1 : 0;
    $status = trim($_POST['status'] ?? 'upcoming');

    // Validations
    if (empty($name)) $errors[] = "Event Name is required.";
    if (empty($event_date)) $errors[] = "Event Date is required.";
    if (empty($venue)) $errors[] = "Venue is required.";
    if (empty($registration_deadline)) $errors[] = "Registration Deadline date & time is required.";
    if (empty($scan_start_time)) $errors[] = "Scanner Start Time is required.";
    if (empty($scan_end_time)) $errors[] = "Scanner End Time is required.";
    
    if ($event_type === 'paid') {
        if ($registration_fee <= 0) {
            $errors[] = "Registration fee must be greater than 0 for paid events.";
        }
        if (!$upi_payment_enabled && !$cash_payment_enabled) {
            $errors[] = "For paid events, at least one payment method (UPI or Cash) must be enabled.";
        }
        if ($upi_payment_enabled && empty($upi_id)) {
            $errors[] = "UPI ID is required if UPI payment is enabled.";
        }
    }

    // Process UPI QR upload if selected
    $upi_qr_filename = null;
    if (empty($errors) && $event_type === 'paid' && $upi_payment_enabled && isset($_FILES['upi_qr_image']) && $_FILES['upi_qr_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_res = handle_file_upload($_FILES['upi_qr_image'], 'upi_qr');
        if ($upload_res['success']) {
            $upi_qr_filename = $upload_res['filename'];
        } else {
            $errors[] = $upload_res['message'];
        }
    }

    // Process Event Banner upload if selected
    $banner_image_filename = null;
    if (empty($errors) && isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_res = handle_file_upload($_FILES['banner_image'], 'event_banners', ['jpg', 'jpeg', 'png', 'webp'], 5242880);
        if ($upload_res['success']) {
            $banner_image_filename = $upload_res['filename'];
        } else {
            $errors[] = $upload_res['message'];
        }
    }

    $canva_template_link = null;
    $certificate_template_filename = null;
    $certificate_template_type = 'full_design';
    $certificate_theme = 'classic_navy'; // default fallback theme
    $certificate_title = 'Certificate of Activity';
    $certificate_coordinator = 'Event Coordinator';
    $certificate_hod = 'Head of Department';

    $layout_config = [
        'name_top' => 48,
        'name_font_size' => 3.2,
        'name_color' => '#b45309',
        'details_top' => 61,
        'details_font_size' => 0.95,
        'details_color' => '#475569',
        'code_top' => 88,
        'code_left' => 50,
        'code_color' => '#0f172a',
        'signatures_enabled' => 0,
        'sig_left_top' => 80,
        'sig_left_left' => 15,
        'sig_right_top' => 80,
        'sig_right_right' => 15,
    ];
    $certificate_layout_config_json = json_encode($layout_config);

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO events (
                    name, description, event_date, venue, registration_fee, registration_deadline, 
                    scan_start_time, scan_end_time, upi_payment_enabled, upi_id, 
                    upi_qr_image, cash_payment_enabled, food_enabled, certificate_template, certificate_template_type,
                    certificate_theme, certificate_title, certificate_coordinator, certificate_hod,
                    certificate_layout_config, canva_template_link, status, banner_image
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $name, $description, $event_date, $venue, $registration_fee, $registration_deadline,
                $scan_start_time, $scan_end_time, $upi_payment_enabled, $upi_id,
                $upi_qr_filename, $cash_payment_enabled, $food_enabled, $certificate_template_filename, $certificate_template_type,
                $certificate_theme, $certificate_title, $certificate_coordinator, $certificate_hod,
                $certificate_layout_config_json, $canva_template_link, $status, $banner_image_filename
            ]);
            
            $event_id = $conn->lastInsertId();
            log_activity($_SESSION['user_id'], 'event_created', "Created event: $name (ID: $event_id)");
            
            set_flash_message('success', "Event '{$name}' created successfully.");
            header("Location: " . BASE_URL . "admin/events/index.php");
            exit();

        } catch (Exception $e) {
            error_log("Failed to create event: " . $e->getMessage());
            $errors[] = "Database operation failed. Make sure dates are formatted correctly.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - Accolades Connect</title>
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
                <li><a href="<?php echo BASE_URL; ?>admin/events/index.php" class="nav-link active">Events</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/payments/index.php" class="nav-link">Payments</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/attendance/index.php" class="nav-link">Attendance</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/scanner_users/index.php" class="nav-link">Scanners</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/certificates/index.php" class="nav-link">Certificates</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/reports/index.php" class="nav-link">Reports</a></li>
                <li><a href="<?php echo BASE_URL; ?>admin/logs/index.php" class="nav-link">Logs</a></li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:12px; height:12px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <div style="max-width: 800px; margin: 0 auto;">
            <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2>Create Department Event</h2>
                    <p style="color: var(--text-muted); font-size: 0.9rem;">Setup event scheduling, payment parameters, and check-in times.</p>
                </div>
                <a href="<?php echo BASE_URL; ?>admin/events/index.php" class="btn btn-secondary btn-sm">
                    <i data-lucide="arrow-left"></i> Back to Events
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger show-alert-anim">
                    <i data-lucide="alert-triangle" class="alert-icon"></i>
                    <div class="alert-content">
                        <ul style="padding-left: 1rem; margin: 0;">
                            <?php foreach ($errors as $err): ?>
                                <li><?php echo e($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <form action="<?php echo BASE_URL; ?>admin/events/create.php" method="POST" enctype="multipart/form-data">
                    <?php csrf_input(); ?>

                    <div class="form-group">
                        <label class="form-label" for="name">Event Name</label>
                        <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Annual Workshop on Machine Learning" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Event Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="Describe the details, agenda, and key notes for this event..." style="resize: vertical; min-height: 80px;"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="banner_image">Event Showcase Banner Image</label>
                        <input type="file" id="banner_image" name="banner_image" class="form-control" accept="image/png, image/jpeg, image/jpg, image/webp" style="padding: 0.4rem;">
                        <small style="color: var(--text-muted); font-size: 0.75rem; display:block; margin-top: 0.25rem;">Optional banner image to display in the events showcase slider. (Allowed formats: JPG, PNG, WEBP. Max size: 5MB)</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="event_date">Event Date</label>
                            <input type="date" id="event_date" name="event_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="venue">Venue</label>
                            <input type="text" id="venue" name="venue" class="form-control" placeholder="e.g. Seminar Hall 1" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="event_type">Event Type</label>
                            <select id="event_type" name="event_type" class="form-control" required onchange="toggleEventType(this.value)">
                                <option value="unpaid" selected>Unpaid / Free Event</option>
                                <option value="paid">Paid Event</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="registration_deadline">Registration Deadline Date & Time</label>
                            <input type="datetime-local" id="registration_deadline" name="registration_deadline" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row" id="fee-row" style="display: none;">
                        <div class="form-group">
                            <label class="form-label" for="registration_fee">Registration Fee (INR)</label>
                            <input type="number" id="registration_fee" name="registration_fee" class="form-control" placeholder="0.00" step="0.01" min="0" value="0.00">
                            <small style="color: var(--text-muted); font-size: 0.75rem; display:block; margin-top: 0.25rem;">Specify the registration fee in INR.</small>
                        </div>
                        <div class="form-group"></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="scan_start_time">QR Scan Start Time</label>
                            <input type="datetime-local" id="scan_start_time" name="scan_start_time" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="scan_end_time">QR Scan End Time</label>
                            <input type="datetime-local" id="scan_end_time" name="scan_end_time" class="form-control" required>
                        </div>
                    </div>

                    <!-- Payment Section (shows only for Paid events) -->
                    <div id="payment-section" style="display: none;">
                        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 2rem 0;">
                        
                        <!-- Payment Toggles -->
                        <div class="form-row" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none;">
                                    <input type="checkbox" name="upi_payment_enabled" id="upi_payment_enabled" value="1" checked onclick="toggleUPIFields(this.checked)">
                                    <strong style="font-size: 0.95rem;">Enable UPI Payments</strong>
                                </label>
                            </div>
                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none;">
                                    <input type="checkbox" name="cash_payment_enabled" id="cash_payment_enabled" value="1" checked>
                                    <strong style="font-size: 0.95rem;">Enable Cash Payments</strong>
                                </label>
                            </div>
                        </div>

                        <!-- UPI ID & UPI QR Code image fields -->
                        <div id="upi-details-container" class="form-row" style="margin-top: 1rem;">
                            <div class="form-group">
                                <label class="form-label" for="upi_id">Department/Coordinator UPI ID</label>
                                <input type="text" id="upi_id" name="upi_id" class="form-control" placeholder="example@okaxis">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="upi_qr_image">Upload UPI QR Scanner Image</label>
                                <input type="file" id="upi_qr_image" name="upi_qr_image" class="form-control" accept="image/png, image/jpeg, image/jpg" style="padding: 0.4rem;">
                                <small style="color: var(--text-muted); font-size: 0.75rem; display:block; margin-top: 0.25rem;">Image generated from UPI app. PNG or JPG.</small>
                            </div>
                        </div>
                    </div>

                    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 2rem 0;">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="status">Initial Event Status</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="upcoming">Upcoming (Listed but Registration Closed)</option>
                                <option value="registration_open" selected>Registration Open (Active Booking)</option>
                                <option value="registration_closed">Registration Closed</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 0.75rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none;">
                                <input type="checkbox" name="food_enabled" value="1" checked>
                                <strong style="font-size: 0.95rem;">Enable Food Preferences Selection</strong>
                            </label>
                        </div>
                    </div>

                    <div style="border-top: 1px solid var(--border-color); margin-top: 2rem; padding-top: 1.5rem;">
                        <h3 style="margin-bottom: 1rem; color: var(--primary);">MAR Certificate Customization</h3>
                        
                        <?php 
                        // Default Canva overlay template coordinates
                        $name_top = 48;
                        $name_font_size = 3.2;
                        $name_color = '#b45309';
                        $details_top = 61;
                        $details_font_size = 0.95;
                        $details_color = '#475569';
                        $code_top = 88;
                        $code_left = 50;
                        $code_color = '#0f172a';
                        $signatures_enabled = 0;
                        $sig_left_top = 80;
                        $sig_left_left = 15;
                        $sig_right_top = 80;
                        $sig_right_right = 15;
                        ?>


                    </div>

                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <a href="<?php echo BASE_URL; ?>admin/events/index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="plus"></i> Create Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleUPIFields(enabled) {
            const container = document.getElementById('upi-details-container');
            const upiId = document.getElementById('upi_id');
            const eventType = document.getElementById('event_type').value;

            if (enabled && eventType === 'paid') {
                container.style.display = 'grid';
                upiId.setAttribute('required', 'required');
            } else {
                container.style.display = 'none';
                upiId.removeAttribute('required');
            }
        }

        function toggleEventType(type) {
            const feeRow = document.getElementById('fee-row');
            const feeInput = document.getElementById('registration_fee');
            const paymentSection = document.getElementById('payment-section');
            const upiPaymentCheckbox = document.getElementById('upi_payment_enabled');

            if (type === 'paid') {
                feeRow.style.display = 'grid';
                feeInput.setAttribute('required', 'required');
                if (feeInput.value === '0.00' || feeInput.value === '0') {
                    feeInput.value = '';
                }
                paymentSection.style.display = 'block';
                toggleUPIFields(upiPaymentCheckbox.checked);
            } else {
                feeRow.style.display = 'none';
                feeInput.removeAttribute('required');
                feeInput.value = '0.00';
                paymentSection.style.display = 'none';
                toggleUPIFields(false);
            }
        }

        // Initialize state on load
        toggleEventType(document.getElementById('event_type').value);
    </script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
