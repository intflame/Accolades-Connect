<?php
/**
 * Student Registration Form
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/mailer.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Fetch Batches for dropdown
$batches = [];
try {
    $stmt = $conn->query("SELECT * FROM batches ORDER BY name DESC");
    $batches = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch batches: " . $e->getMessage());
}

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    verify_csrf_post_request();

    // Sanitize inputs
    $name = trim($_POST['name'] ?? '');
    $course = trim($_POST['course'] ?? 'BCA');
    $batch_id = trim($_POST['batch_id'] ?? '');
    $class_roll = trim($_POST['class_roll'] ?? '');
    $university_roll = trim($_POST['university_roll'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $food_preference = trim($_POST['food_preference'] ?? 'veg');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!in_array($course, ['BCA', 'MCA'])) {
        $course = 'BCA';
    }

    // Hold old input
    $old = $_POST;

    // Validations
    if (empty($name)) $errors[] = "Full Name is required.";
    
    // Fetch batch name and extract admission year (last 2 digits of the start year, e.g. "2023" -> "23")
    $admission_year = '23'; // default fallback
    $batch_name = '';
    if (empty($batch_id)) {
        $errors[] = "Academic Batch is required.";
    } else {
        try {
            $b_stmt = $conn->prepare("SELECT name FROM batches WHERE id = ?");
            $b_stmt->execute([$batch_id]);
            $batch_name = $b_stmt->fetchColumn();
            if ($batch_name) {
                if (preg_match('/^(\d{4})/', $batch_name, $matches)) {
                    $admission_year = substr($matches[1], -2);
                }
                
                // Parse duration
                $parts = explode('-', $batch_name);
                $duration = 0;
                if (count($parts) === 2) {
                    $duration = (int)$parts[1] - (int)$parts[0];
                }
                
                if ($course === 'MCA' && $duration !== 2) {
                    $errors[] = "MCA course is only allowed for batches with a 2-year duration (e.g. 2024-2026).";
                } elseif ($course === 'BCA' && $duration === 2) {
                    $errors[] = "BCA course is not allowed for 2-year duration batches.";
                }
            }
        } catch (Exception $e) {
            error_log("Failed to fetch batch name: " . $e->getMessage());
        }
    }

    // Validate Class Roll (format: 23BCA068 or 23MCA068)
    if (empty($class_roll)) {
        $errors[] = "Class Roll Number is required.";
    } else {
        $class_roll = strtoupper(trim($class_roll));
        $roll_regex = '/^' . $admission_year . $course . '\d{3}$/';
        if (!preg_match($roll_regex, $class_roll)) {
            $expected_roll_pattern = $admission_year . $course . "[roll_number]";
            $errors[] = "Invalid Class Roll format. It must follow the pattern: '{$expected_roll_pattern}' (e.g. {$admission_year}{$course}068).";
        }
    }

    // Validate University Roll (starts with 294 or 148, e.g. 14800122045)
    if (empty($university_roll)) {
        $errors[] = "University Roll Number is required.";
    } else {
        $university_roll = trim($university_roll);
        if (!preg_match('/^(294|148)\d+$/', $university_roll)) {
            $errors[] = "Invalid University Roll format. It must start with either '294' or '148' and contain digits only.";
        }
    }
    if (empty($contact_number)) $errors[] = "Contact Number is required.";
    if (empty($whatsapp_number)) $errors[] = "WhatsApp Number is required.";
    
    // Email validation
    if (empty($email)) {
        $errors[] = "Email is required.";
    } else {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
    }

    // Password validation
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Password confirmation does not match.";
    }

    // Food Preference check
    if (!in_array($food_preference, ['veg', 'non-veg'])) {
        $food_preference = 'veg';
    }

    if (empty($errors)) {
        try {
            // Check if email already exists (excluding admin users)
            $stmt = $conn->prepare("SELECT id, role, status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existing_user = $stmt->fetch();

            if ($existing_user && $existing_user['role'] !== 'admin') {
                if ($existing_user['status'] === 'pending_otp') {
                    // Delete unverified user to allow them to register again
                    $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $del_stmt->execute([$existing_user['id']]);
                    $existing_user = null;
                }
            }

            if ($existing_user && $existing_user['role'] !== 'admin') {
                $errors[] = "An account with this email already exists. Try logging in.";
            } else {
                // Begin Transaction to ensure all updates/inserts complete or fail together
                $conn->beginTransaction();

                // Generate OTP and prepare password hash
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $otp = generate_otp();
                $otp_expiry = date('Y-m-d H:i:s', strtotime('+2 minutes'));

                if ($existing_user && $existing_user['role'] === 'admin') {
                    // Update existing admin user to become a student
                    $stmt = $conn->prepare("
                        UPDATE users 
                        SET password = ?, role = 'student', status = 'pending_otp', otp_code = ?, otp_expiry = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$hashed_password, $otp, $otp_expiry, $existing_user['id']]);
                    $user_id = $existing_user['id'];

                    // Check if student profile already exists (just in case)
                    $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    if ($stmt->fetch()) {
                        // Update student profile
                        $stmt = $conn->prepare("
                            UPDATE students 
                            SET name = ?, course = ?, batch_id = ?, class_roll = ?, university_roll = ?, contact_number = ?, whatsapp_number = ?, food_preference = ?
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$name, $course, $batch_id, $class_roll, $university_roll, $contact_number, $whatsapp_number, $food_preference, $user_id]);
                    } else {
                        // Insert student profile
                        $stmt = $conn->prepare("
                            INSERT INTO students (user_id, name, course, batch_id, class_roll, university_roll, contact_number, whatsapp_number, food_preference) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$user_id, $name, $course, $batch_id, $class_roll, $university_roll, $contact_number, $whatsapp_number, $food_preference]);
                    }
                } else {
                    // Create User
                    $stmt = $conn->prepare("
                        INSERT INTO users (email, password, role, status, otp_code, otp_expiry) 
                        VALUES (?, ?, 'student', 'pending_otp', ?, ?)
                    ");
                    $stmt->execute([$email, $hashed_password, $otp, $otp_expiry]);
                    $user_id = $conn->lastInsertId();

                    // Create Student Profile
                    $stmt = $conn->prepare("
                        INSERT INTO students (user_id, name, course, batch_id, class_roll, university_roll, contact_number, whatsapp_number, food_preference) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$user_id, $name, $course, $batch_id, $class_roll, $university_roll, $contact_number, $whatsapp_number, $food_preference]);
                }

                // Log registration activity
                log_activity($user_id, 'registration_initiated', "Student account registered. Waiting email verification.");

                // Commit Transaction
                $conn->commit();

                // Send OTP Email
                if (send_otp_email($email, $otp)) {
                    $_SESSION['verify_user_id'] = $user_id;
                    $_SESSION['verify_email'] = $email;
                    set_flash_message('success', 'Registration details saved! An OTP has been sent to your email.');
                    header("Location: " . BASE_URL . "verify_email.php");
                    exit();
                } else {
                    // Rollback if email failed and NOT in dev mode (dev mode would have intercepted and succeeded)
                    $conn->rollBack();
                    $errors[] = "Failed to send verification email. Please contact the coordinator or try again.";
                }
            }
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log("Student registration failed: " . $e->getMessage());
            $errors[] = "An unexpected server error occurred during registration. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - Accolades Connect</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css?v=1.0.1">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?php echo BASE_URL; ?>index.php" class="brand">
                <i data-lucide="calendar-check"></i>
                <span>Accolades Connect</span>
            </a>
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>index.php" class="nav-link">Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>login.php" class="nav-link">Login</a></li>
                <li><a href="<?php echo BASE_URL; ?>register.php" class="btn btn-primary btn-sm">Register</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <div class="auth-wrapper">
            <div class="card auth-card">
                 <div class="card-header" style="text-align: center;">
                    <div style="text-align: center; margin-bottom: 1.25rem;">
                        <img src="<?php echo BASE_URL; ?>assets/images/logo_accolades.png" alt="Accolades Logo" style="max-width: 220px; height: auto; background-color: #ffffff; padding: 0.5rem 1.25rem; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); display: inline-block;">
                    </div>
                    <i data-lucide="user-plus" style="width: 32px; height: 32px; color: var(--primary); margin-bottom: 0.5rem;"></i>
                    <h2>Student Registration</h2>
                    <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">Create your departmental event profile</p>
                </div>

                <!-- Display errors -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger show-alert-anim">
                        <i data-lucide="alert-triangle" class="alert-icon"></i>
                        <div class="alert-content">
                            <ul style="padding-left: 1rem; margin: 0;">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo e($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="<?php echo BASE_URL; ?>register.php" method="POST">
                    <?php csrf_input(); ?>

                    <div class="form-group">
                        <label class="form-label" for="name">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control" placeholder="John Doe" value="<?php echo e($old['name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="course">Course</label>
                            <select id="course" name="course" class="form-control" required>
                                <option value="BCA" <?php echo (isset($old['course']) && $old['course'] === 'BCA') ? 'selected' : ''; ?>>BCA</option>
                                <option value="MCA" <?php echo (isset($old['course']) && $old['course'] === 'MCA') ? 'selected' : ''; ?>>MCA</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="batch_id">Batch</label>
                            <select id="batch_id" name="batch_id" class="form-control" required>
                                <option value="" disabled selected>Select Batch</option>
                                <?php foreach ($batches as $batch): ?>
                                    <?php
                                    $parts = explode('-', $batch['name']);
                                    $duration = 0;
                                    if (count($parts) === 2) {
                                        $duration = (int)$parts[1] - (int)$parts[0];
                                    }
                                    ?>
                                    <option value="<?php echo $batch['id']; ?>" data-duration="<?php echo $duration; ?>" <?php echo (isset($old['batch_id']) && $old['batch_id'] == $batch['id']) ? 'selected' : ''; ?>>
                                        Batch <?php echo e($batch['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="food_preference">Food Preference</label>
                            <select id="food_preference" name="food_preference" class="form-control" required>
                                <option value="veg" <?php echo (isset($old['food_preference']) && $old['food_preference'] === 'veg') ? 'selected' : ''; ?>>Veg</option>
                                <option value="non-veg" <?php echo (isset($old['food_preference']) && $old['food_preference'] === 'non-veg') ? 'selected' : ''; ?>>Non-Veg</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="class_roll">Class Roll No.</label>
                            <input type="text" id="class_roll" name="class_roll" class="form-control" placeholder="23BCA068" value="<?php echo e($old['class_roll'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="university_roll">University Roll No.</label>
                            <input type="text" id="university_roll" name="university_roll" class="form-control" placeholder="14800122045" value="<?php echo e($old['university_roll'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="contact_number">Contact Number</label>
                            <input type="tel" id="contact_number" name="contact_number" class="form-control" placeholder="9876543210" value="<?php echo e($old['contact_number'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="whatsapp_number">WhatsApp Number</label>
                            <input type="tel" id="whatsapp_number" name="whatsapp_number" class="form-control" placeholder="9876543210" value="<?php echo e($old['whatsapp_number'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="your.name@example.com" value="<?php echo e($old['email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        <i data-lucide="send"></i> Submit Registration
                    </button>
                </form>

                <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem;">
                    <span style="color: var(--text-muted);">Already registered?</span> 
                    <a href="<?php echo BASE_URL; ?>login.php">Login here</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const courseSelect = document.getElementById('course');
            const batchSelect = document.getElementById('batch_id');
            if (courseSelect && batchSelect) {
                const batchOptions = Array.from(batchSelect.options);

                function filterBatches() {
                    const selectedCourse = courseSelect.value;
                    const currentSelectedValue = batchSelect.value;
                    
                    // Clear the dropdown
                    batchSelect.innerHTML = '';
                    
                    // Add default placeholder option
                    const defaultOption = batchOptions.find(opt => opt.value === "" || opt.disabled);
                    if (defaultOption) {
                        batchSelect.appendChild(defaultOption);
                    }

                    // Filter options
                    let anySelected = false;
                    batchOptions.forEach(opt => {
                        if (opt.value === "") return; // Skip placeholder
                        
                        const duration = parseInt(opt.getAttribute('data-duration') || '0', 10);
                        let show = false;
                        
                        if (selectedCourse === 'MCA') {
                            // Show only 2-year duration batches
                            if (duration === 2) show = true;
                        } else if (selectedCourse === 'BCA') {
                            // Show non-2-year duration batches (e.g. 4 years)
                            if (duration !== 2) show = true;
                        } else {
                            show = true;
                        }
                        
                        if (show) {
                            batchSelect.appendChild(opt);
                            if (opt.value === currentSelectedValue) {
                                opt.selected = true;
                                anySelected = true;
                            }
                        }
                    });

                    // If currently selected option is no longer visible, reset select to default placeholder
                    if (!anySelected) {
                        batchSelect.value = "";
                    }
                }

                courseSelect.addEventListener('change', filterBatches);
                // Run on initial load
                filterBatches();
            }
        });
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
