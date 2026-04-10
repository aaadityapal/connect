<?php
session_start();
$username = 'Design awesome';
$email = 'design.awesome@gmail.com';
$dbRole = null;

function normalizeRoleName($role): string {
    $role = (string)$role;
    $role = trim($role);
    $role = preg_replace('/\s+/u', ' ', $role);
    return $role ?? '';
}
if (isset($_SESSION['user_id'])) {
    // Adjust the path to config depending on where the calling file is executing from.
    // If it's loaded from studio_users/index.php, the working dimen for fetch is studio_users/
    require_once '../../config/db_connect.php';
    $stmt = $pdo->prepare("SELECT username, email, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        $username = $user['username'];
        $email = $user['email'];
        $dbRole = normalizeRoleName($user['role'] ?? '');
        if ($dbRole !== '') {
            $_SESSION['role'] = $dbRole;
        }
    }
}

// Role-based access: fetch dynamic permissions
$_userRole = $dbRole ?? normalizeRoleName($_SESSION['role'] ?? '');
$_userRoleLower = strtolower($_userRole);
$_isAdmin = ($_userRoleLower === 'admin');

$_permissions = [];
if (isset($pdo)) {
    $stmtPerms = $pdo->prepare("SELECT menu_id, can_access FROM sidebar_permissions WHERE role = ?");
    $stmtPerms->execute([$_userRole]);
    $results = $stmtPerms->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        $_permissions[$row['menu_id']] = (int)$row['can_access'];
    }
}

function canShow($menuId, $perms) {
    // Admin always sees everything
    global $_isAdmin;
    if ($_isAdmin) return true;
    
    // Otherwise, require explicit 'can_access = 1'
    if (!isset($perms[$menuId])) return false; 
    return $perms[$menuId] === 1;
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

        <?php if (canShow('index', $_permissions)): ?>
        <div class="menu-title">Main</div>
        <a href="#" onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'index.php'; return false;"
            class="menu-item" data-page="index">
            <i data-lucide="layout-dashboard" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Dashboard</span>
            <div class="tooltip">Dashboard</div>
        </a>
        <?php endif; ?>

        <?php if (canShow('profile', $_permissions)): ?>
        <div class="menu-title">Personal</div>
        <a href="#"
            onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'profile/index.php'; return false;"
            class="menu-item" data-page="profile">
            <i data-lucide="user-round" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">My Profile</span>
            <div class="tooltip">My Profile</div>
        </a>
        <?php endif; ?>

        <?php 
        $showLeave = canShow('apply-leave', $_permissions);
        $showTravel = canShow('travel-expenses', $_permissions);
        $showOvertime = canShow('overtime', $_permissions);
        if ($showLeave || $showTravel || $showOvertime): 
        ?>
        <div class="menu-title">Leave &amp; Expenses</div>
        <?php if ($showLeave): ?>
        <a href="#"
            onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'leave_pages/index.php'; return false;"
            class="menu-item" data-page="apply-leave">
            <i data-lucide="calendar-check" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Apply Leave</span>
            <div class="tooltip">Apply Leave</div>
        </a>
        <?php endif; ?>
        <?php if ($showTravel): ?>
        <a href="#"
            onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'travel_exp/index.php'; return false;"
            class="menu-item" data-page="travel-expenses">
            <i data-lucide="receipt" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Travel Expenses</span>
            <div class="tooltip">Travel Expenses</div>
        </a>
        <?php endif; ?>
        <?php if ($showOvertime): ?>
        <a href="#"
            onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'overtime_page/index.php'; return false;"
            class="menu-item" data-page="overtime">
            <i data-lucide="alarm-clock" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Overtime</span>
            <div class="tooltip">Overtime</div>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php 
        $showProjects = canShow('projects', $_permissions);
        $showUpdates = canShow('site-updates', $_permissions);
        $showTasks = canShow('my-tasks', $_permissions);
        $showWorksheet = canShow('worksheet', $_permissions);
        $showAnalytics = canShow('analytics', $_permissions);
        if ($showProjects || $showUpdates || $showTasks || $showWorksheet || $showAnalytics):
        ?>
        <div class="menu-title">Work</div>
        <?php if ($showProjects): ?>
        <a href="#"
            onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + '../manager_pages/projects/index.php'; return false;"
            class="menu-item" data-page="projects">
            <i data-lucide="folder-kanban" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Projects</span>
            <div class="tooltip">Projects</div>
        </a>
        <?php endif; ?>
        <?php if ($showUpdates): ?>
        <a href="site-updates.php" class="menu-item" data-page="site-updates">
            <i data-lucide="megaphone" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Site Updates</span>
            <div class="tooltip">Site Updates</div>
        </a>
        <?php endif; ?>
        <?php if ($showTasks): ?>
        <a href="my-tasks.php" class="menu-item" data-page="my-tasks">
            <i data-lucide="circle-check-big" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">My Tasks</span>
            <div class="tooltip">My Tasks</div>
        </a>
        <?php endif; ?>
        <?php if ($showWorksheet): ?>
        <a href="#"
            onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'attendance_recrds/index.php'; return false;"
            class="menu-item" data-page="worksheet">
            <i data-lucide="file-spreadsheet" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Work Sheet &amp; Attendance</span>
            <div class="tooltip">Work Sheet</div>
        </a>
        <?php endif; ?>
        <?php if ($showAnalytics): ?>
        <a href="#"
            onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + '../performance.php'; return false;"
            class="menu-item" data-page="analytics">
            <i data-lucide="trending-up" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Performance Analytics</span>
            <div class="tooltip">Analytics</div>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (canShow('hr-corner', $_permissions)): ?>
            <div class="menu-title">HR &amp; Admin</div>
            <a href="javascript:void(0)"
                onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'hr_backend/index.php'; return false;"
                class="menu-item" data-page="hr-corner"
                style="cursor:pointer; display:flex !important; align-items:center !important; width:100%;">
                <i data-lucide="briefcase" class="menu-icon" style="width:17px;height:17px; flex-shrink:0;"></i>
                <span class="menu-text">HR Corner</span>
                <div class="tooltip">HR Corner</div>
            </a>
        <?php endif; ?>

        <?php 
        $showLeaveApp = canShow('leave-approval-mng', $_permissions);
        $showTravelApp = canShow('travel-exp-approval-mng', $_permissions);
        $showHierarchy = canShow('hierarchy', $_permissions);
        $showMngMap = canShow('manager-mapping', $_permissions);
        $showOtMap = canShow('overtime-mapping', $_permissions);
        $showTxMap = canShow('travel-exp-mapping', $_permissions);
        $showPwReset = canShow('password-reset-mng', $_permissions);
        $showWorkReport = canShow('employee-work-report', $_permissions);
        $showEmployeesProfile = canShow('employees-profile', $_permissions);
        $showEmployeesAttendance = canShow('employees-attendance', $_permissions);
        if ($showLeaveApp || $showTravelApp || $showHierarchy || $showMngMap || $showOtMap || $showTxMap || $showPwReset || $showWorkReport || $showEmployeesProfile || $showEmployeesAttendance):
        ?>
            <div class="menu-title">Management</div>
            <?php if ($showLeaveApp): ?>
            <a href="#"
                onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + '../manager_pages/leave_approval/index.php'; return false;"
                class="menu-item" data-page="leave-approval-mng">
                <i data-lucide="calendar-days" class="menu-icon" style="width:17px;height:17px;"></i>
                <span class="menu-text">Leave Approval</span>
                <div class="tooltip">Leave Approval</div>
            </a>
            <?php endif; ?>
            <?php if ($showTravelApp): ?>
            <a href="#"
                onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + '../manager_pages/travel_expenses_approval/index.php'; return false;"
                class="menu-item" data-page="travel-exp-approval-mng">
                <i data-lucide="wallet" class="menu-icon" style="width:17px;height:17px;"></i>
                <span class="menu-text">Travel Exp Appr</span>
                <div class="tooltip">Travel Exp Appr</div>
            </a>
            <?php endif; ?>
            <?php if ($showHierarchy): ?>
            <a href="#" onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'hierarchy.php'; return false;"
                class="menu-item" data-page="hierarchy">
                <i data-lucide="network" class="menu-icon" style="width:17px;height:17px;"></i>
                <span class="menu-text">Team Hierarchy</span>
                <div class="tooltip">Hierarchy</div>
            </a>
            <?php endif; ?>
            <?php if ($showMngMap): ?>
            <a href="#"
                onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'manager_mapping.php'; return false;"
                class="menu-item" data-page="manager-mapping">
                <i data-lucide="users-2" class="menu-icon" style="width:17px;height:17px;"></i>
                <span class="menu-text">Manager Mapping</span>
                <div class="tooltip">Manager Mapping</div>
            </a>
            <?php endif; ?>
            <?php if ($showOtMap): ?>
            <a href="#"
                onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'overtime_mapping.php'; return false;"
                class="menu-item" data-page="overtime-mapping">
                <i data-lucide="git-merge" class="menu-icon" style="width:17px;height:17px;"></i>
                <span class="menu-text">Overtime Mapping</span>
                <div class="tooltip">Overtime Mapping</div>
            </a>
            <?php endif; ?>
            <?php if ($showTxMap): ?>
            <a href="#"
                onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'travel_expenses_mapping.php'; return false;"
                class="menu-item" data-page="travel-exp-mapping">
                <i data-lucide="git-branch" class="menu-icon" style="width:17px;height:17px;"></i>
                <span class="menu-text">Travel Exp Mapping</span>
                <div class="tooltip">Travel Exp Mapping</div>
            </a>
            <?php endif; ?>
            <?php if (canShow('travel-exp-settings', $_permissions)): ?>
            <a href="#"
                onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'travel_exp/settings.php'; return false;"
                class="menu-item" data-page="travel-exp-settings">
                <i data-lucide="settings" class="menu-icon" style="width:17px;height:17px;"></i>
                <span class="menu-text">Travel Exp Settings</span>
                <div class="tooltip">Travel Exp Settings</div>
            </a>
            <?php endif; ?>
            <?php if ($showPwReset): ?>
            <a href="#"
                onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + '../manager_pages/password_reset/index.php'; return false;"
                class="menu-item" data-page="password-reset-mng">
                <i data-lucide="key-round" class="menu-icon" style="width:17px;height:17px;"></i>
                <span class="menu-text">Password Reset</span>
                <div class="tooltip">Password Reset</div>
            </a>
            <?php endif; ?>
            <?php if ($showWorkReport): ?>
            <a href="#"
                onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + '../manager_pages/employee_work_report/index.php'; return false;"
                class="menu-item" data-page="employee-work-report">
                <i data-lucide="file-text" class="menu-icon" style="width:17px;height:17px;"></i>
                <span class="menu-text">Employee Work Report</span>
                <div class="tooltip">Work Report</div>
            </a>
            <?php endif; ?>
            <?php if ($showEmployeesProfile): ?>
            <a href="#"
                onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + '../manager_pages/employees_profile/index.php'; return false;"
                class="menu-item" data-page="employees-profile">
                <i data-lucide="users" class="menu-icon" style="width:17px;height:17px;"></i>
                <span class="menu-text">Employees Profile</span>
                <div class="tooltip">Employees Profile</div>
            </a>
            <?php endif; ?>
            <?php if ($showEmployeesAttendance): ?>
            <a href="#"
                onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + '../manager_pages/employees_attendance/index.php'; return false;"
                class="menu-item" data-page="employees-attendance">
                <i data-lucide="user-check" class="menu-icon" style="width:17px;height:17px;"></i>
                <span class="menu-text">Employees Attendance</span>
                <div class="tooltip">Employees Attendance</div>
            </a>
            <?php endif; ?>
        <?php endif; ?>

        <div class="menu-title">System</div>
        <?php if (canShow('settings', $_permissions)): ?>
        <a href="settings.php" class="menu-item" data-page="settings">
            <i data-lucide="settings-2" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Settings</span>
            <div class="tooltip">Settings</div>
        </a>
        <?php endif; ?>

        <?php if ($_isAdmin): ?>
        <a href="#" onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'sidebar_role_access.php'; return false;"
            class="menu-item" data-page="sidebar-role-access">
            <i data-lucide="lock" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Sidebar Access</span>
            <div class="tooltip">Sidebar Access</div>
        </a>
        <?php endif; ?>

        <?php if (canShow('project-permissions', $_permissions)): ?>
        <a href="#" onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'project_permissions_access.php'; return false;"
            class="menu-item" data-page="project-permissions">
            <i data-lucide="shield-check" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Project Permission</span>
            <div class="tooltip">Project Permission</div>
        </a>
        <?php endif; ?>

        <?php if (canShow('attendance-action-permissions', $_permissions)): ?>
        <a href="#" onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'attendance_permissions_access.php'; return false;"
            class="menu-item" data-page="attendance-action-permissions">
            <i data-lucide="user-check" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Attendance Action Permission</span>
            <div class="tooltip">Attendance Action Permission</div>
        </a>
        <?php endif; ?>

        <?php if (canShow('manual-leave-permissions', $_permissions)): ?>
        <a href="#" onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + 'manual_leave_permissions.php'; return false;"
            class="menu-item" data-page="manual-leave-permissions">
            <i data-lucide="file-edit" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Manual Leave Permission</span>
            <div class="tooltip">Manual Leave Permission</div>
        </a>
        <?php endif; ?>

        <?php if (canShow('help', $_permissions)): ?>
        <a href="help.php" class="menu-item" data-page="help">
            <i data-lucide="help-circle" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Help &amp; Support</span>
            <div class="tooltip">Help &amp; Support</div>
        </a>
        <?php endif; ?>

        <a href="#" onclick="window.location.href = (window.SIDEBAR_BASE_PATH || '') + '../logout.php'; return false;"
            class="menu-item menu-item-logout" data-page="logout">
            <i data-lucide="power" class="menu-icon" style="width:17px;height:17px;"></i>
            <span class="menu-text">Logout</span>
            <div class="tooltip">Logout</div>
        </a>

    </div>
</aside>