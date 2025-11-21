<!-- Admin Left Panel -->
<div class="left-panel" id="leftPanel">
    <div class="brand-logo">
        <img src="assets/img/company-logo.png" alt="Company Logo" onerror="this.src='data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22100%22%20height%3D%2230%22%3E%3Crect%20width%3D%22100%25%22%20height%3D%22100%25%22%20fill%3D%22%23667eea%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20dominant-baseline%3D%22middle%22%20text-anchor%3D%22middle%22%20font-family%3D%22Arial%22%20font-size%3D%2214%22%20fill%3D%22%23ffffff%22%3EAdmin%20Portal%3C%2Ftext%3E%3C%2Fsvg%3E'">
    </div>
    
    <!-- Main Navigation -->
    <div class="menu-item section-start">
        <i class="fas fa-tachometer-alt"></i>
        <span class="menu-text">Main</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>" onclick="window.location.href='admin_dashboard.php'">
        <i class="fas fa-home"></i>
        <span class="menu-text">Dashboard</span>
    </div>

    <!-- User Management Section -->
    <div class="menu-item section-start">
        <i class="fas fa-users-cog"></i>
        <span class="menu-text">User Management</span>
    </div>
    
    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_user_management.php' ? 'active' : ''; ?>" onclick="window.location.href='admin_user_management.php'">
        <i class="fas fa-users"></i>
        <span class="menu-text">Manage Users</span>
    </div>

    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_roles.php' ? 'active' : ''; ?>" onclick="window.location.href='admin_roles.php'">
        <i class="fas fa-user-tag"></i>
        <span class="menu-text">Manage Roles</span>
    </div>

    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_permissions.php' ? 'active' : ''; ?>" onclick="window.location.href='admin_permissions.php'">
        <i class="fas fa-lock"></i>
        <span class="menu-text">Permissions</span>
    </div>

    <!-- System Management Section -->
    <div class="menu-item section-start">
        <i class="fas fa-cogs"></i>
        <span class="menu-text">System Management</span>
    </div>

    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_settings.php' ? 'active' : ''; ?>" onclick="window.location.href='admin_settings.php'">
        <i class="fas fa-sliders-h"></i>
        <span class="menu-text">Settings</span>
    </div>

    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_audit_logs.php' ? 'active' : ''; ?>" onclick="window.location.href='admin_audit_logs.php'">
        <i class="fas fa-history"></i>
        <span class="menu-text">Audit Logs</span>
    </div>

    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_database.php' ? 'active' : ''; ?>" onclick="window.location.href='admin_database.php'">
        <i class="fas fa-database"></i>
        <span class="menu-text">Database</span>
    </div>

    <!-- Reports Section -->
    <div class="menu-item section-start">
        <i class="fas fa-chart-bar"></i>
        <span class="menu-text">Reports</span>
    </div>

    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_reports.php' ? 'active' : ''; ?>" onclick="window.location.href='admin_reports.php'">
        <i class="fas fa-file-alt"></i>
        <span class="menu-text">System Reports</span>
    </div>

    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_analytics.php' ? 'active' : ''; ?>" onclick="window.location.href='admin_analytics.php'">
        <i class="fas fa-chart-line"></i>
        <span class="menu-text">Analytics</span>
    </div>

    <!-- Account Section -->
    <div class="menu-item section-start" style="margin-top: auto;">
        <i class="fas fa-user"></i>
        <span class="menu-text">Account</span>
    </div>

    <div class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" onclick="window.location.href='profile.php'">
        <i class="fas fa-user-circle"></i>
        <span class="menu-text">Profile</span>
    </div>

    <div class="menu-item" onclick="window.location.href='logout.php'">
        <i class="fas fa-sign-out-alt"></i>
        <span class="menu-text">Logout</span>
    </div>
</div>

<style>
    /* Left Panel Styles */
    .left-panel {
        width: 280px;
        background: white;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        padding: 20px;
        overflow-y: auto;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        z-index: 100;
        display: flex;
        flex-direction: column;
    }

    .brand-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e2e8f0;
    }

    .brand-logo img {
        height: 40px;
        width: auto;
    }

    .menu-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 15px;
        margin-bottom: 8px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        color: #4a5568;
        font-weight: 500;
        user-select: none;
    }

    .menu-item:hover {
        background-color: #f7fafc;
        color: #667eea;
    }

    .menu-item.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .menu-item.active i {
        color: white;
    }

    .menu-item i {
        width: 20px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .menu-item.section-start {
        font-size: 0.75em;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #a0aec0;
        cursor: default;
        padding: 20px 15px 10px 15px;
        margin-top: 15px;
        border-top: 1px solid #e2e8f0;
    }

    .menu-item.section-start:hover {
        background-color: transparent;
        color: #a0aec0;
    }

    .menu-text {
        font-size: 0.95em;
    }

    /* Scrollbar styling */
    .left-panel::-webkit-scrollbar {
        width: 6px;
    }

    .left-panel::-webkit-scrollbar-track {
        background: transparent;
    }

    .left-panel::-webkit-scrollbar-thumb {
        background: #cbd5e0;
        border-radius: 3px;
    }

    .left-panel::-webkit-scrollbar-thumb:hover {
        background: #a0aec0;
    }

    @media (max-width: 768px) {
        .left-panel {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .left-panel.show {
            transform: translateX(0);
        }
    }
</style>
