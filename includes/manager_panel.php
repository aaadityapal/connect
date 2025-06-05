<!-- Site Manager Left Panel -->
<div class="left-panel" id="leftPanel">
    <div class="brand-logo" style="padding: 15px 20px; margin-bottom: 10px;">
        <img src="#" alt="Company Logo" style="max-width: 140px; height: auto;">
    </div>
    <button class="toggle-btn" id="leftPanelToggleBtn" title="Toggle Panel (Ctrl+B)">
        <i class="fas fa-chevron-left" id="toggleIcon"></i>
    </button>
    
    <!-- Main Navigation -->
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
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'project_planning.php' ? 'active' : ''; ?>" onclick="window.location.href='project_planning.php'">
        <i class="fas fa-calendar-alt"></i>
        <span class="menu-text">Attendance Overview</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'milestone_tracker.php' ? 'active' : ''; ?>" onclick="window.location.href='milestone_tracker.php'">
        <i class="fas fa-flag-checkered"></i>
        <span class="menu-text">Vendor Management</span>
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
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_allocation.php' ? 'active' : ''; ?>" onclick="window.location.href='site_allocation.php'">
        <i class="fas fa-map-marked-alt"></i>
        <span class="menu-text">Site Allocation</span>
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
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'workforce_overview.php' ? 'active' : ''; ?>" onclick="window.location.href='workforce_overview.php'">
        <i class="fas fa-users"></i>
        <span class="menu-text">Workforce Overview</span>
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
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'performance_metrics.php' ? 'active' : ''; ?>" onclick="window.location.href='performance_metrics.php'">
        <i class="fas fa-tachometer-alt"></i>
        <span class="menu-text">Performance Metrics</span>
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