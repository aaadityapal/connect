<!-- Site Manager Left Panel -->
<div class="left-panel" id="leftPanel">
    <div class="brand-logo">
        <img src="assets/img/company-logo.png" alt="Company Logo" onerror="this.src='data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22100%22%20height%3D%2230%22%3E%3Crect%20width%3D%22100%25%22%20height%3D%22100%25%22%20fill%3D%22%230d1757%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-family%3D%22Arial%22%20font-size%3D%2214%22%20fill%3D%22%23ffffff%22%3ECompany%20Logo%3C%2Ftext%3E%3C%2Fsvg%3E'">
    </div>
    
    <!-- Mobile Hamburger Menu removed from here -->
    
    <!-- Main Navigation -->
    <div class="menu-item" id="leftPanelToggleBtn" style="cursor: pointer;">
        <i class="fas fa-chevron-right" id="toggleIcon"></i>
        <span class="menu-text">Dashboard</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_manager_dashboard.php' ? 'active' : ''; ?>" onclick="window.location.href='site_manager_dashboard.php'">
        <i class="fas fa-home"></i>
        <span class="menu-text">Dashboard</span>
    </div>
    
    <!-- Project Management Section -->
    <div class="menu-item section-start">
        <i class="fas fa-project-diagram"></i>
        <span class="menu-text">Manager Accessible *</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'travel_expenses_approval_manager.php' ? 'active' : ''; ?>" onclick="window.location.href='travel_expenses_approval_manager.php'">
        <i class="fas fa-tasks"></i>
        <span class="menu-text">Travel Expenses</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_attendance.php' ? 'active' : ''; ?>" onclick="window.location.href='site_attendance.php'">
        <i class="fas fa-calendar-alt"></i>
        <span class="menu-text">Attendance Overview</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_manager_ot_approval.php' ? 'active' : ''; ?>" onclick="window.location.href='site_manager_ot_approval.php'">
        <i class="fas fa-clock"></i>
        <span class="menu-text">Overtime Approval</span>
    </div>
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'attendance_approval.php' ? 'active' : ''; ?>" onclick="window.location.href='attendance_approval.php'">
        <i class="fas fa-calendar-check"></i>
        <span class="menu-text">Attendance Approval</span>
    </div>
    
    <!-- Site Supervision Section -->
    <div class="menu-item section-start">
        <i class="fas fa-hard-hat"></i>
        <span class="menu-text">Purchase Management</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'travel_expenses_approval.php' ? 'active' : ''; ?>" onclick="window.location.href='travel_expenses_approval.php'">
        <i class="fas fa-user-tie"></i>
        <span class="menu-text">Travel Expenses Approval</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_expenses.php' ? 'active' : ''; ?>" onclick="window.location.href='site_expenses.php'">
        <i class="fas fa-money-bill-wave"></i>
        <span class="menu-text">Site Expenses</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_progress.php' ? 'active' : ''; ?>" onclick="window.location.href='site_progress.php'">
        <i class="fas fa-chart-line"></i>
        <span class="menu-text">Site Progress</span>
    </div>
    
    <!-- Resource Management Section -->
    <div class="menu-item section-start">
        <i class="fas fa-boxes"></i>
        <span class="menu-text">Resource Management</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'manager_labour_attendance.php' ? 'active' : ''; ?>" onclick="window.location.href='manager_labour_attendance.php'">
        <i class="fas fa-users"></i>
        <span class="menu-text">Labour Attendance</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'materials_management.php' ? 'active' : ''; ?>" onclick="window.location.href='materials_management.php'">
        <i class="fas fa-dolly-flatbed"></i>
        <span class="menu-text">Materials Management</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'equipment_allocation.php' ? 'active' : ''; ?>" onclick="window.location.href='equipment_allocation.php'">
        <i class="fas fa-tools"></i>
        <span class="menu-text">Equipment Allocation</span>
    </div>
    
    <!-- Reports Section -->
    <div class="menu-item section-start">
        <i class="fas fa-chart-bar"></i>
        <span class="menu-text">Reports & Analytics</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'pm_overtime.php' ? 'active' : ''; ?>" onclick="window.location.href='pm_overtime.php'">
        <i class="fas fa-tachometer-alt"></i>
        <span class="menu-text">Overtime</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'travel_expenses.php' ? 'active' : ''; ?>" onclick="window.location.href='travel_expenses.php'">
        <i class="fas fa-car"></i>
        <span class="menu-text">Travel Expenses</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'attendance_overview.php' ? 'active' : ''; ?>" onclick="window.location.href='attendance_overview.php'">
        <i class="fas fa-calendar-check"></i>
        <span class="menu-text">Attendance Overview</span>
    </div>
    
    <!-- Settings & Personal Section -->
    <div class="menu-item section-start">
        <i class="fas fa-user-cog"></i>
        <span class="menu-text">Personal</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_manager_profile.php' ? 'active' : ''; ?>" onclick="window.location.href='site_manager_profile.php'">
        <i class="fas fa-user-circle"></i>
        <span class="menu-text">My Profile</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" onclick="window.location.href='settings.php'">
        <i class="fas fa-cog"></i>
        <span class="menu-text">Settings</span>
    </div>
    
    <!-- Logout at the bottom -->
    <div class="menu-item logout-item" onclick="window.location.href='logout.php'">
        <i class="fas fa-sign-out-alt"></i>
        <span class="menu-text">Logout</span>
    </div>
</div>

<!-- Mobile hamburger menu with display controlled by media query -->
<div class="mobile-toggle" id="mobileToggle" style="display: none; position: fixed; top: 15px; left: 15px; z-index: 1000; background-color: #1a237e; color: white; width: 40px; height: 40px; border-radius: 5px; text-align: center; line-height: 40px; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
    <i class="fas fa-bars"></i>
</div>

<script>
    // Immediately check screen size and show hamburger if needed
    (function() {
        if (window.innerWidth <= 768) {
            document.getElementById('mobileToggle').style.display = 'block';
        }
    })();
</script>

<!-- Overlay for mobile -->
<div class="panel-overlay" id="panelOverlay"></div>

<style>
    /* Updated Left Panel Styling to match the image */
    .left-panel {
        background-color: #1a237e; /* Dark blue background */
        color: #ffffff;
        width: 250px;
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        overflow-y: auto;
        z-index: 100;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
        transition: all 0.3s ease;
        /* Hide scrollbar */
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none; /* IE and Edge */
    }
    
    /* Hide scrollbar for Chrome, Safari and Opera */
    .left-panel::-webkit-scrollbar {
        display: none;
    }
    
    .brand-logo {
        padding: 15px;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .brand-logo img {
        max-width: 80%;
        max-height: 40px;
    }
    
    /* Mobile hamburger menu */
    .mobile-toggle {
        display: none;
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 1000; /* Increased z-index to be above everything */
        background-color: #1a237e;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 5px;
        text-align: center;
        line-height: 40px;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }
    
    .mobile-toggle i {
        font-size: 20px;
    }
    
    /* Overlay for mobile */
    .panel-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 90;
    }
    
    .menu-item {
        padding: 12px 20px;
        display: flex;
        align-items: center;
        cursor: pointer;
        transition: all 0.2s;
        border-left: 4px solid transparent;
    }
    
    .menu-item:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }
    
    .menu-item.active {
        background-color: rgba(255, 255, 255, 0.15);
        border-left: 4px solid #ffffff;
    }
    
    .menu-item i {
        font-size: 16px;
        margin-right: 15px;
        width: 20px;
        text-align: center;
    }
    
    .menu-text {
        font-size: 14px;
        font-weight: 400;
        transition: opacity 0.3s ease;
    }
    
    .section-start {
        margin-top: 10px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding-top: 15px;
        color: rgba(255, 255, 255, 0.7);
        font-weight: 600;
        cursor: default;
    }
    
    .section-start:hover {
        background-color: transparent;
    }
    
    .logout-item {
        margin-top: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        color: #ff5252;
    }
    
    /* Adjust main content to accommodate fixed left panel */
    .main-content {
        margin-left: 250px;
        transition: margin-left 0.3s ease;
    }
    
    /* Collapsed state styling */
    .left-panel.collapsed {
        width: 60px;
    }
    
    .left-panel.collapsed .menu-text {
        opacity: 0;
        width: 0;
        display: none;
    }
    
    .left-panel.collapsed .menu-item i {
        margin-right: 0;
        font-size: 18px;
    }
    
    .left-panel.collapsed #toggleIcon {
        transform: rotate(180deg);
    }
    
    /* Main content expanded state when panel is collapsed */
    .main-content.expanded {
        margin-left: 60px;
    }
    
    /* Responsive adjustments for mobile devices */
    @media (max-width: 768px) {
        .left-panel {
            transform: translateX(-100%);
            width: 250px;
        }
        
        .left-panel.mobile-open {
            transform: translateX(0);
        }
        
        .mobile-toggle {
            display: block !important; /* Force display with !important */
        }
        
        .panel-overlay.active {
            display: block;
        }
        
        .main-content {
            margin-left: 0;
            padding-top: 10px; /* Add padding for hamburger */
        }
    }
    
    /* Specific adjustments for iPhone SE, XR and other small devices */
    @media (max-width: 414px) {
        .mobile-toggle {
            top: 10px;
            left: 10px;
            width: 35px;
            height: 35px;
            line-height: 35px;
        }
        
        .left-panel {
            width: 230px;
        }
    }
</style>

<script>
    // Immediately show hamburger on small screens without waiting for DOM
    (function() {
        if (window.innerWidth <= 768) {
            var toggle = document.getElementById('mobileToggle');
            if (toggle) toggle.style.display = 'block';
        }
    })();

    document.addEventListener('DOMContentLoaded', function() {
        // Desktop panel toggle
        const leftPanel = document.getElementById('leftPanel');
        const toggleBtn = document.getElementById('leftPanelToggleBtn');
        const mainContent = document.querySelector('.main-content');
        
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                leftPanel.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            });
        }
        
        // Mobile hamburger menu toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const panelOverlay = document.getElementById('panelOverlay');
        
        if (mobileToggle) {
            mobileToggle.addEventListener('click', function() {
                leftPanel.classList.toggle('mobile-open');
                panelOverlay.classList.toggle('active');
            });
        }
        
        // Close panel when clicking on overlay
        if (panelOverlay) {
            panelOverlay.addEventListener('click', function() {
                leftPanel.classList.remove('mobile-open');
                panelOverlay.classList.remove('active');
            });
        }
        
        // Close panel when menu item is clicked on mobile
        const menuItems = document.querySelectorAll('.left-panel .menu-item:not(#leftPanelToggleBtn)');
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    leftPanel.classList.remove('mobile-open');
                    panelOverlay.classList.remove('active');
                }
            });
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                leftPanel.classList.remove('mobile-open');
                panelOverlay.classList.remove('active');
            }
        });
    });
</script> 