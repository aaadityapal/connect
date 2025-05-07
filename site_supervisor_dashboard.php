<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has the 'Site Supervisor' role
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Site Supervisor') {
    header("Location: unauthorized.php");
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Fetch username from users table for the greeting
$username = "Supervisor"; // Default value
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $username = $row['username'];
            } else {
                error_log("User not found for ID: " . $_SESSION['user_id']);
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare statement for fetching username");
        }
    } catch (Exception $e) {
        error_log("Error fetching username: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Supervisor Dashboard</title>
    
    <!-- Include CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/supervisor/dashboard.css">
    
    <!-- Include custom styles -->
    <style>
        /* Base styles for quick display - main styles in CSS file */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .main-content.collapsed {
            margin-left: 70px;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
                padding-top: 60px;
            }
            
            .hamburger-menu {
                display: flex !important;
            }
        }
        
        .dashboard-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Hamburger menu style */
        .hamburger-menu {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: #2c3e50;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 5px;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .hamburger-menu i {
            font-size: 1.5rem;
        }
        
        .col-lg-1-5 {
            position: relative;
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
        }
        
        @media (min-width: 992px) {
            .col-lg-1-5 {
                -ms-flex: 0 0 20%;
                flex: 0 0 20%;
                max-width: 20%;
            }
        }
        
        .stats-section {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
        }
        
        .stats-section .stat-card {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
            cursor: pointer;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .stats-section .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }
        
        .stats-filters select {
            border-radius: 4px;
            border: 1px solid #ddd;
            background-color: #f8f9fa;
            font-size: 0.85rem;
            min-width: 100px;
        }
        
        .mr-2 {
            margin-right: 0.5rem;
        }
        
        /* Enhanced styles for stat cards */
        .stat-trend {
            font-size: 0.8rem;
            font-weight: 500;
            padding: 2px 5px;
            border-radius: 3px;
        }
        
        .trend-up {
            color: #27ae60;
        }
        
        .trend-down {
            color: #e74c3c;
        }
        
        .stat-footer {
            margin-top: 8px;
            border-top: 1px dashed rgba(0,0,0,0.1);
            padding-top: 8px;
            font-size: 0.8rem;
        }
        
        .stat-secondary {
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-chart {
            height: 20px;
            width: 100%;
            margin-top: 5px;
        }
        
        .stat-sparkline {
            overflow: visible;
        }
        
        /* New styles for additional features */
        .stat-progress {
            margin-top: 5px;
            margin-bottom: 2px;
            background-color: rgba(0,0,0,0.05);
        }
        
        .stat-goal-text {
            font-size: 0.7rem;
            color: #777;
            margin-bottom: 5px;
        }
        
        .stat-actions .btn {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .mt-2 {
            margin-top: 0.5rem;
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        /* Animated pulse for important stats that need attention */
        @keyframes pulse-animation {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
        }
        
        .stat-card.needs-attention {
            animation: pulse-animation 2s infinite;
        }
        
        /* Calendar styles */
        .calendar-nav {
            display: flex;
            align-items: center;
        }
        
        .calendar-nav-btn {
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
        }
        
        .current-month-display {
            margin: 0 15px;
            font-weight: 500;
            font-size: 1.1rem;
            min-width: 120px;
            text-align: center;
        }
        
        .site-calendar-container {
            position: relative;
            overflow: hidden;
        }
        
        .site-calendar {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3px;
        }
        
        .calendar-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background-color: #f8f9fa;
            border-radius: 6px 6px 0 0;
            overflow: hidden;
        }
        
        .calendar-header-cell {
            padding: 8px 5px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
            color: #495057;
        }
        
        .calendar-body {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-gap: 4px;
            padding: 4px;
        }
        
        .calendar-day {
            height: 120px; /* Significantly increased from 90px to 120px */
            border-radius: 6px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 8px; /* Increased padding for more internal space */
            position: relative;
            transition: all 0.2s ease;
            cursor: pointer;
            overflow: hidden; /* Prevent content overflow */
            display: flex;
            flex-direction: column;
        }
        
        .calendar-day:hover {
            background: #f8f9fa;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .calendar-day.today {
            background-color: #e8f4ff;
            border: 1px solid #4299e1;
        }
        
        .calendar-date-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 4px; /* Increased from 2px to 4px for more space */
            position: relative;
        }
        
        .calendar-date {
            font-weight: 600;
            font-size: 0.9rem;
            padding: 2px;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .add-event-btn {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #3498db; /* Changed to a lighter blue to match image */
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px; /* Specific font size in px instead of rem */
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            opacity: 0.9;
            border: none;
            padding: 0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1); /* Lighter shadow to match image */
            line-height: 1;
            position: absolute; /* Changed to absolute for precise positioning */
            top: 0; /* Positioned at the top of the container */
            right: 2px; /* Slight offset from right edge */
        }
        
        .add-event-btn:hover {
            background-color: #2980b9;
            color: white;
            opacity: 1;
            transform: scale(1.1);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .calendar-day.other-month .add-event-btn {
            opacity: 0.4;
            background-color: #cbd5e0;
        }
        
        .calendar-day:hover .add-event-btn {
            opacity: 1;
        }
        
        .calendar-day.has-events {
            background-color: #fff8e6;
            border: 1px solid #f6e05e;
        }
        
        .calendar-day.other-month {
            opacity: 0.4;
        }
        
        .calendar-events {
            font-size: 0.7rem;
            overflow: hidden;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .calendar-event {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 3px 6px;
            border-radius: 3px;
            margin-bottom: 3px;
            color: white;
            font-weight: 500;
            font-size: 0.7rem;
            line-height: 1.2;
        }
        
        .event-inspection {
            background-color: #38a169;
        }
        
        .event-delivery {
            background-color: #e67e22; /* More orange color to match image */
        }
        
        .event-meeting {
            background-color: #805ad5;
        }
        
        .event-report {
            background-color: #ffb347; /* More yellowish/orange color */
        }
        
        .event-issue {
            background-color: #e53e3e;
        }
        
        .event-more {
            text-align: center;
            color: #718096;
            font-style: italic;
            font-size: 0.65rem;
        }
        
        /* Responsive styles */
        @media (max-width: 992px) {
            .calendar-day {
                height: 110px; /* Significantly increased from 80px to 110px */
            }
            
            .calendar-date {
                width: 24px;
                height: 24px;
                font-size: 0.85rem;
            }
            
            .add-event-btn {
                width: 18px;
                height: 18px;
                font-size: 12px;
                top: 0;
                right: 2px;
            }
        }
        
        @media (max-width: 768px) {
            .calendar-nav {
                flex-wrap: wrap;
                justify-content: center;
                margin-top: 10px;
            }
            
            .current-month-display {
                order: -1;
                width: 100%;
                margin-bottom: 10px;
            }
            
            .calendar-day {
                height: 100px; /* Significantly increased from 70px to 100px */
            }
            
            .calendar-body {
                grid-gap: 3px; /* Reduced gap to provide more space for cells */
                padding: 3px;
            }
            
            .add-event-btn {
                width: 18px;
                height: 18px;
                font-size: 12px;
                top: 0;
                right: 2px;
            }
        }
        
        @media (max-width: 576px) {
            .calendar-header-cell {
                font-size: 0.8rem;
                padding: 5px 2px;
            }
            
            .calendar-day {
                height: 80px; /* Significantly increased from 60px to 80px */
                padding: 5px;
            }
            
            .calendar-date {
                font-size: 0.75rem;
                width: 20px;
                height: 20px;
            }
            
            .calendar-event {
                padding: 2px 4px;
                margin-bottom: 2px;
                font-size: 0.65rem;
            }
            
            .add-event-btn {
                width: 16px;
                height: 16px;
                font-size: 10px;
                top: 0;
                right: 2px;
                box-shadow: 0 1px 1px rgba(0,0,0,0.1);
            }
            
            /* Allow up to two events on mobile now that we have more space */
            .calendar-events .calendar-event:nth-child(n+3),
            .event-more {
                display: none;
            }
            
            .calendar-events {
                margin-top: 3px;
            }
            
            /* Wider cells on mobile */
            .calendar-body {
                grid-gap: 2px; /* Further reduced gap */
                padding: 2px;
            }
        }
        
        /* Alternative display for smallest screens */
        @media (max-width: 450px) {
            .site-calendar-container {
                overflow-x: auto; /* Allow horizontal scrolling if needed */
                padding-bottom: 10px; /* Space for potential scrollbar */
            }
            
            .site-calendar {
                min-width: 400px; /* Set minimum width to ensure cells are wide enough */
            }
            
            .calendar-day {
                width: auto;
                min-width: 55px; /* Ensure minimum width */
            }
            
            .add-event-btn {
                width: 14px;
                height: 14px;
                font-size: 9px;
                top: 0;
                right: 2px;
            }
        }
        
        .today .calendar-date {
            background-color: #3182ce;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Hamburger Menu for Mobile -->
    <div class="hamburger-menu" id="hamburgerMenu" onclick="toggleMobilePanel()">
        <i class="fas fa-bars"></i>
    </div>
    
    <!-- Include Left Panel -->
    <?php include 'includes/supervisor_panel.php'; ?>
    
    <!-- Main Content Area -->
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
        
            
            <!-- Greetings Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card greetings-card">
                        <div class="greetings-header">
                            <div class="greeting-time">
                                <?php
                                // Set timezone to Indian Standard Time
                                date_default_timezone_set('Asia/Kolkata');
                                
                                $hour = date('H');
                                $greeting = '';
                                if ($hour >= 5 && $hour < 12) {
                                    $greeting = 'Good Morning';
                                    $icon = 'fa-sun';
                                    $greet_class = 'morning';
                                } elseif ($hour >= 12 && $hour < 18) {
                                    $greeting = 'Good Afternoon';
                                    $icon = 'fa-cloud-sun';
                                    $greet_class = 'afternoon';
                                } else {
                                    $greeting = 'Good Evening';
                                    $icon = 'fa-moon';
                                    $greet_class = 'evening';
                                }
                                ?>
                                <h4 class="greeting <?php echo $greet_class; ?>"><i class="fas <?php echo $icon; ?>"></i> <?php echo $greeting; ?>, <?php echo $username; ?>!</h4>
                                <div class="date-time" id="live-datetime">
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo date('l, F j, Y'); ?> <small>(IST)</small></span>
                                    <span><i class="fas fa-clock"></i> <span id="live-time"><?php echo date('h:i:s A'); ?></span></span>
                                </div>
                            </div>
                            <div class="greeting-actions">
                                <div class="notification-icon">
                                    <a href="#" class="notification-bell">
                                        <i class="fas fa-bell"></i>
                                        <span class="notification-badge">3</span>
                                    </a>
                                </div>
                                <div class="punch-button">
                                    <?php
                                    // Assume this is the simple check to see if user is punched in
                                    $isPunchedIn = false;
                                    if (isset($_SESSION['punched_in']) && $_SESSION['punched_in'] === true) {
                                        $isPunchedIn = true;
                                    }
                                    ?>
                                    <button id="punchButton" class="btn <?php echo $isPunchedIn ? 'btn-danger' : 'btn-success'; ?> btn-sm">
                                        <i class="fas <?php echo $isPunchedIn ? 'fa-sign-out-alt' : 'fa-sign-in-alt'; ?>"></i>
                                        <?php echo $isPunchedIn ? 'Punch Out' : 'Punch In'; ?>
                                        <span class="punch-button-status <?php echo $isPunchedIn ? 'status-in' : 'status-out'; ?>"></span>
                                    </button>
                                    <?php if($isPunchedIn && isset($_SESSION['punch_in_time'])): ?>
                                    <div class="punch-time">Since: <?php echo date('h:i A', strtotime($_SESSION['punch_in_time'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Overview Row -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card stats-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0">Stats Overview</h4>
                            <div class="stats-filters">
                                <div class="d-flex">
                                    <select class="form-control form-control-sm mr-2" id="statsMonthFilter">
                                        <option value="1" <?php echo date('n') == 1 ? 'selected' : ''; ?>>January</option>
                                        <option value="2" <?php echo date('n') == 2 ? 'selected' : ''; ?>>February</option>
                                        <option value="3" <?php echo date('n') == 3 ? 'selected' : ''; ?>>March</option>
                                        <option value="4" <?php echo date('n') == 4 ? 'selected' : ''; ?>>April</option>
                                        <option value="5" <?php echo date('n') == 5 ? 'selected' : ''; ?>>May</option>
                                        <option value="6" <?php echo date('n') == 6 ? 'selected' : ''; ?>>June</option>
                                        <option value="7" <?php echo date('n') == 7 ? 'selected' : ''; ?>>July</option>
                                        <option value="8" <?php echo date('n') == 8 ? 'selected' : ''; ?>>August</option>
                                        <option value="9" <?php echo date('n') == 9 ? 'selected' : ''; ?>>September</option>
                                        <option value="10" <?php echo date('n') == 10 ? 'selected' : ''; ?>>October</option>
                                        <option value="11" <?php echo date('n') == 11 ? 'selected' : ''; ?>>November</option>
                                        <option value="12" <?php echo date('n') == 12 ? 'selected' : ''; ?>>December</option>
                                    </select>
                                    <select class="form-control form-control-sm" id="statsYearFilter">
                                        <?php 
                                        $currentYear = date('Y');
                                        for($year = $currentYear - 2; $year <= $currentYear + 1; $year++) {
                                            echo '<option value="'.$year.'" '.($year == $currentYear ? 'selected' : '').'>'.$year.'</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
            <div class="row">
                            <div class="col-lg-1-5 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card stat-card" data-toggle="tooltip" data-placement="top" title="All workers currently assigned to active projects">
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-details">
                                        <div class="d-flex justify-content-between align-items-baseline">
                            <h3>42</h3>
                                            <span class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> 8%</span>
                                        </div>
                            <p>Active Workers</p>
                                        <div class="progress stat-progress" style="height: 5px;">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: 84%" aria-valuenow="84" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                                        <div class="d-flex justify-content-between align-items-center stat-goal-text">
                                            <small>Goal: 50</small>
                                            <small>84% Complete</small>
                                        </div>
                                        <div class="stat-footer">
                                            <div class="stat-secondary">
                                                <small>Attendance Rate: 92%</small>
                                            </div>
                                            <div class="stat-chart">
                                                <svg width="100%" height="20" class="stat-sparkline">
                                                    <polyline points="0,15 10,10 20,12 30,7 40,9 50,5 60,8 70,4 80,6 90,2" fill="none" stroke="#5da5f7" stroke-width="2"></polyline>
                                                </svg>
                                            </div>
                                            <div class="stat-actions mt-2">
                                                <a href="worker_attendance.php" class="btn btn-sm btn-outline-primary btn-block"><i class="fas fa-eye"></i> View Details</a>
                                            </div>
                                        </div>
                                    </div>
                    </div>
                </div>
                
                            <div class="col-lg-1-5 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card stat-card" data-toggle="tooltip" data-placement="top" title="Current number of ongoing construction projects">
                        <div class="stat-icon bg-success">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-details">
                                        <div class="d-flex justify-content-between align-items-baseline">
                            <h3>8</h3>
                                            <span class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> 2</span>
                                        </div>
                            <p>Active Projects</p>
                                        <div class="progress stat-progress" style="height: 5px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: 80%" aria-valuenow="80" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                                        <div class="d-flex justify-content-between align-items-center stat-goal-text">
                                            <small>Goal: 10</small>
                                            <small>80% Complete</small>
                                        </div>
                                        <div class="stat-footer">
                                            <div class="stat-secondary">
                                                <small>Completion Rate: 76%</small>
                                            </div>
                                            <div class="stat-chart">
                                                <svg width="100%" height="20" class="stat-sparkline">
                                                    <polyline points="0,10 10,8 20,12 30,9 40,7 50,10 60,6 70,8 80,5 90,3" fill="none" stroke="#2ecc71" stroke-width="2"></polyline>
                                                </svg>
                                            </div>
                                            <div class="stat-actions mt-2">
                                                <a href="site_progress.php" class="btn btn-sm btn-outline-success btn-block"><i class="fas fa-eye"></i> View Details</a>
                                            </div>
                                        </div>
                                    </div>
                    </div>
                </div>
                
                            <div class="col-lg-1-5 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card stat-card" data-toggle="tooltip" data-placement="top" title="Percentage of tasks completed on time vs total tasks">
                        <div class="stat-icon bg-warning">
                                        <i class="fas fa-chart-pie"></i>
                        </div>
                        <div class="stat-details">
                                        <div class="d-flex justify-content-between align-items-baseline">
                                            <h3>78%</h3>
                                            <span class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> 5%</span>
                        </div>
                                        <p>Task Efficiency</p>
                                        <div class="progress stat-progress" style="height: 5px;">
                                            <div class="progress-bar bg-warning" role="progressbar" style="width: 78%" aria-valuenow="78" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center stat-goal-text">
                                            <small>Goal: 85%</small>
                                            <small>92% to Goal</small>
                                        </div>
                                        <div class="stat-footer">
                                            <div class="stat-secondary">
                                                <small>Previous: 73%</small>
                                            </div>
                                            <div class="stat-chart">
                                                <svg width="100%" height="20" class="stat-sparkline">
                                                    <polyline points="0,15 10,13 20,14 30,12 40,10 50,11 60,9 70,8 80,6 90,5" fill="none" stroke="#f39c12" stroke-width="2"></polyline>
                                                </svg>
                                            </div>
                                            <div class="stat-actions mt-2">
                                                <a href="worker_performance.php" class="btn btn-sm btn-outline-warning btn-block"><i class="fas fa-eye"></i> View Details</a>
                                            </div>
                                        </div>
                                    </div>
                    </div>
                </div>
                
                            <div class="col-lg-1-5 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card stat-card" data-toggle="tooltip" data-placement="top" title="Total number of tasks completed this month">
                        <div class="stat-icon bg-info">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="stat-details">
                                        <div class="d-flex justify-content-between align-items-baseline">
                            <h3>12</h3>
                                            <span class="stat-trend trend-down"><i class="fas fa-arrow-down"></i> 2</span>
                                        </div>
                            <p>Completed Tasks</p>
                                        <div class="progress stat-progress" style="height: 5px;">
                                            <div class="progress-bar bg-info" role="progressbar" style="width: 60%" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                                        <div class="d-flex justify-content-between align-items-center stat-goal-text">
                                            <small>Goal: 20</small>
                                            <small>60% Complete</small>
                    </div>
                                        <div class="stat-footer">
                                            <div class="stat-secondary">
                                                <small>This Week: 4</small>
                                            </div>
                                            <div class="stat-chart">
                                                <svg width="100%" height="20" class="stat-sparkline">
                                                    <polyline points="0,6 10,8 20,5 30,9 40,7 50,10 60,8 70,12 80,10 90,6" fill="none" stroke="#3498db" stroke-width="2"></polyline>
                                                </svg>
                                            </div>
                                            <div class="stat-actions mt-2">
                                                <a href="worker_assignment.php" class="btn btn-sm btn-outline-info btn-block"><i class="fas fa-eye"></i> View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-1-5 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card stat-card" data-toggle="tooltip" data-placement="top" title="Total travel expenses reported by workers this month">
                                    <div class="stat-icon bg-danger">
                                        <i class="fas fa-taxi"></i>
                                    </div>
                                    <div class="stat-details">
                                        <div class="d-flex justify-content-between align-items-baseline">
                                            <h3>₹4,250</h3>
                                            <span class="stat-trend trend-down"><i class="fas fa-arrow-down"></i> 12%</span>
                                        </div>
                                        <p>Travel Expenses</p>
                                        <div class="progress stat-progress" style="height: 5px;">
                                            <div class="progress-bar bg-danger" role="progressbar" style="width: 42%" aria-valuenow="42" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center stat-goal-text">
                                            <small>Budget: ₹10,000</small>
                                            <small>42% Used</small>
                                        </div>
                                        <div class="stat-footer">
                                            <div class="stat-secondary">
                                                <small>Avg/Worker: ₹101</small>
                                            </div>
                                            <div class="stat-chart">
                                                <svg width="100%" height="20" class="stat-sparkline">
                                                    <polyline points="0,5 10,7 20,6 30,8 40,10 50,9 60,11 70,10 80,8 90,5" fill="none" stroke="#e74c3c" stroke-width="2"></polyline>
                                                </svg>
                                            </div>
                                            <div class="stat-actions mt-2">
                                                <a href="site_expenses.php" class="btn btn-sm btn-outline-danger btn-block"><i class="fas fa-eye"></i> View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Daily Site Updates with Calendar -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0">Daily Site Updates</h4>
                            <div class="calendar-nav">
                                <button id="prevMonth" class="btn btn-sm btn-outline-secondary calendar-nav-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </button>
                                <span id="currentMonthDisplay" class="current-month-display">June 2023</span>
                                <button id="nextMonth" class="btn btn-sm btn-outline-secondary calendar-nav-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="site-calendar-container">
                            <div id="siteCalendar" class="site-calendar">
                                <!-- Calendar will be generated here by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity and Tasks Row -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="dashboard-card">
                        <h4 class="card-title">Recent Site Activities</h4>
                        <div class="activity-timeline">
                            <div class="activity-item">
                                <div class="activity-icon bg-primary">
                                    <i class="fas fa-hammer"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text">Foundation work completed for Building B</p>
                                    <p class="activity-time">Today, 10:30 AM</p>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon bg-success">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text">New materials delivery received</p>
                                    <p class="activity-time">Yesterday, 2:15 PM</p>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon bg-warning">
                                    <i class="fas fa-hard-hat"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text">Safety inspection completed</p>
                                    <p class="activity-time">Yesterday, 11:00 AM</p>
                                </div>
                            </div>
                            
                            <div class="activity-item">
                                <div class="activity-icon bg-danger">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-text">Minor issue reported in electrical wiring</p>
                                    <p class="activity-time">May 22, 9:45 AM</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 mb-4">
                    <div class="dashboard-card">
                        <h4 class="card-title">Upcoming Tasks</h4>
                        <div class="task-list">
                            <div class="task-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="task1">
                                    <label class="form-check-label" for="task1">
                                        Complete daily inspection report
                                    </label>
                                </div>
                                <span class="badge badge-warning">Today</span>
                            </div>
                            
                            <div class="task-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="task2">
                                    <label class="form-check-label" for="task2">
                                        Review worker attendance
                                    </label>
                                </div>
                                <span class="badge badge-info">Today</span>
                            </div>
                            
                            <div class="task-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="task3">
                                    <label class="form-check-label" for="task3">
                                        Coordinate with material suppliers
                                    </label>
                                </div>
                                <span class="badge badge-primary">Tomorrow</span>
                            </div>
                            
                            <div class="task-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="task4">
                                    <label class="form-check-label" for="task4">
                                        Prepare weekly progress report
                                    </label>
                                </div>
                                <span class="badge badge-success">May 25</span>
                            </div>
                            
                            <div class="task-item">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="task5">
                                    <label class="form-check-label" for="task5">
                                        Attend site management meeting
                                    </label>
                                </div>
                                <span class="badge badge-secondary">May 26</span>
                            </div>
                        </div>
                        
                        <a href="#" class="btn btn-outline-primary btn-sm mt-3">View All Tasks</a>
                    </div>
                </div>
            </div>
            
            <!-- Project Progress Row -->
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="dashboard-card">
                        <h4 class="card-title">Project Progress</h4>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Status</th>
                                        <th>Deadline</th>
                                        <th>Progress</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Building A Construction</td>
                                        <td><span class="badge badge-success">On Track</span></td>
                                        <td>June 30, 2023</td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100">75%</div>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary">View</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Foundation Work Building B</td>
                                        <td><span class="badge badge-warning">Slight Delay</span></td>
                                        <td>July 15, 2023</td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar bg-warning" role="progressbar" style="width: 45%" aria-valuenow="45" aria-valuemin="0" aria-valuemax="100">45%</div>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary">View</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Interior Finishing Phase 1</td>
                                        <td><span class="badge badge-danger">Delayed</span></td>
                                        <td>June 10, 2023</td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar bg-danger" role="progressbar" style="width: 30%" aria-valuenow="30" aria-valuemin="0" aria-valuemax="100">30%</div>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary">View</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Electrical Installation</td>
                                        <td><span class="badge badge-success">On Track</span></td>
                                        <td>August 5, 2023</td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: 60%" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100">60%</div>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary">View</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include JS files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="js/supervisor/dashboard.js"></script>
    
    <!-- Live Time Script -->
    <script>
        // Function to update time
        function updateTime() {
            const now = new Date();
            
            // Convert to IST (UTC+5:30)
            const istTime = new Date(now.getTime() + (5.5 * 60 * 60 * 1000));
            
            let hours = istTime.getUTCHours();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            
            const minutes = istTime.getUTCMinutes().toString().padStart(2, '0');
            const seconds = istTime.getUTCSeconds().toString().padStart(2, '0');
            
            document.getElementById('live-time').textContent = `${hours}:${minutes}:${seconds} ${ampm}`;
        }
        
        // Update time every second
        setInterval(updateTime, 1000);
        
        // Initial call to display time immediately
        updateTime();
        
        // Punch in/out functionality
        document.getElementById('punchButton').addEventListener('click', function() {
            const isPunchedIn = this.classList.contains('btn-danger');
            const button = this;
            
            // Action based on current state
            const action = isPunchedIn ? 'out' : 'in';
            
            // Open camera modal for capturing photo
            openCameraModal(action, function(photoData, locationData) {
                // Show loading state after photo is captured
                const originalText = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                
                const currentTime = document.getElementById('live-time').textContent;
                const punchTimeElem = document.createElement('div');
                punchTimeElem.className = 'punch-time';
                
                // Prepare form data with punch details
                const formData = new FormData();
                formData.append('action', action);
                formData.append('photo', photoData);
                formData.append('latitude', locationData.latitude || '');
                formData.append('longitude', locationData.longitude || '');
                formData.append('accuracy', locationData.accuracy || '');
                formData.append('address', locationData.address || 'Not available');
                formData.append('device_info', navigator.userAgent);
                
                // Send data to server
                fetch('punch_action.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`);
                    }
                    return response.text(); // First get as text to debug
                })
                .then(text => {
                    // Try to parse as JSON, but log the raw text if it fails
                    try {
                        // Check if the response begins with HTML or error tags
                        if (text.trim().startsWith('<')) {
                            console.error('Received HTML instead of JSON:', text);
                            throw new Error('Server returned HTML instead of JSON');
                        }
                        
                        const data = JSON.parse(text);
                        if (data.status === 'success') {
                            // Update button state
                            if (isPunchedIn) {
                                // Switched to punched out
                                button.classList.remove('btn-danger');
                                button.classList.add('btn-success');
                                button.innerHTML = '<i class="fas fa-sign-in-alt"></i> Punch In <span class="punch-button-status status-out"></span>';
                                
                                // Remove any existing punch time indicator
                                const existingPunchTime = button.parentElement.querySelector('.punch-time');
                                if (existingPunchTime) {
                                    existingPunchTime.remove();
                                }
                                
                                // Show toast notification
                                showToast('Punched out successfully', 'success', 'You worked for ' + (data.hours_worked || 'some time'));
                            } else {
                                // Switched to punched in
                                button.classList.remove('btn-success');
                                button.classList.add('btn-danger');
                                button.innerHTML = '<i class="fas fa-sign-out-alt"></i> Punch Out <span class="punch-button-status status-in"></span>';
                                
                                // Add punch time indicator
                                punchTimeElem.innerHTML = 'Since: ' + currentTime;
                                button.parentElement.appendChild(punchTimeElem);
                                
                                // Show toast notification
                                showToast('Punched in successfully', 'success', 'Punch time recorded: ' + currentTime);
                            }
                        } else {
                            // Error handling
                            button.innerHTML = originalText;
                            
                            // Show the specific error message
                            showToast('Action failed', 'danger', data.message || 'Please try again');
                            
                            // If there's a photo error, show it
                            if (data.photo_error) {
                                console.error('Photo error:', data.photo_error);
                                showToast('Photo error', 'warning', data.photo_error);
                            }
                        }
                    } catch (e) {
                        console.error('Error parsing JSON:', e);
                        console.log('Raw response:', text);
                        
                        // More detailed error message
                        let errorMsg = 'Could not process server response';
                        if (text.includes('PHP')) {
                            errorMsg = 'PHP error detected. Please check server logs.';
                        } else if (text.trim().startsWith('<')) {
                            errorMsg = 'Server returned HTML instead of JSON.';
                        }
                        
                        showToast('Response Error', 'danger', errorMsg);
                        button.innerHTML = originalText;
                    }
                    button.disabled = false;
                })
                .catch(error => {
                    console.error('Error:', error);
                    button.innerHTML = originalText;
                    button.disabled = false;
                    showToast('Connection error', 'danger', error.message || 'Please check your connection and try again');
                });
            });
        });
        
        // Function to open camera modal
        function openCameraModal(action, callback) {
            // Create modal if it doesn't exist
            let cameraModal = document.getElementById('camera-modal');
            if (!cameraModal) {
                // Create modal container
                cameraModal = document.createElement('div');
                cameraModal.id = 'camera-modal';
                cameraModal.className = 'camera-modal';
                
                // Create modal content HTML
                cameraModal.innerHTML = `
                    <div class="camera-modal-content">
                        <div class="camera-header">
                            <h4 id="camera-title">Take Photo for Punch In</h4>
                            <button class="camera-close">&times;</button>
                        </div>
                        <div class="camera-body">
                            <div class="video-container">
                                <video id="camera-video" playsinline autoplay></video>
                                <canvas id="camera-canvas" style="display:none;"></canvas>
                                <div class="camera-overlay">
                                    <div class="camera-frame"></div>
                                </div>
                                <div id="camera-error" style="display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); color:white; text-align:center; padding:20px; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                    <p><i class="fas fa-exclamation-triangle" style="font-size:2rem; color:#f39c12; margin-bottom:10px; display:block;"></i>Camera could not be accessed</p>
                                    <p id="camera-error-message">Please try using a different device or check camera permissions</p>
                                    <button id="retry-camera-btn" class="btn btn-warning mt-3"><i class="fas fa-redo"></i> Retry Camera</button>
                                </div>
                                <button id="rotate-camera-btn" class="btn btn-info camera-rotate-btn"><i class="fas fa-sync"></i></button>
                            </div>
                            <div id="photo-preview" style="display:none;">
                                <img id="captured-photo" src="" alt="Captured photo">
                            </div>
                            <div class="location-info">
                                <p><i class="fas fa-map-marker-alt"></i> <span id="location-status">Getting location...</span></p>
                                <p id="location-address" class="location-address"><i class="fas fa-map"></i> <span>Fetching address...</span></p>
                            </div>
                        </div>
                        <div class="camera-footer">
                            <button id="capture-btn" class="btn btn-primary"><i class="fas fa-camera"></i> Capture</button>
                            <button id="retake-btn" class="btn btn-secondary" style="display:none;"><i class="fas fa-redo"></i> Retake</button>
                            <button id="confirm-btn" class="btn btn-success" style="display:none;"><i class="fas fa-check"></i> Confirm</button>
                            <button id="skip-photo-btn" class="btn btn-outline-secondary"><i class="fas fa-forward"></i> Skip Photo</button>
                        </div>
                    </div>
                `;
                
                // Add to body
                document.body.appendChild(cameraModal);
                
                // Add modal styles
                if (!document.getElementById('camera-modal-styles')) {
                    const style = document.createElement('style');
                    style.id = 'camera-modal-styles';
                    style.innerHTML = `
                        .camera-modal {
                            position: fixed;
                            z-index: 9999;
                            left: 0;
                            top: 0;
                            width: 100%;
                            height: 100%;
                            background-color: rgba(0, 0, 0, 0.9);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            opacity: 0;
                            transition: opacity 0.3s ease;
                            pointer-events: none;
                        }
                        .camera-modal.active {
                            opacity: 1;
                            pointer-events: all;
                        }
                        .camera-modal-content {
                            background-color: white;
                            border-radius: 10px;
                            width: 90%;
                            max-width: 500px;
                            overflow: hidden;
                            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
                        }
                        .camera-header {
                            padding: 15px;
                            background-color: var(--primary-color);
                            color: white;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        }
                        .camera-header h4 {
                            margin: 0;
                            font-size: 1.2rem;
                        }
                        .camera-close {
                            background: none;
                            border: none;
                            font-size: 1.5rem;
                            color: white;
                            cursor: pointer;
                        }
                        .camera-body {
                            padding: 15px;
                        }
                        .video-container {
                            position: relative;
                            width: 100%;
                            height: 0;
                            padding-bottom: 75%;
                            background: #f0f0f0;
                            overflow: hidden;
                            border-radius: 5px;
                            margin-bottom: 15px;
                        }
                        #camera-video, #captured-photo {
                            position: absolute;
                            width: 100%;
                            height: 100%;
                            object-fit: cover;
                            background: #000;
                        }
                        .camera-overlay {
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            pointer-events: none;
                        }
                        .camera-frame {
                            width: 80%;
                            height: 80%;
                            border: 2px dashed rgba(255,255,255,0.7);
                            border-radius: 10px;
                        }
                        .camera-rotate-btn {
                            position: absolute;
                            top: 10px;
                            right: 10px;
                            z-index: 10;
                            border-radius: 50%;
                            width: 40px;
                            height: 40px;
                            padding: 0;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }
                        .location-info {
                            background: #f5f5f5;
                            padding: 10px;
                            border-radius: 5px;
                            font-size: 0.9rem;
                            margin-top: 10px;
                        }
                        .location-info p {
                            margin-bottom: 5px;
                        }
                        .location-info i {
                            color: var(--primary-color);
                            width: 20px;
                            text-align: center;
                            margin-right: 5px;
                        }
                        .location-address {
                            font-style: normal;
                            word-break: break-word;
                        }
                        .location-success {
                            color: #2ecc71;
                        }
                        .location-error {
                            color: #e74c3c;
                        }
                        .camera-footer {
                            padding: 15px;
                            background: #f9f9f9;
                            display: flex;
                            justify-content: center;
                            gap: 10px;
                        }
                        #photo-preview {
                            position: relative;
                            width: 100%;
                            height: 0;
                            padding-bottom: 75%;
                            background: #f0f0f0;
                            border-radius: 5px;
                            margin-bottom: 15px;
                            overflow: hidden;
                        }
                    `;
                    document.head.appendChild(style);
                }
            }
            
            // Update modal title based on action
            document.getElementById('camera-title').textContent = `Take Photo for Punch ${action === 'in' ? 'In' : 'Out'}`;
            
            // Show modal
            cameraModal.classList.add('active');
            
            // Elements
            const video = document.getElementById('camera-video');
            const canvas = document.getElementById('camera-canvas');
            const captureBtn = document.getElementById('capture-btn');
            const retakeBtn = document.getElementById('retake-btn');
            const confirmBtn = document.getElementById('confirm-btn');
            const closeBtn = document.querySelector('.camera-close');
            const photoPreview = document.getElementById('photo-preview');
            const videoContainer = document.querySelector('.video-container');
            const locationStatus = document.getElementById('location-status');
            const cameraError = document.getElementById('camera-error');
            const skipPhotoBtn = document.getElementById('skip-photo-btn');
            
            // Location data
            let locationData = {};
            const locationAddress = document.getElementById('location-address');
            
            // Start location tracking
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        locationData = {
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                            accuracy: position.coords.accuracy
                        };
                        locationStatus.innerHTML = `Location found (Accuracy: ${Math.round(position.coords.accuracy)}m)`;
                        locationStatus.className = 'location-success';
                        
                        // Call reverse geocoding to get address
                        getAddressFromCoordinates(position.coords.latitude, position.coords.longitude);
                    },
                    function(error) {
                        locationStatus.innerHTML = 'Unable to get location: ' + error.message;
                        locationStatus.className = 'location-error';
                        locationAddress.style.display = 'none';
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            } else {
                locationStatus.innerHTML = 'Geolocation is not supported by this browser';
                locationStatus.className = 'location-error';
                locationAddress.style.display = 'none';
            }
            
            // Function to get address from coordinates using reverse geocoding
            function getAddressFromCoordinates(latitude, longitude) {
                // Show loading state
                locationAddress.querySelector('span').textContent = 'Fetching address...';
                
                // Use Nominatim API for reverse geocoding (free and no API key required)
                const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`;
                
                fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'User-Agent': 'HR Attendance System' // Nominatim requires a user agent
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Geocoding service failed');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.display_name) {
                        // Store the address in locationData
                        locationData.address = data.display_name;
                        
                        // Display a shorter version of the address
                        let displayAddress = data.display_name;
                        if (displayAddress.length > 60) {
                            displayAddress = displayAddress.substring(0, 57) + '...';
                        }
                        
                        locationAddress.querySelector('span').textContent = displayAddress;
                        locationAddress.title = data.display_name; // Show full address on hover
                    } else {
                        throw new Error('No address found');
                    }
                })
                .catch(error => {
                    console.error('Error getting address:', error);
                    locationAddress.querySelector('span').textContent = 'Address could not be determined';
                });
            }
            
            // Variables for camera facing mode
            let currentFacingMode = 'user';
            let stream = null;
            
            // Start camera stream with specified facing mode
            function startCamera(facingMode) {
                // Hide error message initially
                cameraError.style.display = 'none';
                captureBtn.disabled = false;
                
                // Check if the browser supports the permissions API
                if (navigator.permissions && navigator.permissions.query) {
                    // Check camera permissions
                    navigator.permissions.query({name: 'camera'})
                    .then(function(permissionStatus) {
                        console.log('Camera permission status:', permissionStatus.state);
                        
                        if (permissionStatus.state === 'denied') {
                            // Permission explicitly denied
                            showCameraError("Camera permission denied. Please check your browser settings.");
                            return;
                        }
                        
                        // Continue with camera initialization
                        initializeCamera(facingMode);
                        
                        // Listen for permission changes
                        permissionStatus.onchange = function() {
                            console.log('Permission state changed to:', this.state);
                            if (this.state === 'granted') {
                                initializeCamera(currentFacingMode);
                            } else if (this.state === 'denied') {
                                showCameraError("Camera permission was denied");
                            }
                        };
                    })
                    .catch(function(error) {
                        console.error("Error checking permissions:", error);
                        // Fall back to direct camera access
                        initializeCamera(facingMode);
                    });
                } else {
                    // Browser doesn't support permission API, try direct camera access
                    console.log('Permissions API not supported, trying direct camera access');
                    initializeCamera(facingMode);
                }
            }
            
            // Function to initialize the camera
            function initializeCamera(facingMode) {
                // Stop any existing stream
                if (stream) {
                    stream.getTracks().forEach(track => {
                        track.stop();
                    });
                }
                
                // Hardware constraints
                const constraints = {
                    video: {
                        facingMode: facingMode,
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                };
                
                // Start new stream with specified facing mode
                navigator.mediaDevices.getUserMedia(constraints)
                .then(function(mediaStream) {
                    stream = mediaStream;
                    video.srcObject = mediaStream;
                    
                    // Promise to check if video is actually playing
                    const playPromise = video.play();
                    
                    if (playPromise !== undefined) {
                        playPromise
                        .then(() => {
                            // Video is playing successfully
                            currentFacingMode = facingMode;
                            console.log('Camera started successfully with facing mode:', facingMode);
                        })
                        .catch(error => {
                            console.error('Error playing video:', error);
                            showCameraError("Error starting video playback. Please reload the page.");
                        });
                    }
                    
                    // Verify we're actually getting frames from the camera after a short delay
                    setTimeout(function() {
                        if (video.readyState < 2) { // HAVE_CURRENT_DATA or less
                            showCameraError("Camera connected but not providing video. Try reloading the page.");
                        }
                    }, 3000);
                })
                .catch(function(err) {
                    console.error("Error accessing camera: ", err);
                    
                    // Different error message based on error type
                    if (err.name === 'NotAllowedError') {
                        showCameraError("Camera access denied. Please allow camera access in your browser settings.");
                    } else if (err.name === 'NotFoundError') {
                        showCameraError("No camera found on this device. Try using a different device.");
                    } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                        showCameraError("Camera is in use by another application or not available.");
                    } else if (err.name === 'OverconstrainedError') {
                        // Try again with relaxed constraints
                        navigator.mediaDevices.getUserMedia({ video: true, audio: false })
                        .then(function(mediaStream) {
                            stream = mediaStream;
                            video.srcObject = mediaStream;
                            video.play();
                        })
                        .catch(function(fallbackErr) {
                            showCameraError("Camera not available: " + fallbackErr.message);
                        });
                    } else {
                        showCameraError("Camera error: " + err.message);
                    }
                });
            }
            
            // Helper function to show camera error
            function showCameraError(message) {
                document.getElementById('camera-error-message').textContent = message;
                cameraError.style.display = 'flex';
                captureBtn.disabled = true;
                locationStatus.className = 'location-error';
            }
            
            // Add event listener to check when video actually starts playing
            video.addEventListener('playing', function() {
                // Hide error if video is actually playing
                cameraError.style.display = 'none';
                captureBtn.disabled = false;
            });
            
            // Start camera with front-facing camera by default
            startCamera('user');
            
            // Rotate camera button
            const rotateCameraBtn = document.getElementById('rotate-camera-btn');
            rotateCameraBtn.addEventListener('click', function() {
                // Toggle between front and rear cameras
                const newFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
                startCamera(newFacingMode);
            });
            
            // Photo data
            let photoData = null;
            
            // Capture photo
            captureBtn.addEventListener('click', function() {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                photoData = canvas.toDataURL('image/jpeg', 0.8);
                document.getElementById('captured-photo').src = photoData;
                
                // Show preview and confirm buttons
                videoContainer.style.display = 'none';
                photoPreview.style.display = 'block';
                captureBtn.style.display = 'none';
                retakeBtn.style.display = 'inline-block';
                confirmBtn.style.display = 'inline-block';
            });
            
            // Retake photo
            retakeBtn.addEventListener('click', function() {
                photoPreview.style.display = 'none';
                videoContainer.style.display = 'block';
                captureBtn.style.display = 'inline-block';
                retakeBtn.style.display = 'none';
                confirmBtn.style.display = 'none';
                photoData = null;
            });
            
            // Confirm photo and location
            confirmBtn.addEventListener('click', function() {
                if (photoData) {
                    // Close modal and stop camera
                    closeCamera();
                    
                    // Call the callback with captured data
                    callback(photoData, locationData);
                } else {
                    showToast('Error', 'danger', 'Please capture a photo first');
                }
            });
            
            // Close modal and cleanup
            closeBtn.addEventListener('click', closeCamera);
            
            // Skip photo button
            skipPhotoBtn.addEventListener('click', function() {
                // Close camera and proceed without photo
                closeCamera();
                callback(null, locationData);
            });
            
            // File upload is disabled - we're using camera rotation instead
            function closeCamera() {
                cameraModal.classList.remove('active');
                
                // Stop camera stream
                if (stream) {
                    stream.getTracks().forEach(track => {
                        track.stop();
                    });
                }
                
                // Reset UI state
                videoContainer.style.display = 'block';
                photoPreview.style.display = 'none';
                captureBtn.style.display = 'inline-block';
                retakeBtn.style.display = 'none';
                confirmBtn.style.display = 'none';
                skipPhotoBtn.style.display = 'inline-block';
                cameraError.style.display = 'none';
                captureBtn.disabled = false;
            }
            
            // Retry camera button
            document.getElementById('retry-camera-btn').addEventListener('click', function() {
                // Try to reinitialize camera
                startCamera(currentFacingMode);
            });
        }
        
        // Function to show toast notifications
        function showToast(title, type, message) {
            // Create toast container if it doesn't exist
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                toastContainer.className = 'toast-container';
                document.body.appendChild(toastContainer);
                
                // Add toast container styles if they don't exist
                if (!document.getElementById('toast-styles')) {
                    const style = document.createElement('style');
                    style.id = 'toast-styles';
                    style.innerHTML = `
                        .toast-container {
                            position: fixed;
                            top: 20px;
                            right: 20px;
                            z-index: 9999;
                        }
                        .toast {
                            background: white;
                            border-radius: 8px;
                            padding: 15px 20px;
                            margin-bottom: 10px;
                            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                            display: flex;
                            flex-direction: column;
                            min-width: 250px;
                            max-width: 350px;
                            transform: translateX(100%);
                            opacity: 0;
                            transition: all 0.3s ease;
                        }
                        .toast.show {
                            transform: translateX(0);
                            opacity: 1;
                        }
                        .toast-header {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 8px;
                            font-weight: bold;
                        }
                        .toast-body {
                            font-size: 0.9rem;
                            color: #666;
                        }
                        .toast-success {
                            border-left: 4px solid #2ecc71;
                        }
                        .toast-danger {
                            border-left: 4px solid #e74c3c;
                        }
                        .toast-close {
                            background: none;
                            border: none;
                            font-size: 1rem;
                            cursor: pointer;
                            color: #999;
                        }
                    `;
                    document.head.appendChild(style);
                }
            }
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-header">
                    <span>${title}</span>
                    <button class="toast-close">&times;</button>
                </div>
                <div class="toast-body">${message}</div>
            `;
            
            // Add to container
            toastContainer.appendChild(toast);
            
            // Show toast (delayed to allow animation)
            setTimeout(() => toast.classList.add('show'), 10);
            
            // Set up close button
            toast.querySelector('.toast-close').addEventListener('click', () => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            });
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }
            }, 5000);
        }
        
        // Notification bell click
        document.querySelector('.notification-bell').addEventListener('click', function(e) {
            e.preventDefault();
            alert('You have 3 unread notifications');
            // In a real app, this would open a notification panel
        });
    </script>
    
    <!-- Update JavaScript for enhanced filter functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();

        // Get filter elements
        const monthFilter = document.getElementById('statsMonthFilter');
        const yearFilter = document.getElementById('statsYearFilter');
        
        // Add event listeners
        monthFilter.addEventListener('change', updateStatsData);
        yearFilter.addEventListener('change', updateStatsData);
        
        // Add click handler for stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', function(e) {
                // Only navigate if the click wasn't on a button
                if (!e.target.closest('.stat-actions')) {
                    const detailLink = this.querySelector('.stat-actions a');
                    if (detailLink) {
                        window.location.href = detailLink.getAttribute('href');
                    }
                }
            });
        });
        
        function updateStatsData() {
            // Get selected values
            const selectedMonth = monthFilter.value;
            const selectedYear = yearFilter.value;
            
            // Show loading state
            const statValues = document.querySelectorAll('.stat-details h3');
            statValues.forEach(el => {
                el.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            });
            
            const trendIndicators = document.querySelectorAll('.stat-trend');
            trendIndicators.forEach(el => {
                el.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            });
            
            const secondaryMetrics = document.querySelectorAll('.stat-secondary small');
            secondaryMetrics.forEach(el => {
                el.innerHTML = 'Loading...';
            });
            
            const progressBars = document.querySelectorAll('.stat-progress .progress-bar');
            progressBars.forEach(el => {
                el.style.width = '0%';
            });
            
            const goalTexts = document.querySelectorAll('.stat-goal-text small:last-child');
            goalTexts.forEach(el => {
                el.innerHTML = 'Loading...';
            });
            
            // In a real implementation, you would fetch data from the server
            // For now, we'll simulate a delay and then show random data
            setTimeout(() => {
                // Simulate data - in real implementation, this would come from AJAX
                const mockData = {
                    activeWorkers: {
                        value: Math.floor(Math.random() * 50) + 30,
                        trend: Math.floor(Math.random() * 15) - 5,
                        secondary: 'Attendance Rate: ' + (Math.floor(Math.random() * 10) + 90) + '%',
                        sparkline: generateSparkline(),
                        goal: 50,
                        progress: Math.floor(Math.random() * 30) + 70,
                        needsAttention: false
                    },
                    activeProjects: {
                        value: Math.floor(Math.random() * 10) + 5,
                        trend: Math.floor(Math.random() * 6) - 2,
                        secondary: 'Completion Rate: ' + (Math.floor(Math.random() * 20) + 70) + '%',
                        sparkline: generateSparkline(),
                        goal: 10,
                        progress: Math.floor(Math.random() * 20) + 60,
                        needsAttention: false
                    },
                    taskEfficiency: {
                        value: Math.floor(Math.random() * 30) + 65 + '%',
                        trend: Math.floor(Math.random() * 10) - 3,
                        secondary: 'Previous: ' + (Math.floor(Math.random() * 30) + 65) + '%',
                        sparkline: generateSparkline(),
                        goal: '85%',
                        progress: Math.floor(Math.random() * 15) + 75,
                        toGoal: Math.floor(Math.random() * 10) + 85,
                        needsAttention: false
                    },
                    completedTasks: {
                        value: Math.floor(Math.random() * 20) + 10,
                        trend: Math.floor(Math.random() * 10) - 5,
                        secondary: 'This Week: ' + (Math.floor(Math.random() * 8) + 1),
                        sparkline: generateSparkline(),
                        goal: 20,
                        progress: Math.floor(Math.random() * 40) + 40,
                        needsAttention: Math.random() > 0.7
                    },
                    travelExpenses: {
                        value: '₹' + (Math.floor(Math.random() * 8000) + 2000),
                        trend: Math.floor(Math.random() * 20) - 15,
                        secondary: 'Avg/Worker: ₹' + (Math.floor(Math.random() * 150) + 50),
                        sparkline: generateSparkline(),
                        budget: '₹10,000',
                        progress: Math.floor(Math.random() * 60) + 20,
                        needsAttention: Math.random() > 0.8
                    }
                };
                
                // Update the stats
                const statCards = document.querySelectorAll('.stat-card');
                
                // Card 1: Active Workers
                updateCard(statCards[0], mockData.activeWorkers, 'Complete');
                
                // Card 2: Active Projects
                updateCard(statCards[1], mockData.activeProjects, 'Complete');
                
                // Card 3: Task Efficiency
                updateCard(statCards[2], mockData.taskEfficiency, 'to Goal');
                
                // Card 4: Completed Tasks
                updateCard(statCards[3], mockData.completedTasks, 'Complete');
                
                // Card 5: Travel Expenses
                updateCard(statCards[4], mockData.travelExpenses, 'Used');
            }, 800);
        }
        
        // Helper function to update a card with data
        function updateCard(card, data, progressLabel) {
            // Update main value
            card.querySelector('h3').textContent = data.value;
            
            // Update trend indicator
            const trendElement = card.querySelector('.stat-trend');
            const isTrendUp = data.trend > 0;
            trendElement.className = 'stat-trend ' + (isTrendUp ? 'trend-up' : 'trend-down');
            trendElement.innerHTML = '<i class="fas fa-arrow-' + (isTrendUp ? 'up' : 'down') + '"></i> ' + 
                                   (Math.abs(data.trend) + (isNaN(parseInt(data.trend)) ? '%' : ''));
            
            // Update secondary metric
            card.querySelector('.stat-secondary small').textContent = data.secondary;
            
            // Update sparkline if available
            if(data.sparkline && card.querySelector('.stat-sparkline polyline')) {
                card.querySelector('.stat-sparkline polyline').setAttribute('points', data.sparkline);
            }
            
            // Update progress bar
            if (data.progress !== undefined) {
                const progressBar = card.querySelector('.progress-bar');
                progressBar.style.width = data.progress + '%';
                progressBar.setAttribute('aria-valuenow', data.progress);
            }
            
            // Update goal text
            if (data.goal !== undefined) {
                card.querySelectorAll('.stat-goal-text small')[0].textContent = 
                    data.hasOwnProperty('budget') ? 'Budget: ' + data.budget : 'Goal: ' + data.goal;
                
                card.querySelectorAll('.stat-goal-text small')[1].textContent = 
                    (data.toGoal || data.progress) + '% ' + progressLabel;
            }
            
            // Add attention animation if needed
            if (data.needsAttention) {
                card.classList.add('needs-attention');
            } else {
                card.classList.remove('needs-attention');
            }
        }
        
        // Generate random sparkline data points
        function generateSparkline() {
            let points = '';
            for (let i = 0; i < 10; i++) {
                const x = i * 10;
                const y = Math.floor(Math.random() * 15) + 1;
                points += x + ',' + y + ' ';
            }
            return points.trim();
        }
        });
    </script>
    
    <!-- Add JavaScript for the calendar functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Calendar functionality
        const calendarContainer = document.getElementById('siteCalendar');
        const currentMonthDisplay = document.getElementById('currentMonthDisplay');
        const prevMonthBtn = document.getElementById('prevMonth');
        const nextMonthBtn = document.getElementById('nextMonth');
        
        // Set initial date to current month/year
        let currentDate = new Date();
        
        // Event listeners for navigation buttons
        prevMonthBtn.addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        });
        
        nextMonthBtn.addEventListener('click', function() {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        });
        
        // Function to render the calendar
        function renderCalendar() {
            // Get current month and year
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            // Update the month display
            const monthNames = ["January", "February", "March", "April", "May", "June",
                               "July", "August", "September", "October", "November", "December"];
            currentMonthDisplay.textContent = `${monthNames[month]} ${year}`;
            
            // Get the first day of the month
            const firstDay = new Date(year, month, 1);
            const startingDay = firstDay.getDay(); // 0 = Sunday, 1 = Monday, etc.
            
            // Get the number of days in the month
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            
            // Get the number of days in the previous month
            const prevMonth = month === 0 ? 11 : month - 1;
            const prevYear = month === 0 ? year - 1 : year;
            const daysInPrevMonth = new Date(prevYear, prevMonth + 1, 0).getDate();
            
            // Create calendar HTML
            let calendarHTML = `
                <div class="calendar-header">
                    <div class="calendar-header-cell">Sun</div>
                    <div class="calendar-header-cell">Mon</div>
                    <div class="calendar-header-cell">Tue</div>
                    <div class="calendar-header-cell">Wed</div>
                    <div class="calendar-header-cell">Thu</div>
                    <div class="calendar-header-cell">Fri</div>
                    <div class="calendar-header-cell">Sat</div>
                </div>
                <div class="calendar-body">
            `;
            
            // Get today's date for highlighting
            const today = new Date();
            const isCurrentMonth = today.getMonth() === month && today.getFullYear() === year;
            
            // Generate days from previous month (if needed)
            let dayCount = 1;
            for (let i = 0; i < startingDay; i++) {
                const prevMonthDay = daysInPrevMonth - startingDay + i + 1;
                calendarHTML += createDayCell(prevMonthDay, true, false, []);
            }
            
            // Generate days for current month
            const sampleEvents = generateSampleEvents(year, month, daysInMonth);
            
            for (let day = 1; day <= daysInMonth; day++) {
                const isToday = isCurrentMonth && today.getDate() === day;
                const dayEvents = sampleEvents[day] || [];
                calendarHTML += createDayCell(day, false, isToday, dayEvents);
                dayCount++;
            }
            
            // Generate days for next month (if needed)
            const totalCells = Math.ceil((startingDay + daysInMonth) / 7) * 7;
            const nextMonthDays = totalCells - (startingDay + daysInMonth);
            
            for (let day = 1; day <= nextMonthDays; day++) {
                calendarHTML += createDayCell(day, true, false, []);
            }
            
            calendarHTML += `</div>`;
            
            // Update the calendar
            calendarContainer.innerHTML = calendarHTML;
            
            // Add click events for day cells and add event buttons
            setupCalendarInteractions(sampleEvents, month, year, monthNames);
        }
        
        // Function to set up calendar interactions
        function setupCalendarInteractions(events, month, year, monthNames) {
            // Add click event for day cells (view events)
            document.querySelectorAll('.calendar-day').forEach(cell => {
                cell.addEventListener('click', function(e) {
                    // Don't trigger if the click was on the add button or an event
                    if (e.target.classList.contains('add-event-btn') || 
                        e.target.classList.contains('calendar-event') ||
                        e.target.closest('.add-event-btn') ||
                        e.target.closest('.calendar-event')) {
                        return;
                    }
                    
                    const dayNumber = this.getAttribute('data-day');
                    const isOtherMonth = this.classList.contains('other-month');
                    const monthName = monthNames[month];
                    
                    if (isOtherMonth) {
                        // Handle clicking on days from other months if needed
                        return;
                    }
                    
                    // Show events for this day
                    const dayEvents = events[dayNumber] || [];
                    
                    if (dayEvents.length > 0) {
                        let eventsText = `Events on ${monthName} ${dayNumber}, ${year}:\n\n`;
                        dayEvents.forEach(event => {
                            eventsText += `- ${event.time}: ${event.title}\n`;
                        });
                        
                        alert(eventsText);
                    } else {
                        alert(`No events scheduled for ${monthName} ${dayNumber}, ${year}`);
                    }
                });
            });
            
            // Add click event for add event buttons
            document.querySelectorAll('.add-event-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent the day cell click event
                    
                    const dayCell = this.closest('.calendar-day');
                    const day = dayCell.getAttribute('data-day');
                    const isOtherMonth = dayCell.classList.contains('other-month');
                    
                    if (isOtherMonth) {
                        alert('Cannot add events to days outside the current month');
                        return;
                    }
                    
                    // Get the current month and year from the calendar
                    const monthName = monthNames[month];
                    
                    // Show a simple form (in real implementation, this would be a modal with proper form)
                    const eventType = prompt('Enter event type (inspection, delivery, meeting, report, issue):', 'meeting');
                    if (!eventType) return;
                    
                    const eventTitle = prompt('Enter event title:', 'New Meeting');
                    if (!eventTitle) return;
                    
                    const eventTime = prompt('Enter event time (e.g., 9:00 AM):', '10:00 AM');
                    if (!eventTime) return;
                    
                    alert(`Event added: ${eventTitle} on ${monthName} ${day}, ${year} at ${eventTime}\n\nThis is a demonstration. In a real implementation, this would save to a database.`);
                });
            });
            
            // Add click event for event items
            document.querySelectorAll('.calendar-event').forEach(eventEl => {
                eventEl.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent the day cell click event
                    
                    const title = this.textContent.trim();
                    const details = this.getAttribute('title');
                    
                    alert(`Event details: ${details}\n\nIn a real implementation, this would show a detailed view of the event.`);
                });
            });
        }
        
        // Function to create a day cell
        function createDayCell(day, isOtherMonth, isToday, events) {
            const hasEvents = events.length > 0;
            let cellClass = 'calendar-day';
            
            if (isOtherMonth) cellClass += ' other-month';
            if (isToday) cellClass += ' today';
            if (hasEvents) cellClass += ' has-events';
            
            let cellHTML = `<div class="${cellClass}" data-day="${day}">
                <div class="calendar-date-container">
                    <div class="calendar-date">${day}</div>
                    <button class="add-event-btn" title="Add Event">+</button>
                </div>`;
            
            if (hasEvents) {
                cellHTML += `<div class="calendar-events">`;
                
                // Show max 2 events, then "+ more" indicator
                const displayCount = Math.min(2, events.length);
                for (let i = 0; i < displayCount; i++) {
                    const event = events[i];
                    cellHTML += `<div class="calendar-event event-${event.type}" title="${event.time}: ${event.title}">
                        ${event.title}
                    </div>`;
                }
                
                if (events.length > 2) {
                    cellHTML += `<div class="event-more">+${events.length - 2} more</div>`;
                }
                
                cellHTML += `</div>`;
            }
            
            cellHTML += `</div>`;
            
            return cellHTML;
        }
        
        // Function to generate sample events (this would be replaced with real data)
        function generateSampleEvents(year, month, daysInMonth) {
            const events = {};
            const eventTypes = ['inspection', 'delivery', 'meeting', 'report', 'issue'];
            const eventTitles = {
                'inspection': ['Safety Inspection', 'Quality Check', 'Equipment Inspection'],
                'delivery': ['Material Delivery', 'Equipment Arrival', 'Supplies Delivery'],
                'meeting': ['Team Meeting', 'Client Review', 'Planning Session'],
                'report': ['Progress Report Due', 'Financial Report', 'Weekly Report'],
                'issue': ['Plumbing Issue', 'Electrical Problem', 'Structural Concern']
            };
            
            // Add 15-20 random events throughout the month
            const numEvents = 15 + Math.floor(Math.random() * 6);
            
            for (let i = 0; i < numEvents; i++) {
                const day = Math.floor(Math.random() * daysInMonth) + 1;
                const eventType = eventTypes[Math.floor(Math.random() * eventTypes.length)];
                const eventTitle = eventTitles[eventType][Math.floor(Math.random() * eventTitles[eventType].length)];
                
                // Random time between 8 AM and 5 PM
                const hour = 8 + Math.floor(Math.random() * 10);
                const minute = Math.floor(Math.random() * 4) * 15; // 0, 15, 30, 45
                const time = `${hour}:${minute === 0 ? '00' : minute} ${hour >= 12 ? 'PM' : 'AM'}`;
                
                if (!events[day]) events[day] = [];
                
                events[day].push({
                    type: eventType,
                    title: eventTitle,
                    time: time
                });
            }
            
            // Sort events by time
            for (const day in events) {
                events[day].sort((a, b) => {
                    return a.time.localeCompare(b.time);
                });
            }
            
            return events;
        }
        
        // Initial render
        renderCalendar();
        
        // Remove the duplicate event listener that conflicts with our setupCalendarInteractions handler
    });
    </script>
</body>
</html> 