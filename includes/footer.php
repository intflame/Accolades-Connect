    </div> <!-- Close main container or wrapper -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Department of Computer Application. All Rights Reserved.</p>
        </div>
    </footer>

    <?php
    $header_profile_photo = null;
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student' && isset($conn)) {
        try {
            $photo_stmt = $conn->prepare("SELECT profile_photo FROM students WHERE user_id = ?");
            $photo_stmt->execute([$_SESSION['user_id']]);
            $header_profile_photo = $photo_stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Failed to fetch header profile photo: " . $e->getMessage());
        }
    }
    ?>
    <!-- Lucide Icons Library and Initializer -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        const headerProfilePhoto = <?php echo json_encode($header_profile_photo); ?>;
        const base_url = <?php echo json_encode(BASE_URL); ?>;
        // Apply theme immediately on script execution to minimize Flash of Unstyled Content (FOUC)
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'light') {
                document.body.classList.add('light-theme');
            }
        })();

        // Initialize theme toggle buttons & icons once the DOM is loaded
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            const navLinks = document.querySelector('.nav-links');
            const navbarInner = document.querySelector('.navbar-inner');
            const brand = document.querySelector('.brand');
            if (brand) {
                const icon = brand.querySelector('i') || brand.querySelector('svg');
                if (icon) {
                    const img = document.createElement('img');
                    img.src = '<?php echo BASE_URL; ?>assets/images/logo_ca.png';
                    img.alt = 'CA';
                    img.style.width = '30px';
                    img.style.height = '30px';
                    img.style.borderRadius = '50%';
                    img.style.marginRight = '0.6rem';
                    img.style.verticalAlign = 'middle';
                    img.style.objectFit = 'cover';
                    brand.replaceChild(img, icon);
                }
            }
            const navbar = document.querySelector('.navbar');
            let portalType = '';
            if (window.location.pathname.includes('/admin/')) portalType = 'admin';
            else if (window.location.pathname.includes('/student/')) portalType = 'student';
            else if (window.location.pathname.includes('/scanner/')) portalType = 'scanner';
            
            const isDashboard = portalType !== '';

            // 1. Dashboard Portal Restructuring (options panel on the left, top controls on the right)
            if (isDashboard && navbar && navbarInner && brand && navLinks) {
                document.body.classList.add('dashboard-portal');
                
                if (portalType === 'admin') {
                    if (!navLinks.querySelector('a[href*="admin/gallery.php"]')) {
                        const galleryLi = document.createElement('li');
                        const isActive = window.location.pathname.includes('/admin/gallery.php') ? 'active' : '';
                        galleryLi.innerHTML = `<a href="${base_url}admin/gallery.php" class="nav-link ${isActive}">Gallery</a>`;
                        const logoutLi = navLinks.querySelector('.btn-logout')?.parentElement;
                        if (logoutLi) {
                            navLinks.insertBefore(galleryLi, logoutLi);
                        } else {
                            navLinks.appendChild(galleryLi);
                        }
                    }
                }
                
                // Create Sidebar container
                const sidebar = document.createElement('aside');
                sidebar.className = 'dashboard-sidebar';
                
                // Create Logo container wrapping the brand logo link
                const sidebarLogo = document.createElement('div');
                sidebarLogo.className = 'sidebar-logo';
                sidebarLogo.appendChild(brand);
                sidebar.appendChild(sidebarLogo);
                
                // Create sidebar navigation menu
                const sidebarNav = document.createElement('ul');
                sidebarNav.className = 'sidebar-nav';
                
                // Separate option links from theme controls and move them to sidebar
                const listItems = Array.from(navLinks.querySelectorAll('li'));
                listItems.forEach(li => {
                    const isNotification = li.classList.contains('nav-notification-wrapper') || li.id === 'nav-notification-li';
                    if (!isNotification) {
                        sidebarNav.appendChild(li);
                    }
                });
                
                sidebar.appendChild(sidebarNav);
                
                // Inject sidebar at the very start of the body
                document.body.insertBefore(sidebar, document.body.firstChild);
                
                // Update navbar styling class to act as a top header
                navbar.className = 'navbar dashboard-header';
                
                // Inject Profile Avatar Icon inside the top header (navLinks)
                if (!document.getElementById('dashboard-profile-icon')) {
                    const profileLi = document.createElement('li');
                    profileLi.className = 'dashboard-profile-wrapper';
                    
                    let roleTitle = 'Logged in';
                    if (portalType === 'admin') roleTitle = 'Logged in as Admin';
                    else if (portalType === 'student') roleTitle = 'Logged in as Student';
                    else if (portalType === 'scanner') roleTitle = 'Logged in as Scanner';
                    
                    let profileHtml = `<i data-lucide="user" style="width: 16px; height: 16px;"></i>`;
                    let profileLink = 'javascript:void(0)';
                    let cursorStyle = 'default';
                    
                    if (portalType === 'student') {
                        profileLink = `${base_url}student/profile.php`;
                        cursorStyle = 'pointer';
                        if (headerProfilePhoto) {
                            profileHtml = `<img src="${base_url}uploads/profile_photos/${headerProfilePhoto}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; display: block;">`;
                        }
                    }
                    
                    profileLi.innerHTML = `
                        <a href="${profileLink}" style="color: inherit; text-decoration: none; display: block; cursor: ${cursorStyle};">
                            <div class="dashboard-profile-icon" id="dashboard-profile-icon" title="${roleTitle}" style="cursor: ${cursorStyle}; overflow: hidden; padding: 0;">
                                ${profileHtml}
                            </div>
                        </a>
                    `;
                    navLinks.insertBefore(profileLi, navLinks.firstChild);
                }
            }

            // 2. Inject Hamburger Menu Button (visible on mobile only)
            if (navbarInner && navLinks && !document.getElementById('nav-toggle-btn')) {
                const toggleBtn = document.createElement('button');
                toggleBtn.className = 'nav-toggle';
                toggleBtn.id = 'nav-toggle-btn';
                toggleBtn.setAttribute('aria-label', 'Toggle Menu');
                toggleBtn.innerHTML = '<i data-lucide="menu" style="width: 20px; height: 20px;"></i>';
                
                if (isDashboard) {
                    if (portalType === 'student') {
                        // Place hamburger toggle on the RIGHT (inside navLinks as a list item wrapper)
                        const toggleLi = document.createElement('li');
                        toggleLi.className = 'nav-toggle-wrapper';
                        toggleLi.id = 'nav-toggle-li';
                        toggleLi.appendChild(toggleBtn);
                        navLinks.appendChild(toggleLi);
                    } else {
                        // In dashboard layout, place hamburger toggle on the LEFT of the header bar
                        navbarInner.insertBefore(toggleBtn, navbarInner.firstChild);
                    }
                    
                    // Create backdrop overlay for mobile drawer
                    const overlay = document.createElement('div');
                    overlay.className = 'sidebar-overlay';
                    document.body.appendChild(overlay);
                    
                    // Hamburger toggles the dashboard sidebar visibility drawer
                    toggleBtn.addEventListener('click', function() {
                        const sidebar = document.querySelector('.dashboard-sidebar');
                        if (sidebar) {
                            sidebar.classList.toggle('active');
                            const isActive = sidebar.classList.contains('active');
                            if (isActive) overlay.classList.add('active');
                            else overlay.classList.remove('active');
                            
                            toggleBtn.innerHTML = isActive 
                                ? '<i data-lucide="x" style="width: 20px; height: 20px;"></i>' 
                                : '<i data-lucide="menu" style="width: 20px; height: 20px;"></i>';
                            if (typeof lucide !== 'undefined') {
                                lucide.createIcons();
                            }
                        }
                    });

                    // Click backdrop overlay to close drawer
                    overlay.addEventListener('click', function() {
                        const sidebar = document.querySelector('.dashboard-sidebar');
                        if (sidebar) {
                            sidebar.classList.remove('active');
                            overlay.classList.remove('active');
                            toggleBtn.innerHTML = '<i data-lucide="menu" style="width: 20px; height: 20px;"></i>';
                            if (typeof lucide !== 'undefined') {
                                lucide.createIcons();
                            }
                        }
                    });
                } else {
                    // Normal public pages hamburger toggles navLinks dropdown
                    navbarInner.insertBefore(toggleBtn, navLinks);

                    toggleBtn.addEventListener('click', function() {
                        navLinks.classList.toggle('active');
                        const isActive = navLinks.classList.contains('active');
                        toggleBtn.innerHTML = isActive 
                            ? '<i data-lucide="x" style="width: 20px; height: 20px;"></i>' 
                            : '<i data-lucide="menu" style="width: 20px; height: 20px;"></i>';
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    });
                }
            }

            // 3. Inject Theme Toggle Switcher Button
            if (!document.getElementById('theme-toggle-btn')) {
                const li = document.createElement('li');
                li.className = 'theme-toggle-wrapper';
                
                if (isDashboard) {
                    li.innerHTML = `
                        <a href="javascript:void(0)" id="theme-toggle-btn" class="nav-link" style="display: flex; align-items: center; gap: 0.75rem;">
                            <i data-lucide="sun" class="theme-icon" style="width: 16px; height: 16px;"></i>
                            <span class="theme-text">Toggle Theme</span>
                        </a>
                    `;
                    
                    const sidebarNav = document.querySelector('.sidebar-nav');
                    if (sidebarNav) {
                        const logoutLi = sidebarNav.querySelector('.btn-logout')?.parentElement;
                        if (logoutLi) {
                            sidebarNav.insertBefore(li, logoutLi);
                        } else {
                            sidebarNav.appendChild(li);
                        }
                    } else if (navLinks) {
                        navLinks.appendChild(li);
                    }
                } else {
                    li.innerHTML = `
                        <button id="theme-toggle-btn" class="theme-toggle-btn" aria-label="Toggle Theme" title="Toggle Light/Dark Theme" style="margin-left: 0.5rem;">
                            <i data-lucide="sun" class="sun-icon" style="width: 18px; height: 18px;"></i>
                            <i data-lucide="moon" class="moon-icon" style="width: 18px; height: 18px;"></i>
                        </button>
                    `;
                    if (navLinks) {
                        navLinks.appendChild(li);
                    }
                }

                // Bind click event handler for theme toggle
                const themeBtn = document.getElementById('theme-toggle-btn');
                if (themeBtn) {
                    function updateThemeUI() {
                        const isLight = document.body.classList.contains('light-theme');
                        const themeText = themeBtn.querySelector('.theme-text');
                        const themeIconWrap = themeBtn.querySelector('.theme-icon');
                        
                        if (themeText) {
                            themeText.textContent = isLight ? 'Dark Theme' : 'Light Theme';
                        }
                        
                        if (themeIconWrap) {
                            themeIconWrap.setAttribute('data-lucide', isLight ? 'moon' : 'sun');
                            if (typeof lucide !== 'undefined') {
                                lucide.createIcons();
                            }
                        }
                    }
                    
                    themeBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (document.body.classList.contains('light-theme')) {
                            document.body.classList.remove('light-theme');
                            localStorage.setItem('theme', 'dark');
                        } else {
                            document.body.classList.add('light-theme');
                            localStorage.setItem('theme', 'light');
                        }
                        if (isDashboard) {
                            updateThemeUI();
                        }
                    });
                    
                    if (isDashboard) {
                        updateThemeUI();
                    }
                }
            }

            // 4. Bind click event handler for notifications toggle dropdown
            const notificationBtn = document.getElementById('nav-notification-btn');
            const notificationDropdown = document.getElementById('notification-dropdown');
            if (notificationBtn && notificationDropdown) {
                notificationBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('show');
                });
                
                document.addEventListener('click', function(e) {
                    if (!notificationDropdown.contains(e.target) && e.target !== notificationBtn && !notificationBtn.contains(e.target)) {
                        notificationDropdown.classList.remove('show');
                    }
                });
            }

            // 5. Inject Mobile Dashboard CA Logo Button (student panel only, visible on mobile)
            if (portalType === 'student' && navbarInner && !document.getElementById('mobile-dashboard-logo-btn')) {
                const logoLink = document.createElement('a');
                logoLink.href = `${base_url}student/dashboard.php`;
                logoLink.id = 'mobile-dashboard-logo-btn';
                logoLink.className = 'nav-mobile-logo-btn';
                logoLink.title = 'Student Dashboard';
                logoLink.innerHTML = `
                    <img src="${base_url}assets/images/logo_ca.png" alt="CA Logo" style="width: 42px; height: 42px; border-radius: 20%; object-fit: cover; border: 1px solid var(--border-color); display: block;">
                `;
                // Insert as the first element of navbarInner (on the LEFT side)
                navbarInner.insertBefore(logoLink, navbarInner.firstChild);
            }

            // Finally, render all icons on page load
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>
