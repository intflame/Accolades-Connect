<?php
/**
 * Student Dashboard
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
    set_flash_message('danger', 'Profile records missing. Please contact administrator.');
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// Fetch registration statistics for current student
$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0
];
try {
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) as count 
        FROM event_registrations 
        WHERE student_id = ? 
        GROUP BY status
    ");
    $stmt->execute([$student['id']]);
    $results = $stmt->fetchAll();
    
    foreach ($results as $row) {
        $stats['total'] += $row['count'];
        if ($row['status'] === 'approved') {
            $stats['approved'] = $row['count'];
        } elseif (in_array($row['status'], ['pending_payment', 'pending_verification'])) {
            $stats['pending'] += $row['count'];
        }
    }
} catch (Exception $e) {
    error_log("Failed to fetch stats: " . $e->getMessage());
}

// Fetch registered events details
$registrations = [];
try {
    $stmt = $conn->prepare("
        SELECT r.id as reg_id, r.status as reg_status, r.payment_method, r.created_at as reg_date,
               r.assigned_role, r.applied_role, r.role_status,
               e.id as event_id, e.name as event_name, e.description, e.event_date, e.venue, e.registration_fee, e.status as event_status,
               p.status as payment_status, p.proof_image, p.rejection_reason,
               c.id as certificate_id
        FROM event_registrations r
        JOIN events e ON r.event_id = e.id
        LEFT JOIN payments p ON p.registration_id = r.id
        LEFT JOIN certificates c ON c.registration_id = r.id
        WHERE r.student_id = ?
        ORDER BY e.event_date ASC
    ");
    $stmt->execute([$student['id']]);
    $registrations = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch registrations: " . $e->getMessage());
}

// Fetch events for showcase gallery
$gallery_events = [];
try {
    $stmt = $conn->prepare("
        SELECT id, name, description, event_date, venue, status, banner_image 
        FROM events 
        ORDER BY event_date ASC 
        LIMIT 6
    ");
    $stmt->execute();
    $gallery_events = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to fetch gallery events: " . $e->getMessage());
}

// Fetch pending payment event registrations for notifications
$notifications = [];
try {
    $stmt = $conn->prepare("
        SELECT r.id as reg_id, e.name as event_name 
        FROM event_registrations r 
        JOIN events e ON r.event_id = e.id 
        WHERE r.student_id = ? AND r.status = 'pending_payment' AND e.registration_fee > 0
    ");
    $stmt->execute([$student['id']]);
    $pending_payments = $stmt->fetchAll();
    
    foreach ($pending_payments as $p) {
        $notifications[] = [
            'type' => 'payment',
            'title' => 'Pending Payment Required',
            'text' => 'Upload payment proof for ' . e($p['event_name']) . '.',
            'link' => BASE_URL . 'student/upload_payment.php?reg_id=' . $p['reg_id'],
            'icon' => 'credit-card'
        ];
    }
} catch (Exception $e) {
    error_log("Failed to fetch pending payment notifications: " . $e->getMessage());
}

// Fetch upcoming active events student has not registered for yet for notifications
try {
    $stmt = $conn->prepare("
        SELECT e.id as event_id, e.name as event_name, e.event_date 
        FROM events e 
        LEFT JOIN event_registrations r ON e.id = r.event_id AND r.student_id = ? 
        WHERE e.status IN ('upcoming', 'registration_open') 
          AND e.event_date >= CURDATE() 
          AND r.id IS NULL 
        ORDER BY e.event_date ASC 
        LIMIT 3
    ");
    $stmt->execute([$student['id']]);
    $upcoming_news = $stmt->fetchAll();
    
    foreach ($upcoming_news as $u) {
        $notifications[] = [
            'type' => 'event',
            'title' => 'New Event Open',
            'text' => e($u['event_name']) . ' on ' . date('M d', strtotime($u['event_date'])) . '.',
            'link' => BASE_URL . 'student/events.php',
            'icon' => 'calendar'
        ];
    }
} catch (Exception $e) {
    error_log("Failed to fetch upcoming event notifications: " . $e->getMessage());
}

// Thematic Image Mapper function for showcase gallery
function get_event_mock_image($event_name) {
    $name = strtolower($event_name);
    if (strpos($name, 'code') !== false || strpos($name, 'program') !== false || strpos($name, 'hack') !== false || strpos($name, 'web') !== false || strpos($name, 'dev') !== false || strpos($name, 'tech') !== false) {
        return 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&w=600&auto=format&fit=crop';
    }
    if (strpos($name, 'game') !== false || strpos($name, 'gaming') !== false || strpos($name, 'esport') !== false || strpos($name, 'lan') !== false || strpos($name, 'pubg') !== false) {
        return 'https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=600&auto=format&fit=crop';
    }
    if (strpos($name, 'seminar') !== false || strpos($name, 'workshop') !== false || strpos($name, 'talk') !== false || strpos($name, 'lecture') !== false || strpos($name, 'panel') !== false || strpos($name, 'discuss') !== false || strpos($name, 'conference') !== false) {
        return 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?q=80&w=600&auto=format&fit=crop';
    }
    if (strpos($name, 'sport') !== false || strpos($name, 'cricket') !== false || strpos($name, 'football') !== false || strpos($name, 'chess') !== false || strpos($name, 'tennis') !== false || strpos($name, 'athlet') !== false || strpos($name, 'run') !== false) {
        return 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?q=80&w=600&auto=format&fit=crop';
    }
    if (strpos($name, 'cultur') !== false || strpos($name, 'music') !== false || strpos($name, 'dance') !== false || strpos($name, 'fest') !== false || strpos($name, 'drama') !== false || strpos($name, 'sing') !== false || strpos($name, 'art') !== false) {
        return 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?q=80&w=600&auto=format&fit=crop';
    }
    return 'https://images.unsplash.com/photo-1504384308090-c894fdcc538d?q=80&w=600&auto=format&fit=crop';
}

// Scan admin uploaded gallery photos
$gallery_photos = [];
$gallery_dir = UPLOAD_PATH . '/gallery_photos';
if (file_exists($gallery_dir)) {
    $files = scandir($gallery_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'])) {
            $gallery_photos[] = [
                'name' => $file,
                'time' => filemtime($gallery_dir . '/' . $file),
                'url' => BASE_URL . 'uploads/gallery_photos/' . $file
            ];
        }
    }
    usort($gallery_photos, function($a, $b) {
        return $b['time'] - $a['time'];
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Accolades Connect</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css?v=1.1.3">
</head>
<body>
    <nav class="navbar">
        <div class="container navbar-inner">
            <a href="<?php echo BASE_URL; ?>index.php" class="brand">
                <i data-lucide="calendar-check"></i>
                <span>Accolades Connect</span>
            </a>
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>student/dashboard.php" class="nav-link active">Dashboard</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/events.php" class="nav-link">Browse Events</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/attendance_records.php" class="nav-link">My Attendance</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/certificates.php" class="nav-link">My Certificates</a></li>
                <li><a href="<?php echo BASE_URL; ?>student/profile.php" class="nav-link">My Profile</a></li>
                <li class="nav-notification-wrapper" id="nav-notification-li">
                    <button class="nav-notification-btn" id="nav-notification-btn" aria-label="Notifications" title="View Notifications">
                        <i data-lucide="bell" style="width: 18px; height: 18px;"></i>
                        <?php if (count($notifications) > 0): ?>
                            <span class="notification-badge"><?php echo count($notifications); ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown" id="notification-dropdown">
                        <div class="notification-dropdown-header">
                            <span>Notifications</span>
                            <?php if (count($notifications) > 0): ?>
                                <span class="notification-count-badge"><?php echo count($notifications); ?> new</span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-dropdown-list">
                            <?php if (empty($notifications)): ?>
                                <div class="notification-empty-state">
                                    <i data-lucide="bell-off"></i>
                                    <p>No new notifications</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <a href="<?php echo $notif['link']; ?>" class="notification-item">
                                        <div class="notification-item-icon <?php echo $notif['type']; ?>">
                                            <i data-lucide="<?php echo $notif['icon']; ?>"></i>
                                        </div>
                                        <div class="notification-item-content">
                                            <div class="notification-item-title"><?php echo $notif['title']; ?></div>
                                            <div class="notification-item-text"><?php echo $notif['text']; ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link btn-logout"><i data-lucide="log-out" style="width:14px; height:14px; display:inline; vertical-align:middle;"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container main-content">
        <!-- Welcoming User -->
        <header style="margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; justify-content: space-between;">
                <div>
                    <h1 style="font-size: 2rem; margin-bottom: 0.25rem;">Welcome, <?php echo e($student['name']); ?></h1>
                    <p style="color: var(--text-muted); font-size: 0.95rem;"><?php echo e($student['course'] ?? 'BCA'); ?> &bull; Batch <?php echo e($student['batch_name']); ?> &bull; Roll: <?php echo e($student['class_roll']); ?></p>
                </div>
                <div>
                    <a href="<?php echo BASE_URL; ?>student/events.php" class="btn btn-primary">
                        <i data-lucide="calendar-plus"></i> View Events
                    </a>
                </div>
            </div>
        </header>

        <!-- Photo Gallery Highlights Slider -->
        <?php if (!empty($gallery_photos)): ?>
            <div class="photo-gallery-slider">
                <!-- Slides Wrapper -->
                <div class="photo-gallery-slides">
                    <?php foreach ($gallery_photos as $index => $photo): ?>
                        <div class="photo-gallery-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-photo-slide-index="<?php echo $index; ?>">
                            <img src="<?php echo $photo['url']; ?>" alt="Highlight Photo" class="photo-gallery-image">
                            <div class="photo-gallery-overlay"></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Navigation Arrows -->
                <button class="photo-gallery-arrow photo-gallery-arrow-left" aria-label="Previous Photo">
                    <i data-lucide="chevron-left"></i>
                </button>
                <button class="photo-gallery-arrow photo-gallery-arrow-right" aria-label="Next Photo">
                    <i data-lucide="chevron-right"></i>
                </button>

                <!-- Indicators (Dots/Bars) -->
                <div class="photo-gallery-indicators">
                    <?php foreach ($gallery_photos as $index => $photo): ?>
                        <button class="photo-gallery-dot <?php echo $index === 0 ? 'active' : ''; ?>" data-photo-dot-index="<?php echo $index; ?>" aria-label="Go to Photo <?php echo $index + 1; ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Messages banner -->
        <?php display_flash_message(); ?>

        <!-- Quick Info Widgets Removed -->

        <!-- Department Events Showcase Carousel -->
        <div class="card" style="margin-top: 2rem; padding: 0; overflow: hidden; position: relative;">
            <div class="carousel-container">
                <?php if (empty($gallery_events)): ?>
                    <div style="text-align: center; padding: 4rem 1rem;">
                        <i data-lucide="calendar" style="width: 40px; height: 40px; color: var(--text-muted); margin-bottom: 1rem;"></i>
                        <p style="color: var(--text-muted);">No events found to showcase.</p>
                    </div>
                <?php else: ?>
                    <!-- Slides Wrapper -->
                    <div class="carousel-slides">
                        <?php foreach ($gallery_events as $index => $event): ?>
                            <div class="carousel-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-slide-index="<?php echo $index; ?>">
                                <?php 
                                    $slide_img = !empty($event['banner_image']) 
                                        ? BASE_URL . 'uploads/event_banners/' . $event['banner_image'] 
                                        : get_event_mock_image($event['name']);
                                ?>
                                <img src="<?php echo $slide_img; ?>" alt="<?php echo e($event['name']); ?>" class="carousel-slide-image">
                                <div class="carousel-slide-overlay"></div>
                                <div class="carousel-slide-content">
                                    <span class="carousel-badge"><?php echo str_replace('_', ' ', $event['status']); ?></span>
                                    <h3 class="carousel-title"><?php echo e($event['name']); ?></h3>
                                    <div class="carousel-meta">
                                        <span class="carousel-meta-item">
                                            <i data-lucide="map-pin"></i>
                                            <?php echo e($event['venue']); ?>
                                        </span>
                                        <span class="carousel-meta-item">
                                            <i data-lucide="calendar"></i>
                                            <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($event['description'])): ?>
                                        <p class="carousel-desc">
                                            <?php echo e(strlen($event['description']) > 150 ? substr($event['description'], 0, 147) . '...' : $event['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <div style="margin-top: 1rem;">
                                        <a href="<?php echo BASE_URL; ?>student/events.php" class="btn btn-primary btn-sm">
                                            Register & Details <i data-lucide="arrow-right" style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-left: 0.25rem;"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Navigation Arrows -->
                    <button class="carousel-arrow carousel-arrow-left" aria-label="Previous Slide">
                        <i data-lucide="chevron-left"></i>
                    </button>
                    <button class="carousel-arrow carousel-arrow-right" aria-label="Next Slide">
                        <i data-lucide="chevron-right"></i>
                    </button>

                    <!-- Indicators (Dots/Bars) -->
                    <div class="carousel-indicators">
                        <?php foreach ($gallery_events as $index => $event): ?>
                            <button class="carousel-dot <?php echo $index === 0 ? 'active' : ''; ?>" data-dot-index="<?php echo $index; ?>" aria-label="Go to Slide <?php echo $index + 1; ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const slides = document.querySelectorAll('.carousel-slide');
            const dots = document.querySelectorAll('.carousel-dot');
            const leftArrow = document.querySelector('.carousel-arrow-left');
            const rightArrow = document.querySelector('.carousel-arrow-right');
            
            if (slides.length === 0) return;
            
            let currentIndex = 0;
            let slideInterval;
            
            function showSlide(index) {
                if (index >= slides.length) index = 0;
                if (index < 0) index = slides.length - 1;
                
                slides[currentIndex].classList.remove('active');
                if (dots.length > 0) dots[currentIndex].classList.remove('active');
                
                currentIndex = index;
                
                slides[currentIndex].classList.add('active');
                if (dots.length > 0) dots[currentIndex].classList.add('active');
            }
            
            function startAutoPlay() {
                stopAutoPlay();
                slideInterval = setInterval(function() {
                    showSlide(currentIndex + 1);
                }, 5000);
            }
            
            function stopAutoPlay() {
                if (slideInterval) {
                    clearInterval(slideInterval);
                }
            }
            
            if (leftArrow) {
                leftArrow.addEventListener('click', function() {
                    showSlide(currentIndex - 1);
                    startAutoPlay();
                });
            }
            
            if (rightArrow) {
                rightArrow.addEventListener('click', function() {
                    showSlide(currentIndex + 1);
                    startAutoPlay();
                });
            }
            
            dots.forEach(function(dot, idx) {
                dot.addEventListener('click', function() {
                    showSlide(idx);
                    startAutoPlay();
                });
            });
            
            startAutoPlay();

            // --- Photo Gallery Highlights Slider Controls ---
            const pSlides = document.querySelectorAll('.photo-gallery-slide');
            const pDots = document.querySelectorAll('.photo-gallery-dot');
            const pLeftArrow = document.querySelector('.photo-gallery-arrow-left');
            const pRightArrow = document.querySelector('.photo-gallery-arrow-right');
            
            if (pSlides.length > 0) {
                let pCurrentIndex = 0;
                let pSlideInterval;
                
                function showPhotoSlide(index) {
                    if (index >= pSlides.length) index = 0;
                    if (index < 0) index = pSlides.length - 1;
                    
                    pSlides[pCurrentIndex].classList.remove('active');
                    if (pDots.length > 0) pDots[pCurrentIndex].classList.remove('active');
                    
                    pCurrentIndex = index;
                    
                    pSlides[pCurrentIndex].classList.add('active');
                    if (pDots.length > 0) pDots[pCurrentIndex].classList.add('active');
                }
                
                function startPhotoAutoPlay() {
                    stopPhotoAutoPlay();
                    pSlideInterval = setInterval(function() {
                        showPhotoSlide(pCurrentIndex + 1);
                    }, 4000);
                }
                
                function stopPhotoAutoPlay() {
                    if (pSlideInterval) {
                        clearInterval(pSlideInterval);
                    }
                }
                
                if (pLeftArrow) {
                    pLeftArrow.addEventListener('click', function() {
                        showPhotoSlide(pCurrentIndex - 1);
                        startPhotoAutoPlay();
                    });
                }
                
                if (pRightArrow) {
                    pRightArrow.addEventListener('click', function() {
                        showPhotoSlide(pCurrentIndex + 1);
                        startPhotoAutoPlay();
                    });
                }
                
                pDots.forEach(function(dot, idx) {
                    dot.addEventListener('click', function() {
                        showPhotoSlide(idx);
                        startPhotoAutoPlay();
                    });
                });
                
                startPhotoAutoPlay();
            }
        });
    </script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
