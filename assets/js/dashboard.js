    function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('hidden');
                mainContent.classList.toggle('expanded');
            }
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        }

        function toggleUserDropdown() {
            const isMobile = window.innerWidth <= 768;
            const dropdownId = isMobile ? 'userDropdown' : 'desktopUserDropdown';
            const dropdown = document.getElementById(dropdownId);
            
            // Close other dropdowns
            const otherDropdownId = isMobile ? 'desktopUserDropdown' : 'userDropdown';
            const otherDropdown = document.getElementById(otherDropdownId);
            if (otherDropdown) {
                otherDropdown.classList.remove('show');
            }
            
            dropdown.classList.toggle('show');
        }

        // Dark Mode Toggle
        function toggleTheme() {
            const body = document.body;
            const isDark = body.getAttribute('data-theme') === 'dark';
            body.setAttribute('data-theme', isDark ? 'light' : 'dark');
            
            // Sync both toggles
            const themeToggles = document.querySelectorAll('.theme-toggle-checkbox');
            themeToggles.forEach(toggle => {
                toggle.checked = !isDark;
            });
            
            // Save preference
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const hamburger = document.querySelector('.hamburger');
            const userAvatars = document.querySelectorAll('.user-avatar');
            const dropdowns = document.querySelectorAll('.user-dropdown');
            
            // Close sidebar on mobile when clicking outside
            if (window.innerWidth <= 768 && 
                !sidebar.contains(e.target) && 
                !hamburger.contains(e.target)) {
                closeSidebar();
            }
            
            // Close user dropdowns when clicking outside
            let clickedOnAvatar = false;
            userAvatars.forEach(avatar => {
                if (avatar.contains(e.target)) {
                    clickedOnAvatar = true;
                }
            });
            
            if (!clickedOnAvatar) {
                dropdowns.forEach(dropdown => {
                    if (!dropdown.contains(e.target)) {
                        dropdown.classList.remove('show');
                    }
                });
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
            
            // Close all dropdowns on resize
            document.querySelectorAll('.user-dropdown').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        });

        // Eye icon toggle
        document.querySelector('.eye-icon').addEventListener('click', function() {
            const icon = this.querySelector('i');
            const amount = document.querySelector('.balance-amount');
            
            if (icon.classList.contains('fa-eye-slash')) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                amount.textContent = '****';
            } else {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                amount.textContent = '$0.00';
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize theme based on saved preference or system preference
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                document.body.setAttribute('data-theme', savedTheme);
                document.querySelectorAll('.theme-toggle-checkbox').forEach(toggle => {
                    toggle.checked = savedTheme === 'dark';
                });
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.body.setAttribute('data-theme', 'dark');
                document.querySelectorAll('.theme-toggle-checkbox').forEach(toggle => {
                    toggle.checked = true;
                });
            }

            // Add event listeners for theme toggles
            document.querySelectorAll('.theme-toggle-checkbox').forEach(toggle => {
                toggle.addEventListener('change', toggleTheme);
            });
        });