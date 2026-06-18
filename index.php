<?php
/**
 * Main Application Landing Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Fetch active or upcoming events
$events = [];
try {
    $stmt = $conn->query("
        SELECT * FROM events 
        WHERE status IN ('upcoming', 'registration_open') 
        ORDER BY event_date ASC 
        LIMIT 6
    ");
    $events = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch events: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accolades Connect - Department Event Portal</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css">
</head>
<body>
    <!-- Premium Sticky Header Navbar -->
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?php echo BASE_URL; ?>index.php" class="brand">
                <i data-lucide="calendar-check"></i>
                <span>Accolades Connect</span>
            </a>
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>index.php" class="nav-link active">Home</a></li>
                <?php if ($user_id): ?>
                    <?php if ($user_role === 'admin'): ?>
                        <li><a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="nav-link">Admin Dashboard</a></li>
                    <?php elseif ($user_role === 'student'): ?>
                        <li><a href="<?php echo BASE_URL; ?>student/dashboard.php" class="nav-link">Student Portal</a></li>
                    <?php elseif ($user_role === 'scanner'): ?>
                        <li><a href="<?php echo BASE_URL; ?>scanner/dashboard.php" class="nav-link">Scanner Portal</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:16px; height:16px; display:inline; vertical-align:middle;"></i> Logout</a></li>
                <?php else: ?>
                    <li><a href="<?php echo BASE_URL; ?>login.php" class="nav-link">Login</a></li>
                    <li><a href="<?php echo BASE_URL; ?>register.php" class="btn btn-primary btn-sm">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container main-content">
        
        <!-- Hero Title Layout -->
        <header class="hero">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <img src="<?php echo BASE_URL; ?>assets/images/logo_accolades.png" alt="Accolades Logo" style="max-width: 280px; height: auto; background-color: #ffffff; padding: 0.6rem 1.5rem; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); display: inline-block;">
            </div>
            <h1 class="hero-title">Department Events Hub</h1>
            <p class="hero-subtitle">Register for academic workshops, technical hackathons, and cultural activities. Manage profiles, access verification codes, and track attendance seamlessly.</p>
            <?php if (!$user_id): ?>
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
                    <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-primary"><i data-lucide="user-plus"></i> Student Sign Up</a>
                    <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-secondary">Access Portal</a>
                </div>
            <?php endif; ?>
        </header>

        <!-- Events List Header -->
        <section style="margin-top: 4rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2>Upcoming & Open Events</h2>
                <span style="color: var(--text-muted); font-size: 0.9rem; font-weight: 500;">
                    <?php echo count($events); ?> event(s) listed
                </span>
            </div>

            <?php display_flash_message(); ?>

            <?php if (empty($events)): ?>
                <div class="card" style="text-align: center; padding: 4rem 2rem;">
                    <i data-lucide="calendar" style="width: 48px; height: 48px; color: var(--text-muted); margin: 0 auto 1.5rem;"></i>
                    <p style="font-size: 1.1rem; color: var(--text-muted);">No upcoming events scheduled at this moment.</p>
                    <p style="font-size: 0.9rem; color: var(--border-glow); margin-top: 0.5rem;">Check back later or log in to view historical registrations.</p>
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
                                <h3 style="margin-bottom: 0.75rem; font-size: 1.4rem; color: #ffffff;"><?php echo e($event['name']); ?></h3>
                                
                                <?php if (!empty($event['description'])): ?>
                                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem; line-height: 1.5; word-wrap: break-word;">
                                        <?php echo e(strlen($event['description']) > 120 ? substr($event['description'], 0, 117) . '...' : $event['description']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div style="display: flex; flex-direction: column; gap: 0.5rem; margin: 1.5rem 0; font-size: 0.9rem; color: var(--text-muted);">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i data-lucide="calendar" style="width:16px; height:16px;"></i>
                                        <span><?php echo date('M d, Y', strtotime($event['event_date'])); ?></span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i data-lucide="map-pin" style="width:16px; height:16px;"></i>
                                        <span><?php echo e($event['venue']); ?></span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i data-lucide="clock" style="width:16px; height:16px;"></i>
                                        <span>Deadline: <?php echo date('M d, Y H:i', strtotime($event['registration_deadline'])); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                                <?php if ($user_role === 'student'): ?>
                                    <a href="<?php echo BASE_URL; ?>student/events.php" class="btn btn-primary" style="width: 100%;">
                                        <i data-lucide="user-check"></i> Register Event
                                    </a>
                                <?php elseif (!$user_id): ?>
                                    <a href="<?php echo BASE_URL; ?>login.php?redirect=student/events.php" class="btn btn-secondary" style="width: 100%;">
                                        Login to Register
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 0.85rem; color: var(--text-muted); text-align: center; display: block;">
                                        Logged in as Admin/Scanner
                                    </span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
