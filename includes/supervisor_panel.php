<!-- Site Supervisor Left Panel -->
<div class="left-panel" id="leftPanel">
    <div class="brand-logo" style="padding: 20px 25px; margin-bottom: 20px;">
        <img src="#" alt="Company Logo" style="max-width: 150px; height: auto;">
    </div>
    <button class="toggle-btn" onclick="togglePanel()">
        <i class="fas fa-chevron-left" id="toggleIcon"></i>
    </button>
    
    <!-- Main Navigation -->
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_supervisor_dashboard.php' ? 'active' : ''; ?>" onclick="window.location.href='site_supervisor_dashboard.php'">
        <i class="fas fa-home"></i>
        <span class="menu-text">Dashboard</span>
    </div>
    
    <!-- Site Management Section -->
    <div class="menu-item section-start">
        <i class="fas fa-hard-hat"></i>
        <span class="menu-text">Site Management</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_inspections.php' ? 'active' : ''; ?>" onclick="window.location.href='site_inspections.php'">
        <i class="fas fa-clipboard-check"></i>
        <span class="menu-text">Site Inspections</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_issues.php' ? 'active' : ''; ?>" onclick="window.location.href='site_issues.php'">
        <i class="fas fa-exclamation-triangle"></i>
        <span class="menu-text">Issues & Reports</span>
    </div>
    
    <!-- Worker Management Section -->
    <div class="menu-item section-start">
        <i class="fas fa-users"></i>
        <span class="menu-text">Worker Management</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'worker_attendance.php' ? 'active' : ''; ?>" onclick="window.location.href='worker_attendance.php'">
        <i class="fas fa-user-clock"></i>
        <span class="menu-text">Attendance</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'worker_assignment.php' ? 'active' : ''; ?>" onclick="window.location.href='worker_assignment.php'">
        <i class="fas fa-tasks"></i>
        <span class="menu-text">Task Assignment</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'worker_performance.php' ? 'active' : ''; ?>" onclick="window.location.href='worker_performance.php'">
        <i class="fas fa-chart-bar"></i>
        <span class="menu-text">Performance</span>
    </div>
    
    <!-- Materials & Equipment Section -->
    <div class="menu-item section-start">
        <i class="fas fa-tools"></i>
        <span class="menu-text">Materials & Equipment</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'materials_inventory.php' ? 'active' : ''; ?>" onclick="window.location.href='materials_inventory.php'">
        <i class="fas fa-warehouse"></i>
        <span class="menu-text">Inventory</span>
    </div>
    
    <!-- Reports Section -->
    <div class="menu-item section-start">
        <i class="fas fa-file-alt"></i>
        <span class="menu-text">Reports</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'view_travel_expenses.php' ? 'active' : ''; ?>" onclick="window.location.href='view_travel_expenses.php'">
        <i class="fas fa-file-invoice-dollar"></i>
        <span class="menu-text">Travel Expenses</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'attendance_visualizer.php' ? 'active' : ''; ?>" onclick="window.location.href='attendance_visualizer.php'">
        <i class="fas fa-user-clock"></i>
        <span class="menu-text">Attendance Reports</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'weekly_reports.php' ? 'active' : ''; ?>" onclick="window.location.href='weekly_reports.php'">
        <i class="fas fa-calendar-week"></i>
        <span class="menu-text">Weekly Reports</span>
    </div>
    
    <!-- Settings & Personal Section -->
    <div class="menu-item section-start">
        <i class="fas fa-user-cog"></i>
        <span class="menu-text">Personal</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'site_supervisor_profile.php' ? 'active' : ''; ?>" onclick="window.location.href='site_supervisor_profile.php'">
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