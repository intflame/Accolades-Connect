<?php
/**
 * Public Certificate Verification Portal
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
$cert = null;
$error = null;
$searched = false;

if (!empty($code)) {
    $searched = true;
    try {
        $stmt = $conn->prepare("
            SELECT c.id as certificate_id, c.certificate_code, c.issued_at,
                   r.assigned_role,
                   s.name as student_name, s.course, b.name as batch_name, s.class_roll, s.university_roll,
                   e.name as event_name, e.event_date, e.certificate_title, e.certificate_coordinator, e.certificate_hod
            FROM certificates c
            JOIN event_registrations r ON c.registration_id = r.id
            JOIN students s ON r.student_id = s.id
            LEFT JOIN batches b ON s.batch_id = b.id
            JOIN events e ON r.event_id = e.id
            WHERE c.certificate_code = ?
        ");
        $stmt->execute([$code]);
        $cert = $stmt->fetch();
        if (!$cert) {
            $error = "Certificate with code <strong>" . e($code) . "</strong> could not be found or is invalid.";
        }
    } catch (Exception $e) {
        error_log("Failed to verify certificate: " . $e->getMessage());
        $error = "An error occurred while verifying the certificate. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification - Accolades Connect</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css">
    <style>
        .verify-container {
            max-width: 650px;
            margin: 3rem auto;
            padding: 0 1rem;
        }
        .verify-card {
            background: rgba(17, 24, 39, 0.45);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .verify-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }
        .verify-card.success::before {
            background: linear-gradient(90deg, #10b981, #059669);
        }
        .verify-card.error::before {
            background: linear-gradient(90deg, #ef4444, #dc2626);
        }
        .status-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .status-icon.success {
            background: rgba(16, 185, 129, 0.1);
            color: #34d399;
            border: 2px solid rgba(16, 185, 129, 0.25);
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.15);
        }
        .status-icon.error {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 2px solid rgba(239, 68, 68, 0.25);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.15);
        }
        .status-icon.search {
            background: rgba(248, 123, 27, 0.1);
            color: var(--primary);
            border: 2px solid rgba(248, 123, 27, 0.25);
            box-shadow: 0 0 20px rgba(248, 123, 27, 0.15);
        }
        .verify-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #ffffff;
        }
        .verify-subtitle {
            font-size: 0.95rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
        }
        .details-list {
            text-align: left;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .details-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            align-items: center;
        }
        .details-row:last-child {
            border-bottom: none;
        }
        .details-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .details-value {
            font-size: 0.9rem;
            color: #ffffff;
            font-weight: 600;
            text-align: right;
        }
        .badge-verified {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.5rem;
        }
        .search-form {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        .search-input {
            flex-grow: 1;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            color: #ffffff;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s;
        }
        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(248, 123, 27, 0.2);
        }
    </style>
</head>
<body>

    <!-- Sticky Header Navbar -->
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?php echo BASE_URL; ?>index.php" class="brand">
                <i data-lucide="calendar-check"></i>
                <span>Accolades Connect</span>
            </a>
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>index.php" class="nav-link">Home</a></li>
                <?php if ($user_id): ?>
                    <?php if ($user_role === 'admin'): ?>
                        <li><a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="nav-link">Admin Dashboard</a></li>
                    <?php elseif ($user_role === 'student'): ?>
                        <li><a href="<?php echo BASE_URL; ?>student/dashboard.php" class="nav-link">Student Portal</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:16px; height:16px; display:inline-block; vertical-align:middle;"></i> Logout</a></li>
                <?php else: ?>
                    <li><a href="<?php echo BASE_URL; ?>login.php" class="nav-link">Login</a></li>
                    <li><a href="<?php echo BASE_URL; ?>register.php" class="btn btn-primary btn-sm">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container main-content">
        <div class="verify-container">
            
            <?php if ($searched && $cert): ?>
                <!-- VERIFIED SUCCESS CARD -->
                <div class="verify-card success">
                    <div class="status-icon success">
                        <i data-lucide="shield-check" style="width: 38px; height: 38px;"></i>
                    </div>
                    <h2 class="verify-title">Certificate Verified</h2>
                    <p class="verify-subtitle">This certificate is authentic and officially issued by the Department of Computer Application.</p>
                    
                    <div class="details-list">
                        <div class="details-row">
                            <span class="details-label">Student Name</span>
                            <span class="details-value" style="color: var(--primary); font-size: 1.05rem;"><?php echo e($cert['student_name']); ?></span>
                        </div>
                        <div class="details-row">
                            <span class="details-label">University Roll No.</span>
                            <span class="details-value"><?php echo e($cert['university_roll'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="details-row">
                            <span class="details-label">Class Roll No.</span>
                            <span class="details-value"><?php echo e($cert['class_roll'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="details-row">
                            <span class="details-label">Course & Batch</span>
                            <span class="details-value"><?php echo e($cert['course']); ?> <?php echo $cert['batch_name'] ? '(Batch ' . e($cert['batch_name']) . ')' : ''; ?></span>
                        </div>
                        <div class="details-row">
                            <span class="details-label">Event Name</span>
                            <span class="details-value"><?php echo e($cert['event_name']); ?></span>
                        </div>
                        <div class="details-row">
                            <span class="details-label">Assigned Role</span>
                            <span class="details-value" style="text-transform: capitalize;"><?php echo e(str_replace('OC', 'Organizing Committee', str_replace('CC', 'Co-coordinator', $cert['assigned_role']))); ?></span>
                        </div>
                        <div class="details-row">
                            <span class="details-label">Event Date</span>
                            <span class="details-value"><?php echo date('F d, Y', strtotime($cert['event_date'])); ?></span>
                        </div>
                        <div class="details-row">
                            <span class="details-label">Verification Code</span>
                            <span class="details-value" style="font-family: monospace; font-size: 0.95rem; letter-spacing: 0.05em; color: var(--accent);"><?php echo e($cert['certificate_code']); ?></span>
                        </div>
                        <div class="details-row">
                            <span class="details-label">Issued On</span>
                            <span class="details-value"><?php echo date('M d, Y', strtotime($cert['issued_at'])); ?></span>
                        </div>
                        <div class="details-row">
                            <span class="details-label">Signing Authorities</span>
                            <span class="details-value" style="font-size: 0.8rem; line-height: 1.4;">
                                <?php echo e($cert['certificate_coordinator'] ?: 'Event Coordinator'); ?><br>
                                <?php echo e($cert['certificate_hod'] ?: 'Head of Department'); ?>
                            </span>
                        </div>
                    </div>
                    
                    <a href="<?php echo BASE_URL; ?>verify.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 0.4rem;">
                        <i data-lucide="search" style="width: 14px; height: 14px;"></i> Verify Another Certificate
                    </a>
                </div>

            <?php elseif ($searched && $error): ?>
                <!-- INVALID CODE CARD -->
                <div class="verify-card error">
                    <div class="status-icon error">
                        <i data-lucide="shield-alert" style="width: 38px; height: 38px;"></i>
                    </div>
                    <h2 class="verify-title">Verification Failed</h2>
                    <p class="verify-subtitle"><?php echo $error; ?></p>
                    
                    <form action="<?php echo BASE_URL; ?>verify.php" method="GET" style="margin-top: 2rem;">
                        <label class="form-label" style="text-align: left; display: block; font-size: 0.85rem; margin-bottom: 0.5rem;">Enter Verification Code</label>
                        <div class="search-form">
                            <input type="text" name="code" class="search-input" placeholder="e.g. MAR-E1-S123" required>
                            <button type="submit" class="btn btn-primary">Lookup</button>
                        </div>
                    </form>
                    
                    <div style="margin-top: 1.5rem; text-align: left; font-size: 0.8rem; color: var(--text-muted); background: rgba(0,0,0,0.1); padding: 1rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.02);">
                        <strong>Need help?</strong> Verification codes are printed at the bottom or overlayed as a QR code on department-issued certificates. Please verify that the code matches the formatting (letters, numbers, hyphens) precisely.
                    </div>
                </div>

            <?php else: ?>
                <!-- EMPTY / SEARCH CARD -->
                <div class="verify-card">
                    <div class="status-icon search">
                        <i data-lucide="shield" style="width: 38px; height: 38px;"></i>
                    </div>
                    <h2 class="verify-title">Certificate Verification</h2>
                    <p class="verify-subtitle">Enter the certificate verification code to verify the authenticity of a student's MAR activities credentials.</p>
                    
                    <form action="<?php echo BASE_URL; ?>verify.php" method="GET">
                        <div style="text-align: left; margin-bottom: 1.5rem;">
                            <label class="form-label" style="font-size: 0.85rem; margin-bottom: 0.5rem; display:block;">Verification Code</label>
                            <input type="text" name="code" class="search-input" placeholder="Enter verification code (e.g. MAR-E...)" style="width:100%; box-sizing: border-box;" required>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem;">
                            <i data-lucide="search" style="width: 16px; height: 16px; display:inline-block; vertical-align:middle; margin-right: 0.25rem;"></i> Search Database
                        </button>
                    </form>
                </div>
            <?php endif; ?>

        </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
