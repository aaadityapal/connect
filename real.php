<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard-styles.css">
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
                        <i class="fas fa-shopping-cart"></i>
                        <span class="sidebar-text">Sales</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-users"></i>
                        <span class="sidebar-text">Customers</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-box"></i>
                        <span class="sidebar-text">Products</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-file-invoice"></i>
                        <span class="sidebar-text">Invoices</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="fas fa-clipboard-list"></i>
                        <span class="sidebar-text">Orders</span>
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
                        <span class="sidebar-text">Reports</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-header">
                <h3 class="sidebar-text">SETTINGS</h3>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="#">
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
                    <a href="#">
                        <i class="fas fa-cog"></i>
                        <span class="sidebar-text">Settings</span>
                    </a>
                </li>
            </ul>

            <!-- Add logout at the end of sidebar -->
            <div class="sidebar-footer">
                <ul class="sidebar-menu">
                    <li>
                        <a href="#" class="logout-btn">
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
                            Good morning
                        </h2>
                        <div class="datetime-container">
                            <div class="time-display">
                                <i class="fas fa-clock"></i>
                                <span id="current-time">9:41 AM</span>
                            </div>
                            <div class="date-display">
                                <i class="fas fa-calendar-alt"></i>
                                <span id="current-date">Monday, January 1, 2023</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="actions-container">
                        <div class="notification-container">
                            <button class="notification-btn">
                                <i class="fas fa-bell"></i>
                                <span class="notification-badge">3</span>
                            </button>
                        </div>
                        
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
                                        <div class="stat-number">4</div>
                                        <div class="stat-label">/ 16 Total Employees</div>
                                    </div>
                                </div>
                                
                                <!-- Pending Leaves -->
                                    <div class="stat-card" data-tooltip="pending-details">
                                    <div class="stat-card-icon pending-icon">
                                        <i class="fas fa-hourglass-half"></i>
                                    </div>
                                    <div class="stat-card-content">
                                        <h3>Pending Leaves</h3>
                                        <div class="stat-number">0</div>
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
                                        <div class="stat-number">0</div>
                                        <div class="stat-label">/ 16 Today's Short Leaves</div>
                                    </div>
                                </div>
                                
                                <!-- On Leave -->
                                    <div class="stat-card" data-tooltip="on-leave-details">
                                    <div class="stat-card-icon on-leave-icon">
                                        <i class="fas fa-calendar-day"></i>
                                    </div>
                                    <div class="stat-card-content">
                                        <h3>On Leave</h3>
                                        <div class="stat-number">0</div>
                                        <div class="stat-label">/ 16 Full Day Leave</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right side: calendar -->
                        <div class="calendar-wrapper">
                            <div class="calendar-container">
                                <div class="calendar-header">
                                    <div class="calendar-navigation">
                                        <button class="calendar-nav prev"><i class="fas fa-chevron-left"></i></button>
                                        <h3 id="calendar-month">March 2025</h3>
                                        <button class="calendar-nav next"><i class="fas fa-chevron-right"></i></button>
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
                                        <!-- Calendar days will be inserted by JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                    <!-- New Leaves Section -->
                    <div class="leaves-section">
                        <!-- Leaves Section Header -->
                        <div class="section-header">
                            <div class="section-title">
                                <i class="fas fa-calendar-check"></i>
                                <h2>Leaves</h2>
                            </div>
                        </div>
                        
                        <div class="leaves-content">
                            <!-- Add your leaves content here -->
                            <div class="leave-card">
                                <div class="leave-card-header">
                                    <i class="fas fa-user"></i>
                                    <h3>John Doe</h3>
                                </div>
                                <div class="leave-card-body">
                                    <p class="leave-type">Sick Leave</p>
                                    <p class="leave-date">Dec 15 - Dec 16</p>
                                    <span class="leave-status pending">Pending</span>
                                </div>
                            </div>
                            
                            <!-- Add more leave cards as needed -->
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
                            <button class="add-project-btn-minimal">
                                <i class="fas fa-plus"></i>
                                Add Project
                            </button>
                        </div>
                        
                        <!-- Center: Toggle Switch -->
                        <div class="project-view-toggle">
                            <span class="toggle-label active" id="projectViewStatusLabel">By Status</span>
                            <label class="toggle-switch">
                                <input type="checkbox" id="projectViewToggleSwitch">
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="toggle-label" id="projectViewDeptLabel">By Department</span>
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
                        <!-- Project Stat Card 1: Total Projects -->
                        <div class="project-stat-card" data-tooltip="total-projects-details">
                            <div class="project-stat-icon total-projects-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="project-stat-content">
                                <h3>Total Projects</h3>
                                <div class="project-stat-number">12</div>
                                <div class="project-stat-label">Active + Completed</div>
                            </div>
                        </div>
                        
                        <!-- Project Stat Card 2: In Progress -->
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
                        
                        <!-- Project Stat Card 3: Completed -->
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
                        
                        <!-- Project Stat Card 4: Upcoming Deadlines -->
                        <div class="project-stat-card" data-tooltip="deadline-details">
                            <div class="project-stat-icon deadline-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="project-stat-content">
                                <h3>Upcoming Deadlines</h3>
                                <div class="project-stat-number">3</div>
                                <div class="project-stat-label">Within 7 days</div>
                            </div>
                        </div>
                        
                        <!-- Project Stat Card 5: Budget Utilization -->
                        <div class="project-stat-card" data-tooltip="budget-details">
                            <div class="project-stat-icon budget-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="project-stat-content">
                                <h3>Budget Utilization</h3>
                                <div class="project-stat-number">78%</div>
                                <div class="project-stat-label">$156K / $200K</div>
                            </div>
                        </div>
                        
                        <!-- Project Stat Card 6: Project Issues -->
                        <div class="project-stat-card" data-tooltip="issues-details">
                            <div class="project-stat-icon issues-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="project-stat-content">
                                <h3>Project Issues</h3>
                                <div class="project-stat-number">5</div>
                                <div class="project-stat-label">Requires attention</div>
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
                        
                        <!-- Department legend -->
                        <div class="project-department-legend">
                            <h4>Departments</h4>
                            <div class="project-department-items">
                                <div class="project-department-item">
                                    <span class="project-dept-color" style="background-color: #4361ee;"></span>
                                    <span class="project-dept-name">Engineering</span>
                                </div>
                                <div class="project-department-item">
                                    <span class="project-dept-color" style="background-color: #10B981;"></span>
                                    <span class="project-dept-name">Marketing</span>
                                </div>
                                <div class="project-department-item">
                                    <span class="project-dept-color" style="background-color: #F59E0B;"></span>
                                    <span class="project-dept-name">Sales</span>
                                </div>
                                <div class="project-department-item">
                                    <span class="project-dept-color" style="background-color: #EC4899;"></span>
                                    <span class="project-dept-name">Design</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add tooltip containers at the end of the body -->
    <div class="tooltip" id="present-details">
        <div class="tooltip-header">
            <i class="fas fa-user-check"></i>
            <h4>Present Employees</h4>
        </div>
        <div class="tooltip-content">
            <ul class="employee-list">
                <li>
                    <span class="employee-name">John Doe</span>
                    <span class="time-info">09:00 AM</span>
                </li>
                <li>
                    <span class="employee-name">Jane Smith</span>
                    <span class="time-info">08:45 AM</span>
                </li>
                <!-- Add more employees as needed -->
            </ul>
        </div>
    </div>

    <!-- Similar tooltips for other stat cards -->
    <div class="tooltip" id="pending-details">
        <div class="tooltip-header">
            <i class="fas fa-hourglass-half"></i>
            <h4>Pending Leave Requests</h4>
        </div>
        <div class="tooltip-content">
            <ul class="employee-list">
                <li>
                    <span class="employee-name">No pending requests</span>
                    <span class="time-info">-</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="tooltip" id="short-leave-details">
        <div class="tooltip-header">
            <i class="fas fa-clock"></i>
            <h4>Short Leaves Today</h4>
        </div>
        <div class="tooltip-content">
            <ul class="employee-list">
                <li>
                    <span class="employee-name">No short leaves today</span>
                    <span class="time-info">-</span>
                </li>
            </ul>
        </div>
    </div>

    <div class="tooltip" id="on-leave-details">
        <div class="tooltip-header">
            <i class="fas fa-calendar-day"></i>
            <h4>Employees on Leave</h4>
        </div>
        <div class="tooltip-content">
            <ul class="employee-list">
                <li>
                    <span class="employee-name">No employees on leave</span>
                    <span class="time-info">-</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Add tooltips for project cards -->
    <div class="project-tooltip" id="total-projects-details">
        <div class="project-tooltip-header">
            <i class="fas fa-tasks"></i>
            <h4>Total Projects</h4>
        </div>
        <div class="project-tooltip-content">
            <ul class="project-list">
                <li>
                    <span class="project-name">Website Redesign</span>
                    <span class="project-status in-progress">In Progress</span>
                </li>
                <li>
                    <span class="project-name">Mobile App Development</span>
                    <span class="project-status in-progress">In Progress</span>
                </li>
                <li>
                    <span class="project-name">Database Migration</span>
                    <span class="project-status completed">Completed</span>
                </li>
                <!-- Add more projects as needed -->
            </ul>
        </div>
    </div>

    <!-- Similar tooltips for other project cards -->

    <script src="dashboard-script.js"></script>
</body>
</html>