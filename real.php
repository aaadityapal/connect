<?php
session_start();
// Check if user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Senior Manager (Studio)') {
    // Redirect to login page if not authorized
    header('Location: login.php');
    exit();
}

// Debug: Print session data
error_log("Session Data: " . print_r($_SESSION, true));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senior Manager Dashboard</title>
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard-styles.css">
    <link rel="stylesheet" href="assets/css/notification-system.css">
    <link rel="stylesheet" href="css/fingerprint_button.css">
    <link rel="stylesheet" href="css/fingerprint_notification.css">
</head>
<body>
    <div class="dashboard">
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
                    <a href="#">
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
                    <a href="overtime_reports.php">
                        <i class="fas fa-hourglass-half"></i>
                        <span class="sidebar-text"> Overtime Reports</span>
                    </a>
                </li>
                <li>
                    <a href="travel_report.php">
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
        
        <div class="main-content">
            
            <!-- Updated greeting section with notification icon and gradient background -->
            <div class="greeting-section">
                <div class="greeting-content">
                    <div class="greeting-info">
                        <h2 id="greeting-text">
                            <span class="sun-icon-container">
                                <i class="fas fa-sun rotating-sun"></i>
                            </span>
                            Good morning, <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </h2>
                        <div class="datetime-container">
                            <div class="time-display">
                                <i class="fas fa-clock"></i>
                                <span id="current-time">11:43 am</span>
                            </div>
                            <div class="date-display">
                                <i class="fas fa-calendar-alt"></i>
                                <span id="current-date">Thursday 27 March, 2025</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="actions-container">
                        <!-- Replace the existing notification button with this -->
                        <div class="notification-container">
                            <div class="notification-wrapper">
                                <div class="notification-icon">
                                    <i class="fas fa-bell"></i>
                                    <span class="notification-badge">0</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- New Avatar with Dropdown -->
                        <div class="avatar-container">
                            <button class="avatar-btn" id="avatarBtn">
                                <?php 
                                // Debug: Print profile picture path
                                error_log("Profile Picture Path: " . (isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'No profile picture set'));
                                ?>
                                <img src="<?php 
                                    if (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])) {
                                        echo htmlspecialchars($_SESSION['profile_picture']);
                                    } else {
                                        echo 'assets/default-avatar.png';
                                    }
                                ?>" 
                                alt="Profile" 
                                class="avatar-img">
                            </button>
                            <div class="avatar-dropdown" id="avatarDropdown">
                                <div class="dropdown-header">
                                    <img src="<?php 
                                        if (isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])) {
                                            echo htmlspecialchars($_SESSION['profile_picture']);
                                        } else {
                                            echo 'assets/default-avatar.png';
                                        }
                                    ?>" 
                                    alt="Profile" 
                                    class="dropdown-avatar">
                                    <div class="user-info">
                                        <span class="user-name"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></span>
                                        <span class="user-role"><?php echo isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'Role'; ?></span>
                                    </div>
                                </div>
                                <div class="dropdown-divider"></div>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a href="manager_profile.php">
                                            <i class="fas fa-user"></i>
                                            My Profile
                                        </a>
                                    </li>
                                    <li>
                                        <a href="logout.php">
                                            <i class="fas fa-sign-out-alt"></i>
                                            Logout
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Existing punch-in container -->
                        <div class="punch-in-container">
                            <button id="punch-button" class="punch-button">
                                <span class="punch-icon">
                                    <i class="fas fa-fingerprint"></i>
                                </span>
                                <span class="punch-text">Punch In</span>
                            </button>
                            <div class="punch-status">Not punched in today</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="content">
                <div class="dashboard-sections-wrapper">
                    <!-- Existing Employee Overview Section -->
                <div class="employee-overview">
                    <div class="section-header">
                            <div class="section-title">
                        <i class="fas fa-users"></i>
                        <h2>Employees Overview</h2>
                            </div>
                            <div class="date-filter">
                                <button class="filter-btn">
                                    <i class="fas fa-calendar"></i>
                                    <span>March 2025</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                    </div>
                    
                    <div class="employee-dashboard-layout">
                        <!-- Left side: stat cards in a 2x2 grid -->
                        <div class="stat-cards-container">
                            <!-- First row -->
                            <div class="stat-row">
                                <!-- Present Today -->
                                <div class="stat-card" data-tooltip="present-details">
                                    <div class="stat-card-icon present-icon">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                    <div class="stat-card-content">
                                        <h3>Present Today</h3>
                                        <div class="stat-number" id="present-count">0</div>
                                        <div class="stat-label" id="present-total">/ 0 Total Employees</div>
                                    </div>
                                </div>
                                
                                <!-- Pending Leaves -->
                                <div class="stat-card" data-tooltip="pending-details">
                                    <div class="stat-card-icon pending-icon">
                                        <i class="fas fa-hourglass-half"></i>
                                    </div>
                                    <div class="stat-card-content">
                                        <h3>Pending Leaves</h3>
                                        <div class="stat-number" id="pending-count">0</div>
                                        <div class="stat-label">Awaiting Approval</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Second row -->
                            <div class="stat-row">
                                <!-- Short Leave -->
                                <div class="stat-card" data-tooltip="short-leave-details">
                                    <div class="stat-card-icon short-leave-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="stat-card-content">
                                        <h3>Short Leave</h3>
                                        <div class="stat-number" id="short-leave-count">0</div>
                                        <div class="stat-label" id="short-leave-total">/ 0 Today's Short Leaves</div>
                                    </div>
                                </div>
                                
                                <!-- On Leave -->
                                <div class="stat-card" data-tooltip="on-leave-details">
                                    <div class="stat-card-icon on-leave-icon">
                                        <i class="fas fa-calendar-day"></i>
                                    </div>
                                    <div class="stat-card-content">
                                        <h3>On Leave</h3>
                                        <div class="stat-number" id="on-leave-count">0</div>
                                        <div class="stat-label" id="on-leave-total">Full Day Leave</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right side: Calendar -->
                        <div class="calendar-wrapper">
                            <div class="calendar-container">
                                <div class="calendar-header">
                                    <div class="calendar-navigation">
                                        <button class="calendar-nav prev">
                                            <i class="fas fa-chevron-left"></i>
                                        </button>
                                        <h3 id="calendar-month">March 2025</h3>
                                        <button class="calendar-nav next">
                                            <i class="fas fa-chevron-right"></i>
                                        </button>
                                    </div>
                                    <div class="calendar-legend">
                                        <div class="legend-item">
                                            <span class="legend-dot present"></span>
                                            <span>Present</span>
                                        </div>
                                        <div class="legend-item">
                                            <span class="legend-dot leave"></span>
                                            <span>On Leave</span>
                                        </div>
                                        <div class="legend-item">
                                            <span class="legend-dot holiday"></span>
                                            <span>Holiday</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="calendar-body">
                                    <div class="calendar-weekdays">
                                        <div>Sun</div>
                                        <div>Mon</div>
                                        <div>Tue</div>
                                        <div>Wed</div>
                                        <div>Thu</div>
                                        <div>Fri</div>
                                        <div>Sat</div>
                                    </div>
                                    <div class="calendar-days" id="calendar-days">
                                        <!-- Days will be inserted by JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                    <!-- Leaves Section -->
                    <div class="leaves-section">
                        <div class="section-header">
                            <div class="section-title">
                                <i class="fas fa-calendar-check"></i>
                                <h2>Recent Leaves</h2>
                            </div>
                            <div class="leaves-filter">
                                <select id="leaveTypeFilter">
                                    <option value="all">All Types</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="leaves-content">
                            <!-- Scrollable container for leave cards -->
                            <div class="leaves-scroll-container" id="leavesContainer">
                                <div class="leaves-grid" id="leavesGrid">
                                    <!-- Leave cards will be inserted here -->
                                </div>
                            </div>
                            
                            <!-- Empty state -->
                            <div class="leaves-empty-state" style="display: none;">
                                <i class="fas fa-calendar-xmark"></i>
                                <p>No leave requests found</p>
                            </div>
                            
                            <!-- Loading state -->
                            <div class="leaves-loading">
                                <div class="spinner"></div>
                                <p>Loading leaves...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Project Overview Section with toggle functionality -->
                <div class="project-overview-section" id="projectOverviewContainer">
                    <div class="section-header project-header">
                        <!-- Left side: Title and Add Project button -->
                        <div class="title-with-action">
                            <div class="section-title">
                                <i class="fas fa-project-diagram"></i>
                                <h2>Project Overview</h2>
                            </div>
                            
                            <!-- Add Project Button (next to title) -->
                            <button class="add-project-btn-minimal" id="openProjectModal">
                                <i class="fas fa-plus"></i>
                                Add Project
                            </button>
                        </div>
                        
                        <!-- Center: Toggle Switch -->
                        <div class="project-view-toggle">
                            <span class="toggle-label active" id="projectViewStatusLabel">Quick View</span>
                            <label class="toggle-switch">
                                <input type="checkbox" id="projectViewToggleSwitch">
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="toggle-label" id="projectViewDeptLabel">Calendar View</span>
                        </div>
                        
                        <!-- Right side: Date Filter -->
                        <div class="project-date-filter">
                            <div class="date-range-inputs">
                                <div class="date-input-group">
                                    <label>From</label>
                                    <div class="date-input-wrapper">
                                        <input type="text" class="date-input" id="project-date-from" value="01-03-2025" readonly>
                                        <button class="date-picker-button">
                                            <i class="fas fa-calendar-alt small-calendar"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="date-input-group">
                                    <label>To</label>
                                    <div class="date-input-wrapper">
                                        <input type="text" class="date-input" id="project-date-to" value="31-03-2025" readonly>
                                        <button class="date-picker-button">
                                            <i class="fas fa-calendar-alt small-calendar"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button class="apply-filter-btn">Apply</button>
                        </div>
                    </div>
                    
                    <!-- Project Stats View (default view) -->
                    <div class="project-stats-container" id="projectStatisticsView">
                        <!-- Total Projects Card -->
                        <div class="project-stat-card" data-tooltip="total-projects-details">
                            <div class="project-stat-icon total-projects-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="project-stat-content">
                                <h3>Total Projects</h3>
                                <div class="project-stat-number">0</div>
                                <div class="project-stat-label">Active + Completed</div>
                            </div>
                        </div>
                        
                        <!-- In Progress Card -->
                        <div class="project-stat-card" data-tooltip="in-progress-details">
                            <div class="project-stat-icon in-progress-icon">
                                <i class="fas fa-spinner"></i>
                            </div>
                            <div class="project-stat-content">
                                <h3>In Progress</h3>
                                <div class="project-stat-number">8</div>
                                <div class="project-stat-label">Projects underway</div>
                            </div>
                        </div>
                        
                        <!-- Completed Card -->
                        <div class="project-stat-card" data-tooltip="completed-details">
                            <div class="project-stat-icon completed-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="project-stat-content">
                                <h3>Completed</h3>
                                <div class="project-stat-number">4</div>
                                <div class="project-stat-label">Projects delivered</div>
                            </div>
                        </div>
                        
                        <!-- Overdue Card -->
                        <div class="project-stat-card" data-tooltip="overdue-details">
                            <div class="project-stat-icon overdue-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="project-stat-content">
                                <h3>Overdue</h3>
                                <div class="project-stat-number">0</div>
                                <div class="project-stat-label">Past deadline</div>
                            </div>
                        </div>
                        
                        <!-- Stages Pending Card -->
                        <div class="project-stat-card" data-tooltip="stages-pending-details">
                            <div class="project-stat-icon stages-pending-icon">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <div class="project-stat-content">
                                <h3>Stages Pending</h3>
                                <div class="project-stat-number">0</div>
                                <div class="project-stat-label">Awaiting completion</div>
                            </div>
                        </div>
                        
                        <!-- Substages Pending Card -->
                        <div class="project-stat-card" data-tooltip="substages-pending-details">
                            <div class="project-stat-icon substages-pending-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="project-stat-content">
                                <h3>Substages Pending</h3>
                                <div class="project-stat-number">0</div>
                                <div class="project-stat-label">Tasks to be completed</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Project Calendar View (hidden by default) -->
                    <div class="project-calendar-view" id="projectDepartmentCalendarView" style="display: none;">
                        <!-- Calendar header -->
                        <div class="project-calendar-header">
                            <div class="project-calendar-nav">
                                <button class="project-calendar-prev-btn"><i class="fas fa-chevron-left"></i></button>
                                <h3 class="project-calendar-month">March 2025</h3>
                                <button class="project-calendar-next-btn"><i class="fas fa-chevron-right"></i></button>
                            </div>
                            <div class="project-calendar-views">
                                <button class="project-calendar-view-btn active" data-view="month">Month</button>
                                <button class="project-calendar-view-btn" data-view="week">Week</button>
                                <button class="project-calendar-view-btn" data-view="day">Day</button>
                            </div>
                        </div>
                        
                        <!-- Calendar grid -->
                        <div class="project-calendar-grid">
                            <!-- Calendar weekdays -->
                            <div class="project-calendar-weekdays">
                                <div>Sun</div>
                                <div>Mon</div>
                                <div>Tue</div>
                                <div>Wed</div>
                                <div>Thu</div>
                                <div>Fri</div>
                                <div>Sat</div>
                            </div>
                            
                            <!-- Calendar days -->
                            <div class="project-calendar-days">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                        
                        <!-- Project Type legend -->
                        <div class="project-department-legend">
                            <h4>Project Types</h4>
                            <div class="project-department-items">
                                <div class="project-department-item">
                                    <span class="project-dept-color" style="background-color: #4361ee;"></span>
                                    <span class="project-dept-name">Architecture</span>
                                </div>
                                <div class="project-department-item">
                                    <span class="project-dept-color" style="background-color: #10B981;"></span>
                                    <span class="project-dept-name">Interior</span>
                                </div>
                                <div class="project-department-item">
                                    <span class="project-dept-color" style="background-color: #F59E0B;"></span>
                                    <span class="project-dept-name">Construction</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tooltip for Present Users -->
    <div class="tooltip" id="present-details">
        <div class="tooltip-header">
            <i class="fas fa-user-check"></i>
            <h4>Present Employees</h4>
        </div>
        <div class="tooltip-content">
            <div class="tooltip-stats">
                <span class="attendance-stat">On Time: <span id="ontime-count">0</span></span>
                <span class="attendance-stat">Late: <span id="late-count">0</span></span>
            </div>
            <div class="employee-list" id="present-employees-list">
                <div class="loading-spinner">Loading...</div>
            </div>
        </div>
    </div>

    <!-- Tooltips -->
    <div class="tooltip" id="pending-details">
        <div class="tooltip-header">
            <i class="fas fa-hourglass-half"></i>
            <h4>Pending Leave Requests</h4>
        </div>
        <div class="tooltip-content">
            <div class="employee-list" id="pending-leaves-list">
                <div class="loading-spinner">Loading...</div>
            </div>
        </div>
    </div>

    <div class="tooltip" id="short-leave-details">
        <div class="tooltip-header">
            <i class="fas fa-clock"></i>
            <h4>Short Leaves Today</h4>
        </div>
        <div class="tooltip-content">
            <div class="employee-list" id="short-leaves-list">
                <div class="loading-spinner">Loading...</div>
            </div>
        </div>
    </div>

    <div class="tooltip" id="on-leave-details">
        <div class="tooltip-header">
            <i class="fas fa-calendar-day"></i>
            <h4>Employees on Leave</h4>
        </div>
        <div class="tooltip-content">
            <div class="employee-list" id="on-leave-list">
                <div class="loading-spinner">Loading...</div>
            </div>
        </div>
    </div>

    <!-- Add tooltips for project cards -->
    <div class="project-tooltip" id="total-projects-details">
        <div class="project-tooltip-header">
            <i class="fas fa-tasks"></i>
            <h4>Total Projects</h4>
        </div>
        <div class="project-tooltip-content">
            <ul class="project-list" id="projects-list">
                <!-- Will be populated dynamically -->
            </ul>
        </div>
    </div>

    <!-- Stages Pending Tooltip -->
    <div class="project-tooltip" id="stages-pending-details">
        <div class="project-tooltip-header">
            <i class="fas fa-layer-group"></i>
            <h4>Pending Stages</h4>
        </div>
        <div class="project-tooltip-content">
            <div class="tooltip-summary">
                <span class="tooltip-count">0 stages pending</span>
            </div>
            <ul class="project-list stages-list">
                <!-- Will be populated by JavaScript -->
            </ul>
        </div>
    </div>

    <!-- Substages Pending Tooltip -->
    <div class="project-tooltip" id="substages-pending-details">
        <div class="project-tooltip-header">
            <i class="fas fa-tasks"></i>
            <h4>Pending Substages</h4>
        </div>
        <div class="project-tooltip-content">
            <div class="tooltip-summary">
                <span class="tooltip-count">0 substages pending</span>
            </div>
            <ul class="project-list substages-list">
                <!-- Will be populated by JavaScript -->
            </ul>
        </div>
    </div>

    <!-- Add project modal and toast container at the end of the body before scripts -->
    <div id="modalContainer"></div>

    <!-- Work Report Modal -->
    <div class="work-report-modal" id="workReportModal">
        <div class="work-report-content">
            <div class="work-report-header">
                <h3>Daily Work Report</h3>
                <button class="close-modal" id="closeWorkReport">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="work-report-body">
                <div class="form-group">
                    <label for="workReport">Please describe your work for today:</label>
                    <textarea id="workReport" rows="5" placeholder="Enter your work description here..."></textarea>
                </div>
            </div>
            <div class="work-report-footer">
                <button class="cancel-btn" id="cancelWorkReport">Cancel</button>
                <button class="submit-btn" id="submitWorkReport">Submit & Punch Out</button>
            </div>
        </div>
    </div>

    <!-- Leave Action Modal -->
    <div class="leave-action-modal" id="leaveActionModal">
        <div class="leave-action-content">
            <div class="leave-action-header">
                <h3 id="modalTitle">Approve Leave Request</h3>
                <button class="close-modal" id="closeLeaveModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="leave-action-body">
                <div class="leave-user-preview">
                    <img src="" alt="" id="modalUserAvatar" class="modal-user-avatar">
                    <div class="leave-user-details">
                        <span id="modalUsername" class="modal-username"></span>
                        <span id="modalLeaveType" class="modal-leave-type"></span>
                        <span id="modalLeaveDates" class="modal-leave-dates"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="actionReason">Reason for <span id="actionType">approval</span>:</label>
                    <textarea id="actionReason" rows="3" placeholder="Enter your reason..."></textarea>
                </div>
            </div>
            <div class="leave-action-footer">
                <button class="cancel-btn" id="cancelLeaveAction">Cancel</button>
                <button class="submit-btn" id="submitLeaveAction">Submit</button>
            </div>
        </div>
    </div>

    <script src="dashboard-script.js"></script>
    <!-- Add project form script -->
    <script src="modals/scripts/project_form_handler_v1.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Load the project modal HTML
            fetch('modals/project_form.php')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('modalContainer').innerHTML = html;
                    
                    // Set up event listener for opening the modal
                    document.getElementById('openProjectModal').addEventListener('click', function() {
                        const projectModal = document.getElementById('projectModal');
                        projectModal.style.display = 'flex';
                        setTimeout(() => {
                            projectModal.classList.add('active');
                        }, 10);
                    });
                })
                .catch(error => console.error('Error loading project modal:', error));
                
            // Add project form styles
            const projectFormStyles = document.createElement('link');
            projectFormStyles.rel = 'stylesheet';
            projectFormStyles.href = 'modals/styles/project_form_styles_v1.css';
            document.head.appendChild(projectFormStyles);
        });
    </script>
    <script src="assets/js/notification-handler.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Load notification modal
        fetch('components/notification-modal.php')
            .then(response => response.text())
            .then(html => {
                document.body.insertAdjacentHTML('beforeend', html);
                // Initialize notification system
                window.notificationSystem = new NotificationSystem();
            })
            .catch(error => {
                console.error('Error loading notification modal:', error);
            });
    });
    </script>
    <script src="assets/js/stage-chat.js"></script>
    <script src="js/fingerprint_download.js"></script>
</body>
</html>