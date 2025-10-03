<?php
// Start session for authentication
session_start();

// Check if user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Redirect to login page if not logged in
    $_SESSION['error'] = "You must log in to access the dashboard";
    header('Location: login.php');
    exit();
}

// Check if user has the correct role
$allowed_roles = ['Site Manager', 'Senior Manager (Site)', 'Site Coordinator', 'Senior Manager (Purchase)', 'Purchase Manager'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    // Redirect to appropriate page based on role
    $_SESSION['error'] = "You don't have permission to access this page";
    header('Location: login.php');
    exit();
}

// Include database connection if needed for future features
// include_once('includes/db_connect.php');

// Get the site manager's name from session
$siteManagerName = isset($_SESSION['username']) ? $_SESSION['username'] : "Site Manager";

// Projects data
$projects = [
    [
        'id' => 1,
        'title' => 'Residential Tower - Phase 1',
        'location' => 'Mumbai, Maharashtra',
        'status' => 'progress',
        'progress' => 68,
        'budget' => '₹4.2 Cr',
        'start_date' => '2023-07-15',
        'end_date' => '2024-03-30',
        'supervisors' => [
            ['name' => 'Rahul Sharma', 'department' => 'Civil'],
            ['name' => 'Prakash Patel', 'department' => 'Electrical'],
            ['name' => 'Sunil Kumar', 'department' => 'MEP']
        ]
    ],
    [
        'id' => 2,
        'title' => 'Commercial Complex',
        'location' => 'Bangalore, Karnataka',
        'status' => 'pending',
        'progress' => 25,
        'budget' => '₹6.8 Cr',
        'start_date' => '2023-10-05',
        'end_date' => '2024-12-20',
        'supervisors' => [
            ['name' => 'Vijay Mehta', 'department' => 'Civil'],
            ['name' => 'Rajesh Singh', 'department' => 'Plumbing']
        ]
    ],
    [
        'id' => 3,
        'title' => 'Township Development',
        'location' => 'Hyderabad, Telangana',
        'status' => 'hold',
        'progress' => 42,
        'budget' => '₹12.5 Cr',
        'start_date' => '2023-03-10',
        'end_date' => '2025-06-15',
        'supervisors' => [
            ['name' => 'Amar Reddy', 'department' => 'Civil'],
            ['name' => 'Kishore Kumar', 'department' => 'Electrical']
        ]
    ],
    [
        'id' => 4,
        'title' => 'Luxury Villa Project',
        'location' => 'Goa',
        'status' => 'completed',
        'progress' => 100,
        'budget' => '₹3.6 Cr',
        'start_date' => '2022-11-20',
        'end_date' => '2023-08-30',
        'supervisors' => [
            ['name' => 'Kunal Kapoor', 'department' => 'Civil']
        ]
    ]
];

// Recent activities data
$activities = [
    [
        'id' => 1,
        'title' => 'Budget approved for Commercial Complex',
        'time' => '2 hours ago',
        'user' => 'Finance Department',
        'icon' => 'money-bill-wave',
        'color' => 'success'
    ],
    [
        'id' => 2,
        'title' => 'New issue reported at Residential Tower',
        'time' => '4 hours ago',
        'user' => 'Rahul Sharma',
        'icon' => 'exclamation-triangle',
        'color' => 'danger'
    ],
    [
        'id' => 3,
        'title' => 'Township Development timeline updated',
        'time' => 'Yesterday',
        'user' => 'Project Planning',
        'icon' => 'calendar-alt',
        'color' => 'primary'
    ],
    [
        'id' => 4,
        'title' => 'Equipment allocation approved for Site #2',
        'time' => '2 days ago',
        'user' => 'Logistics Department',
        'icon' => 'truck',
        'color' => 'info'
    ],
    [
        'id' => 5,
        'title' => 'Monthly report for April submitted',
        'time' => '4 days ago',
        'user' => 'Reporting Team',
        'icon' => 'file-alt',
        'color' => 'secondary'
    ]
];

// Tasks data
$tasks = [
    [
        'id' => 1,
        'title' => 'Review budget for Q2',
        'due' => 'Today',
        'priority' => 'high'
    ],
    [
        'id' => 2,
        'title' => 'Meeting with civil contractors',
        'due' => 'Tomorrow',
        'priority' => 'medium'
    ],
    [
        'id' => 3,
        'title' => 'Approve material requisitions',
        'due' => 'May 25, 2023',
        'priority' => 'high'
    ],
    [
        'id' => 4,
        'title' => 'Site visit to Residential Tower',
        'due' => 'May 27, 2023',
        'priority' => 'medium'
    ],
    [
        'id' => 5,
        'title' => 'Quarterly performance review',
        'due' => 'June 5, 2023',
        'priority' => 'low'
    ]
];

// Stats data
$stats = [
    [
        'title' => 'Active Projects',
        'value' => 8,
        'icon' => 'building',
        'color' => 'primary'
    ],
    [
        'title' => 'Active Sites',
        'value' => 12,
        'icon' => 'map-marker-alt',
        'color' => 'success'
    ],
    [
        'title' => 'Supervisors',
        'value' => 24,
        'icon' => 'user-hard-hat',
        'color' => 'info'
    ],
    [
        'title' => 'Workers',
        'value' => 356,
        'icon' => 'users',
        'color' => 'warning'
    ],
    [
        'title' => 'Open Issues',
        'value' => 15,
        'icon' => 'exclamation-circle',
        'color' => 'danger'
    ]
];

// Get current date and time in IST (Indian Standard Time)
date_default_timezone_set('Asia/Kolkata');
$currentHour = (int)date('H');
$currentMinute = date('i');
$currentSecond = date('s');
$currentTime = date('h:i A'); // Format with leading zero for hour
$currentDate = date('l, d F Y'); // Format with leading zero for day

// Set greeting based on time of day
if ($currentHour < 12) {
    $greeting = "Good Morning";
    $greetingIcon = "sun";
    $greetingColor = "#f6ad55"; // Orange
} elseif ($currentHour < 17) {
    $greeting = "Good Afternoon";
    $greetingIcon = "sun";
    $greetingColor = "#f6ad55"; // Orange
} else {
    $greeting = "Good Evening";
    $greetingIcon = "moon";
    $greetingColor = "#6b46c1"; // Purple
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Manager Dashboard</title>
    
    <!-- Include CSS files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/manager/dashboard.css">
    <link rel="stylesheet" href="css/manager/site-overview.css">
    <link rel="stylesheet" href="css/manager/calendar-stats.css">
    <link rel="stylesheet" href="css/manager/calendar-event-modal.css">
    <link rel="stylesheet" href="css/supervisor/new-travel-expense-modal.css">
    <link rel="stylesheet" href="css/manager/event-details-modal.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --primary-hover: #0b5ed7;
            --success-color: #28a745;
            --success-hover: #218838;
            --danger-color: #dc3545;
            --danger-hover: #c82333;
            --warning-color: #ffc107;
            --warning-hover: #e0a800;
            --info-color: #17a2b8;
            --info-hover: #138496;
            --secondary-color: #6c757d;
            --secondary-hover: #5a6268;
            --text-color: #212529;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
            --light-bg: #f8f9fa;
        }

        /* Greeting Section Styles with adjusted positioning */
        .greeting-section {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
            position: relative;
            overflow: visible;
            width: 100%;
            box-sizing: border-box;
        }

        .greeting-container {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            position: relative;
            z-index: 900;
            flex-wrap: wrap;
        }
        
        .greeting-text {
            flex: 1;
            min-width: 280px;
            margin-right: 20px;
            z-index: 900;
        }
        
        .greeting-text h1 {
            font-size: 1.7rem;
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            word-break: break-word;
        }
        
        .greeting-text p {
            color: var(--text-muted);
            font-size: 1rem;
            margin: 0 0 12px 0;
        }
        
        .greeting-icon {
            margin-right: 15px;
            font-size: 1.5rem;
        }
        
        .time-info {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .time-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .time-item i {
            color: var(--primary-color);
            font-size: 1rem;
        }
        
        .time-label {
            background-color: rgba(13, 110, 253, 0.1);
            color: var(--primary-color);
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 5px;
        }
        
        @media (max-width: 768px) {
        .main-content {
                margin-left: 0;
            padding: 20px;
        }

            #leftPanel {
                width: 0;
            overflow: hidden;
        }

            #leftPanel.mobile-open {
                width: 250px;
            }
            
            .greeting-text h1 {
                font-size: 1.5rem;
            }
            
            .user-actions {
                margin-top: 15px;
                justify-content: flex-start;
            width: 100%;
            }
        }

        /* User Actions Styles */
        .user-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-left: auto;
            position: relative;
            flex-wrap: wrap;
        }

        /* Notification Bell */
        .notification-dropdown, .profile-dropdown {
            position: relative;
            z-index: 10000 !important;
        }

        .profile-dropdown .dropdown-menu {
            right: 0;
            left: auto;
        }

        .notification-dropdown .dropdown-menu {
            left: 0;
            right: auto;
        }

        .notification-bell {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(13, 110, 253, 0.1);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }

        .notification-bell:hover {
            background-color: rgba(13, 110, 253, 0.2);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: var(--danger-color);
            color: white;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }

        /* Profile Avatar */
        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .profile-avatar:hover {
            border-color: var(--primary-color);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Dropdown Menu Styles */
        .dropdown-menu {
            position: fixed;
            top: auto;
            right: auto;
            min-width: 250px;
            width: 250px;
            max-height: 350px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            padding: 0;
            z-index: 10000 !important;
            display: none;
            overflow: hidden;
        }

        .notification-items-container {
            max-height: 250px;
            overflow-y: auto;
            scrollbar-width: thin;
        }

        .notification-items-container::-webkit-scrollbar {
            width: 4px;
        }

        .notification-items-container::-webkit-scrollbar-thumb {
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
        }

        /* Add dropdown arrow */
        .notification-dropdown .dropdown-menu::before {
            content: '';
            position: absolute;
            top: -8px;
            left: 15px;
            width: 16px;
            height: 16px;
            background-color: white;
            transform: rotate(45deg);
            box-shadow: -2px -2px 5px rgba(0, 0, 0, 0.04);
            z-index: -1;
        }

        /* Profile dropdown arrow */
        .profile-dropdown .dropdown-menu::before {
            content: '';
            position: absolute;
            top: -8px;
            right: 15px;
            left: auto;
            width: 16px;
            height: 16px;
            background-color: white;
            transform: rotate(45deg);
            box-shadow: -2px -2px 5px rgba(0, 0, 0, 0.04);
            z-index: -1;
        }

        .dropdown-menu.show {
            display: block;
            animation: fadeInDown 0.3s ease;
        }

        .dropdown-header {
            padding: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dropdown-header h6 {
            margin: 0;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .dropdown-header p {
            color: var(--text-muted);
            margin: 3px 0 0 0;
            font-size: 0.75rem;
        }

        .mark-all {
            color: var(--primary-color);
            font-size: 0.75rem;
            cursor: pointer;
        }

        .dropdown-divider {
            height: 1px;
            background-color: var(--border-color);
            margin: 0;
        }

        .notification-item {
            display: flex;
            padding: 12px;
            transition: background-color 0.3s ease;
            cursor: pointer;
        }

        .notification-item:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .notification-item.unread {
            background-color: rgba(13, 110, 253, 0.05);
        }

        .notification-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
        }
        
        .greeting-section {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
            position: relative;
            overflow: visible;
            width: 100%;
            box-sizing: border-box;
            /* Diwali Theme Background */
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(255, 215, 0, 0.1) 0px, transparent 2px),
                radial-gradient(circle at 20% 80%, rgba(255, 69, 0, 0.1) 0px, transparent 2px),
                radial-gradient(circle at 80% 30%, rgba(255, 215, 0, 0.1) 0px, transparent 2px),
                radial-gradient(circle at 60% 70%, rgba(255, 69, 0, 0.1) 0px, transparent 2px);
            background-size: 100px 100px;
        }
        
        /* Diwali Decorations */
        .diwali-decoration {
            position: absolute;
            z-index: 1;
            pointer-events: none;
        }
        
        .diya {
            width: 24px;
            height: 30px;
            background: linear-gradient(to bottom, #FFD700, #FF8C00);
            border-radius: 50% 50% 20% 20%;
            position: relative;
            box-shadow: 0 0 8px #FFD700, 0 0 20px #FF8C00;
            animation: diyaGlow 2s infinite alternate;
        }
        
        .diya::before {
            content: '';
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 12px;
            background: #8B4513;
        }
        
        .diya::after {
            content: '';
            position: absolute;
            top: -22px;
            left: 50%;
            transform: translateX(-50%);
            width: 8px;
            height: 12px;
            background: #FFD700;
            border-radius: 50%;
            box-shadow: 0 0 10px #FFD700, 0 0 20px #FF8C00;
            animation: flameFlicker 0.5s infinite alternate;
        }
        
        .firecracker {
            position: absolute;
            width: 6px;
            height: 20px;
            background: linear-gradient(to bottom, #FF4500, #8B0000);
            border-radius: 3px;
            animation: firecrackerExplode 3s infinite;
        }
        
        .firecracker::before {
            content: '';
            position: absolute;
            top: -4px;
            left: 50%;
            transform: translateX(-50%);
            width: 10px;
            height: 10px;
            background: #FFD700;
            border-radius: 50%;
            box-shadow: 0 0 5px #FFD700;
        }
        
        .sparkle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: #FFD700;
            border-radius: 50%;
            box-shadow: 0 0 8px #FFD700;
            animation: sparkleTwinkle 1.5s infinite alternate;
        }
        
        @keyframes diyaGlow {
            0% { box-shadow: 0 0 8px #FFD700, 0 0 20px #FF8C00; }
            100% { box-shadow: 0 0 12px #FFD700, 0 0 25px #FF8C00, 0 0 35px #FF8C00; }
        }
        
        @keyframes flameFlicker {
            0% { box-shadow: 0 0 10px #FFD700, 0 0 20px #FF8C00; }
            100% { box-shadow: 0 0 15px #FFD700, 0 0 25px #FF8C00, 0 0 35px #FF8C00; }
        }
        
        @keyframes firecrackerExplode {
            0% { transform: translateY(0); opacity: 1; }
            50% { transform: translateY(-20px); opacity: 0.8; }
            100% { transform: translateY(-100px); opacity: 0; }
        }
        
        @keyframes sparkleTwinkle {
            0% { opacity: 0.3; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1.2); }
        }
        .notification-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px; 
            color: white;
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
        }

        .notification-text {
            margin: 0 0 5px 0;
            font-size: 0.85rem;
            white-space: normal;
            word-wrap: break-word;
        }

        .notification-time {
            color: var(--text-muted);
            font-size: 0.7rem;
            margin: 0;
        }

        .dropdown-footer {
            padding: 8px;
            text-align: center;
        }

        .dropdown-footer a {
            color: var(--primary-color);
            font-size: 0.8rem;
            text-decoration: none;
        }

        .dropdown-footer a:hover {
            text-decoration: underline;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            text-decoration: none;
            color: var(--text-color);
            transition: background-color 0.3s ease;
        }

        .dropdown-item:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .dropdown-item i {
            margin-right: 10px;
            color: var(--primary-color);
            width: 20px;
            text-align: center;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive styles for user actions */
        @media (max-width: 768px) {
            .greeting-container {
                flex-direction: column;
            }
            
            .user-actions {
                margin-left: 0;
                margin-top: 20px;
                align-self: flex-end;
            }
        }

        /* Add or update overlay styles in your CSS */
        .overlay {
                position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 900;
            display: none;
        }

        .overlay.active {
            display: block;
            }
            
            .main-container {
            display: flex;
            height: 100vh;
            overflow: hidden;
            position: relative;
        }

        .main-content {
            flex: 1;
            padding: 30px 30px 30px 30px;
            overflow-y: auto;
            height: 100vh;
            box-sizing: border-box;
            margin-left: 250px; /* Match the width of the left panel */
            position: relative;
            transition: margin-left 0.3s;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Collapsed left panel styles */
        #leftPanel.collapsed {
            width: 70px;
            overflow: visible; /* Important to keep the toggle button visible */
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
            background-color: #f8f9fa;
            overflow: hidden;
            height: 100vh;
        }

        html {
            height: 100%;
            overflow: hidden;
        }

        /* Add specific positioning for profile dropdown arrow */
        .profile-menu::before {
            left: auto;
            right: 15px;
        }

        /* Punch Button Styles */
        .punch-button {
            background-color: var(--success-color, #28a745);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 15px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-right: 15px;
        }

        .punch-button:hover {
            background-color: var(--success-hover, #218838);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .punch-button:active {
            transform: translateY(1px);
        }

        .punch-button i {
            font-size: 0.9rem;
        }

        /* Notification Styles */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10002;
        }

        .notification {
            background: white;
            border-radius: 6px;
            padding: 12px 20px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease;
            max-width: 300px;
        }

        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }

        .notification i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .notification.success i {
            color: var(--success-color, #28a745);
        }

        .notification.error i {
            color: var(--danger-color, #dc3545);
        }

        .notification.warning i {
            color: var(--warning-color, #ffc107);
        }

        .notification-message {
            flex-grow: 1;
            font-size: 0.9rem;
        }

        .row {
            margin-right: -15px;
            margin-left: -15px;
            overflow-x: hidden;
        }

        /* Add scrollbar styling for the main content */
        .main-content::-webkit-scrollbar {
            width: 8px;
        }

        .main-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .main-content::-webkit-scrollbar-thumb {
            background: #c5c5c5;
            border-radius: 10px;
        }

        .main-content::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .dashboard-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 25px;
            overflow: hidden;
        }

        /* Adjust left panel to ensure it doesn't overlap */
        #leftPanel {
            width: 250px;
            background-color: #1e2a78;
            color: white;
            transition: all 0.3s;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
                position: fixed;
            left: 0;
            top: 0;
        }

        #leftPanel.needs-scrolling {
            overflow-y: auto;
        }

        #leftPanel::-webkit-scrollbar {
            width: 6px;
            background-color: rgba(255, 255, 255, 0.1);
        }

        #leftPanel::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }

        #leftPanel::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .profile-menu, .notification-menu {
            z-index: 10001 !important;
        }

        .hamburger-menu {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 5px;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            z-index: 1001;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 768px) {
            .hamburger-menu {
                display: flex;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            #leftPanel {
                width: 0;
                overflow: hidden;
                transform: translateX(-100%);
                transition: transform 0.3s, width 0.3s;
            }
            
            #leftPanel.mobile-open {
                width: 250px;
                transform: translateX(0);
            }
        }

        /* Ensure the toggle button is always visible */
        .toggle-btn {
            position: absolute;
            right: -15px;
            top: 20px;
            width: 30px;
            height: 30px;
            background-color: white;
            color: #1e2a78;
            border-radius: 50%;
            border: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1001;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .toggle-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
        }

        /* Prevent the panel from completely disappearing */
        #leftPanel.collapsed {
            width: 70px;
            overflow: visible; /* Important to keep the toggle button visible */
        }

        /* Hide text but keep icons when collapsed */
        #leftPanel.collapsed .menu-text {
            display: none;
        }

        /* Adjust spacing for icons when panel is collapsed */
        #leftPanel.collapsed .menu-item i {
            margin-right: 0;
        }

        /* Center the icons when collapsed */
        #leftPanel.collapsed .menu-item {
            justify-content: center;
        }

        /* Camera Modal Styles */
        .camera-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            background-color: rgba(0, 0, 0, 0.85);
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .camera-modal.open {
            display: flex;
        }

        .camera-container {
            width: 90%;
            max-width: 640px;
            max-height: 80vh;
            background-color: #fff;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .camera-header {
            padding: 15px;
            background-color: #1e2a78;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .camera-title {
            font-size: 1.2rem;
            font-weight: 500;
            margin: 0;
        }

        .close-camera-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 5px;
        }

        .camera-body {
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            overflow-y: auto;
        }

        .video-container {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 75%; /* 4:3 aspect ratio */
            background-color: #000;
            border-radius: 8px;
            overflow: hidden;
        }

        #cameraVideo {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        #capturedImageContainer {
            display: none;
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 75%; /* 4:3 aspect ratio */
            background-color: #f1f1f1;
            border-radius: 8px;
            overflow: hidden;
        }

        #capturedImage {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .camera-controls {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .camera-btn {
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            border: none;
            transition: all 0.2s ease;
        }

        .camera-btn-primary {
            background-color: #0d6efd;
            color: white;
        }

        .camera-btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .camera-btn-success {
            background-color: #28a745;
            color: white;
        }

        .camera-btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .location-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            font-size: 0.9rem;
        }

        .location-info h4 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1rem;
            color: #495057;
        }

        .location-details {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .location-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .location-item i {
            color: #0d6efd;
            width: 20px;
            text-align: center;
        }

        .rotate-camera-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 2;
        }

        @media (max-width: 576px) {
            .camera-container {
                width: 95%;
                max-height: 90vh;
            }
            
            .camera-controls {
                flex-direction: column;
                width: 100%;
            }
            
            .camera-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Location Item Styles */
        .location-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
            padding: 5px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }

        .location-item:hover {
            background-color: rgba(0, 0, 0, 0.03);
        }

        .location-item i {
            color: #0d6efd;
            width: 20px;
            text-align: center;
        }
        
        .location-item.success i {
            color: #28a745;
        }
        
        .location-item.warning i {
            color: #ffc107;
        }
        
        .location-item.danger i {
            color: #dc3545;
        }
        
        /* Outside Location Reason Container */
        .outside-location-reason {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .outside-location-reason label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #856404;
        }
        
        .word-count-display {
            font-size: 12px;
            color: #6c757d;
            text-align: right;
            margin-top: 5px;
        }
        
        /* Shift Info Styles */
        .shift-info-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-top: 15px;
            font-size: 0.9rem;
        }
        
        .shift-info-container h4 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1rem;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .shift-info-container h4 i {
            color: #0d6efd;
        }
        
        .shift-details {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .shift-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .shift-item i {
            color: #0d6efd;
            width: 20px;
            text-align: center;
        }
        
        /* Camera Controls */
        .rotate-camera-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 2;
        }

        @media (max-width: 576px) {
            .camera-container {
                width: 95%;
                max-height: 90vh;
            }
            
            .camera-controls {
                flex-direction: column;
                width: 100%;
            }
            
            .camera-btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Preloader Overlay Styles */
        .preloader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        
        .preloader-content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .preloader-content .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        #preloaderMessage {
            font-size: 1.1rem;
            margin-top: 15px;
            color: #333;
        }
    </style>
</head>
<body>
    <!-- Overlay for mobile menu -->
    <div class="overlay" id="overlay"></div>
    
    <!-- Hamburger menu for mobile -->
    <div class="hamburger-menu" id="hamburgerMenu">
        <i class="fas fa-bars"></i>
    </div>

    <!-- Camera Modal -->
    <div class="camera-modal" id="cameraModal">
        <div class="camera-container">
            <div class="camera-header">
                <h3 class="camera-title" id="cameraTitle">Take Selfie for Punch In</h3>
                <button class="close-camera-btn" id="closeCameraBtn">
                    <i class="fas fa-times"></i>
            </button>
            </div>
            <div class="camera-body">
                <div class="video-container" id="videoContainer">
                    <video id="cameraVideo" autoplay playsinline></video>
                    <button class="rotate-camera-btn" id="rotateCameraBtn">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div id="capturedImageContainer">
                    <img id="capturedImage" src="" alt="Captured photo">
                </div>
                <div class="location-info">
                    <h4><i class="fas fa-map-marker-alt"></i> Your Location</h4>
                    <div class="location-details">
                        <div class="location-item" id="locationStatus">
                            <i class="fas fa-info-circle"></i>
                            <span>Getting your location...</span>
                        </div>
                        <div class="location-item" id="locationCoords">
                            <i class="fas fa-compass"></i>
                            <span>Coordinates: --</span>
                        </div>
                        <div class="location-item" id="locationAddress">
                            <i class="fas fa-map"></i>
                            <span>Address: --</span>
                        </div>
                        <!-- Add geofence status -->
                        <div class="location-item" id="geofenceStatus">
                            <i class="fas fa-map-marked-alt"></i>
                            <span>Checking location boundaries...</span>
                        </div>
                    </div>
                </div>
                <div class="shift-info-container" style="margin-top: 15px; background-color: #f8f9fa; border-radius: 8px; padding: 12px;">
                    <h4><i class="fas fa-business-time"></i> Your Shift</h4>
                    <div class="shift-details" id="shiftDetails">
                        <div class="shift-item">
                            <i class="fas fa-calendar-day"></i>
                            <span id="shiftName">Loading shift information...</span>
                        </div>
                        <div class="shift-item">
                            <i class="fas fa-clock"></i>
                            <span id="shiftTiming">--</span>
                        </div>
                        <div class="shift-item">
                            <i class="fas fa-calendar-minus"></i>
                            <span id="shiftWeeklyOffs">--</span>
                        </div>
                    </div>
                </div>
                        <!-- Outside location reason container -->
        <div class="outside-location-reason" id="outsideLocationReasonContainer" style="display: none; margin-top: 15px;">
            <label for="outsideLocationReason">Please provide a reason for being outside assigned location:</label>
            <textarea id="outsideLocationReason" class="form-control" rows="3" placeholder="Enter reason here..."></textarea>
            <div id="outsideLocationWordCount" class="word-count-display">Words: 0 (minimum 5)</div>
        </div>
        <script>
            // Initialize word counter when the document is ready
            document.addEventListener('DOMContentLoaded', function() {
                const outsideLocationReason = document.getElementById('outsideLocationReason');
                const outsideLocationWordCount = document.getElementById('outsideLocationWordCount');
                
                if (outsideLocationReason && outsideLocationWordCount) {
                    outsideLocationReason.addEventListener('input', function() {
                        updateWordCount(this, outsideLocationWordCount);
                    });
                }
            });
            
            // Function to update word count
function updateWordCount(textarea, displayElement) {
    if (!textarea || !displayElement) return;
    
    const text = textarea.value.trim();
    // Split by whitespace, filter out empty strings and strings with only special characters
    const wordCount = text ? text.split(/\s+/)
        .filter(word => word.length > 0)
        .filter(word => /[a-zA-Z0-9\u0900-\u097F]/.test(word)) // Ensure word has at least one alphanumeric or Hindi character
        .length : 0;
    
    // Update the display
    displayElement.textContent = `Words: ${wordCount} (minimum 5)`;
    
    // Change color based on word count
    if (wordCount < 5) {
        displayElement.style.color = '#dc3545'; // Red for less than minimum
    } else {
        displayElement.style.color = '#28a745'; // Green for meeting minimum
    }
}
        </script>
                        <div class="work-report-container" id="workReportContainer" style="display: none; margin-top: 15px;">
            <h4><i class="fas fa-clipboard-list"></i> Work Report</h4>
            <textarea id="workReportText" class="form-control" rows="3" placeholder="Please summarize what you worked on today..."></textarea>
            <div id="workReportWordCount" class="word-count-display">Words: 0 (minimum 20)</div>
        </div>
        <script>
            // Initialize work report word counter when the document is ready
            document.addEventListener('DOMContentLoaded', function() {
                const workReportText = document.getElementById('workReportText');
                const workReportWordCount = document.getElementById('workReportWordCount');
                
                if (workReportText && workReportWordCount) {
                    workReportText.addEventListener('input', function() {
                        updateWorkReportWordCount(this, workReportWordCount);
                    });
                }
            });
            
            // Function to update work report word count
function updateWorkReportWordCount(textarea, displayElement) {
    if (!textarea || !displayElement) return;
    
    const text = textarea.value.trim();
    // Split by whitespace, filter out empty strings and strings with only special characters
    const wordCount = text ? text.split(/\s+/)
        .filter(word => word.length > 0)
        .filter(word => /[a-zA-Z0-9\u0900-\u097F]/.test(word)) // Ensure word has at least one alphanumeric or Hindi character
        .length : 0;
    
    // Update the display
    displayElement.textContent = `Words: ${wordCount} (minimum 20)`;
    
    // Change color based on word count
    if (wordCount < 20) {
        displayElement.style.color = '#dc3545'; // Red for less than minimum
    } else {
        displayElement.style.color = '#28a745'; // Green for meeting minimum
    }
}
        </script>
                <div class="camera-controls">
                    <button class="camera-btn camera-btn-primary" id="captureBtn">
                        <i class="fas fa-camera"></i> Capture Photo
                    </button>
                    <button class="camera-btn camera-btn-danger" id="retakeBtn" style="display: none;">
                        <i class="fas fa-redo"></i> Retake Photo
                    </button>
                    <button class="camera-btn camera-btn-success" id="confirmPunchBtn" style="display: none;">
                        <i class="fas fa-check"></i> Confirm Punch
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="main-container">
        <!-- Include left panel -->
        <?php include_once('includes/manager_panel.php'); ?>
        
        <!-- Main Content Area -->
        <div class="main-content" id="mainContent">
            <!-- Greeting Section -->
            <div class="greeting-section">
                <!-- Diwali Decorations -->
                <div class="diwali-decoration" style="top: 10px; left: 10px;">
                    <div class="diya"></div>
                </div>
                <div class="diwali-decoration" style="top: 10px; right: 10px;">
                    <div class="diya"></div>
                </div>
                <div class="diwali-decoration" style="bottom: 10px; left: 150px;">
                    <div class="sparkle"></div>
                </div>
                <div class="diwali-decoration" style="bottom: 30px; right: 120px;">
                    <div class="sparkle"></div>
                </div>
                <div class="diwali-decoration" style="top: 40px; right: 200px;">
                    <div class="sparkle"></div>
                </div>
                <div class="diwali-decoration" style="bottom: 50px; left: 200px;">
                    <div class="firecracker"></div>
                </div>
                <div class="greeting-container">
                    <div class="greeting-text">
                        <h1>
                            <i class="fas fa-<?php echo $greetingIcon; ?> greeting-icon" style="color: <?php echo $greetingColor; ?>"></i>
                            <?php echo $greeting; ?>, <?php echo $siteManagerName; ?>!
                        </h1>
                        <p>Welcome to your dashboard. Here's an overview of your projects and tasks.</p>
                        
                        <div class="time-info">
                            <span class="time-item"><i class="far fa-clock"></i> <span class="time-text"><?php echo $currentTime; ?></span> <span class="time-label">IST</span></span>
                            <span class="time-item">
                                <i class="far fa-calendar-alt"></i> 
                                <span class="date-container">
                                    <span class="weekday-text"><?php echo date('l'); ?></span>, 
                                    <span class="day-text"><?php echo date('d'); ?></span> 
                                    <span class="month-text"><?php echo date('F'); ?></span> 
                                    <span class="year-text"><?php echo date('Y'); ?></span>
                                </span>
                            </span>
                        </div>
                        <div class="shift-time-remaining" style="margin-top: 10px;">
                            <div class="time-display">
                                <i class="fas fa-business-time" style="color: #0d6efd;"></i>
                                <span>Shift remaining: <span id="shift-remaining-time">--:--:--</span></span>
                            </div>
                            <div class="shift-info" id="greeting-shift-info" style="margin-top: 5px; font-size: 0.85rem; color: #6c757d;"></div>
                    </div>
                </div>

                    <div class="user-actions">
                        <!-- Punch In Button -->
                        <button class="punch-button" id="punchButton">
                            <i class="fas fa-sign-in-alt"></i> Punch In
                        </button>
                        
                        <!-- Notification Bell -->
                        <div class="notification-dropdown">
                            <div class="notification-bell" id="notificationBell">
                                <i class="fas fa-bell"></i>
                                <span class="notification-badge">3</span>
                            </div>
                            <div class="dropdown-menu notification-menu" id="notificationMenu">
                                <div class="dropdown-header">
                                    <h6>Notifications</h6>
                                    <span class="mark-all">Mark all read</span>
                        </div>
                                <div class="notification-items-container">
                                    <div class="notification-item unread">
                                        <div class="notification-icon bg-primary">
                                            <i class="fas fa-file-alt"></i>
                    </div>
                                        <div class="notification-content">
                                            <p class="notification-text">New report is available</p>
                                            <p class="notification-time">2 hours ago</p>
                            </div>
                        </div>
                                    <div class="notification-item unread">
                                        <div class="notification-icon bg-success">
                                            <i class="fas fa-check-circle"></i>
                    </div>
                                        <div class="notification-content">
                                            <p class="notification-text">Project "Commercial Complex" approved</p>
                                            <p class="notification-time">5 hours ago</p>
                            </div>
                        </div>
                                    <div class="notification-item unread">
                                        <div class="notification-icon bg-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                    </div>
                                        <div class="notification-content">
                                            <p class="notification-text">Deadline approaching: Township Development</p>
                                            <p class="notification-time">1 day ago</p>
                            </div>
                                    </div>
                                </div>
                                <div class="dropdown-footer">
                                    <a href="#">View all notifications</a>
                        </div>
                    </div>
                </div>

                        <!-- User Profile -->
                        <div class="profile-dropdown">
                            <div class="profile-avatar" id="profileAvatar">
                                <?php if(isset($_SESSION['profile_picture']) && !empty($_SESSION['profile_picture'])): ?>
                                    <img src="<?php echo $_SESSION['profile_picture']; ?>" alt="<?php echo $siteManagerName; ?>">
                                <?php else: ?>
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($siteManagerName); ?>&background=0D8ABC&color=fff" alt="<?php echo $siteManagerName; ?>">
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-menu profile-menu" id="profileMenu">
                                <div class="dropdown-header">
                                    <h6><?php echo $siteManagerName; ?></h6>
                                    <p><?php echo $_SESSION['role']; ?></p>
                                </div>
                                <div class="dropdown-divider"></div>
                                <a href="site_manager_profile.php" class="dropdown-item">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                                <a href="settings.php" class="dropdown-item">
                                    <i class="fas fa-cog"></i> Settings
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="logout.php" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Site Overview Dashboard -->
            <div class="site-overview-section">
                <div class="site-overview-header">
                    <h2>Site Overview Dashboard</h2>
                    <div class="d-flex align-items-center">
                        <button id="refreshSiteOverview" class="btn btn-sm btn-outline-primary mr-2" title="Refresh Data">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <a href="site_details.php" class="view-all">
                            View All Sites <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="site-overview-cards">
                    <!-- Safety Card -->
                    <div class="site-card" data-card-type="supervisors">
                        <div class="site-card-header">
                            <h3 class="site-card-title">Site Supervisors Present</h3>
                            <div class="site-card-icon bg-safety">
                                <i class="fas fa-user-hard-hat"></i>
                            </div>
                        </div>
                        <div class="site-card-body">
                            <div class="site-card-value" data-value="0">0</div>
                            <div class="site-card-trend trend-neutral">
                                <i class="fas fa-minus"></i>
                                <span>Loading data...</span>
                            </div>
                            <div class="site-card-progress">
                                <div class="site-card-progress-bar bg-safety" data-width="0" style="width: 0%"></div>
                            </div>
                            <div class="site-card-stats">
                                <div class="site-card-stat" data-stat="present">
                                    <div class="site-card-stat-value">0</div>
                                    <div class="site-card-stat-label">Present</div>
                                </div>
                                <div class="site-card-stat">
                                    <div class="site-card-stat-value">0</div>
                                    <div class="site-card-stat-label">Total</div>
                                </div>
                            </div>
                            
                            <!-- Site Supervisors Present Today -->
                            <div class="supervisors-present">
                                <h4>Today's Supervisors</h4>
                                <div class="supervisor-avatars" id="supervisorAvatars">
                                    <div class="text-center w-100 py-2">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                        <p class="mt-2 small text-muted">Loading supervisors...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="site-card-footer">
                            <span>Click on card to view all supervisors</span>
                        </div>
                    </div>
                    
                    <!-- Supervisor Details Modal -->
                    <div class="modal fade" id="supervisorModal" tabindex="-1" role="dialog" aria-labelledby="supervisorModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="supervisorModalLabel">Site Supervisors Present Today</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="row" id="supervisorModalContent">
                                        <!-- Supervisor details will be loaded here dynamically -->
                                        <div class="col-12">
                                            <div class="text-center py-5">
                                                <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                                                    <span class="sr-only">Loading...</span>
                                                </div>
                                                <p class="mt-3 text-muted">Loading supervisor details...</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary" id="viewAllSupervisorsBtn">View All Supervisors</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Productivity Card -->
                    <div class="site-card" data-card-type="productivity">
                        <div class="site-card-header">
                            <h3 class="site-card-title">Labor Attendance</h3>
                            <div class="site-card-icon bg-productivity">
                                <i class="fas fa-hard-hat"></i>
                            </div>
                        </div>
                        <div class="site-card-body">
                            <div class="site-card-value" data-value="0">0</div>
                            <div class="site-card-trend trend-neutral">
                                <i class="fas fa-minus"></i>
                                <span>Loading data...</span>
                            </div>
                            <div class="site-card-progress">
                                <div class="site-card-progress-bar bg-productivity" data-width="0" style="width: 0%"></div>
                            </div>
                            <div class="site-card-stats">
                                <div class="site-card-stat" data-stat="tasks">
                                    <div class="site-card-stat-value">0</div>
                                    <div class="site-card-stat-label">Present</div>
                                </div>
                                <div class="site-card-stat">
                                    <div class="site-card-stat-value">0%</div>
                                    <div class="site-card-stat-label">Attendance</div>
                                </div>
                            </div>
                        </div>
                        <div class="site-card-footer">
                            Click to view labor attendance details
                        </div>
                    </div>
                    
                    <!-- Site Supervisors on Leave Card -->
                    <div class="site-card" data-card-type="supervisors-leave">
                        <div class="site-card-header">
                            <h3 class="site-card-title">Supervisors on Leave</h3>
                            <div class="site-card-icon bg-warning">
                                <i class="fas fa-user-clock"></i>
                            </div>
                        </div>
                        <div class="site-card-body">
                            <div class="site-card-value" id="supervisorLeaveCount">0</div>
                            <div class="site-card-trend trend-neutral">
                                <i class="fas fa-info-circle"></i>
                                <span>Currently on leave</span>
                            </div>
                            <div class="site-card-progress">
                                <div class="site-card-progress-bar bg-warning" id="supervisorLeaveProgressBar" style="width: 0%"></div>
                            </div>
                            <div class="supervisors-on-leave mt-3">
                                <h4>Supervisors on Leave</h4>
                                <div class="supervisor-leave-list" id="supervisorLeaveList">
                                    <div class="text-center py-2">
                                        <div class="spinner-border spinner-border-sm text-secondary" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                        <span class="ml-2">Loading data...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="site-card-footer">
                            <span>Click to view all leave details</span>
                        </div>
                    </div>
                    
                    <!-- Pending Leave Requests Card - Only visible to Senior Manager (Site) -->
                    <div id="pendingLeaveCardContainer" style="display: none;">
                        <div class="site-card" data-card-type="pending-leave">
                            <div class="site-card-header">
                                <h3 class="site-card-title">Leave Requests</h3>
                                <div class="site-card-icon bg-danger">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                            <div class="site-card-body">
                                <div class="site-card-value" id="pendingLeaveCount">0</div>
                                <div class="site-card-trend trend-neutral">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Awaiting approval</span>
                                </div>
                                <div class="site-card-progress">
                                    <div class="site-card-progress-bar bg-danger" id="pendingLeaveProgressBar" style="width: 0%"></div>
                                </div>
                                                            <div class="pending-leave-requests mt-3">
                                                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2">
                            <h4 class="mb-2 mb-md-0">Recent Requests</h4>
                            <div class="d-flex flex-wrap w-100 w-md-auto">
                                <div class="mr-2 mb-2 mb-md-0" style="min-width: 120px;">
                                    <select id="leaveMonthYearFilter" class="form-control form-control-sm w-100">
                                        <option value="">All Dates</option>
                                        <!-- Month options will be added by JavaScript -->
                                    </select>
                                </div>
                                <div style="min-width: 100px;">
                                    <select id="leaveStatusFilter" class="form-control form-control-sm w-100">
                                        <option value="pending" selected>Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                        <option value="all">All</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                                <div class="pending-leave-list" id="pendingLeaveList" style="max-height: 180px; overflow-y: auto; scrollbar-width: thin; border-radius: 4px; -webkit-overflow-scrolling: touch;">
                                    <div class="text-center py-2">
                                        <div class="spinner-border spinner-border-sm text-secondary" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                        <span class="ml-2">Loading data...</span>
                                    </div>
                                </div>
                            </div>
                            </div>
                            <div class="site-card-footer">
                                <span>Click to view all leave requests</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Travel Expenses Card (Replacing Workforce Card) - Hidden for Senior Manager (Site) -->
                    <div class="site-card" data-card-type="travel-expenses" id="travelExpensesCardContainer">
                        <div class="site-card-header">
                            <h3 class="site-card-title">Travel Expenses</h3>
                            <div class="site-card-icon bg-primary">
                                <i class="fas fa-car"></i>
                            </div>
                        </div>
                        <div class="site-card-body">
                            <div class="site-card-value" data-value="0">0</div>
                            <div class="site-card-trend trend-neutral">
                                <i class="fas fa-minus"></i>
                                <span>No recent expenses</span>
                            </div>
                            <div class="site-card-progress">
                                <div class="site-card-progress-bar bg-primary" data-width="0" style="width: 0%"></div>
                            </div>
                            <div class="site-card-stats">
                                <div class="site-card-stat" data-stat="expenses">
                                    <div class="site-card-stat-value">0</div>
                                    <div class="site-card-stat-label">This Month</div>
                                </div>
                                <div class="site-card-stat">
                                    <div class="site-card-stat-value">₹0</div>
                                    <div class="site-card-stat-label">Pending Payment</div>
                                </div>
                            </div>
                            <div class="text-center mt-3">
                                <button id="addTravelExpenseBtn" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-plus"></i> Add Expense
                                </button>
                            </div>
                        </div>
                        <div class="site-card-footer">
                            Click to view all travel expenses
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Calendar Stats Section -->
            <div class="calendar-stats-section">
                <div class="calendar-stats-header">
                    <h2>Calendar Stats</h2>
                    <div class="d-flex align-items-center">
                        <button id="refreshCalendarStats" class="btn btn-sm btn-outline-primary mr-2" title="Refresh Calendar">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <a href="calendar_view.php" class="view-all">
                            View Full Calendar <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="calendar-container">
                                <div class="calendar-header">
                                    <h3 class="mb-3"><?php echo date('F Y'); ?></h3>
                                    <div class="calendar-nav">
                                        <button class="btn btn-sm btn-outline-secondary" id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                                        <button class="btn btn-sm btn-outline-primary" id="currentMonth">Today</button>
                                        <button class="btn btn-sm btn-outline-secondary" id="nextMonth"><i class="fas fa-chevron-right"></i></button>
                                    </div>
                                </div>
                                
                                <div class="calendar-grid-container">
                                    <div class="calendar-grid">
                                        <!-- Weekday Headers -->
                                        <div class="calendar-weekday">Sun</div>
                                        <div class="calendar-weekday">Mon</div>
                                        <div class="calendar-weekday">Tue</div>
                                        <div class="calendar-weekday">Wed</div>
                                        <div class="calendar-weekday">Thu</div>
                                        <div class="calendar-weekday">Fri</div>
                                        <div class="calendar-weekday">Sat</div>
                                        
                                        <?php
                                        // Get current month and year
                                        $month = date('m');
                                        $year = date('Y');
                                        
                                        // Calculate the first day of the month
                                        $firstDayOfMonth = date('w', strtotime("$year-$month-01"));
                                        
                                        // Get the number of days in the month
                                        $daysInMonth = date('t', strtotime("$year-$month-01"));
                                        
                                        // Add empty cells for days before the 1st
                                        for ($i = 0; $i < $firstDayOfMonth; $i++) {
                                            echo '<div class="calendar-day empty"></div>';
                                        }
                                        
                                        // Output all days of the month
                                        for ($day = 1; $day <= $daysInMonth; $day++) {
                                            $date = "$year-$month-" . sprintf('%02d', $day);
                                            $isToday = ($date == date('Y-m-d'));
                                            
                                            // Sample data - in a real app, you would fetch events from database
                                            $hasEvents = in_array($day, [3, 8, 12, 15, 20, 25]);
                                            $eventCount = $hasEvents ? rand(1, 3) : 0;
                                            
                                            $classes = 'calendar-day';
                                            if ($isToday) $classes .= ' today';
                                            if ($hasEvents) $classes .= ' has-events';
                                            
                                            echo '<div class="' . $classes . '">';
                                            echo '<div class="day-number">' . $day . '</div>';
                                            
                                            if ($eventCount > 0) {
                                                echo '<div class="event-indicator">';
                                                echo '<span class="event-dot"></span>';
                                                if ($eventCount > 1) {
                                                    echo '<span class="event-count">+' . $eventCount . '</span>';
                                                }
                                                echo '</div>';
                                            }
                                            
                                            echo '</div>';
                                        }
                                        
                                        // Add empty cells for days after the last day of the month to complete the grid
                                        $cellsAfterMonth = 42 - ($firstDayOfMonth + $daysInMonth); // 42 = 6 rows × 7 days
                                        for ($i = 0; $i < $cellsAfterMonth; $i++) {
                                            echo '<div class="calendar-day empty"></div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="calendar-stats">
                                <h4>Month Overview</h4>
                                <div class="stats-item">
                                    <div class="stats-icon bg-primary">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <div class="stats-content">
                                        <div class="stats-value">12</div>
                                        <div class="stats-label">Total Events</div>
                                    </div>
                                </div>
                                
                                <div class="stats-item">
                                    <div class="stats-icon bg-success">
                                        <i class="fas fa-tasks"></i>
                                    </div>
                                    <div class="stats-content">
                                        <div class="stats-value">8</div>
                                        <div class="stats-label">Completed Tasks</div>
                                    </div>
                                </div>
                                
                                <div class="stats-item">
                                    <div class="stats-icon bg-warning">
                                        <i class="fas fa-exclamation-circle"></i>
                                    </div>
                                    <div class="stats-content">
                                        <div class="stats-value">5</div>
                                        <div class="stats-label">Pending Tasks</div>
                                    </div>
                                </div>
                                
                                <div class="stats-item">
                                    <div class="stats-icon bg-info">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stats-content">
                                        <div class="stats-value">3</div>
                                        <div class="stats-label">Team Meetings</div>
                                    </div>
                                </div>
                                
                                <div class="upcoming-events">
                                    <h4>Upcoming Events</h4>
                                    <div class="event-list">
                                        <div class="event-item">
                                            <div class="event-date">
                                                <span class="event-day"><?php echo date('d', strtotime('+2 days')); ?></span>
                                                <span class="event-month"><?php echo date('M', strtotime('+2 days')); ?></span>
                                            </div>
                                            <div class="event-details">
                                                <h5>Site Inspection</h5>
                                                <p><i class="fas fa-clock"></i> 10:00 AM - 12:00 PM</p>
                                            </div>
                                        </div>
                                        
                                        <div class="event-item">
                                            <div class="event-date">
                                                <span class="event-day"><?php echo date('d', strtotime('+5 days')); ?></span>
                                                <span class="event-month"><?php echo date('M', strtotime('+5 days')); ?></span>
                                            </div>
                                            <div class="event-details">
                                                <h5>Progress Review Meeting</h5>
                                                <p><i class="fas fa-clock"></i> 2:00 PM - 4:00 PM</p>
                                            </div>
                                        </div>
                                        
                                        <div class="event-item">
                                            <div class="event-date">
                                                <span class="event-day"><?php echo date('d', strtotime('+8 days')); ?></span>
                                                <span class="event-month"><?php echo date('M', strtotime('+8 days')); ?></span>
                                            </div>
                                            <div class="event-details">
                                                <h5>Material Delivery</h5>
                                                <p><i class="fas fa-clock"></i> 9:00 AM - 11:00 AM</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <style>
                /* Calendar Stats Section Styles */
                .calendar-stats-section {
                    margin-bottom: 30px;
                }
                
                .calendar-stats-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 20px;
                }
                
                .calendar-stats-header h2 {
                    font-size: 1.5rem;
                    margin: 0;
                }
                
                .calendar-container {
                    padding: 10px;
                }
                
                .calendar-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 15px;
                }
                
                .calendar-header h3 {
                    margin: 0;
                    font-weight: 600;
                }
                
                .calendar-nav {
                    display: flex;
                    gap: 10px;
                }
                
                .calendar-grid-container {
                    overflow-x: auto;
                }
                
                .calendar-grid {
                    display: grid;
                    grid-template-columns: repeat(7, 1fr);
                    gap: 5px;
                    min-width: 100%;
                }
                
                .calendar-weekday {
                    text-align: center;
                    font-weight: 600;
                    font-size: 0.8rem;
                    color: #6c757d;
                    padding: 5px;
                    background-color: #f8f9fa;
                    border-radius: 4px;
                }
                
                .calendar-day {
                    aspect-ratio: 1/1;
                    border-radius: 4px;
                    background-color: #ffffff;
                    border: 1px solid #e9ecef;
                    padding: 5px;
                    position: relative;
                    min-height: 40px;
                    display: flex;
                    flex-direction: column;
                }
                
                .calendar-day.empty {
                    background-color: #f8f9fa;
                    border: 1px dashed #dee2e6;
                }
                
                .calendar-day.today {
                    background-color: rgba(13, 110, 253, 0.1);
                    border: 2px solid #0d6efd;
                }
                
                .calendar-day.has-events {
                    background-color: rgba(40, 167, 69, 0.05);
                }
                
                .day-number {
                    font-weight: 600;
                    font-size: 0.9rem;
                }
                
                .event-indicator {
                    display: flex;
                    align-items: center;
                    gap: 3px;
                    margin-top: 3px;
                }
                
                .event-dot {
                    width: 6px;
                    height: 6px;
                    background-color: #0d6efd;
                    border-radius: 50%;
                }
                
                .event-count {
                    font-size: 0.7rem;
                    color: #6c757d;
                }
                
                /* Calendar Stats Styles */
                .calendar-stats {
                    height: 100%;
                    display: flex;
                    flex-direction: column;
                }
                
                .calendar-stats h4 {
                    font-size: 1.1rem;
                    margin-bottom: 15px;
                    font-weight: 600;
                }
                
                .stats-item {
                    display: flex;
                    align-items: center;
                    margin-bottom: 15px;
                    padding: 10px;
                    background-color: #f8f9fa;
                    border-radius: 8px;
                    transition: transform 0.2s;
                }
                
                .stats-item:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
                }
                
                .stats-icon {
                    width: 40px;
                    height: 40px;
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 15px;
                    color: white;
                }
                
                .stats-content {
                    flex: 1;
                }
                
                .stats-value {
                    font-size: 1.2rem;
                    font-weight: 600;
                }
                
                .stats-label {
                    font-size: 0.8rem;
                    color: #6c757d;
                }
                
                /* Upcoming Events Styles */
                .upcoming-events {
                    margin-top: 20px;
                }
                
                .event-list {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }
                
                .event-item {
                    display: flex;
                    background-color: #f8f9fa;
                    border-radius: 8px;
                    padding: 10px;
                    transition: transform 0.2s;
                }
                
                .event-item:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
                }
                
                .event-date {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    min-width: 50px;
                    margin-right: 15px;
                    background-color: #0d6efd;
                    color: white;
                    border-radius: 6px;
                    padding: 5px;
                }
                
                .event-day {
                    font-size: 1.2rem;
                    font-weight: 600;
                    line-height: 1;
                }
                
                .event-month {
                    font-size: 0.7rem;
                    text-transform: uppercase;
                }
                
                .event-details {
                    flex: 1;
                }
                
                .event-details h5 {
                    font-size: 0.9rem;
                    margin: 0 0 5px 0;
                }
                
                .event-details p {
                    font-size: 0.8rem;
                    color: #6c757d;
                    margin: 0;
                }
                
                .event-details p i {
                    margin-right: 5px;
                }
                
                @media (max-width: 768px) {
                    .calendar-grid {
                        grid-template-columns: repeat(7, minmax(40px, 1fr));
                    }
                    
                    .calendar-day {
                        min-height: 30px;
                    }
                }
            </style>
            
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="welcome-section">
                    <h1>Dashboard Overview</h1>
                    <p>Monitor your projects and activities</p>
                </div>
                <div class="header-actions">
                    <div class="btn btn-outline-primary">
                        <i class="fas fa-plus"></i> New Project
                    </div>
                    <div class="btn btn-outline-secondary">
                        <i class="fas fa-download"></i> Generate Report
                    </div>
                </div>
            </div>
            
            <!-- Stats Overview Row -->
                <div class="row mb-4">
                <?php foreach ($stats as $stat): ?>
                <div class="col-lg-1-5 col-md-4 col-sm-6 mb-3">
                    <div class="dashboard-card stat-card">
                        <div class="stat-icon bg-<?php echo $stat['color']; ?>">
                            <i class="fas fa-<?php echo $stat['icon']; ?>"></i>
                            </div>
                        <div class="stat-content">
                            <h4><?php echo $stat['value']; ?></h4>
                            <p><?php echo $stat['title']; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                    </div>

            <!-- Quick Actions -->
                <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <h3 class="card-title">Quick Actions</h3>
                        <div class="quick-actions">
                            <div class="quick-action-card">
                                <div class="quick-action-icon">
                                    <i class="fas fa-plus-circle"></i>
                            </div>
                                <div class="quick-action-title">New Project</div>
                                </div>
                            <div class="quick-action-card">
                                <div class="quick-action-icon">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div class="quick-action-title">Add Supervisor</div>
                                </div>
                            <div class="quick-action-card">
                                <div class="quick-action-icon">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                                <div class="quick-action-title">Material Request</div>
                            </div>
                            <div class="quick-action-card">
                                <div class="quick-action-icon">
                                    <i class="fas fa-calendar-plus"></i>
                        </div>
                                <div class="quick-action-title">Schedule Meeting</div>
                            </div>
                            <div class="quick-action-card">
                                <div class="quick-action-icon">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <div class="quick-action-title">Generate Report</div>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>

            <!-- Main Dashboard Content -->
                <div class="row">
                <!-- Projects Overview -->
                <div class="col-lg-8 mb-4">
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="card-title mb-0">Project Overview</h3>
                            <a href="project_overview.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>

                        <!-- Project Cards -->
                        <?php foreach ($projects as $project): ?>
                        <div class="project-card">
                            <div class="project-header">
                                <h4 class="project-title"><?php echo $project['title']; ?></h4>
                                <span class="project-status status-<?php echo $project['status']; ?>">
                                    <?php 
                                    switch ($project['status']) {
                                        case 'pending':
                                            echo 'Pending';
                                            break;
                                        case 'progress':
                                            echo 'In Progress';
                                            break;
                                        case 'hold':
                                            echo 'On Hold';
                                            break;
                                        case 'completed':
                                            echo 'Completed';
                                            break;
                                        case 'delayed':
                                            echo 'Delayed';
                                            break;
                                        default:
                                            echo 'Unknown';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="project-details">
                                <i class="fas fa-map-marker-alt"></i> <?php echo $project['location']; ?>
                            </div>
                            <div class="progress-container">
                                <div class="progress-header">
                                    <span>Progress</span>
                                    <span><?php echo $project['progress']; ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill bg-<?php echo $project['status'] == 'hold' ? 'warning' : ($project['status'] == 'completed' ? 'success' : 'primary'); ?>" style="width: <?php echo $project['progress']; ?>%"></div>
                                </div>
                            </div>
                            <div class="project-stats mt-3">
                                <div class="project-stat">
                                    <div class="project-stat-value"><?php echo $project['budget']; ?></div>
                                    <div class="project-stat-label">Budget</div>
                                </div>
                                <div class="project-stat">
                                    <div class="project-stat-value"><?php echo date('M j, Y', strtotime($project['start_date'])); ?></div>
                                    <div class="project-stat-label">Start Date</div>
                                </div>
                                <div class="project-stat">
                                    <div class="project-stat-value"><?php echo date('M j, Y', strtotime($project['end_date'])); ?></div>
                                    <div class="project-stat-label">End Date</div>
                                </div>
                                <div class="project-stat">
                                    <div class="project-stat-value"><?php echo count($project['supervisors']); ?></div>
                                    <div class="project-stat-label">Supervisors</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Right Sidebar -->
                <div class="col-lg-4">
                    <!-- Calendar Widget -->
                    <div class="dashboard-card calendar-widget mb-4">
                        <div class="calendar-header">
                            <h3 class="card-title mb-0">May 2023</h3>
                            <div class="calendar-nav">
                                <button><i class="fas fa-chevron-left"></i></button>
                                <button><i class="fas fa-chevron-right"></i></button>
                            </div>
                        </div>
                        <div class="calendar-grid">
                            <!-- Weekday Headers -->
                            <div class="calendar-weekday">Sun</div>
                            <div class="calendar-weekday">Mon</div>
                            <div class="calendar-weekday">Tue</div>
                            <div class="calendar-weekday">Wed</div>
                            <div class="calendar-weekday">Thu</div>
                            <div class="calendar-weekday">Fri</div>
                            <div class="calendar-weekday">Sat</div>
                            
                            <!-- Placeholder for calendar days (would be generated dynamically) -->
                            <?php
                            // Generate days for sample calendar
                            $days = [];
                            for ($i = 1; $i <= 31; $i++) {
                                $days[] = [
                                    'day' => $i,
                                    'is_today' => ($i == date('j') && date('m') == 5),
                                    'has_events' => in_array($i, [3, 8, 12, 15, 20, 25])
                                ];
                            }
                            
                            // Add empty cells for days before the 1st
                            $firstDayOfMonth = date('w', strtotime('2023-05-01')); // 0 for Sunday
                            for ($i = 0; $i < $firstDayOfMonth; $i++) {
                                echo '<div class="calendar-day"></div>';
                            }
                            
                            // Output all days
                            foreach ($days as $day) {
                                $classes = 'calendar-day';
                                if ($day['is_today']) $classes .= ' today';
                                if ($day['has_events']) $classes .= ' has-events';
                                
                                echo '<div class="' . $classes . '">' . $day['day'] . '</div>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Tasks Widget -->
                    <div class="dashboard-card mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="card-title mb-0">Upcoming Tasks</h3>
                            <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <ul class="task-list">
                            <?php foreach ($tasks as $task): ?>
                            <li class="task-item">
                                <div class="task-checkbox">
                                    <input type="checkbox" id="task-<?php echo $task['id']; ?>">
                                </div>
                                <div class="task-content">
                                    <div class="task-title"><?php echo $task['title']; ?></div>
                                    <div class="task-due">
                                        Due: <?php echo $task['due']; ?>
                                        <span class="task-priority priority-<?php echo $task['priority']; ?>"><?php echo ucfirst($task['priority']); ?></span>
                                    </div>
                            </div>
                                    </li>
                            <?php endforeach; ?>
                                </ul>
                            </div>
                    
                    <!-- Recent Activities -->
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="card-title mb-0">Recent Activities</h3>
                            <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <ul class="activity-list">
                            <?php foreach ($activities as $activity): ?>
                            <li class="activity-item">
                                <div class="activity-icon bg-<?php echo $activity['color']; ?>">
                                    <i class="fas fa-<?php echo $activity['icon']; ?>"></i>
                    </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo $activity['title']; ?></div>
                                    <div class="activity-meta">
                                        <span><i class="far fa-clock"></i> <?php echo $activity['time']; ?></span>
                                        <span><i class="far fa-user"></i> <?php echo $activity['user']; ?></span>
                            </div>
                            </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="js/manager/site-overview.js"></script>
    <script src="js/manager/supervisors-on-leave.js"></script>
    <script src="js/manager/pending-leave-requests.js"></script>
    <script src="js/manager/calendar-event-modal.js"></script>
    <script src="js/manager/calendar-stats.js"></script>
    <script>
        // Override the save_travel_expenses.php endpoint for the travel expense modal
        window.travelExpenseEndpoint = 'save_travel_expenses.php';
    </script>
    <script src="js/supervisor/new-travel-expense-modal.js"></script>
    
    <!-- Include manager password update modal with unique name -->
    <?php include_once('include_manager_pwd_update.php'); ?>
    <script>
        // Variables to track punch status
        let isPunchedIn = false;
        let isCompletedForToday = false;
        
        // DOM elements
        const punchButton = document.getElementById('punchButton');
        const cameraModal = document.getElementById('cameraModal');
        const cameraTitle = document.getElementById('cameraTitle');
        const closeCameraBtn = document.getElementById('closeCameraBtn');
        const cameraVideo = document.getElementById('cameraVideo');
        const videoContainer = document.getElementById('videoContainer');
        const capturedImageContainer = document.getElementById('capturedImageContainer');
        const capturedImage = document.getElementById('capturedImage');
        const captureBtn = document.getElementById('captureBtn');
        const retakeBtn = document.getElementById('retakeBtn');
        const confirmPunchBtn = document.getElementById('confirmPunchBtn');
        const rotateCameraBtn = document.getElementById('rotateCameraBtn');
        const locationStatus = document.getElementById('locationStatus');
        const locationCoords = document.getElementById('locationCoords');
        const locationAddress = document.getElementById('locationAddress');
        const geofenceStatus = document.getElementById('geofenceStatus');
        const outsideLocationReasonContainer = document.getElementById('outsideLocationReasonContainer');
        const outsideLocationReason = document.getElementById('outsideLocationReason');
        const workReportContainer = document.getElementById('workReportContainer');
        const workReportText = document.getElementById('workReportText');
        const shiftName = document.getElementById('shiftName');
        const shiftTiming = document.getElementById('shiftTiming');
        const shiftWeeklyOffs = document.getElementById('shiftWeeklyOffs');
        const preloaderOverlay = document.getElementById('preloaderOverlay');
        const preloaderProgress = document.getElementById('preloaderProgress');
        const preloaderMessage = document.getElementById('preloaderMessage');
        
        // Camera variables
        let stream = null;
        let facingMode = 'user'; // Default to front camera
        let capturedPhotoData = null;
        let userLocation = null;
        let userShiftInfo = null;
        
        // Geofence variables
        let geofenceLocations = [];
        let isWithinGeofence = false;
        let closestLocationName = "";
        
        // Initialize components when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
            // Check punch status
            checkPunchStatus();
            
            // Use either the new updateDateTime function or the legacy updateTime function, but not both
            if (typeof updateDateTime === 'function') {
                // Start updating date and time with the new function
                updateDateTime();
            } else {
                // Use legacy updateTime as fallback
                updateTime();
                setInterval(updateTime, 1000);
            }
            
            // No need to set interval since updateDateTime calls itself
        });
        
        // Punch button click handler
        punchButton.addEventListener('click', function() {
            // Check if already completed for today
            if (isCompletedForToday) {
                showNotification('Attendance already completed for today', 'warning');
                return;
            }
            
            // Update camera title
            cameraTitle.textContent = isPunchedIn ? 'Take Selfie for Punch Out' : 'Take Selfie for Punch In';
            
            // Show/hide work report based on punch type
            workReportContainer.style.display = isPunchedIn ? 'block' : 'none';
            
            // Open camera modal
            openCameraModal();
            
            // Initialize camera
            initCamera();
            
            // Get user location
            getUserLocation();
            
            // Fetch geofence locations
            fetchGeofenceLocations();
        });
        
        // Close camera button click handler
        closeCameraBtn.addEventListener('click', closeCameraModal);
        
        // Capture photo button click handler
        captureBtn.addEventListener('click', capturePhoto);
        
        // Retake photo button click handler
        retakeBtn.addEventListener('click', function() {
            // Show video container and hide image container
            videoContainer.style.display = 'block';
            capturedImageContainer.style.display = 'none';
            
            // Show capture button and hide retake/confirm buttons
            captureBtn.style.display = 'block';
            retakeBtn.style.display = 'none';
            confirmPunchBtn.style.display = 'none';
            
            // Reset captured photo data
            capturedPhotoData = null;
        });
        
        // Rotate camera button click handler
        rotateCameraBtn.addEventListener('click', function() {
            // Toggle facing mode
            facingMode = facingMode === 'user' ? 'environment' : 'user';
            
            // Reinitialize camera with new facing mode
            initCamera();
        });
        
        // Confirm punch button click handler
        confirmPunchBtn.addEventListener('click', function() {
            // Check if work report is provided for punch out
            if (isPunchedIn && workReportContainer.style.display === 'block') {
                if (!workReportText.value.trim()) {
                    showNotification('Please enter your work report before punching out', 'warning');
                    workReportText.focus();
                    return;
                }
                
                // Check if work report has at least 20 words
                const workText = workReportText.value.trim();
                const workReportWordCount = workText ? workText.split(/\s+/)
                    .filter(word => word.length > 0)
                    .filter(word => /[a-zA-Z0-9\u0900-\u097F]/.test(word)) // Ensure word has at least one alphanumeric or Hindi character
                    .length : 0;
                    
                if (workReportWordCount < 20) {
                    showNotification('Please provide a more detailed work report (minimum 20 valid words)', 'warning');
                    workReportText.focus();
                    return;
                }
            }
            
            // Check if outside location reason is provided when outside geofence
            if (!isWithinGeofence && outsideLocationReasonContainer.style.display !== 'none') {
                if (!outsideLocationReason.value.trim()) {
                    showNotification('Please provide a reason for being outside the assigned location', 'warning');
                    outsideLocationReason.focus();
                    return;
                }
                
                // Check if reason has at least 5 words
                const text = outsideLocationReason.value.trim();
                const reasonWordCount = text ? text.split(/\s+/)
                    .filter(word => word.length > 0)
                    .filter(word => /[a-zA-Z0-9\u0900-\u097F]/.test(word)) // Ensure word has at least one alphanumeric or Hindi character
                    .length : 0;
                    
                if (reasonWordCount < 5) {
                    showNotification('Please provide a more detailed reason (minimum 5 valid words)', 'warning');
                    outsideLocationReason.focus();
                    return;
                }
            }
            
            // Set button to loading state
            confirmPunchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            confirmPunchBtn.disabled = true;
            
            // Prepare form data
            const formData = new FormData();
            formData.append('punch_type', isPunchedIn ? 'out' : 'in');
            
            // Add photo data if available
            if (capturedPhotoData) {
                formData.append('photo_data', capturedPhotoData);
            } else {
                // Show error and return if no photo was captured
                showNotification('Please capture a photo before submitting', 'error');
                confirmPunchBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Punch';
                confirmPunchBtn.disabled = false;
                return;
            }
            
            // Add location data if available
            if (userLocation) {
                formData.append('latitude', userLocation.latitude);
                formData.append('longitude', userLocation.longitude);
                formData.append('accuracy', userLocation.accuracy);
                if (userLocation.address) {
                    formData.append('address', userLocation.address);
                }
                
                // Add geofence information - using column names exactly as in the database
                formData.append('within_geofence', isWithinGeofence ? '1' : '0');
                // Also send as is_within_geofence for backward compatibility
                formData.append('is_within_geofence', isWithinGeofence ? '1' : '0');
                formData.append('closest_location', closestLocationName || '');
                
                // Add geofence ID if available
                if (userLocation.geofenceId) {
                    formData.append('geofence_id', userLocation.geofenceId);
                }
                
                // Add distance from geofence if available
                if (typeof userLocation.distanceFromGeofence !== 'undefined') {
                    formData.append('distance_from_geofence', userLocation.distanceFromGeofence);
                }
            } else {
                // Show error and return if no location data
                showNotification('Location data is required. Please allow location access.', 'error');
                confirmPunchBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Punch';
                confirmPunchBtn.disabled = false;
                return;
            }
            
            // Add work report for punch out
            if (isPunchedIn && workReportText.value.trim()) {
                formData.append('work_report', workReportText.value.trim());
                // Also send as punch_out_work_report for newer implementations
                formData.append('punch_out_work_report', workReportText.value.trim());
            }
            
            // Add outside location reason if not within geofence - using specific column names for punch in vs punch out
            if (!isWithinGeofence && outsideLocationReason.value.trim()) {
                if (isPunchedIn) {
                    // This is a punch out, so use punch_out_outside_reason
                    formData.append('punch_out_outside_reason', outsideLocationReason.value.trim());
                } else {
                    // This is a punch in, so use punch_in_outside_reason
                    formData.append('punch_in_outside_reason', outsideLocationReason.value.trim());
                }
            }
            
            // Add shift information if available
            if (userShiftInfo && userShiftInfo.shift_id) {
                formData.append('shift_id', userShiftInfo.shift_id);
            }
            
            // Get IP address and device info
            formData.append('ip_address', '<?php echo $_SERVER['REMOTE_ADDR']; ?>');
            formData.append('device_info', navigator.userAgent);
            
            console.log('Submitting punch data. Type:', isPunchedIn ? 'out' : 'in'); // Debug log
            
            // Send request to process_punch.php
            fetch('process_punch.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status} ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Punch response:', data); // Debug log
                
                if (data.status === 'success') {
                    // Close camera modal
                    closeCameraModal();
                    
                    // Update button state
                    checkPunchStatus();
                    
                    // Show success notification
                    let successMessage = isPunchedIn ? 
                        `Punched out successfully at ${data.time}` : 
                        `Punched in successfully at ${data.time}`;
                    
                    // Add working hours info for punch out
                    if (isPunchedIn && data.working_hours) {
                        // Format the hours:minutes:seconds nicely
                        let workingHoursDisplay = '';
                        const parts = data.working_hours.split(':');
                        if (parts.length === 3) {
                            const hours = parseInt(parts[0]);
                            const minutes = parseInt(parts[1]);
                            const seconds = parseInt(parts[2]);
                            
                            if (hours > 0) {
                                workingHoursDisplay += `${hours}h `;
                            }
                            if (minutes > 0 || hours > 0) {
                                workingHoursDisplay += `${minutes}m `;
                            }
                            workingHoursDisplay += `${seconds}s`;
                        } else {
                            workingHoursDisplay = data.working_hours;
                        }
                        
                        successMessage += `<br>Hours worked: ${workingHoursDisplay}`;
                        
                        // Format and add overtime if exists
                        if (data.overtime_hours && data.overtime_hours !== '00:00:00') {
                            // Format the overtime nicely
                            let overtimeDisplay = '';
                            const overtimeParts = data.overtime_hours.split(':');
                            if (overtimeParts.length === 3) {
                                const overtimeHours = parseInt(overtimeParts[0]);
                                const overtimeMinutes = parseInt(overtimeParts[1]);
                                const overtimeSeconds = parseInt(overtimeParts[2]);
                                
                                if (overtimeHours > 0) {
                                    overtimeDisplay += `${overtimeHours}h `;
                                }
                                if (overtimeMinutes > 0 || overtimeHours > 0) {
                                    overtimeDisplay += `${overtimeMinutes}m `;
                                }
                                overtimeDisplay += `${overtimeSeconds}s`;
                            } else {
                                overtimeDisplay = data.overtime_hours;
                            }
                            
                            successMessage += ` (including ${overtimeDisplay} overtime)`;
                        }
                    }
                    
                    showNotification(successMessage, 'success');
                } else {
                    // Show error notification
                    showNotification(data.message || 'Error processing punch', 'error');
                    
                    // Reset button state
                    confirmPunchBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Punch';
                    confirmPunchBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Connection error: ' + error.message, 'error');
                
                // Reset button state
                confirmPunchBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Punch';
                confirmPunchBtn.disabled = false;
            });
        });
        
        /**
         * Opens the camera modal
         */
        function openCameraModal() {
            cameraModal.classList.add('open');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }
        
        /**
         * Closes the camera modal and cleans up resources
         */
        function closeCameraModal() {
            cameraModal.classList.remove('open');
            document.body.style.overflow = 'auto'; // Restore scrolling
            
            // Stop camera stream
            stopCamera();
            
            // Reset UI
            videoContainer.style.display = 'block';
            capturedImageContainer.style.display = 'none';
            captureBtn.style.display = 'block';
            retakeBtn.style.display = 'none';
            confirmPunchBtn.style.display = 'none';
            workReportContainer.style.display = 'none';
            workReportText.value = '';
            outsideLocationReasonContainer.style.display = 'none';
            outsideLocationReason.value = '';
            
            // Reset data
            capturedPhotoData = null;
        }
        
        /**
         * Initializes the camera
         */
        function initCamera() {
            // Check if browser supports getUserMedia
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                locationStatus.textContent = 'Camera not supported in this browser';
                return;
            }
            
            // Stop any existing stream
            stopCamera();
            
            // Set up camera constraints
            const constraints = {
                video: {
                    facingMode: facingMode,
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            };
            
            // Get camera stream
            navigator.mediaDevices.getUserMedia(constraints)
                .then(function(mediaStream) {
                    stream = mediaStream;
                    cameraVideo.srcObject = mediaStream;
                    cameraVideo.play()
                        .catch(error => {
                            console.error('Error playing video:', error);
                        });
                })
                .catch(function(error) {
                    console.error('Error accessing camera:', error);
                    locationStatus.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Camera access denied or not available';
                });
        }
        
        /**
         * Stops the camera stream
         */
        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
        }
        
        /**
         * Captures a photo from the camera
         */
        function capturePhoto() {
            if (!stream) return;
            
            // Create a canvas element
            const canvas = document.createElement('canvas');
            canvas.width = cameraVideo.videoWidth;
            canvas.height = cameraVideo.videoHeight;
            
            // Draw video frame to canvas
            const context = canvas.getContext('2d');
            context.drawImage(cameraVideo, 0, 0, canvas.width, canvas.height);
            
            // Get image data as base64
            capturedPhotoData = canvas.toDataURL('image/jpeg');
            
            // Display captured image
            capturedImage.src = capturedPhotoData;
            
            // Show image container and hide video container
            videoContainer.style.display = 'none';
            capturedImageContainer.style.display = 'block';
            
            // Show retake and confirm buttons, hide capture button
            captureBtn.style.display = 'none';
            retakeBtn.style.display = 'block';
            confirmPunchBtn.style.display = 'block';
        }
        
        /**
         * Gets the user's current location
         */
        function getUserLocation() {
            // Check if geolocation is supported
            if (!navigator.geolocation) {
                locationStatus.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Geolocation not supported';
                return;
            }
            
            // Update status
            locationStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting your location...';
            
            // Get current position
            navigator.geolocation.getCurrentPosition(
                // Success callback
                function(position) {
                    userLocation = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    };
                    
                    // Update location info
                    locationStatus.innerHTML = `<i class="fas fa-check-circle"></i> Location found (Accuracy: ${Math.round(position.coords.accuracy)}m)`;
                    locationCoords.innerHTML = `<i class="fas fa-compass"></i> Coordinates: ${position.coords.latitude.toFixed(6)}, ${position.coords.longitude.toFixed(6)}`;
                    
                    // Get address from coordinates
                    getAddressFromCoordinates(position.coords.latitude, position.coords.longitude);
                    
                    // Check if within geofence
                    if (geofenceLocations.length > 0) {
                        checkGeofence(position.coords.latitude, position.coords.longitude);
                    }
                },
                // Error callback
                function(error) {
                    console.error('Geolocation error:', error);
                    
                    let errorMessage = 'Error getting location';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage = 'Location permission denied';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage = 'Location information unavailable';
                            break;
                        case error.TIMEOUT:
                            errorMessage = 'Location request timed out';
                            break;
                    }
                    
                    locationStatus.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${errorMessage}`;
                    locationCoords.innerHTML = '<i class="fas fa-compass"></i> Coordinates: Not available';
                    locationAddress.innerHTML = '<i class="fas fa-map"></i> Address: Not available';
                    geofenceStatus.innerHTML = '<i class="fas fa-map-marked-alt"></i> Location check failed';
                    geofenceStatus.style.color = '#dc3545'; // Red color
                },
                // Options
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        }
        
        /**
         * Gets address from coordinates using reverse geocoding
         */
        function getAddressFromCoordinates(latitude, longitude) {
            // Update status
            locationAddress.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting address...';
            
            // Use our server-side proxy to avoid CORS issues
            const url = `ajax_handlers/get_address.php?lat=${latitude}&lon=${longitude}`;
            
            fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success' && data.address) {
                    locationAddress.innerHTML = `<i class="fas fa-map"></i> Address: ${data.address}`;
                    userLocation.address = data.address;
                } else {
                    locationAddress.innerHTML = '<i class="fas fa-map"></i> Address: Could not determine address';
                }
            })
            .catch(error => {
                console.error('Error fetching address:', error);
                locationAddress.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Address: Error retrieving address';
            });
        }
        
        /**
         * Fetches geofence locations from the server
         */
        function fetchGeofenceLocations() {
            geofenceStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading geofence data...';
            
            fetch('api/get_geofence_locations.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Server responded with status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        geofenceLocations = data.locations;
                        console.log('Geofence locations loaded:', geofenceLocations);
                        
                        // If we already have location data, check geofence
                        if (userLocation && userLocation.latitude && userLocation.longitude) {
                            checkGeofence(userLocation.latitude, userLocation.longitude);
                        }
                    } else {
                        throw new Error(data.message || 'Failed to load geofence locations');
                    }
                })
                .catch(error => {
                    console.error('Error loading geofence locations:', error);
                    geofenceStatus.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Could not load location boundaries';
                    geofenceStatus.style.color = '#dc3545'; // Red color for error
                });
        }
        
        /**
         * Checks if the user's location is within any geofence
         */
        function checkGeofence(latitude, longitude) {
            geofenceStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking location boundaries...';
            
            // If no geofence locations available yet, try to fetch them
            if (geofenceLocations.length === 0) {
                fetchGeofenceLocations();
                return;
            }
            
            let minDistance = Infinity;
            let closestLocation = null;
            
            // Check distance to each geofence location
            geofenceLocations.forEach(location => {
                const distance = calculateDistance(
                    latitude, 
                    longitude, 
                    parseFloat(location.latitude), 
                    parseFloat(location.longitude)
                );
                
                if (distance < minDistance) {
                    minDistance = distance;
                    closestLocation = location;
                }
            });
            
            // If we found a closest location
            if (closestLocation) {
                closestLocationName = closestLocation.name;
                const locationRadius = parseInt(closestLocation.radius);
                
                // Store geofence ID if available
                if (closestLocation.id) {
                    userLocation.geofenceId = closestLocation.id;
                }
                
                // Check if within radius
                if (minDistance <= locationRadius) {
                    isWithinGeofence = true;
                    geofenceStatus.innerHTML = `<i class="fas fa-check-circle"></i> Within ${closestLocation.name} (${Math.round(minDistance)}m from center)`;
                    geofenceStatus.style.color = '#28a745'; // Green color for success
                    
                    // Hide outside location reason container
                    outsideLocationReasonContainer.style.display = 'none';
                    
                    userLocation.distanceFromGeofence = 0; // Inside geofence, so distance is 0
                } else {
                    isWithinGeofence = false;
                    geofenceStatus.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Outside ${closestLocation.name} (${Math.round(minDistance)}m from center)`;
                    geofenceStatus.style.color = '#dc3545'; // Red color for error
                    
                    // Show outside location reason container
                    outsideLocationReasonContainer.style.display = 'block';
                    
                    userLocation.distanceFromGeofence = Math.round(minDistance - locationRadius); // Distance beyond the geofence boundary
                }
            } else {
                isWithinGeofence = false;
                geofenceStatus.innerHTML = '<i class="fas fa-exclamation-triangle"></i> No registered locations found';
                geofenceStatus.style.color = '#ffc107'; // Yellow/warning color
                
                // Show outside location reason container
                outsideLocationReasonContainer.style.display = 'block';
            }
        }
        
        /**
         * Calculates the distance between two coordinates in meters using the Haversine formula
         */
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371000; // Earth radius in meters
            const φ1 = lat1 * Math.PI / 180;
            const φ2 = lat2 * Math.PI / 180;
            const Δφ = (lat2 - lat1) * Math.PI / 180;
            const Δλ = (lon2 - lon1) * Math.PI / 180;
            
            const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                    Math.cos(φ1) * Math.cos(φ2) *
                    Math.sin(Δλ/2) * Math.sin(Δλ/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            
            return R * c; // Distance in meters
        }
        
        /**
         * Check current punch status from the server
         */
        function checkPunchStatus() {
            // Set button to loading state
            punchButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            punchButton.disabled = true;
            
            // Fetch status from server
            fetch('api/check_punch_status.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Server returned ${response.status} ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Punch status data:', data); // Debug log
                    
                    // Update variables
                    isPunchedIn = data.is_punched_in || false;
                    isCompletedForToday = data.is_completed || false;
                    
                    // Save shift info
                    userShiftInfo = data.shift_info || {
                        shift_id: null,
                        shift_name: 'Default Shift',
                        start_time: '09:00:00',
                        end_time: '18:00:00',
                        start_time_formatted: '09:00 AM',
                        end_time_formatted: '06:00 PM',
                        weekly_offs: 'Saturday,Sunday'
                    };
                    
                    // Update shift information display
                    updateShiftDisplay();
                    
                    // Update button UI
                    updatePunchButton();
                    
                    // Update display of punch time if available
                    const buttonContainer = punchButton.closest('.user-actions');
                    
                    // Remove any existing time display
                    const existingPunchTime = document.querySelector('.punch-time');
                    if (existingPunchTime) {
                        existingPunchTime.remove();
                    }
                    
                    // Add time display if available
                    if (isPunchedIn && data.last_punch_in) {
                        const punchTimeElem = document.createElement('div');
                        punchTimeElem.className = 'punch-time';
                        punchTimeElem.innerHTML = `Since: ${data.last_punch_in}`;
                        punchTimeElem.style.marginTop = '5px';
                        punchTimeElem.style.fontSize = '0.8rem';
                        punchTimeElem.style.color = '#666';
                        buttonContainer.appendChild(punchTimeElem);
                    } else if (isCompletedForToday && data.working_hours) {
                        const punchTimeElem = document.createElement('div');
                        punchTimeElem.className = 'punch-time';
                        punchTimeElem.innerHTML = `Hours worked: ${data.working_hours}`;
                        punchTimeElem.style.marginTop = '5px';
                        punchTimeElem.style.fontSize = '0.8rem';
                        punchTimeElem.style.color = '#666';
                        buttonContainer.appendChild(punchTimeElem);
                    }
                })
                .catch(error => {
                    console.error('Error checking punch status:', error);
                    
                    // Set default state on error
                    punchButton.classList.remove('btn-danger', 'btn-secondary');
                    punchButton.classList.add('btn-success');
                    punchButton.innerHTML = '<i class="fas fa-sign-in-alt"></i> Punch In';
                    punchButton.disabled = false;
                    
                    showNotification('Failed to check punch status', 'error');
                });
        }
        
        /**
         * Update the punch button appearance based on current status
         */
        function updatePunchButton() {
            if (isCompletedForToday) {
                // User has completed attendance for today
                punchButton.classList.remove('btn-success', 'btn-danger');
                punchButton.classList.add('btn-secondary');
                punchButton.innerHTML = '<i class="fas fa-check-circle"></i> Completed';
                punchButton.disabled = true;
            } else if (isPunchedIn) {
                // User is punched in but not yet punched out
                punchButton.classList.remove('btn-success', 'btn-secondary');
                punchButton.classList.add('btn-danger');
                punchButton.innerHTML = '<i class="fas fa-sign-out-alt"></i> Punch Out';
                punchButton.disabled = false;
            } else {
                // User is not punched in
                punchButton.classList.remove('btn-danger', 'btn-secondary');
                punchButton.classList.add('btn-success');
                punchButton.innerHTML = '<i class="fas fa-sign-in-alt"></i> Punch In';
                punchButton.disabled = false;
            }
        }
        
        /**
         * Update the shift information display
         */
        function updateShiftDisplay() {
            // Update the shift info in the camera modal
            if (userShiftInfo) {
                shiftName.textContent = userShiftInfo.shift_name || 'Default Shift';
                shiftTiming.textContent = `${userShiftInfo.start_time_formatted || '09:00 AM'} - ${userShiftInfo.end_time_formatted || '06:00 PM'}`;
                shiftWeeklyOffs.textContent = userShiftInfo.weekly_offs ? `Weekly offs: ${userShiftInfo.weekly_offs}` : 'Weekly offs: Saturday, Sunday';
                
                // Update the greeting section shift info
                const greetingShiftInfo = document.getElementById('greeting-shift-info');
                if (greetingShiftInfo) {
                    greetingShiftInfo.innerHTML = `
                        <span style="display: flex; align-items: center; gap: 5px;">
                            <i class="fas fa-id-badge" style="color: #0d6efd; width: 15px; text-align: center;"></i>
                            ${userShiftInfo.shift_name || 'Default Shift'}
                        </span>
                        <span style="display: flex; align-items: center; gap: 5px; margin-top: 2px;">
                            <i class="fas fa-clock" style="color: #0d6efd; width: 15px; text-align: center;"></i>
                            ${userShiftInfo.start_time_formatted || '09:00 AM'} - ${userShiftInfo.end_time_formatted || '06:00 PM'}
                        </span>
                    `;
                }
                
                // Start updating shift time
                updateShiftTime();
            } else {
                shiftName.textContent = 'Default Shift';
                shiftTiming.textContent = '09:00 AM - 06:00 PM';
                shiftWeeklyOffs.textContent = 'Weekly offs: Saturday, Sunday';
                
                // Default greeting section shift info
                const greetingShiftInfo = document.getElementById('greeting-shift-info');
                if (greetingShiftInfo) {
                    greetingShiftInfo.innerHTML = `
                        <span style="display: flex; align-items: center; gap: 5px;">
                            <i class="fas fa-id-badge" style="color: #0d6efd; width: 15px; text-align: center;"></i>
                            Default Shift
                        </span>
                        <span style="display: flex; align-items: center; gap: 5px; margin-top: 2px;">
                            <i class="fas fa-clock" style="color: #0d6efd; width: 15px; text-align: center;"></i>
                            09:00 AM - 06:00 PM
                        </span>
                    `;
                }
            }
        }
        
        /**
         * Updates the shift remaining time
         */
        function updateShiftTime() {
            const shiftRemainingTimeEl = document.getElementById('shift-remaining-time');
            if (!shiftRemainingTimeEl || !userShiftInfo || !userShiftInfo.end_time) {
                return;
            }
            
            const now = new Date();
            
            // Parse shift end time
            const [endHours, endMinutes, endSeconds] = userShiftInfo.end_time.split(':').map(Number);
            
            // Create shift end date for today
            const shiftEndDate = new Date(
                now.getFullYear(),
                now.getMonth(),
                now.getDate(),
                endHours,
                endMinutes,
                endSeconds || 0
            );
            
            // If current time is after shift end time, the shift has ended for today
            if (now > shiftEndDate) {
                // Only update if text has changed to avoid flickering
                if (shiftRemainingTimeEl.textContent !== 'Shift ended') {
                    shiftRemainingTimeEl.textContent = 'Shift ended';
                    shiftRemainingTimeEl.style.color = '#6c757d'; // Gray color
                }
                return;
            }
            
            // Calculate time difference
            let timeDiff = shiftEndDate - now;
            
            // Convert time difference to hours, minutes, seconds
            const hours = Math.floor(timeDiff / (1000 * 60 * 60));
            timeDiff -= hours * (1000 * 60 * 60);
            
            const minutes = Math.floor(timeDiff / (1000 * 60));
            timeDiff -= minutes * (1000 * 60);
            
            const seconds = Math.floor(timeDiff / 1000);
            
            // Format the countdown string
            const countdownText = `${hours}h ${minutes}m ${seconds}s`;
            
            // Only update if text has changed to avoid flickering
            if (shiftRemainingTimeEl.textContent !== countdownText) {
                shiftRemainingTimeEl.textContent = countdownText;
                
                // Only set color if it's not already blue
                if (shiftRemainingTimeEl.style.color !== '#0d6efd') {
                    shiftRemainingTimeEl.style.color = '#0d6efd'; // Blue color
                }
            }
            
            // Schedule next update in 1 second
            setTimeout(updateShiftTime, 1000);
        }
        
        /**
         * Shows a notification message
         */
        function showNotification(message, type) {
            // Check if the notification container exists
            let notificationContainer = document.querySelector('.notification-container');
            
            // Create notification container if it doesn't exist
            if (!notificationContainer) {
                notificationContainer = document.createElement('div');
                notificationContainer.className = 'notification-container';
                document.body.appendChild(notificationContainer);
            }
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            
            // Set icon based on notification type
            let icon = 'fa-info-circle';
            if (type === 'success') icon = 'fa-check-circle';
            if (type === 'error') icon = 'fa-exclamation-circle';
            if (type === 'warning') icon = 'fa-exclamation-triangle';
            
            notification.innerHTML = `
                <i class="fas ${icon}"></i>
                <div class="notification-message">${message}</div>
            `;
            
            // Add to container
            notificationContainer.appendChild(notification);
            
            // Show notification
            setTimeout(() => notification.classList.add('show'), 10);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        /**
         * Updates the date and time display
         */
        function updateDateTime() {
            const now = new Date();
            
            // Options for formatting date and time with consistent format
            const dateOptions = {
                weekday: 'long',
                day: '2-digit',  // Use 2-digit to ensure leading zeros
                month: 'long',
                year: 'numeric',
                timeZone: 'Asia/Kolkata' // IST timezone
            };
            
            const timeOptions = {
                hour: '2-digit',  // Use 2-digit to ensure leading zeros
                minute: '2-digit', // Use 2-digit to ensure leading zeros
                hour12: true,
                timeZone: 'Asia/Kolkata' // IST timezone
            };
            
            // Format date and time using Intl.DateTimeFormat with specific parts
            const formattedTime = new Intl.DateTimeFormat('en-US', timeOptions).format(now);
            
            // Format date components separately to avoid inconsistencies
            const formatter = new Intl.DateTimeFormat('en-US', dateOptions);
            const dateParts = formatter.formatToParts(now);
            
            // Extract individual date components
            let weekday = '', day = '', month = '', year = '';
            dateParts.forEach(part => {
                if (part.type === 'weekday') weekday = part.value;
                if (part.type === 'day') day = part.value;
                if (part.type === 'month') month = part.value;
                if (part.type === 'year') year = part.value;
            });
            
            // Cache current values to avoid unnecessary updates
            if (!window.lastTimeValues) {
                window.lastTimeValues = {
                    time: '',
                    weekday: '',
                    day: '',
                    month: '',
                    year: ''
                };
            }
            
            // Find the time display elements
            const timeElements = document.querySelectorAll('.time-item');
            if (timeElements && timeElements.length >= 2) {
                // Only update time if changed
                if (window.lastTimeValues.time !== formattedTime) {
                    // Find or create time text element
                    let timeTextEl = timeElements[0].querySelector('.time-text');
                    if (!timeTextEl) {
                        // First time setup - create stable structure
                        timeElements[0].innerHTML = `<i class="far fa-clock"></i> <span class="time-text">${formattedTime}</span> <span class="time-label">IST</span>`;
                    } else {
                        // Update only the text content, not the whole structure
                        timeTextEl.textContent = formattedTime;
                    }
                    window.lastTimeValues.time = formattedTime;
                }
                
                // Update date components individually to avoid flickering
                const dateEl = timeElements[1];
                
                // Create date structure if not exists
                if (!dateEl.querySelector('.weekday-text')) {
                    dateEl.innerHTML = `
                        <i class="far fa-calendar-alt"></i> 
                        <span class="date-container">
                            <span class="weekday-text">${weekday}</span>, 
                            <span class="day-text">${day}</span> 
                            <span class="month-text">${month}</span> 
                            <span class="year-text">${year}</span>
                        </span>
                    `;
                } else {
                    // Update individual parts only if changed
                    if (window.lastTimeValues.weekday !== weekday) {
                        dateEl.querySelector('.weekday-text').textContent = weekday;
                        window.lastTimeValues.weekday = weekday;
                    }
                    
                    if (window.lastTimeValues.day !== day) {
                        dateEl.querySelector('.day-text').textContent = day;
                        window.lastTimeValues.day = day;
                    }
                    
                    if (window.lastTimeValues.month !== month) {
                        dateEl.querySelector('.month-text').textContent = month;
                        window.lastTimeValues.month = month;
                    }
                    
                    if (window.lastTimeValues.year !== year) {
                        dateEl.querySelector('.year-text').textContent = year;
                        window.lastTimeValues.year = year;
                    }
                }
            }
            
            // Update greeting based on time of day
            updateGreeting(now);
            
            // Schedule next update in 1 second
            setTimeout(updateDateTime, 1000);
        }
        
        /**
         * Update the greeting based on the time of day
         */
        function updateGreeting(now) {
            const hour = now.getHours();
            let greeting = '';
            let iconClass = '';
            let iconColor = '';
            
            if (hour >= 5 && hour < 12) {
                greeting = 'Good morning';
                iconClass = 'fa-sun';
                iconColor = '#f39c12'; // yellow/orange
            } else if (hour >= 12 && hour < 17) {
                greeting = 'Good afternoon';
                iconClass = 'fa-sun';
                iconColor = '#e67e22'; // orange
            } else if (hour >= 17 && hour < 22) {
                greeting = 'Good evening';
                iconClass = 'fa-moon';
                iconColor = '#9b59b6'; // purple
            } else {
                greeting = 'Good night';
                iconClass = 'fa-moon';
                iconColor = '#34495e'; // dark blue
            }
            
            // Update the greeting text
            const greetingEl = document.querySelector('.greeting-text h1');
            if (greetingEl) {
                // Extract username from the current text by removing the greeting part
                // This prevents accumulating exclamation marks
                let userName = '';
                const commaIndex = greetingEl.textContent.indexOf(',');
                if (commaIndex !== -1) {
                    // Extract everything after the comma and before any exclamation marks
                    const afterComma = greetingEl.textContent.substring(commaIndex + 1);
                    const exclamationIndex = afterComma.indexOf('!');
                    if (exclamationIndex !== -1) {
                        userName = afterComma.substring(0, exclamationIndex).trim();
                    } else {
                        userName = afterComma.trim();
                    }
                }
                
                // Set the greeting with exactly one exclamation mark
                greetingEl.innerHTML = `
                    <i class="fas ${iconClass} greeting-icon" style="color: ${iconColor}"></i>
                    ${greeting}, ${userName || 'User'}!
                `;
            }
        }

        /**
         * Shows the preloader overlay with optional message
         * @param {string} message - Message to display
         */
        function showPreloader(message = 'Saving data...') {
            preloaderMessage.textContent = message;
            preloaderProgress.style.width = '0%';
            preloaderOverlay.style.display = 'flex';
        }
        
        /**
         * Updates the preloader progress
         * @param {number} percent - Progress percentage (0-100)
         * @param {string} message - Optional new message
         */
        function updatePreloaderProgress(percent, message = null) {
            preloaderProgress.style.width = percent + '%';
            if (message) {
                preloaderMessage.textContent = message;
            }
        }
        
        /**
         * Hides the preloader overlay
         */
        function hidePreloader() {
            preloaderOverlay.style.display = 'none';
        }
    </script>
    <script>
        // Toggle Panel Function
        function togglePanel() {
            const leftPanel = document.getElementById('leftPanel');
        const mainContent = document.getElementById('mainContent');
            const toggleIcon = document.getElementById('toggleIcon');
            
            leftPanel.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            if (leftPanel.classList.contains('collapsed')) {
                toggleIcon.classList.remove('fa-chevron-left');
                toggleIcon.classList.add('fa-chevron-right');
                mainContent.style.marginLeft = '70px'; // Changed from 0 to 70px to match the collapsed panel width
            } else {
                toggleIcon.classList.remove('fa-chevron-right');
                toggleIcon.classList.add('fa-chevron-left');
                mainContent.style.marginLeft = '250px';
            }
        }
        
        // Mobile menu functions
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerMenu = document.getElementById('hamburgerMenu');
            const leftPanel = document.getElementById('leftPanel');
            const overlay = document.getElementById('overlay');
            
            // Initialize notification badge count
            if (typeof updateNotificationBadge === 'function') {
                updateNotificationBadge();
            }
            
            // Make travel expenses card clickable
            const travelExpensesCard = document.querySelector('.site-card[data-card-type="travel-expenses"]');
            if (travelExpensesCard) {
                travelExpensesCard.addEventListener('click', function(e) {
                    // Don't navigate if the click was on the Add Expense button (it has its own handler)
                    if (!e.target.closest('#addTravelExpenseBtn')) {
                        // Navigate to travel expenses page
                        window.location.href = 'travel_expenses.php';
                    }
                });
                
                // Add click handler for the Add Expense button
                const addTravelExpenseBtn = document.getElementById('addTravelExpenseBtn');
                if (addTravelExpenseBtn) {
                    addTravelExpenseBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        $('#newTravelExpenseModal').modal('show');
                    });
                }
            }
            
            // Check if we should enable scrolling based on screen height
            function checkPanelScrolling() {
                if (window.innerHeight < 700 || window.innerWidth <= 768) {
                    leftPanel.classList.add('needs-scrolling');
                } else {
                    leftPanel.classList.remove('needs-scrolling');
                }
            }
            
            // Hamburger menu click handler
            hamburgerMenu.addEventListener('click', function() {
                leftPanel.classList.toggle('mobile-open');
                overlay.classList.toggle('active');
                checkPanelScrolling();
            });
            
            // Overlay click handler (close menu when clicking outside)
            overlay.addEventListener('click', function() {
                leftPanel.classList.remove('mobile-open');
                overlay.classList.remove('active');
                
                // Also close any open dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    leftPanel.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                }
                checkPanelScrolling();
            });
            
            // Initial check for scrolling
            checkPanelScrolling();
            
            // Update time every second
            function updateTime() {
                // Initialize cache for values if not exists
                if (!window.lastTimeValues) {
                    window.lastTimeValues = {
                        time: '',
                        date: ''
                    };
                }
                
                // Get the current time in IST directly from the server via AJAX
                fetch('get_ist_time.php')
                    .then(response => response.json())
                    .then(data => {
                        // Update the DOM with the server time
                        const timeElements = document.querySelectorAll('.time-item');
                        
                        if (timeElements && timeElements.length >= 2) {
                            // Only update time if changed
                            if (window.lastTimeValues.time !== data.time) {
                                // Find or create time text element
                                let timeTextEl = timeElements[0].querySelector('.time-text');
                                if (!timeTextEl) {
                                    // First time setup - create stable structure
                                    timeElements[0].innerHTML = `<i class="far fa-clock"></i> <span class="time-text">${data.time}</span> <span class="time-label">IST</span>`;
                                } else {
                                    // Update only the text content, not the whole structure
                                    timeTextEl.textContent = data.time;
                                }
                                window.lastTimeValues.time = data.time;
                            }
                            
                            // Only update date if changed
                            if (window.lastTimeValues.date !== data.date) {
                                // Find or create date text element
                                let dateTextEl = timeElements[1].querySelector('.date-text');
                                if (!dateTextEl) {
                                    // First time setup - create stable structure
                                    timeElements[1].innerHTML = `<i class="far fa-calendar-alt"></i> <span class="date-text">${data.date}</span>`;
                                } else {
                                    // Update only the text content, not the whole structure
                                    dateTextEl.textContent = data.date;
                                }
                                window.lastTimeValues.date = data.date;
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching IST time:', error);
                        
                        // Fallback to client-side calculation if server fetch fails
                        const now = new Date();
                        
                        // Format as IST string with consistent format
                        const options = { 
                            timeZone: 'Asia/Kolkata',
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true
                        };
                        const dateOptions = {
                            timeZone: 'Asia/Kolkata',
                            weekday: 'long',
                            day: '2-digit',
                            month: 'long',
                            year: 'numeric'
                        };
                        
                        // Use Intl.DateTimeFormat for correct timezone formatting
                        const timeFormatter = new Intl.DateTimeFormat('en-US', options);
                        const dateFormatter = new Intl.DateTimeFormat('en-US', dateOptions);
                        
                        const formattedTime = timeFormatter.format(now);
                        const formattedDate = dateFormatter.format(now);
                        
                        // Update the DOM
                        const timeElements = document.querySelectorAll('.time-item');
                        
                        if (timeElements && timeElements.length >= 2) {
                            // Only update time if changed
                            if (window.lastTimeValues.time !== formattedTime) {
                                // Find or create time text element
                                let timeTextEl = timeElements[0].querySelector('.time-text');
                                if (!timeTextEl) {
                                    // First time setup - create stable structure
                                    timeElements[0].innerHTML = `<i class="far fa-clock"></i> <span class="time-text">${formattedTime}</span> <span class="time-label">IST</span>`;
                                } else {
                                    // Update only the text content, not the whole structure
                                    timeTextEl.textContent = formattedTime;
                                }
                                window.lastTimeValues.time = formattedTime;
                            }
                            
                            // Only update date if changed
                            if (window.lastTimeValues.date !== formattedDate) {
                                // Find or create date text element
                                let dateTextEl = timeElements[1].querySelector('.date-text');
                                if (!dateTextEl) {
                                    // First time setup - create stable structure
                                    timeElements[1].innerHTML = `<i class="far fa-calendar-alt"></i> <span class="date-text">${formattedDate}</span>`;
                                } else {
                                    // Update only the text content, not the whole structure
                                    dateTextEl.textContent = formattedDate;
                                }
                                window.lastTimeValues.date = formattedDate;
                            }
                        }
                    });
            }
            
            // Update time immediately and then every second
            updateTime();
            setInterval(updateTime, 1000);
            
            // Function to ensure the menu is completely visible within the viewport
            function ensureMenuVisible(menu, trigger) {
                // Get trigger position
                const triggerRect = trigger.getBoundingClientRect();
                
                // Calculate menu width
                const menuWidth = 250; // Fixed width from CSS
                
                // Different positioning based on which dropdown
                const isProfileDropdown = menu.classList.contains('profile-menu');
                
                // Position menu relative to trigger - directly below it
                menu.style.position = 'fixed';
                menu.style.top = (triggerRect.bottom + 5) + 'px';
                
                if (isProfileDropdown) {
                    // Profile menu aligns to the right
                    const rightPosition = window.innerWidth - triggerRect.right;
                    menu.style.right = rightPosition + 'px';
                    menu.style.left = 'auto';
                } else {
                    // Notification menu aligns to the left
                    menu.style.left = triggerRect.left + 'px';
                    menu.style.right = 'auto';
                }
                
                // After positioning, check viewport constraints
                setTimeout(() => {
                    const menuRect = menu.getBoundingClientRect();
                    const windowHeight = window.innerHeight;
                    const windowWidth = window.innerWidth;
                    
                    // Check if menu is too far to the right
                    if (menuRect.right > windowWidth - 10) {
                        menu.style.left = 'auto';
                        menu.style.right = '10px';
                    }
                    
                    // Check if menu is too far to the left
                    if (menuRect.left < 10) {
                        menu.style.left = '10px';
                        menu.style.right = 'auto';
                    }
                    
                    // Check if menu is too far to the bottom
                    if (menuRect.bottom > windowHeight - 10) {
                        // Position above trigger element instead
                        menu.style.top = 'auto';
                        menu.style.bottom = (window.innerHeight - triggerRect.top + 5) + 'px';
                    }
                }, 0);
            }
            
            // Notification Bell Dropdown
            const notificationBell = document.getElementById('notificationBell');
            const notificationMenu = document.getElementById('notificationMenu');
            
            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                
                // Close profile menu if open
                profileMenu.classList.remove('show');
                
                // Toggle notification menu
                const isVisible = notificationMenu.classList.toggle('show');
                
                if (isVisible) {
                    // Make sure menu is completely visible
                    ensureMenuVisible(notificationMenu, notificationBell);
                    
                    // Bring to front
                    notificationMenu.style.zIndex = '10001';
                    if (profileMenu) profileMenu.style.zIndex = '10000';
                }
            });
            
            // Profile Avatar Dropdown
            const profileAvatar = document.getElementById('profileAvatar');
            const profileMenu = document.getElementById('profileMenu');
            
            profileAvatar.addEventListener('click', function(e) {
                e.stopPropagation();
                
                // Close notification menu if open
                notificationMenu.classList.remove('show');
                
                // Toggle profile menu
                const isVisible = profileMenu.classList.toggle('show');
                
                if (isVisible) {
                    // Make sure menu is completely visible
                    ensureMenuVisible(profileMenu, profileAvatar);
                    
                    // Bring to front
                    profileMenu.style.zIndex = '10001';
                    if (notificationMenu) notificationMenu.style.zIndex = '10000';
                }
            });
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                if (!notificationBell.contains(e.target) && !notificationMenu.contains(e.target)) {
                    notificationMenu.classList.remove('show');
                }
                
                if (!profileAvatar.contains(e.target) && !profileMenu.contains(e.target)) {
                    profileMenu.classList.remove('show');
                }
        });
    });
    </script>
</body>

<?php include_once('modals/travel_expense_modal_new.php'); ?>

<!-- Event Details Modal -->
<div id="eventDetailsModal" class="event-details-modal">
  <div class="event-details-modal-content">
    <div class="event-details-modal-header">
      <h5 class="event-details-modal-title">Event Details</h5>
      <button type="button" class="event-details-modal-close" id="closeEventDetailsModal">&times;</button>
    </div>
    <div class="event-details-modal-body">
      <div class="event-details-loader">
        <div class="spinner-border text-primary" role="status">
          <span class="sr-only">Loading...</span>
        </div>
      </div>
      <div id="eventDetailsContent"></div>
    </div>
    <div class="event-details-modal-footer">
      <button type="button" class="btn btn-secondary" id="closeEventDetailsModalBtn">Close</button>
    </div>
  </div>
</div>

<!-- Add this right before the closing body tag -->
<div class="image-viewer-overlay" id="imageViewerOverlay">
  <div class="image-viewer-container">
    <img src="" alt="Full size image" class="image-viewer-image" id="imageViewerImage">
    <button class="image-viewer-close" id="imageViewerClose">&times;</button>
    <div class="image-viewer-caption" id="imageViewerCaption"></div>
  </div>
</div>

</body>
</html>

<!-- Add this before the closing body tag of site_manager_dashboard.php -->
<script src="js/manager/event-details-modal.js"></script>

<!-- Add this right before the closing body tag -->
<script>
  // Additional event listeners for closing the event details modal
  document.addEventListener('DOMContentLoaded', function() {
    // Get close buttons
    const closeEventDetailsBtn = document.getElementById('closeEventDetailsModal');
    const closeEventDetailsModalBtn = document.getElementById('closeEventDetailsModalBtn');
    
    // Add click handlers
    if (closeEventDetailsBtn) {
      closeEventDetailsBtn.onclick = function() {
        const modal = document.getElementById('eventDetailsModal');
        if (modal) modal.classList.remove('show');
      };
    }
    
    if (closeEventDetailsModalBtn) {
      closeEventDetailsModalBtn.onclick = function() {
        const modal = document.getElementById('eventDetailsModal');
        if (modal) modal.classList.remove('show');
      };
    }
  });
</script>

<!-- Add SheetJS library for Excel export -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

