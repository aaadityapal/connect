<?php
// This is the reusable sidebar component
?>
<div class="sidebar" id="sidebar">
    <div class="toggle-btn" id="toggle-btn">
        <i class="fas fa-chevron-left"></i>
    </div>
    
    <div class="sidebar-header">
        <h3 class="sidebar-text">MAIN</h3>
    </div>
    
    <ul class="sidebar-menu">
        <li class="active">
            <a href="#">
                <i class="fas fa-tachometer-alt"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
        </li>
        <li>
            <a href="#">
                <i class="fas fa-calendar-check"></i>
                <span class="sidebar-text">Leaves</span>
            </a>
        </li>
        <li>
            <a href="#">
                <i class="fas fa-users"></i>
                <span class="sidebar-text">Employees</span>
            </a>
        </li>
        <li>
            <a href="projects.php">
                <i class="fas fa-box"></i>
                <span class="sidebar-text">Projects</span>
            </a>
        </li>
    </ul>
    
    <div class="sidebar-header">
        <h3 class="sidebar-text">ANALYTICS</h3>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="#">
                <i class="fas fa-chart-line"></i>
                <span class="sidebar-text"> Employee Reports</span>
            </a>
        </li>
        <li>
            <a href="work_report.php">
                <i class="fas fa-file-invoice"></i>
                <span class="sidebar-text"> Work Reports</span>
            </a>
        </li>
        <li>
            <a href="attendance_report.php">
                <i class="fas fa-clock"></i>
                <span class="sidebar-text"> Attendance Reports</span>
            </a>
        </li>
        <li>
            <a href="attendance_approval.php">
                <i class="fas fa-calendar-check"></i>
                <span class="sidebar-text"> Attendance Approval</span>
            </a>
        </li>
        <li>
            <a href="overtime_dashboard.php">
                <i class="fas fa-hourglass-half"></i>
                <span class="sidebar-text"> Overtime Reports</span>
            </a>
        </li>
        <li>
            <a href="travelling_allowancest.php">
                <i class="fas fa-plane"></i>
                <span class="sidebar-text"> Travel Reports</span>
            </a>
        </li>
        
    </ul>
    
    <div class="sidebar-header">
        <h3 class="sidebar-text">SETTINGS</h3>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="manager_profile.php">
                <i class="fas fa-user"></i>
                <span class="sidebar-text">Profile</span>
            </a>
        </li>
        <li>
            <a href="#">
                <i class="fas fa-bell"></i>
                <span class="sidebar-text">Notifications</span>
            </a>
        </li>
        <li>
            <a href="manager_settings.php">
                <i class="fas fa-cog"></i>
                <span class="sidebar-text">Settings</span>
            </a>
        </li>
        <li>
            <a href="reset_password.php">
                <i class="fas fa-lock"></i>
                <span class="sidebar-text">Reset Password</span>
            </a>
        </li>
    </ul>

    <!-- Add logout at the end of sidebar -->
    <div class="sidebar-footer">
        <ul class="sidebar-menu">
            <li>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="sidebar-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>
</div>