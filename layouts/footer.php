<?php
/**
 * Master Page Footer Layout
 * Developed by Senior PHP Software Architect
 * 
 * Closes structural grid tags and loads essential javascript libraries
 * such as Bootstrap 5, Chart.js, and AOS, with full theme and toggle handlers.
 */
?>
    </div> <!-- /d-flex (closed from header.php) -->

    <!-- 1. Bootstrap 5 JS Bundle CDN (Includes Popper.js) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- 2. AOS (Animate on Scroll) JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    
    <!-- 3. Dynamic Javascript Controllers -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // A. Initialize AOS
            AOS.init({
                duration: 600,
                easing: 'ease-out-cubic',
                once: true,
                disable: 'mobile'
            });

            // B. Mobile Sidebar Hamburger Toggle
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebar-toggle');
            if (sidebar && sidebarToggle) {
                sidebarToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('show');
                });
                
                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function(e) {
                    if (window.innerWidth < 992 && sidebar.classList.contains('show')) {
                        if (!sidebar.contains(e.target) && e.target !== sidebarToggle) {
                            sidebar.classList.remove('show');
                        }
                    }
                });
            }

            // C. Unified Dark/Light Theme Manager
            const themeBtn = document.getElementById('theme-switcher-btn');
            const themeIcon = document.getElementById('theme-btn-icon');
            
            function updateThemeIcon(theme) {
                if (!themeIcon) return;
                if (theme === 'light') {
                    themeIcon.className = 'bi bi-sun-fill fs-5 text-warning';
                    themeIcon.title = 'Switch to Dark Mode';
                } else {
                    themeIcon.className = 'bi bi-moon-stars-fill fs-5 text-secondary';
                    themeIcon.title = 'Switch to Light Mode';
                }
            }

            // Read starting state
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            updateThemeIcon(currentTheme);

            if (themeBtn) {
                themeBtn.addEventListener('click', function() {
                    const activeTheme = document.documentElement.getAttribute('data-theme') || 'dark';
                    const nextTheme = activeTheme === 'dark' ? 'light' : 'dark';
                    
                    document.documentElement.setAttribute('data-theme', nextTheme);
                    localStorage.setItem('theme', nextTheme);
                    updateThemeIcon(nextTheme);
                    
                    // Dispatch custom event to let nested components (like Chart.js instances) adapt to color schemes dynamically
                    window.dispatchEvent(new CustomEvent('themeChanged', { detail: nextTheme }));
                });
            }
        });
    </script>
</body>
</html>
