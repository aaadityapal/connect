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

// Determine Site In/Out button state
$site_action = 'site_in';
$site_action_label = 'Site In';
$site_action_icon = 'fa-sign-in-alt';
if (isset($_SESSION['user_id'])) {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT action FROM site_in_out_logs WHERE user_id = ? AND DATE(timestamp) = ? ORDER BY timestamp DESC LIMIT 1");
    $stmt->bind_param('is', $_SESSION['user_id'], $today);
    $stmt->execute();
    $stmt->bind_result($last_action);
    if ($stmt->fetch()) {
        if ($last_action === 'site_in') {
            $site_action = 'site_out';
            $site_action_label = 'Site Out';
            $site_action_icon = 'fa-sign-out-alt';
        } else {
            $site_action = 'site_in';
            $site_action_label = 'Site In';
            $site_action_icon = 'fa-sign-in-alt';
        }
    }
    $stmt->close();
}

// Determine if Site In/Out button should be enabled
$site_button_disabled = true;
if (isset($_SESSION['user_id'])) {
    $today = date('Y-m-d');
    // Check punch in/out status
    $punch_stmt = $conn->prepare("SELECT punch_in, punch_out FROM attendance WHERE user_id = ? AND date = ?");
    $punch_stmt->bind_param('is', $_SESSION['user_id'], $today);
    $punch_stmt->execute();
    $punch_stmt->bind_result($punch_in, $punch_out);
    if ($punch_stmt->fetch()) {
        if ($punch_in && !$punch_out) {
            $site_button_disabled = false; // Only enable if punched in and not yet punched out
        }
    }
    $punch_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Supervisor Dashboard</title>

    <!-- Include CSS files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/supervisor/dashboard.css">
    <link rel="stylesheet" href="css/supervisor/calendar-modal.css">
    <link rel="stylesheet" href="css/supervisor/calendar-stats.css">
    <link rel="stylesheet" href="css/supervisor/calendar-events-modal.css">
    <link rel="stylesheet" href="css/supervisor/calendar-events-modal-enhanced.css">
    <link rel="stylesheet" href="css/supervisor/event-view-modal.css">
    <link rel="stylesheet" href="css/supervisor/enhanced-event-view.css">
    <link rel="stylesheet" href="css/supervisor/greeting-section.css">
    <link rel="stylesheet" href="css/supervisor/travel-expense-modal.css">
    <link rel="stylesheet" href="css/supervisor/supervisor-camera-modal.css">


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
            border-top: 1px dashed rgba(0, 0, 0, 0.1);
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
            background-color: rgba(0, 0, 0, 0.05);
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

        /* Pending expense indicator styles */
        .pending-expense-indicator {
            position: absolute;
            top: 0px;
            right: 0px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            padding: 4px 8px;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 1px solid #f8d7da;
        }

        .pending-amount {
            font-weight: bold;
            color: #dc3545;
            font-size: 0.9rem;
        }

        .pending-label {
            font-size: 0.7rem;
            color: #dc3545;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Position the stat card as relative for absolute positioning of the indicator */
        .stat-card {
            position: relative;
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
            height: 120px;
            /* Significantly increased from 90px to 120px */
            border-radius: 6px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 8px;
            /* Increased padding for more internal space */
            position: relative;
            transition: all 0.2s ease;
            cursor: pointer;
            overflow: hidden;
            /* Prevent content overflow */
            display: flex;
            flex-direction: column;
        }

        .calendar-day:hover {
            background: #f8f9fa;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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
            margin-bottom: 4px;
            /* Increased from 2px to 4px for more space */
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
            border-radius: 18px;
            background-color: #3498db;
            /* Changed to a lighter blue to match image */
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            /* Specific font size in px instead of rem */
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            opacity: 0.9;
            border: none;
            padding: 0;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            /* Lighter shadow to match image */
            line-height: 1;
            position: absolute;
            /* Changed to absolute for precise positioning */
            top: 0;
            /* Positioned at the top of the container */
            right: 10px;
            /* Slight offset from right edge */
            border-radius: 18px;
            padding: 10px 14px;
        }

        .add-event-btn:hover {
            background-color: #2980b9;
            color: white;
            opacity: 1;
            transform: scale(1.1);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
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
            background-color: #e67e22;
            /* More orange color to match image */
        }

        .event-meeting {
            background-color: #805ad5;
        }

        .event-report {
            background-color: #ffb347;
            /* More yellowish/orange color */
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
                height: 110px;
                /* Significantly increased from 80px to 110px */
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
                right: 10px;
                border-radius: 18px;
                padding: 10px 14px;
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
                height: 100px;
                /* Significantly increased from 70px to 100px */
            }

            .calendar-body {
                grid-gap: 3px;
                /* Reduced gap to provide more space for cells */
                padding: 3px;
            }

            .add-event-btn {
                width: 18px;
                height: 18px;
                font-size: 12px;
                top: 0;
                right: 10px;
                border-radius: 18px;
                padding: 10px 14px;
            }
        }

        @media (max-width: 576px) {
            .calendar-header-cell {
                font-size: 0.8rem;
                padding: 5px 2px;
            }

            .calendar-day {
                height: 80px;
                /* Significantly increased from 60px to 80px */
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
                right: 10px;
                box-shadow: 0 1px 1px rgba(0, 0, 0, 0.1);
                border-radius: 18px;
                padding: 10px 14px;
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
                grid-gap: 2px;
                /* Further reduced gap */
                padding: 2px;
            }
        }

        /* Alternative display for smallest screens */
        @media (max-width: 450px) {
            .site-calendar-container {
                overflow-x: auto;
                /* Allow horizontal scrolling if needed */
                padding-bottom: 10px;
                /* Space for potential scrollbar */
            }

            .site-calendar {
                min-width: 400px;
                /* Set minimum width to ensure cells are wide enough */
            }

            .calendar-day {
                width: auto;
                min-width: 55px;
                /* Ensure minimum width */
            }

            .add-event-btn {
                width: 14px;
                height: 14px;
                font-size: 9px;
                top: 0;
                right: 10px;
                border-radius: 18px;
                padding: 10px 14px;
            }
        }

        .today .calendar-date {
            background-color: #3182ce;
            color: white;
        }

        /* Calendar Stats Section Styles */
        .calendar-stats-section {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
        }

        .supervisor-calendar-wrapper {
            position: relative;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .supervisor-calendar-container {
            width: 100%;
            min-height: 350px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 15px;
        }

        .calendar-stats-summary {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 15px;
            height: 100%;
        }

        .stats-summary-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }

        .stats-summary-item {
            margin-bottom: 15px;
        }

        .stats-summary-item .badge {
            font-size: 0.85rem;
            padding: 5px 8px;
        }

        .upcoming-events {
            margin-top: 20px;
        }

        .upcoming-event-item {
            display: flex;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .upcoming-event-item:last-child {
            border-bottom: none;
        }

        .event-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
            margin-top: 5px;
        }

        .event-inspection {
            background-color: #38a169;
        }

        .event-delivery {
            background-color: #e67e22;
        }

        .event-meeting {
            background-color: #805ad5;
        }

        .event-report {
            background-color: #ffb347;
        }

        .event-issue {
            background-color: #e53e3e;
        }

        .event-details {
            flex: 1;
        }

        .event-title {
            font-weight: 500;
            font-size: 0.9rem;
        }

        .event-time {
            color: #666;
            font-size: 0.8rem;
        }

        /* Supervisor Calendar Styles */
        .supervisor-calendar-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 3px;
        }

        .supervisor-calendar-header {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background-color: #f8f9fa;
            border-radius: 6px 6px 0 0;
            overflow: hidden;
        }

        .supervisor-calendar-header-cell {
            padding: 8px 5px;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
            color: #495057;
        }

        .supervisor-calendar-body {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-gap: 4px;
            padding: 4px;
        }

        .supervisor-calendar-day {
            height: 80px;
            border-radius: 6px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 8px;
            position: relative;
            transition: all 0.2s ease;
            cursor: pointer;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .supervisor-calendar-day:hover {
            background: #f8f9fa;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .supervisor-calendar-day.today {
            background-color: #e8f4ff;
            border: 1px solid #4299e1;
        }

        .supervisor-calendar-date {
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

        /* Add supervisor calendar date container to hold date and add button */
        .supervisor-calendar-date-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 4px;
            position: relative;
            width: 100%;
        }

        @media (max-width: 576px) {
            .supervisor-calendar-date-container {
                justify-content: flex-start;
                margin-bottom: 2px;
            }
        }

        /* Add button styles for supervisor calendar */
        .supervisor-add-event-btn {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            opacity: 0.9;
            border: none;
            padding: 0;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            line-height: 1;
            position: relative;
            z-index: 5;
        }

        /* Add plus sign using ::before pseudo-element */
        .supervisor-add-event-btn::before {
            content: "+";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 14px;
            font-weight: bold;
            line-height: 1;
        }

        .supervisor-add-event-btn:hover {
            background-color: #2980b9;
            opacity: 1;
            transform: scale(1.1);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .supervisor-calendar-day.other-month .supervisor-add-event-btn {
            opacity: 0.4;
            background-color: #cbd5e0;
        }

        .supervisor-calendar-day:hover .supervisor-add-event-btn {
            opacity: 1;
        }

        .supervisor-calendar-events {
            font-size: 0.7rem;
            overflow: hidden;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            margin-top: 5px;
        }

        .supervisor-calendar-event {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 2px 4px;
            border-radius: 3px;
            margin-bottom: 2px;
            color: white;
            font-weight: 500;
            font-size: 0.65rem;
            line-height: 1.2;
        }

        .supervisor-calendar-day.other-month {
            opacity: 0.4;
        }

        .supervisor-event-more {
            text-align: center;
            color: #718096;
            font-style: italic;
            font-size: 0.65rem;
        }

        /* Responsive styles for calendar stats */
        @media (max-width: 992px) {
            .supervisor-calendar-container {
                min-height: 300px;
            }

            .supervisor-calendar-day {
                height: 70px;
            }

            .supervisor-calendar-date {
                width: 22px;
                height: 22px;
                font-size: 0.85rem;
            }

            .supervisor-add-event-btn {
                width: 18px;
                height: 18px;
                font-size: 12px;
            }

            .calendar-stats-summary {
                margin-top: 20px;
            }
        }

        @media (max-width: 768px) {
            .supervisor-calendar-container {
                min-height: 250px;
            }

            .supervisor-calendar-day {
                height: 60px;
            }

            .supervisor-calendar-events .supervisor-calendar-event:nth-child(n+2),
            .supervisor-event-more {
                display: none;
            }
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

            <?php include 'greetings_section.php'; ?>

            <!-- Camera Container for Punch In/Out -->
            <div id="cameraContainer" class="punch-camera-container">
                <div class="punch-camera-overlay"></div>
                <div class="punch-camera-content">
                    <div class="punch-camera-header">
                        <h4 id="camera-title">Take Selfie for Punch In</h4>
                        <button id="closeCameraBtn" class="punch-close-camera-btn"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="punch-camera-body">
                        <div class="punch-video-wrapper">
                            <video id="cameraVideo" autoplay playsinline></video>
                            <canvas id="cameraCanvas" style="display: none;"></canvas>
                            <div id="cameraCaptureBtn" class="punch-camera-capture-btn">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        <div class="punch-captured-image-wrapper" style="display: none;">
                            <img id="capturedImage" src="" alt="Captured selfie">
                        </div>
                        <div class="punch-location-info">
                            <div class="punch-location-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span id="locationStatus">Getting your location...</span>
                            </div>
                            <div class="punch-location-item">
                                <i class="fas fa-globe"></i>
                                <span id="locationCoords">Latitude: -- | Longitude: --</span>
                            </div>
                            <div class="punch-location-item">
                                <i class="fas fa-map"></i>
                                <span id="locationAddress">Address: --</span>
                            </div>
                        </div>

                        <!-- Work Report Section (shown only when punching out) -->
                        <div id="workReportSection" class="punch-work-report" style="display: none;">
                            <div class="work-report-header">
                                <i class="fas fa-clipboard-list"></i>
                                <span>Daily Work Report</span>
                            </div>
                            <div class="form-group">
                                <textarea id="workReportText" class="form-control" rows="4"
                                    placeholder="Enter details about your work today..."></textarea>
                                <small class="form-text text-muted">Please provide a brief description of tasks
                                    completed today.</small>
                            </div>
                        </div>
                    </div>
                    <div class="punch-camera-footer">
                        <button id="retakePhotoBtn" class="btn btn-secondary punch-retake-btn" style="display: none;">
                            <i class="fas fa-redo"></i> Retake
                        </button>
                        <button id="confirmPunchBtn" class="btn btn-primary punch-confirm-btn" style="display: none;">
                            <i class="fas fa-check"></i> Confirm Punch
                        </button>
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
                                        <option value="2" <?php echo date('n') == 2 ? 'selected' : ''; ?>>February
                                        </option>
                                        <option value="3" <?php echo date('n') == 3 ? 'selected' : ''; ?>>March</option>
                                        <option value="4" <?php echo date('n') == 4 ? 'selected' : ''; ?>>April</option>
                                        <option value="5" <?php echo date('n') == 5 ? 'selected' : ''; ?>>May</option>
                                        <option value="6" <?php echo date('n') == 6 ? 'selected' : ''; ?>>June</option>
                                        <option value="7" <?php echo date('n') == 7 ? 'selected' : ''; ?>>July</option>
                                        <option value="8" <?php echo date('n') == 8 ? 'selected' : ''; ?>>August</option>
                                        <option value="9" <?php echo date('n') == 9 ? 'selected' : ''; ?>>September
                                        </option>
                                        <option value="10" <?php echo date('n') == 10 ? 'selected' : ''; ?>>October
                                        </option>
                                        <option value="11" <?php echo date('n') == 11 ? 'selected' : ''; ?>>November
                                        </option>
                                        <option value="12" <?php echo date('n') == 12 ? 'selected' : ''; ?>>December
                                        </option>
                                    </select>
                                    <select class="form-control form-control-sm" id="statsYearFilter">
                                        <?php
                                        $currentYear = date('Y');
                                        for ($year = $currentYear - 2; $year <= $currentYear + 1; $year++) {
                                            echo '<option value="' . $year . '" ' . ($year == $currentYear ? 'selected' : '') . '>' . $year . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-1-5 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card stat-card" data-toggle="tooltip" data-placement="top"
                                    title="All workers currently assigned to active projects">
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
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: 84%"
                                                aria-valuenow="84" aria-valuemin="0" aria-valuemax="100"></div>
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
                                                    <polyline
                                                        points="0,15 10,10 20,12 30,7 40,9 50,5 60,8 70,4 80,6 90,2"
                                                        fill="none" stroke="#5da5f7" stroke-width="2"></polyline>
                                                </svg>
                                            </div>
                                            <div class="stat-actions mt-2">
                                                <a href="worker_attendance.php"
                                                    class="btn btn-sm btn-outline-primary btn-block"><i
                                                        class="fas fa-eye"></i> View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-1-5 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card stat-card" data-toggle="tooltip" data-placement="top"
                                    title="Current number of ongoing construction projects">
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
                                            <div class="progress-bar bg-success" role="progressbar" style="width: 80%"
                                                aria-valuenow="80" aria-valuemin="0" aria-valuemax="100"></div>
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
                                                    <polyline
                                                        points="0,10 10,8 20,12 30,9 40,7 50,10 60,6 70,8 80,5 90,3"
                                                        fill="none" stroke="#2ecc71" stroke-width="2"></polyline>
                                                </svg>
                                            </div>
                                            <div class="stat-actions mt-2">
                                                <a href="site_progress.php"
                                                    class="btn btn-sm btn-outline-success btn-block"><i
                                                        class="fas fa-eye"></i> View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-1-5 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card stat-card" data-toggle="tooltip" data-placement="top"
                                    title="Percentage of tasks completed on time vs total tasks">
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
                                            <div class="progress-bar bg-warning" role="progressbar" style="width: 78%"
                                                aria-valuenow="78" aria-valuemin="0" aria-valuemax="100"></div>
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
                                                    <polyline
                                                        points="0,15 10,13 20,14 30,12 40,10 50,11 60,9 70,8 80,6 90,5"
                                                        fill="none" stroke="#f39c12" stroke-width="2"></polyline>
                                                </svg>
                                            </div>
                                            <div class="stat-actions mt-2">
                                                <a href="worker_performance.php"
                                                    class="btn btn-sm btn-outline-warning btn-block"><i
                                                        class="fas fa-eye"></i> View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-1-5 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card stat-card" data-toggle="tooltip" data-placement="top"
                                    title="Total number of tasks completed this month">
                                    <div class="stat-icon bg-info">
                                        <i class="fas fa-clipboard-check"></i>
                                    </div>
                                    <div class="stat-details">
                                        <div class="d-flex justify-content-between align-items-baseline">
                                            <h3>12</h3>
                                            <span class="stat-trend trend-down"><i class="fas fa-arrow-down"></i>
                                                2</span>
                                        </div>
                                        <p>Completed Tasks</p>
                                        <div class="progress stat-progress" style="height: 5px;">
                                            <div class="progress-bar bg-info" role="progressbar" style="width: 60%"
                                                aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"></div>
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
                                                    <polyline
                                                        points="0,6 10,8 20,5 30,9 40,7 50,10 60,8 70,12 80,10 90,6"
                                                        fill="none" stroke="#3498db" stroke-width="2"></polyline>
                                                </svg>
                                            </div>
                                            <div class="stat-actions mt-2">
                                                <a href="worker_assignment.php"
                                                    class="btn btn-sm btn-outline-info btn-block"><i
                                                        class="fas fa-eye"></i> View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-1-5 col-md-4 col-sm-6 mb-3">
                                <div class="dashboard-card stat-card" data-toggle="tooltip" data-placement="top"
                                    title="Total travel expenses reported by workers this month">
                                    <?php
                                    // Fetch total pending amount for travel expenses for the current month
                                    // ONLY for the currently logged-in user
                                    $current_month = date('m');
                                    $current_year = date('Y');
                                    $current_user_id = $_SESSION['user_id']; // Get the logged-in user's ID
                                    
                                    // Query to get total pending amount for the current user only
                                    $pending_query = "SELECT SUM(amount) as pending_amount 
                                                      FROM travel_expenses 
                                                      WHERE status = 'pending' 
                                                      AND user_id = ? 
                                                      AND MONTH(travel_date) = ? 
                                                      AND YEAR(travel_date) = ?";

                                    $pending_stmt = $conn->prepare($pending_query);
                                    $pending_stmt->bind_param("iii", $current_user_id, $current_month, $current_year);
                                    $pending_stmt->execute();
                                    $pending_result = $pending_stmt->get_result();
                                    $pending_data = $pending_result->fetch_assoc();

                                    $pending_amount = $pending_data['pending_amount'] ? $pending_data['pending_amount'] : 0;

                                    // Format the pending amount
                                    $formatted_pending = '₹' . number_format($pending_amount, 2);

                                    // If there are pending expenses for this user, show the indicator
                                    if ($pending_amount > 0) {
                                        echo '<div class="pending-expense-indicator">
                                                <span class="pending-amount">' . $formatted_pending . '</span>
                                                <span class="pending-label">Your Pending</span>
                                             </div>';
                                    }
                                    ?>
                                    <div class="stat-icon bg-danger">
                                        <i class="fas fa-taxi"></i>
                                    </div>
                                    <div class="stat-details">
                                        <div class="d-flex justify-content-between align-items-baseline">
                                            <?php
                                            // Fetch total approved expenses for the current month
                                            $approved_query = "SELECT SUM(amount) as total_amount 
                                                               FROM travel_expenses 
                                                               WHERE status = 'approved' 
                                                               AND MONTH(travel_date) = ? 
                                                               AND YEAR(travel_date) = ?";

                                            $approved_stmt = $conn->prepare($approved_query);
                                            $approved_stmt->bind_param("ii", $current_month, $current_year);
                                            $approved_stmt->execute();
                                            $approved_result = $approved_stmt->get_result();
                                            $approved_data = $approved_result->fetch_assoc();

                                            $total_amount = $approved_data['total_amount'] ? $approved_data['total_amount'] : 0;

                                            // Format the approved amount
                                            $formatted_amount = '₹' . number_format($total_amount, 0);

                                            // Get percentage change from last month
                                            $last_month = $current_month == 1 ? 12 : $current_month - 1;
                                            $last_month_year = $current_month == 1 ? $current_year - 1 : $current_year;

                                            $last_month_query = "SELECT SUM(amount) as last_month_amount 
                                                                 FROM travel_expenses 
                                                                 WHERE status = 'approved' 
                                                                 AND MONTH(travel_date) = ? 
                                                                 AND YEAR(travel_date) = ?";

                                            $last_month_stmt = $conn->prepare($last_month_query);
                                            $last_month_stmt->bind_param("ii", $last_month, $last_month_year);
                                            $last_month_stmt->execute();
                                            $last_month_result = $last_month_stmt->get_result();
                                            $last_month_data = $last_month_result->fetch_assoc();

                                            $last_month_amount = $last_month_data['last_month_amount'] ? $last_month_data['last_month_amount'] : 0;

                                            // Calculate percentage change
                                            $percent_change = 0;
                                            if ($last_month_amount > 0) {
                                                $percent_change = round((($total_amount - $last_month_amount) / $last_month_amount) * 100);
                                            }

                                            // Determine if trend is up or down
                                            $trend_class = $percent_change >= 0 ? 'trend-up' : 'trend-down';
                                            $trend_icon = $percent_change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                                            ?>
                                            <h3><?php echo $formatted_amount; ?></h3>
                                            <span class="stat-trend <?php echo $trend_class; ?>">
                                                <i class="fas <?php echo $trend_icon; ?>"></i>
                                                <?php echo abs($percent_change); ?>%
                                            </span>
                                        </div>
                                        <p>Travel Expenses <button id="addTravelExpenseBtn"
                                                class="btn btn-sm btn-danger ml-2" title="Add Travel Expense"
                                                type="button" style="border-radius: 18px;"><i
                                                    class="fas fa-plus"></i></button></p>
                                        <div class="progress stat-progress" style="height: 5px;">
                                            <?php
                                            // Calculate budget usage percentage (budget is ₹10,000)
                                            $budget = 10000;
                                            $budget_percent = min(100, round(($total_amount / $budget) * 100));
                                            ?>
                                            <div class="progress-bar bg-danger" role="progressbar"
                                                style="width: <?php echo $budget_percent; ?>%"
                                                aria-valuenow="<?php echo $budget_percent; ?>" aria-valuemin="0"
                                                aria-valuemax="100"></div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center stat-goal-text">
                                            <small>Budget: ₹10,000</small>
                                            <small><?php echo $budget_percent; ?>% Used</small>
                                        </div>
                                        <div class="stat-footer">
                                            <div class="stat-secondary">
                                                <?php
                                                // Calculate average expense per worker
                                                $workers_query = "SELECT COUNT(DISTINCT user_id) as worker_count 
                                                                  FROM travel_expenses 
                                                                  WHERE status = 'approved' 
                                                                  AND MONTH(travel_date) = ? 
                                                                  AND YEAR(travel_date) = ?";

                                                $workers_stmt = $conn->prepare($workers_query);
                                                $workers_stmt->bind_param("ii", $current_month, $current_year);
                                                $workers_stmt->execute();
                                                $workers_result = $workers_stmt->get_result();
                                                $workers_data = $workers_result->fetch_assoc();

                                                $worker_count = $workers_data['worker_count'] ? $workers_data['worker_count'] : 1;
                                                $avg_per_worker = round($total_amount / $worker_count);
                                                ?>
                                                <small>Avg/Worker: ₹<?php echo $avg_per_worker; ?></small>
                                            </div>
                                            <div class="stat-chart">
                                                <svg width="100%" height="20" class="stat-sparkline">
                                                    <?php
                                                    // Generate sparkline from last 6 months data
                                                    $points = '';

                                                    for ($i = 5; $i >= 0; $i--) {
                                                        $month_num = ($current_month - $i) <= 0 ? ($current_month - $i + 12) : ($current_month - $i);
                                                        $year_num = ($current_month - $i) <= 0 ? ($current_year - 1) : $current_year;

                                                        $month_query = "SELECT SUM(amount) as month_amount 
                                                                       FROM travel_expenses 
                                                                       WHERE status = 'approved' 
                                                                       AND MONTH(travel_date) = ? 
                                                                       AND YEAR(travel_date) = ?";

                                                        $month_stmt = $conn->prepare($month_query);
                                                        $month_stmt->bind_param("ii", $month_num, $year_num);
                                                        $month_stmt->execute();
                                                        $month_result = $month_stmt->get_result();
                                                        $month_data = $month_result->fetch_assoc();

                                                        $month_amount = $month_data['month_amount'] ? $month_data['month_amount'] : 0;

                                                        // Scale the amount to fit in our 20px height (max assumed to be ₹20,000)
                                                        $height = 15 - min(15, round(($month_amount / 20000) * 15));

                                                        // Add point to the sparkline
                                                        $x_pos = 90 - ($i * 18);
                                                        $points .= "$x_pos,$height ";
                                                    }
                                                    ?>
                                                    <polyline points="<?php echo trim($points); ?>" fill="none"
                                                        stroke="#e74c3c" stroke-width="2"></polyline>
                                                </svg>
                                            </div>
                                            <div class="stat-actions mt-2">
                                                <a href="view_travel_expenses.php"
                                                    class="btn btn-sm btn-outline-danger btn-block"><i
                                                        class="fas fa-eye"></i> View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calendar Stats Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="dashboard-card calendar-stats-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0">Calendar Stats</h4>
                            <div class="calendar-nav">
                                <button id="prevMonthCalStats"
                                    class="btn btn-sm btn-outline-secondary calendar-nav-btn">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <span id="currentMonthCalStats" class="current-month-display">May 2023</span>
                                <button id="nextMonthCalStats"
                                    class="btn btn-sm btn-outline-secondary calendar-nav-btn">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>

                        <div class="calendar-stats-container">
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="supervisor-calendar-wrapper">
                                        <div id="supervisorCalendar" class="supervisor-calendar-container">
                                            <!-- Calendar will be rendered here by JavaScript -->
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="calendar-stats-summary">
                                        <h5 class="stats-summary-title">Recent Month Targets</h5>
                                        <div class="monthly-targets-section">
                                            <!-- Monthly Targets -->
                                            <div class="monthly-targets-group">
                                                <div class="monthly-targets-header">
                                                    <div class="monthly-targets-title">
                                                        <i class="fas fa-bullseye"></i> Monthly Objectives
                                                    </div>
                                                    <div class="monthly-targets-period-selector">
                                                        <select class="monthly-targets-period-dropdown"
                                                            id="monthlyTargetPeriod">
                                                            <option value="previous">Previous Month</option>
                                                            <option value="present" selected>Current Month</option>
                                                            <option value="next">Next Month</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- Current Month Targets -->
                                                <ul class="monthly-targets-list" id="currentMonthTargets">
                                                    <li class="target-item">Complete foundation work for entire building
                                                    </li>
                                                    <li class="target-item">Finish structural framework for floors 1-3
                                                    </li>
                                                    <li class="target-item">Install main electrical panels and
                                                        distribution</li>
                                                    <li class="target-item">Complete plumbing rough-in for ground floor
                                                    </li>
                                                    <li class="target-item">Finish exterior wall construction</li>
                                                    <li class="target-item">Begin window installation on completed
                                                        floors</li>
                                                    <li class="target-item">Set up permanent site security measures</li>
                                                    <li class="target-item">Complete drainage system installation</li>
                                                    <li class="target-item">Begin HVAC ductwork installation</li>
                                                    <li class="target-item">Complete monthly safety inspection and
                                                        reporting</li>
                                                </ul>

                                                <!-- Previous Month Targets -->
                                                <ul class="monthly-targets-list" id="previousMonthTargets"
                                                    style="display: none;">
                                                    <li class="target-item">Site preparation and excavation completed
                                                    </li>
                                                    <li class="target-item">Temporary facilities and utilities set up
                                                    </li>
                                                    <li class="target-item">Initial foundation layout and marking</li>
                                                    <li class="target-item">Procurement of primary building materials
                                                    </li>
                                                    <li class="target-item">Approval of final architectural drawings
                                                    </li>
                                                    <li class="target-item">Soil testing and foundation preparation</li>
                                                    <li class="target-item">Security fencing and site access control
                                                    </li>
                                                    <li class="target-item">Environmental compliance measures
                                                        implemented</li>
                                                    <li class="target-item">Subcontractor agreements finalized</li>
                                                    <li class="target-item">Initial site drainage systems installed</li>
                                                </ul>

                                                <!-- Next Month Targets -->
                                                <ul class="monthly-targets-list" id="nextMonthTargets"
                                                    style="display: none;">
                                                    <li class="target-item">Begin interior wall framing on all floors
                                                    </li>
                                                    <li class="target-item">Complete roof structure and waterproofing
                                                    </li>
                                                    <li class="target-item">Install electrical wiring in completed
                                                        sections</li>
                                                    <li class="target-item">Begin plumbing fixture installation</li>
                                                    <li class="target-item">Start interior drywall installation</li>
                                                    <li class="target-item">Complete all window and exterior door
                                                        installation</li>
                                                    <li class="target-item">Begin exterior finishing and cladding</li>
                                                    <li class="target-item">Install fire suppression systems</li>
                                                    <li class="target-item">Begin elevator shaft construction</li>
                                                    <li class="target-item">Prepare for initial building inspection</li>
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- Upcoming Events section removed -->
                                    </div>
                                </div>
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
                                                <div class="progress-bar bg-success" role="progressbar"
                                                    style="width: 75%" aria-valuenow="75" aria-valuemin="0"
                                                    aria-valuemax="100">75%</div>
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
                                                <div class="progress-bar bg-warning" role="progressbar"
                                                    style="width: 45%" aria-valuenow="45" aria-valuemin="0"
                                                    aria-valuemax="100">45%</div>
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
                                                <div class="progress-bar bg-danger" role="progressbar"
                                                    style="width: 30%" aria-valuenow="30" aria-valuemin="0"
                                                    aria-valuemax="100">30%</div>
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
                                                <div class="progress-bar bg-success" role="progressbar"
                                                    style="width: 60%" aria-valuenow="60" aria-valuemin="0"
                                                    aria-valuemax="100">60%</div>
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
    <script src="js/supervisor/calendar-modal.js"></script>
    <script src="js/supervisor/calendar-stats.js"></script>
    <script src="js/supervisor/calendar-events-save.js"></script>
    <script src="js/supervisor/calendar-events-modal.js"></script>
    <script src="js/supervisor/date-events-modal.js"></script>
    <script src="js/supervisor/enhanced-event-view-modal.js"></script>
    <script src="js/supervisor/travel-expense-modal.js"></script>
    <script src="js/supervisor/supervisor-camera-module.js"></script>
    <script src="js/supervisor/come_in_module.js?v=<?php echo time(); ?>"></script>


    <!-- Override native alerts for calendar messages -->
    <script>
        // Override the native alert for calendar-related messages
        const originalAlert = window.alert;
        window.alert = function (message) {
            // Check if the message is a calendar event notification
            if (message && message.includes && (
                message.includes('No events on') ||
                message.includes('Events on') ||
                message.includes('Add new event on')
            )) {
                console.log('Suppressed alert:', message);
                return; // Don't show the alert
            }

            // For other alerts, use the original function
            originalAlert(message);
        };
    </script>

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
        document.getElementById('punchButton').addEventListener('click', function () {
            const isPunchedIn = this.classList.contains('btn-danger');
            const button = this;

            // Action based on current state
            const action = isPunchedIn ? 'out' : 'in';

            // Open camera modal for capturing photo
            openCameraModal(action, function (photoData, locationData) {
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

                                    // Don't add punch time indicator
                                    // Removed "Since punch in time" display

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
                            max-height: 90vh;
                            display: flex;
                            flex-direction: column;
                        }
                        .camera-header {
                            padding: 15px;
                            background-color: #3498db;
                            color: white;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            flex-shrink: 0;
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
                            flex-shrink: 0;
                            position: sticky;
                            bottom: 0;
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
                    function (position) {
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
                    function (error) {
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
                    navigator.permissions.query({ name: 'camera' })
                        .then(function (permissionStatus) {
                            console.log('Camera permission status:', permissionStatus.state);

                            if (permissionStatus.state === 'denied') {
                                // Permission explicitly denied
                                showCameraError("Camera permission denied. Please check your browser settings.");
                                return;
                            }

                            // Continue with camera initialization
                            initializeCamera(facingMode);

                            // Listen for permission changes
                            permissionStatus.onchange = function () {
                                console.log('Permission state changed to:', this.state);
                                if (this.state === 'granted') {
                                    initializeCamera(currentFacingMode);
                                } else if (this.state === 'denied') {
                                    showCameraError("Camera permission was denied");
                                }
                            };
                        })
                        .catch(function (error) {
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
                    .then(function (mediaStream) {
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
                        setTimeout(function () {
                            if (video.readyState < 2) { // HAVE_CURRENT_DATA or less
                                showCameraError("Camera connected but not providing video. Try reloading the page.");
                            }
                        }, 3000);
                    })
                    .catch(function (err) {
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
                                .then(function (mediaStream) {
                                    stream = mediaStream;
                                    video.srcObject = mediaStream;
                                    video.play();
                                })
                                .catch(function (fallbackErr) {
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
            video.addEventListener('playing', function () {
                // Hide error if video is actually playing
                cameraError.style.display = 'none';
                captureBtn.disabled = false;
            });

            // Start camera with front-facing camera by default
            startCamera('user');

            // Rotate camera button
            const rotateCameraBtn = document.getElementById('rotate-camera-btn');
            rotateCameraBtn.addEventListener('click', function () {
                // Toggle between front and rear cameras
                const newFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
                startCamera(newFacingMode);
            });

            // Photo data
            let photoData = null;

            // Capture photo
            captureBtn.addEventListener('click', function () {
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
            retakeBtn.addEventListener('click', function () {
                photoPreview.style.display = 'none';
                videoContainer.style.display = 'block';
                captureBtn.style.display = 'inline-block';
                retakeBtn.style.display = 'none';
                confirmBtn.style.display = 'none';
                photoData = null;
            });

            // Confirm photo and location
            confirmBtn.addEventListener('click', function () {
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
            skipPhotoBtn.addEventListener('click', function () {
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
            document.getElementById('retry-camera-btn').addEventListener('click', function () {
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
        document.querySelector('.notification-bell').addEventListener('click', function (e) {
            e.preventDefault();
            alert('You have 3 unread notifications');
            // In a real app, this would open a notification panel
        });
    </script>

    <!-- Update JavaScript for enhanced filter functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
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
                card.addEventListener('click', function (e) {
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
                if (data.sparkline && card.querySelector('.stat-sparkline polyline')) {
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



    <?php include 'include_password_change.php'; ?>






    <!-- Travel Expense Modal -->
    <div class="modal fade" id="travelExpenseModal" tabindex="-1" role="dialog"
        aria-labelledby="travelExpenseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="travelExpenseModalLabel">Add Travel Expense</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="travel-expenses-container">
                        <div class="travel-expenses-list">
                            <!-- Travel expense entries will be added here dynamically -->
                        </div>

                        <form id="travelExpenseForm">
                            <div class="row form-row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="purposeOfVisit">Purpose of Visit</label>
                                        <input type="text" class="form-control" id="purposeOfVisit" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="modeOfTransport">Mode of Transport</label>
                                        <select class="form-control" id="modeOfTransport" required>
                                            <option value="">Select mode</option>
                                            <option value="Car">Car</option>
                                            <option value="Bike">Bike</option>
                                            <option value="Public Transport">Public Transport</option>
                                            <option value="Taxi">Taxi</option>
                                            <option value="Metro">Metro</option>
                                            <option value="Aeroplane">Aeroplane</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row form-row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="fromLocation">From</label>
                                        <input type="text" class="form-control" id="fromLocation" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="toLocation">To</label>
                                        <input type="text" class="form-control" id="toLocation" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row form-row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="travelDate">Date</label>
                                        <input type="date" class="form-control" id="travelDate" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="approxDistance">Approx Distance (km)</label>
                                        <input type="number" min="0" step="0.1" class="form-control" id="approxDistance"
                                            required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="totalExpense">Total Expenses (₹)</label>
                                        <input type="number" min="0" step="0.01" class="form-control" id="totalExpense"
                                            required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="expenseNotes">Notes (Optional)</label>
                                <textarea class="form-control" id="expenseNotes" rows="2"></textarea>
                            </div>

                            <!-- Bill Upload Section (shown only for Aeroplane) -->
                            <div class="form-group" id="billUploadSection" style="display: none;">
                                <label for="billFile">Upload Bill (Required)<span class="text-danger">*</span></label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="billFile"
                                        accept=".jpg,.jpeg,.png,.pdf">
                                    <label class="custom-file-label" for="billFile">Choose file...</label>
                                </div>
                                <small class="form-text text-muted">Please upload bill receipt (JPG, PNG, or PDF
                                    only)</small>
                            </div>

                            <div class="text-right">
                                <button type="button" class="btn btn-outline-secondary"
                                    id="resetExpenseForm">Reset</button>
                                <button type="button" class="btn btn-primary" id="addExpenseEntry">Add Entry</button>
                            </div>
                        </form>

                        <div class="travel-expenses-summary mt-4" style="display: none;">
                            <h5>Summary</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p>Total Entries: <span id="totalEntries">0</span></p>
                                </div>
                                <div class="col-md-6 text-right">
                                    <p>Total Amount: ₹<span id="totalAmount">0.00</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="saveAllExpenses">Save All Expenses</button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Supervisor Camera Modal -->
    <div id="supervisorCameraModal" class="supervisor-camera-modal-overlay">
        <div class="supervisor-camera-modal-content">
            <div class="supervisor-camera-modal-header">
                <h4>Supervisor Camera</h4>
                <button id="closeSupervisorCameraBtn" class="supervisor-camera-close-btn"><i
                        class="fas fa-times"></i></button>
            </div>
            <div class="supervisor-camera-modal-body">
                <div class="supervisor-camera-container">
                    <video id="supervisorCameraVideo" autoplay playsinline></video>
                    <canvas id="supervisorCameraCanvas" style="display: none;"></canvas>
                    <div id="supervisorCameraCaptureBtn" class="supervisor-camera-capture-btn">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
                <div class="supervisor-captured-image-container" style="display: none;">
                    <img id="supervisorCapturedImage" src="" alt="Captured image">
                </div>
            </div>
            <div class="supervisor-camera-modal-footer">
                <button id="supervisorRetakeBtn" class="btn btn-secondary" style="display: none;">Retake</button>
                <button id="supervisorSaveImageBtn" class="btn btn-success" style="display: none;">Save Image</button>
            </div>
        </div>
    </div>

    <!-- Include Come In Modal -->
    <?php include 'modals/come_in_modal.php'; ?>

    <!-- Add JavaScript for Site In/Out button functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const siteInOutBtn = document.getElementById('siteInOutBtn');
            if (siteInOutBtn) {
                siteInOutBtn.addEventListener('click', function () {
                    if (!navigator.geolocation) {
                        showToast('Error', 'danger', 'Geolocation is not supported by your browser.');
                        return;
                    }
                    siteInOutBtn.disabled = true;
                    siteInOutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting location...';
                    navigator.geolocation.getCurrentPosition(function (position) {
                        const latitude = position.coords.latitude;
                        const longitude = position.coords.longitude;
                        const accuracy = position.coords.accuracy;
                        // Optional: Reverse geocode to get address
                        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`, {
                            headers: {
                                'Accept': 'application/json',
                                'User-Agent': 'HR Attendance System'
                            }
                        })
                            .then(response => response.json())
                            .then(data => {
                                let address = typeof data.display_name === 'string' ? data.display_name : '';
                                // Use the button's data-action attribute
                                let action = siteInOutBtn.getAttribute('data-action');
                                // Prepare data
                                const formData = new FormData();
                                formData.append('action', action);
                                formData.append('latitude', latitude);
                                formData.append('longitude', longitude);
                                formData.append('address', address);
                                formData.append('device_info', navigator.userAgent);
                                fetch('ajax_handlers/site_in_out.php', {
                                    method: 'POST',
                                    body: formData,
                                    headers: { 'Accept': 'application/json' }
                                })
                                    .then(res => res.json())
                                    .then(res => {
                                        if (res.status === 'success') {
                                            showToast('Success', 'success', res.message + '<br><small>Location: ' + (address ? address : 'Unknown') + '</small>');
                                            setTimeout(() => { window.location.reload(); }, 1200);
                                        } else {
                                            showToast('Error', 'danger', res.message);
                                            siteInOutBtn.disabled = false;
                                            siteInOutBtn.innerHTML = action === 'site_in'
                                                ? '<i class="fas fa-sign-in-alt"></i> Site In'
                                                : '<i class="fas fa-sign-out-alt"></i> Site Out';
                                        }
                                    })
                                    .catch(err => {
                                        showToast('Error', 'danger', 'Failed to record site in/out.');
                                        siteInOutBtn.disabled = false;
                                        siteInOutBtn.innerHTML = action === 'site_in'
                                            ? '<i class="fas fa-sign-in-alt"></i> Site In'
                                            : '<i class="fas fa-sign-out-alt"></i> Site Out';
                                    });
                            })
                            .catch(() => {
                                showToast('Error', 'danger', 'Could not get address.');
                                siteInOutBtn.disabled = false;
                                siteInOutBtn.innerHTML = '<i class="fas fa-map-marker-alt"></i> Site In/Out';
                            });
                    }, function (error) {
                        showToast('Error', 'danger', 'Could not get location: ' + error.message);
                        siteInOutBtn.disabled = false;
                        siteInOutBtn.innerHTML = '<i class="fas fa-map-marker-alt"></i> Site In/Out';
                    }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
                });
            }
        });
    </script>
    <?php include 'include_password_change.php'; ?>

    <!-- Include Instant Modal for Missing Punch Records -->
    <?php include 'instant_modal.php'; ?>

    <!-- Fix for Instant Modal positioning -->
    <style>
        #instantModal {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background-color: rgba(0, 0, 0, 0.7) !important;
            z-index: 3000 !important;
            justify-content: center !important;
            align-items: center !important;
            display: none !important;
            backdrop-filter: blur(3px);
        }

        #instantModal.active {
            display: flex !important;
        }

        .work-report-content {
            background: #ffffff;
            width: 90%;
            max-width: 600px;
            border-radius: 10px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease forwards;
            overflow: hidden;
            border: none;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .work-report-header {
            padding: 16px 20px;
            border-bottom: 1px solid #eef2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #4a6cf7, #6a11cb);
            color: white;
        }

        .work-report-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .work-report-header h3 i {
            font-size: 1.2rem;
        }

        .close-modal {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: white;
            font-size: 1rem;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .work-report-body {
            padding: 20px;
            max-height: 50vh;
            overflow-y: auto;
        }

        .work-report-footer {
            padding: 16px 20px;
            border-top: 1px solid #eef2f7;
            display: flex;
            flex-direction: column;
            background: #f8fafc;
        }

        .modal-suppression-checkbox {
            margin-bottom: 16px;
        }

        .modal-suppression-checkbox label {
            font-size: 0.85rem;
            color: #475569;
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .modal-suppression-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
            margin-right: 8px;
            accent-color: #4a6cf7;
            cursor: pointer;
        }

        .submit-btn {
            background: linear-gradient(135deg, #4a6cf7, #6a11cb);
            border: none;
            color: white;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 3px 5px rgba(74, 108, 247, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 10px rgba(74, 108, 247, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        /* Enhanced styles for missing punch items */
        .missing-punch-item {
            display: flex;
            align-items: center;
            padding: 14px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 3px solid #4a6cf7;
            transition: all 0.3s ease;
            position: relative;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .missing-punch-item:hover {
            background: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            border-left-width: 4px;
        }

        .missing-punch-item.punch-out {
            border-left-color: #10b981;
        }

        .missing-punch-item i {
            font-size: 1.1rem;
            margin-right: 12px;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: rgba(74, 108, 247, 0.1);
            color: #4a6cf7;
        }

        .missing-punch-item.punch-out i {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .missing-punch-details {
            flex: 1;
        }

        .missing-punch-date {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
            font-size: 0.95rem;
        }

        .missing-punch-type {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 6px;
        }

        .missing-punch-status {
            font-weight: 600;
            color: #ef4444;
            font-size: 0.8rem;
            background: rgba(239, 68, 68, 0.1);
            padding: 3px 8px;
            border-radius: 20px;
        }

        .missing-punch-timer {
            font-size: 0.75rem;
            color: #ef4444;
            font-weight: 500;
            background: rgba(254, 226, 226, 0.7);
            padding: 5px 10px;
            border-radius: 20px;
            margin-top: 6px;
            display: inline-block;
        }

        .missing-punch-timer.warning {
            color: #f59e0b;
            background: rgba(255, 243, 205, 0.7);
        }

        .missing-punch-timer.safe {
            color: #10b981;
            background: rgba(209, 250, 229, 0.7);
        }

        /* Alert box styling */
        .alert-box {
            margin-bottom: 20px;
            padding: 16px;
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border-radius: 8px;
            border: 1px solid #fde68a;
            color: #92400e;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .alert-box i {
            margin-right: 8px;
            font-size: 1rem;
        }

        /* Scrollable content */
        .scrollable-content {
            max-height: 350px;
            overflow-y: auto;
            padding-right: 8px;
        }

        /* Custom scrollbar */
        .scrollable-content::-webkit-scrollbar {
            width: 5px;
        }

        .scrollable-content::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 8px;
        }

        .scrollable-content::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 8px;
        }

        .scrollable-content::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Loading content */
        .loading-content {
            text-align: center;
            padding: 30px 16px;
        }

        .loading-content i {
            font-size: 2.5rem;
            color: #4a6cf7;
            margin-bottom: 16px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .loading-content h4 {
            margin-bottom: 12px;
            color: #1e293b;
            font-weight: 600;
            font-size: 1rem;
        }

        .loading-content p {
            color: #64748b;
            font-size: 0.85rem;
        }

        /* Error message */
        .error-message {
            text-align: center;
            padding: 30px 16px;
            background: #fef2f2;
            border-radius: 8px;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .error-message i {
            font-size: 2.5rem;
            color: #ef4444;
            margin-bottom: 16px;
        }

        .error-message h4 {
            margin-bottom: 12px;
            color: #b91c1c;
            font-weight: 600;
            font-size: 1rem;
        }

        .error-message p {
            font-size: 0.9rem;
            margin-bottom: 16px;
        }

        /* No missing punches */
        .no-missing-punches {
            text-align: center;
            padding: 40px 16px;
            background: #f0fdf4;
            border-radius: 8px;
        }

        .no-missing-punches i {
            font-size: 2.5rem;
            color: #10b981;
            margin-bottom: 16px;
        }

        .no-missing-punches h4 {
            margin-bottom: 12px;
            color: #047857;
            font-weight: 600;
            font-size: 1rem;
        }

        .no-missing-punches p {
            color: #065f46;
            font-size: 0.85rem;
        }
    </style>
    <!-- Task Notification Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize lastTaskId from localStorage or 0
            let lastTaskId = parseInt(localStorage.getItem('supervisorLastTaskId') || '0');

            // Initial check
            checkNewTasks(true);

            // Poll every 10 seconds
            setInterval(() => {
                checkNewTasks(false);
            }, 10000);

            function checkNewTasks(isFirstLoad) {
                fetch('site/get_latest_task_id.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.latest_task_id) {
                            const currentId = parseInt(data.latest_task_id);

                            // If it's the very first time running ever (lastTaskId is 0), just set it
                            if (lastTaskId === 0) {
                                lastTaskId = currentId;
                                localStorage.setItem('supervisorLastTaskId', currentId);
                                return;
                            }

                            if (currentId > lastTaskId) {
                                // New task detected!
                                // Ensure we don't play sound on refresh if we already knew about this task
                                // But here currentId > lastTaskId means it IS new relative to what we stored.

                                playNotificationSound();

                                // Optional: Show browser notification
                                if ("Notification" in window && Notification.permission === "granted") {
                                    new Notification("New Task Assigned!");
                                } else if ("Notification" in window && Notification.permission !== "denied") {
                                    Notification.requestPermission().then(permission => {
                                        if (permission === "granted") {
                                            new Notification("New Task Assigned!");
                                        }
                                    });
                                }

                                // Update lastTaskId
                                lastTaskId = currentId;
                                localStorage.setItem('supervisorLastTaskId', currentId);
                            }
                        }
                    })
                    .catch(err => console.error('Error checking new tasks:', err));
            }

            function playNotificationSound() {
                const audio = new Audio('notification/task_update_real.mp3');
                audio.play().catch(e => console.log('Audio play failed:', e));
            }

            // Request notification permission on load
            if ("Notification" in window && Notification.permission !== "granted" && Notification.permission !== "denied") {
                Notification.requestPermission();
            }
        });
    </script>
</body>

</html>