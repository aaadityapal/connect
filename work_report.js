        // Enhanced Work Report JS
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('toggle-btn');
            const mainContent = document.querySelector('.main-content');
            const reportCards = document.querySelectorAll('.report-card');
            
            // Check for saved sidebar state
            const savedSidebarState = localStorage.getItem('sidebarCollapsed');
            if (savedSidebarState === 'true') {
                sidebar.classList.add('collapsed');
                updateToggleIcon(true);
            }
            
            // Toggle sidebar collapse/expand with animation
            toggleBtn.addEventListener('click', function() {
                const isCollapsing = !sidebar.classList.contains('collapsed');
                sidebar.classList.toggle('collapsed');
                
                // Save state to localStorage
                localStorage.setItem('sidebarCollapsed', isCollapsing);
                
                // Update icon
                updateToggleIcon(isCollapsing);
                
                // Add transition class to main content
                mainContent.style.transition = 'padding-left 0.3s ease';
            });
            
            function updateToggleIcon(isCollapsed) {
                const icon = toggleBtn.querySelector('i');
                if (isCollapsed) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
            }
            
            // For mobile: click outside to close expanded sidebar
            document.addEventListener('click', function(e) {
                const isMobile = window.innerWidth <= 768;
                
                if (isMobile && !sidebar.contains(e.target) && sidebar.classList.contains('expanded')) {
                    sidebar.classList.remove('expanded');
                }
            });
            
            // For mobile: toggle expanded class
            if (window.innerWidth <= 768) {
                sidebar.addEventListener('click', function(e) {
                    if (e.target.closest('a')) return; // Allow clicking links
                    
                    if (!sidebar.classList.contains('expanded')) {
                        e.stopPropagation();
                        sidebar.classList.add('expanded');
                    }
                });
            }
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('expanded');
                }
            });

            // Date validation and enhanced UX
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            const filterForm = document.querySelector('.filters');
            const applyFiltersBtn = document.querySelector('.apply-filters');
            const exportBtn = document.getElementById('export-excel');

            // Set default dates if not already set
            if (!startDate.value) {
                const today = new Date();
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                startDate.value = formatDate(firstDay);
            }
            
            if (!endDate.value) {
                endDate.value = formatDate(new Date());
            }

            // Ensure end date is not before start date
            startDate.addEventListener('change', function() {
                if (endDate.value && this.value > endDate.value) {
                    endDate.value = this.value;
                }
                endDate.min = this.value;
            });

            endDate.addEventListener('change', function() {
                if (startDate.value && this.value < startDate.value) {
                    startDate.value = this.value;
                }
                startDate.max = this.value;
            });
            
            // Add loading state to filter form
            filterForm.addEventListener('submit', function(e) {
                applyFiltersBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                applyFiltersBtn.disabled = true;
            });

            // Export to Excel
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    const start = document.getElementById('start_date')?.value || '';
                    const end = document.getElementById('end_date')?.value || '';
                    const user = document.getElementById('user_id')?.value || '';

                    const params = new URLSearchParams();
                    if (start) params.set('start_date', start);
                    if (end) params.set('end_date', end);
                    if (user) params.set('user_id', user);

                    const url = `manager_work_export_excel.php?${params.toString()}`;
                    window.location.href = url;
                });
            }
            
            // Helper function to format date as YYYY-MM-DD
            function formatDate(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
            
            // Add staggered animation to report cards
            if (reportCards.length > 0) {
                reportCards.forEach((card, index) => {
                    setTimeout(() => {
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(20px)';
                        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                        
                        setTimeout(() => {
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        }, 50);
                    }, index * 100);
                });
            }
        });