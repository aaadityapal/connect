<?php
session_start();
$username = 'Design awesome';
$email = 'design.awesome@gmail.com';
if (isset($_SESSION['user_id'])) {
    // Adjust the path to config depending on where the calling file is executing from.
    // If it's loaded from studio_users/index.php, the working dimen for fetch is studio_users/
    require_once '../../config/db_connect.php';
    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        $username = $user['username'];
        $email = $user['email'];
    }
}
?>
<!-- =====================================================
     REUSABLE SIDEBAR COMPONENT
     Usage: loaded dynamically by components/sidebar-loader.js
     Do NOT add <html>, <head>, or <body> tags here.
====================================================== -->

<!-- Mobile Overlay -->
<div class="mobile-sidebar-overlay" id="mobileSidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar collapsed" id="appSidebar">

    <!-- Collapse / Expand Toggle -->
    <button class="toggle-btn" id="sidebarToggleBtn" title="Toggle Sidebar">
        <i data-lucide="chevron-left" class="toggle-icon" style="width:14px;height:14px;"></i>
    </button>

    <!-- Brand Header -->
    <div class="sidebar-header">
        <div class="brand-logo">
            <img src="https://raw.githubusercontent.com/aaadityapal/connect/refs/heads/main/images/logo.png" alt="Logo"
                style="width:36px;height:36px;object-fit:contain;border-radius:6px;">
        </div>
        <div class="header-text">
            <span class="header-title">
                <?php echo htmlspecialchars($username); ?>
            </span>
            <span class="header-subtitle">
                <?php echo htmlspecialchars($email); ?>
            </span>
        </div>
    </div>

    <!-- Navigation -->
    <div class="sidebar-nav">

        <div class="menu-title">Main</div>
        <a href="#" onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'index.php'; return false;"
            class="menu-item" data-page="index">
            <i data-lucide="layout-dashboard" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Dashboard</span>
            <div class="tooltip">Dashboard</div>
        </a>

        <div class="menu-title">Personal</div>
        <a href="#" onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'profile/index.php'; return false;"
            class="menu-item" data-page="profile">
            <i data-lucide="user-round" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">My Profile</span>
            <div class="tooltip">My Profile</div>
        </a>

        <div class="menu-title">Leave &amp; Expenses</div>
        <a href="apply-leave.php" class="menu-item" data-page="apply-leave">
            <i data-lucide="calendar-check" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Apply Leave</span>
            <div class="tooltip">Apply Leave</div>
        </a>
        <a href="travel-expenses.php" class="menu-item" data-page="travel-expenses">
            <i data-lucide="receipt" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Travel Expenses</span>
            <div class="tooltip">Travel Expenses</div>
        </a>
        <a href="overtime.php" class="menu-item" data-page="overtime">
            <i data-lucide="alarm-clock" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Overtime</span>
            <div class="tooltip">Overtime</div>
        </a>

        <div class="menu-title">Work</div>
        <a href="projects.php" class="menu-item" data-page="projects">
            <i data-lucide="folder-kanban" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Projects</span>
            <div class="tooltip">Projects</div>
        </a>
        <a href="site-updates.php" class="menu-item" data-page="site-updates">
            <i data-lucide="megaphone" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Site Updates</span>
            <div class="tooltip">Site Updates</div>
        </a>
        <a href="my-tasks.php" class="menu-item" data-page="my-tasks">
            <i data-lucide="circle-check-big" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">My Tasks</span>
            <div class="tooltip">My Tasks</div>
        </a>
        <a href="#"
            onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'attendance_recrds/index.php'; return false;"
            class="menu-item" data-page="worksheet">
            <i data-lucide="file-spreadsheet" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Work Sheet &amp; Attendance</span>
            <div class="tooltip">Work Sheet</div>
        </a>
        <a href="analytics.php" class="menu-item" data-page="analytics">
            <i data-lucide="trending-up" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Performance Analytics</span>
            <div class="tooltip">Analytics</div>
        </a>

        <?php if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'relationship manager'): ?>
        <div class="menu-title">Management</div>
        <a href="#" onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'hierarchy.php'; return false;"
            class="menu-item" data-page="hierarchy">
            <i data-lucide="network" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Team Hierarchy</span>
            <div class="tooltip">Hierarchy</div>
        </a>
        <?php
endif; ?>

        <div class="menu-title">System</div>
        <a href="settings.php" class="menu-item" data-page="settings">
            <i data-lucide="settings-2" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Settings</span>
            <div class="tooltip">Settings</div>
        </a>
        <a href="help.php" class="menu-item" data-page="help">
            <i data-lucide="help-circle" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Help &amp; Support</span>
            <div class="tooltip">Help &amp; Support</div>
        </a>

        <a href="../logout.php" class="menu-item menu-item-logout" data-page="logout">
            <i data-lucide="power" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Logout</span>
            <div class="tooltip">Logout</div>
        </a>

    </div>
</aside>