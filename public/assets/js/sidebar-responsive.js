/**
 * Responsive Sidebar Toggle Functionality
 * Handles mobile sidebar open/close with overlay
 */

(function () {
    'use strict';

    // Wait for DOM to be ready and ensure other scripts have loaded
    function initSidebar() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarCloseBtn = document.querySelector('.sidebar-close-btn');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        // Check if elements exist
        if (!sidebar || !sidebarToggle) {
            return;
        }

        // Toggle sidebar function
        function toggleSidebar() {
            const isActive = sidebar.classList.contains('active');

            if (isActive) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        // Open sidebar function
        function openSidebar() {
            // Add active class to trigger CSS transition
            sidebar.classList.add('active');

            // Fade in overlay with transition
            if (sidebarOverlay) {
                // Small delay to ensure sidebar starts animating first
                setTimeout(function () {
                    sidebarOverlay.classList.add('active');
                }, 10);
            }

            // Prevent body scroll on mobile
            if (window.innerWidth <= 767.98) {
                document.body.style.overflow = 'hidden';
            }
        }

        // Close sidebar function
        function closeSidebar() {
            // Remove active class to trigger CSS transition
            sidebar.classList.remove('active');

            // Fade out overlay with transition
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('active');
            }

            // Restore body scroll after a short delay to allow transition
            setTimeout(function () {
                document.body.style.overflow = '';
            }, 50);
        }

        // Event listeners - use event delegation to avoid conflicts
        document.addEventListener('click', function (e) {
            // Handle burger button click
            if (e.target.closest('#sidebarToggle')) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            }

            // Handle close button click
            if (e.target.closest('.sidebar-close-btn')) {
                e.preventDefault();
                e.stopPropagation();
                closeSidebar();
            }

            // Handle overlay click
            if (e.target === sidebarOverlay) {
                closeSidebar();
            }
        });

        // Close sidebar when clicking on navigation links (mobile only)
        // Only close when navigation actually happens, not on submenu toggles
        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 767.98) {
                const link = e.target.closest('#sidebarMenu a');
                if (link) {
                    const href = link.getAttribute('href');
                    const sidebarItem = link.closest('.sidebar-item');

                    // Skip if it's a submenu toggle link (has href="#" and parent has has-sub class)
                    if (href === '#' && sidebarItem && sidebarItem.classList.contains('has-sub')) {
                        return; // Don't close for submenu toggles
                    }

                    // Only close for actual navigation links (not # or empty)
                    if (href && href !== '#' && href !== '') {
                        // Store current URL
                        const currentUrl = window.location.href;
                        const currentPath = window.location.pathname;

                        // Check if navigation happened after a delay
                        // This works for both traditional and AJAX navigation
                        setTimeout(function () {
                            // Only close if URL actually changed (navigation happened)
                            if (window.location.href !== currentUrl || window.location.pathname !== currentPath) {
                                closeSidebar();
                            }
                        }, 200);

                        // Also set up a periodic check for slower navigations
                        let checkCount = 0;
                        const maxChecks = 15; // Check for 1.5 seconds
                        const intervalId = setInterval(function () {
                            checkCount++;
                            if (window.location.href !== currentUrl || window.location.pathname !== currentPath) {
                                closeSidebar();
                                clearInterval(intervalId);
                            } else if (checkCount >= maxChecks) {
                                clearInterval(intervalId);
                            }
                        }, 100);
                    }
                }
            }
        });

        // Handle window resize
        let resizeTimeout;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function () {
                // On desktop, ensure sidebar is open and remove overlay
                if (window.innerWidth >= 768) {
                    sidebar.classList.add('active');
                    if (sidebarOverlay) {
                        sidebarOverlay.classList.remove('active');
                    }
                    document.body.style.overflow = '';
                }
            }, 100);
        });

        // Handle escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                closeSidebar();
            }
        });

        // Initialize sidebar state based on screen size
        function initializeSidebar() {
            if (window.innerWidth >= 768) {
                // Desktop: sidebar should be open by default
                sidebar.classList.add('active');
            } else {
                // Mobile: sidebar should be closed by default
                sidebar.classList.remove('active');
            }
        }

        // Initialize on load
        initializeSidebar();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        // DOM is already ready
        initSidebar();
    }

    // Also initialize after a short delay to ensure other scripts have loaded
    setTimeout(initSidebar, 100);

})();
