<?php
session_start();
error_log("Current user ID: " . ($_SESSION['user_id'] ?? 'Not set'));
require_once 'config/db_connect.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Update the database timezone for this connection
$conn->query("SET time_zone = '+05:30'");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get user role and details
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

// Check if user has appropriate role
$restricted_roles = ['HR', 'Admin', 'Senior Manager (Studio)', 'Senior Manager (Site)', 
                    'Senior Manager (Marketing)', 'Senior Manager (Sales)'];
if (in_array($user_data['role'], $restricted_roles)) {
    header('Location: unauthorized.php');
    exit();
}

// Get current time and date in IST
$current_time = date("h:i:s A"); // 12-hour format with seconds and AM/PM
$current_date = date("l, F j, Y");

// Get greeting based on IST hour
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour >= 12 && $hour < 16) {
    $greeting = "Good Afternoon";
} elseif ($hour >= 16 && $hour < 20) {
    $greeting = "Good Evening";
} else {
    $greeting = "Good Night";
}

// Add these functions at the top of your PHP section
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function getDeviceInfo() {
    return $_SERVER['HTTP_USER_AGENT'];
}

// Check if user has already punched in today (using IST date)
$today = date('Y-m-d');
$check_punch = $conn->prepare("SELECT id, punch_in, punch_out FROM attendance WHERE user_id = ? AND date = ?");
$check_punch->bind_param("is", $user_id, $today);
$check_punch->execute();
$punch_result = $check_punch->get_result();
$attendance = $punch_result->fetch_assoc();

// Update these lines with proper null checks
$is_punched_in = !empty($attendance) && !empty($attendance['punch_in']) && empty($attendance['punch_out']);
$already_completed = !empty($attendance) && !empty($attendance['punch_in']) && !empty($attendance['punch_out']);

// Add these to display current IST time in the greeting section
$ist_time = date('h:i A'); // 12-hour format
$ist_date = date('l, F j, Y');
// Initialize $shift_end with a default value
$shift_end = 0;
$remaining_seconds = 0;
$overtime_seconds = 0;
$is_overtime = false;

// Get shift details for the user
if ($user_data && isset($user_data['shift_id'])) {
    $shift_query = "SELECT start_time, end_time FROM shifts WHERE id = ?";
    $shift_stmt = $conn->prepare($shift_query);
    $shift_stmt->bind_param("i", $user_data['shift_id']);
    $shift_stmt->execute();
    $shift_result = $shift_stmt->get_result();
    $shift_details = $shift_result->fetch_assoc();
    
    if ($shift_details && $shift_details['end_time']) {
        // Get punch in time for today
        $get_punch_in = "SELECT punch_in FROM attendance WHERE user_id = ? AND date = ? AND punch_out IS NULL";
        $punch_stmt = $conn->prepare($get_punch_in);
        $today = date('Y-m-d');
        $punch_stmt->bind_param("is", $user_id, $today);
        $punch_stmt->execute();
        $punch_result = $punch_stmt->get_result();
        $punch_data = $punch_result->fetch_assoc();

        // Convert times to timestamps
        $shift_end = strtotime($today . ' ' . $shift_details['end_time']);
        $current_time = time();
        $punch_in_time = $punch_data ? strtotime($today . ' ' . $punch_data['punch_in']) : $current_time;

        // Check if punch in was after shift end
        if ($punch_in_time > $shift_end) {
            // If punched in after shift end, overtime starts from punch in time
            $overtime_seconds = $current_time - $punch_in_time;
            $is_overtime = true;
            $remaining_seconds = 0;
        } else {
            // Normal scenario
            if ($current_time > $shift_end) {
                // Calculate overtime
                $overtime_seconds = $current_time - $shift_end;
                $is_overtime = true;
                $remaining_seconds = 0;
            } else {
                // Calculate remaining time
                $remaining_seconds = $shift_end - $current_time;
                $is_overtime = false;
                $overtime_seconds = 0;
            }
        }
    }
}

// Make sure all PHP code is before any HTML output
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/project-metrics-dashboard.css">
    <script src="assets/js/project-metrics-dashboard.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/substage-details.css">
    <link rel="stylesheet" href="assets/css/chat-widget.css">
    <script src="assets/js/chat.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/task-overview.css">
    <script src="assets/js/task-overview-manager.js"></script>

    <style>
        .dashboard-container {
            display: flex;
            height: 100vh;
            overflow: hidden;
            position: relative;
            z-index: 1;
        }
        
        .left-panel {
            width: 280px;
            background: linear-gradient(180deg, #2c3e50, #34495e);
            color: #fff;
            height: 100vh;
            transition: all 0.3s ease;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }
        
        .left-panel.collapsed {
            width: 70px;
        }
        
        .left-panel.collapsed + .main-content {
            margin-left: 70px;
        }
        
        .toggle-btn {
            position: absolute;
            right: -18px;
            top: 25px;
            background: #fff;
            border: none;
            color: #2c3e50;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .toggle-btn:hover {
            transform: scale(1.15);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            background: #f8f9fa;
        }
        
        .toggle-btn i {
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .toggle-btn:hover i {
            color: #1a237e;
            transform: scale(1.2);
        }
        
        .menu-item {
            padding: 16px 25px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            margin: 5px 0;
            position: relative;
            overflow: hidden;
        }
        
        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left: 4px solid #3498db;
            padding-left: 30px;
        }
        
        .menu-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 4px solid #3498db;
        }
        
        .menu-item::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: rgba(255, 255, 255, 0.1);
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s ease;
            z-index: 0;
        }
        
        .menu-item:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }
        
        .menu-item i {
            margin-right: 15px;
            width: 20px;
            font-size: 1.2em;
            text-align: center;
            position: relative;
            z-index: 1;
            color: #3498db;
        }
        
        .menu-text {
            transition: all 0.3s ease;
            font-size: 0.95em;
            letter-spacing: 0.3px;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        
        .collapsed .menu-text {
            display: none;
        }
        
        .greeting-section {
            border-radius: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background: linear-gradient(145deg, #2c3e50, #1a1a1a);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.2);
            color: #ffffff;
        }
        
        .greeting-content {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .greeting-header h2 {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
            font-size: 1.6rem;
            font-weight: 600;
            color: #ffffff;
        }
        
        .greeting-text {
            color: #e0e0e0;
            font-weight: 500;
        }
        
        .user-name-text {
            background: linear-gradient(120deg, #ff4444, #cc0000);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.1);
            color: #00ff00;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-left: 5px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .status-badge i {
            font-size: 0.6rem;
            animation: pulse 2s infinite;
        }
        
        .datetime-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #e0e0e0;
            font-size: 0.9rem;
        }
        
        .date-display, .time-display {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .date-display i, .time-display i {
            color: #ff4444;
        }
        
        .time-divider {
            color: rgba(255, 255, 255, 0.3);
        }
        
        @keyframes pulse {
            0% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
            100% {
                opacity: 1;
            }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .greeting-section {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
            }
            
            .greeting-header h2 {
                font-size: 1.3rem;
            }
            
            .datetime-info {
                font-size: 0.8rem;
            }
        }
        
        .notification-wrapper,
        .chat-wrapper {
            position: relative;
            margin-left: 8px;
        }
        
        .notification-icon,
        .chat-icon {
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            color: #ffffff;
        }
        
        .notification-icon:hover,
        .chat-icon:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .notification-icon i,
        .chat-icon i {
            font-size: 1rem;
            color: #475569;
        }
        
        .notification-badge,
        .chat-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            padding: 2px 5px;
            font-size: 0.7rem;
            min-width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
        }
        
        .attendance-action {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .punch-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .punch-in {
            background: linear-gradient(135deg, #ff4444, #cc0000);
            color: white;
            box-shadow: 0 2px 8px rgba(255, 68, 68, 0.3);
        }
        
        .punch-in:hover {
            background: linear-gradient(135deg, #cc0000, #990000);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 68, 68, 0.4);
        }
        
        .punch-out {
            background: linear-gradient(135deg, #2c3e50, #1a1a1a);
            color: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .punch-out:hover {
            background: linear-gradient(135deg, #1a1a1a, #000000);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            height: 100vh;
            overflow-y: auto;
            background: #f8f9fa;
            padding-bottom: 30px;
            transition: margin-left 0.3s ease;
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;     /* Firefox */
        }
        
        /* Hide scrollbar for Chrome, Safari and Opera */
        .main-content::-webkit-scrollbar {
            display: none;
            width: 0;
        }

        /* Hide scrollbar for the entire body */
        body {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;     /* Firefox */
        }

        body::-webkit-scrollbar {
            display: none;
            width: 0;
        }

        /* Hide scrollbar for kanban columns */
        .kanban-column {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;     /* Firefox */
        }

        .kanban-column::-webkit-scrollbar {
            display: none;
            width: 0;
        }

        .logout-item {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 0, 0, 0.1);
        }
        
        .logout-item:hover {
            background: rgba(255, 0, 0, 0.2);
            border-left: 4px solid #ff4444 !important;
        }
        
        .logout-item i {
            color: #ff4444 !important;
        }
        
        .menu-item.section-start {
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }
        
        .attendance-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: flex-end;
        }

        .last-session {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }

        .working-hours {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            font-weight: 500;
            color: #2c3e50;
        }

        .swal2-html-container {
            margin: 1em 1.6em 0.3em;
        }

        .overtime-hours {
            margin-top: 5px;
            color: #e74c3c;
            font-weight: 500;
        }

        .last-session {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
            line-height: 1.4;
        }

        .last-session .overtime {
            color: #e74c3c;
            margin-top: 3px;
        }

        .notification-wrapper {
            margin-left: 8px;
            position: relative;
        }

        .notification-icon {
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            color: #ffffff;
        }

        .notification-icon:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .notification-icon i {
            font-size: 1rem;
            color: #475569;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            padding: 2px 5px;
            font-size: 0.7rem;
            min-width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
        }

        .user-avatar-wrapper {
            margin-right: 20px;
            position: relative;
        }

        .user-avatar {
            cursor: pointer;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .user-avatar:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .user-avatar i {
            font-size: 1.5em;
            color: #2c3e50;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 220px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            margin-top: 10px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .profile-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .profile-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95rem;
        }

        .user-role {
            color: #64748b;
            font-size: 0.8rem;
        }

        .dropdown-divider {
            height: 1px;
            background: #eee;
            margin: 5px 0;
        }

        .dropdown-item {
            padding: 12px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #475569;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: #f8fafc;
            color: #2c3e50;
        }

        .dropdown-item i {
            font-size: 0.9rem;
            width: 16px;
        }

        .dropdown-item:last-child {
            color: #ef4444;
        }

        .dropdown-item:last-child:hover {
            background: #fef2f2;
        }

        .shift-timer {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 68, 68, 0.1);
            padding: 4px 12px;
            border-radius: 4px;
            border: 1px solid rgba(255, 68, 68, 0.2);
            color: #ff4444;
        }

        .shift-timer i {
            color: #ff4444;
            animation: rotate 2s linear infinite;
        }

        #remainingTime {
            color: #ffffff;
            font-weight: bold;
            min-width: 100px;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        .chat-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .chat-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0084ff, #0055ff);
            box-shadow: 0 4px 15px rgba(0, 132, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .chat-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 132, 255, 0.4);
        }

        .chat-button i {
            color: white;
            font-size: 24px;
        }

        .chat-container {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 350px;
            height: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
            display: none;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-container.active {
            display: flex;
        }

        .chat-header {
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-tabs {
            display: flex;
            gap: 20px;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }

        .chat-tab {
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            color: #6c757d;
            transition: all 0.3s ease;
        }

        .chat-tab:hover {
            background-color: #f8f9fa;
            color: #212529;
        }

        .chat-tab.active {
            background-color: #0084ff;
            color: white;
        }

        .chat-actions {
            padding: 15px;
            display: flex;
            justify-content: flex-end;
        }

        /* Show/hide create group button based on active tab */
        .chat-tab[data-tab="groups"].active ~ .chat-actions .create-group-btn {
            display: flex !important;
        }

        /* Update container styles */
        .user-list,
        .group-list {
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            height: calc(100% - 60px);
            overflow-y: auto;
        }

        /* Smooth transitions */
        .chat-body > div {
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .chat-body {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }

        .chat-input {
            padding: 15px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }

        .chat-input input {
            flex: 1;
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 20px;
            outline: none;
        }

        .chat-input button {
            background: #0084ff;
            color: white;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .chat-input button:hover {
            background: #0055ff;
        }

        .unread-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            padding: 2px 5px;
            font-size: 0.7rem;
            min-width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
        }

        .unread-badge:empty,
        .unread-badge[style*="display: none"] {
            display: none !important;
        }

        .conversation-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .conversation-item:hover {
            background-color: #f8f9fa;
        }

        .conversation-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .conversation-avatar i {
            color: #6c757d;
            font-size: 1.2em;
        }

        .conversation-info {
            flex: 1;
        }

        .conversation-name {
            font-weight: 500;
            color: #212529;
        }

        .conversation-preview {
            font-size: 0.9em;
            color: #6c757d;
        }

        .messages-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .message {
            display: flex;
            margin: 4px 0;
        }

        .message.sent {
            justify-content: flex-end;
        }

        .message-content {
            max-width: 70%;
            padding: 8px 12px;
            border-radius: 12px;
            background: #f8f9fa;
        }

        .message.sent .message-content {
            background: #0084ff;
            color: white;
        }

        .message-sender {
            font-size: 0.8em;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .message-time {
            font-size: 0.7em;
            color: #6c757d;
            margin-top: 4px;
            text-align: right;
        }

        .message.sent .message-time {
            color: rgba(255, 255, 255, 0.8);
        }

        .user-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 10px;
        }

        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .user-item:hover {
            background-color: #f8f9fa;
        }

        .user-avatar {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .status-indicator {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6c757d;
            border: 2px solid white;
        }

        .status-indicator.online {
            background: #28a745;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 500;
            color: #212529;
        }

        .user-status {
            font-size: 0.8em;
            color: #6c757d;
        }

        .unread-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            padding: 0 6px;
        }

        .messages-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 10px;
        }

        .message {
            display: flex;
            margin: 4px 0;
        }

        .message.sent {
            justify-content: flex-end;
        }

        .message-content {
            max-width: 70%;
            padding: 8px 12px;
            border-radius: 12px;
            background: #f8f9fa;
        }

        .message.sent .message-content {
            background: #0084ff;
            color: white;
        }

        .message-text {
            word-break: break-word;
        }

        .message-time {
            font-size: 0.7em;
            color: #6c757d;
            margin-top: 4px;
        }

        .message.sent .message-time {
            color: rgba(255, 255, 255, 0.8);
        }

        .chat-header {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .back-button {
            cursor: pointer;
            padding: 8px;
            margin-right: 10px;
            border-radius: 50%;
            transition: background-color 0.3s ease;
        }

        .back-button:hover {
            background-color: #e9ecef;
        }

        .back-button i {
            font-size: 1.2em;
            color: #6c757d;
        }

        .chat-user-info {
            flex: 1;
        }

        .chat-user-name {
            font-weight: 500;
            font-size: 1.1em;
            color: #212529;
        }

        .chat-view {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .chat-view .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }

        .messages-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 10px;
            height: calc(100% - 60px); /* Adjust for header height */
            overflow-y: auto;
        }

        .user-avatar {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .unread-dot {
            position: absolute;
            top: 0;
            right: 0;
            width: 12px;
            height: 12px;
            background: #ef4444;
            border-radius: 50%;
            border: 2px solid white;
        }

        .unread-count {
            color: #ef4444;
            font-size: 0.85em;
            margin-left: 5px;
            font-weight: 500;
        }

        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .user-item:hover {
            background-color: #f8f9fa;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .user-item.has-unread {
            background-color: rgba(239, 68, 68, 0.05);
        }

        .user-item.has-unread:hover {
            background-color: rgba(239, 68, 68, 0.1);
        }

        /* Add these styles for group functionality */
        .member-selection {
            max-height: 200px;
            overflow-y: auto;
            margin-top: 15px;
        }

        .member-item {
            padding: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .member-item:hover {
            background: #f8f9fa;
        }

        .group-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 10px;
        }

        .group-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .group-item:hover {
            background-color: #f8f9fa;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .group-avatar {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .group-info {
            flex: 1;
        }

        .group-name {
            font-weight: 500;
            color: #212529;
        }

        .group-role {
            font-size: 0.8em;
            color: #6c757d;
        }

        /* Add a create group button to the chat header */
        .chat-actions {
            display: flex;
            gap: 10px;
        }

        .create-group-btn {
            padding: 8px 16px;
            background: #0084ff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }

        .create-group-btn:hover {
            background: #0055ff;
            transform: translateY(-1px);
        }

        .create-group-btn i {
            font-size: 1.1em;
        }

        /* Initially hide the button when in Chats tab */
        .chat-tab[data-tab="chats"].active ~ .chat-actions .create-group-btn {
            display: none;
        }

        /* Add these styles for group chat */
        .group-actions {
            display: flex;
            gap: 10px;
        }

        .group-members-btn {
            padding: 8px;
            cursor: pointer;
            border-radius: 50%;
            transition: background-color 0.3s ease;
        }

        .group-members-btn:hover {
            background-color: #e9ecef;
        }

        .group-members-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .group-member-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .member-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 500;
        }

        .member-role {
            font-size: 0.8em;
            color: #6c757d;
        }

        .message-sender {
            font-size: 0.8em;
            font-weight: 500;
            color: #6c757d;
            margin-bottom: 2px;
        }

        .message.sent .message-sender {
            display: none;
        }

        /* Add these styles */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            text-align: center;
        }

        .empty-state i {
            font-size: 3em;
            color: #6c757d;
            margin-bottom: 15px;
        }

        .empty-state p {
            color: #6c757d;
            margin-bottom: 20px;
        }

        .no-groups-message {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
        }

        .no-groups-message .create-group-btn {
            padding: 8px 16px;
            background: #0084ff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.3s ease;
        }

        .no-groups-message .create-group-btn:hover {
            background: #0055ff;
        }

        /* Update message box styles */
        .message-box {
            position: sticky;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 15px;
            border-top: 1px solid #dee2e6;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .message-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            outline: none;
            font-size: 0.95em;
            transition: border-color 0.3s ease;
        }

        .message-input:focus {
            border-color: #0084ff;
        }

        .send-button {
            background: #0084ff;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .send-button:hover {
            background: #0055ff;
            transform: scale(1.05);
        }

        .send-button i {
            font-size: 1.1em;
        }

        /* Update chat body to account for message box */
        .chat-body {
            height: calc(100% - 60px); /* Adjust height when message box is visible */
            overflow-y: auto;
        }

        /* Add transition for smooth show/hide */
        .message-box {
            transition: all 0.3s ease;
        }

        /* Update status indicator styles */
        .status-indicator {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid white;
        }

        .status-indicator.online {
            background: #22c55e;
            animation: pulse 2s infinite;
        }

        .status-indicator.offline {
            background: #94a3b8;
        }

        .user-status {
            font-size: 0.8em;
            color: #64748b;
        }

        .user-status.online {
            color: #22c55e;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(34, 197, 94, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
            }
        }

        /* Update user item hover state */
        .user-item:hover {
            background-color: #f8f9fa;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .user-item.has-unread {
            background-color: rgba(239, 68, 68, 0.05);
        }

        /* Add styles for group actions */
        .group-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .group-content {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            cursor: pointer;
        }

        .group-actions {
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .group-item:hover .group-actions {
            opacity: 1;
        }

        .group-action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: transparent;
            color: #64748b;
        }

        .group-action-btn:hover {
            transform: scale(1.1);
        }

        .group-action-btn.edit:hover {
            background: #0084ff;
            color: white;
        }

        .group-action-btn.delete:hover {
            background: #ef4444;
            color: white;
        }

        .group-action-btn i {
            font-size: 1em;
        }

        .file-attach-btn {
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            color: #64748b;
        }

        .file-attach-btn:hover {
            background-color: #f1f5f9;
            color: #0084ff;
        }

        .file-preview {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 8px;
            background: #f1f5f9;
            border-radius: 4px;
            margin: 4px 0;
            font-size: 0.9em;
        }

        .file-preview i {
            color: #64748b;
        }

        .remove-file {
            cursor: pointer;
            color: #ef4444;
            padding: 4px;
        }

        .remove-file:hover {
            color: #dc2626;
        }

        /* Update message-box styles */
        .message-box {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 15px;
            background: white;
            border-top: 1px solid #e2e8f0;
        }

        .message-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            outline: none;
            font-size: 0.95em;
        }

        .message-input:focus {
            border-color: #0084ff;
        }

        .message-box {
            display: flex;
            align-items: center;
            padding: 10px;
            gap: 10px;
            background: #fff;
            border-top: 1px solid #e0e0e0;
        }

        .file-attach-btn {
            cursor: pointer;
            padding: 8px;
            color: #666;
            transition: color 0.2s ease;
        }

        .file-attach-btn:hover {
            color: #333;
        }

        .message-image {
            max-width: 200px;
            border-radius: 8px;
            margin-bottom: 5px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .message-image:hover {
            transform: scale(1.05);
        }

        .file-attachment {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }

        .file-download {
            color: #007bff;
            text-decoration: none;
            font-size: 0.9em;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .file-download:hover {
            text-decoration: underline;
        }

        /* Additional styles for better file message display */
        .message .file-attachment {
            max-width: 300px;
            word-break: break-word;
        }

        .message.sent .file-attachment {
            background: #dcf8c6;
        }

        .message.received .file-attachment {
            background: #f0f0f0;
        }

        /* Loading indicator for file uploads */
        .file-uploading {
            opacity: 0.7;
            pointer-events: none;
            position: relative;
        }

        .file-uploading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid #007bff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .task-card {
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .task-description i {
            width: 20px;
            text-align: center;
        }

        /* Add these styles to your existing CSS */
        .punch-out-form textarea {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
        }

        .punch-out-form textarea:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
            outline: none;
        }

        .work-report-summary {
            margin-top: 15px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .work-report-summary h4 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
        }

        .work-report-summary p {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
            white-space: pre-wrap;
        }

        .punch-out-popup {
            max-width: 500px !important;
        }

        /* Enhanced styles for punch-out form */
        .punch-out-form {
            padding: 20px;
            background: linear-gradient(to bottom, #ffffff, #f8fafc);
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
        }

        .punch-out-form textarea {
            width: 100%;
            min-height: 120px;
            padding: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.6;
            resize: vertical;
            transition: all 0.3s ease;
            background-color: #ffffff;
            color: #334155;
            font-family: inherit;
        }

        .punch-out-form textarea:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
            outline: none;
            transform: translateY(-1px);
        }

        .punch-out-form textarea::placeholder {
            color: #94a3b8;
            font-style: italic;
        }

        /* Enhanced work report summary styles */
        .work-report-summary {
            margin-top: 20px;
            padding: 20px;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
        }

        .work-report-summary:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transform: translateY(-1px);
        }

        .work-report-summary h4 {
            color: #1e293b;
            margin-bottom: 12px;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .work-report-summary h4::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 16px;
            background: #3498db;
            border-radius: 2px;
            margin-right: 8px;
        }

        .work-report-summary p {
            color: #475569;
            font-size: 14px;
            line-height: 1.7;
            white-space: pre-wrap;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid #3498db;
        }

        /* Enhanced punch-out popup styles */
        .punch-out-popup {
            max-width: 550px !important;
            padding: 10px;
        }

        .punch-out-popup .swal2-html-container {
            margin: 1em 0;
        }

        /* Time details styling */
        .time-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 15px 0;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
        }

        .time-details p {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .time-details p.regular-hours::before {
            content: '⏱';
            font-size: 16px;
        }

        .time-details p.overtime-hours::before {
            content: '⌛';
            font-size: 16px;
            color: #e74c3c;
        }

        .time-details p.overtime-hours {
            color: #e74c3c;
            background: #fff5f5;
        }

        /* Success message styling */
        .punch-time {
            font-size: 18px;
            font-weight: 500;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background: #f0f9ff;
            border-radius: 8px;
            border: 1px dashed #3498db;
        }

        /* Animation for the form */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .punch-out-form, .work-report-summary {
            animation: slideIn 0.3s ease-out;
        }

        /* Enhanced Punch Out Form Styles */
        .punch-out-popup {
            max-width: 550px !important;
            padding: 25px !important;
            border-radius: 16px !important;
            background: linear-gradient(to bottom right, #ffffff, #f8fafc) !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
        }

        .punch-out-popup .swal2-title {
            padding: 0 0 20px 0 !important;
            position: relative;
        }

        .punch-out-popup .swal2-title::after {
            content: '';
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: #3498db;
            border-radius: 2px;
        }

        .punch-out-form {
            padding: 0;
            margin: 0;
        }

        .punch-out-form textarea {
            width: 100% !important;
            min-height: 150px !important;
            padding: 16px !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            font-size: 15px !important;
            line-height: 1.6 !important;
            color: #334155 !important;
            background-color: #ffffff !important;
            resize: none !important;
            transition: all 0.3s ease !important;
            margin: 0 !important;
            font-family: inherit !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02) !important;
        }

        .punch-out-form textarea:focus {
            border-color: #3498db !important;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15) !important;
            outline: none !important;
        }

        .punch-out-form textarea::placeholder {
            color: #94a3b8 !important;
            font-style: italic !important;
        }

        /* Enhanced Button Styles */
        .punch-out-confirm-btn {
            background: #3498db !important;
            color: white !important;
            padding: 12px 24px !important;
            font-size: 15px !important;
            font-weight: 500 !important;
            border-radius: 8px !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 2px 4px rgba(52, 152, 219, 0.2) !important;
        }

        .punch-out-confirm-btn:hover {
            background: #2980b9 !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3) !important;
        }

        .punch-out-cancel-btn {
            background: #f1f5f9 !important;
            color: #64748b !important;
            padding: 12px 24px !important;
            font-size: 15px !important;
            font-weight: 500 !important;
            border-radius: 8px !important;
            transition: all 0.3s ease !important;
        }

        .punch-out-cancel-btn:hover {
            background: #e2e8f0 !important;
            color: #475569 !important;
        }

        /* Remove Default SweetAlert2 Styles */
        .punch-out-popup .swal2-html-container {
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
        }

        .punch-out-popup .swal2-actions {
            margin: 25px 0 0 0 !important;
            gap: 12px !important;
        }

        /* Success State Styles */
        .work-report-summary {
            margin-top: 20px;
            padding: 20px;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }

        .time-details {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            margin: 15px 0;
        }

        .time-details p {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: white;
            border-radius: 8px;
            margin: 0;
            font-size: 14px;
            color: #1e293b;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

    </style>
    <!-- Add this in the head section or before closing body tag -->
    </style>
    <!-- Add this in the head section or before closing body tag -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body data-user-role="<?php echo htmlspecialchars($_SESSION['user_role'] ?? 'default'); ?>">
    <div class="dashboard-container">
        <div class="left-panel" id="leftPanel">
            <div class="brand-logo" style="padding: 20px 25px; margin-bottom: 20px;">
                <img src="" alt="Logo" style="max-width: 150px; height: auto;">
            </div>
            <button class="toggle-btn" onclick="togglePanel()">
                <i class="fas fa-chevron-left" id="toggleIcon"></i>
            </button>
            
            <!-- Main Navigation -->
            <div class="menu-item active">
                <i class="fas fa-home"></i>
                <span class="menu-text">Dashboard</span>
            </div>
            
            <!-- Personal Section -->
            <div class="menu-item" onclick="window.location.href='profile.php'">
                <i class="fas fa-user-circle"></i>
                <span class="menu-text">My Profile</span>
            </div>
            <div class="menu-item" onclick="window.location.href='leave.php'">
                <i class="fas fa-calendar-alt"></i>
                <span class="menu-text">Apply Leave</span>
            </div>
            
            <!-- Work Section -->
            <div class="menu-item">
                <i class="fas fa-tasks"></i>
                <span class="menu-text">My Tasks</span>
            </div>
            <div class="menu-item" onclick="window.location.href='work_sheet.php'">
                <i class="fas fa-file-alt"></i>
                <span class="menu-text">Work Sheet</span>
            </div>
            <div class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span class="menu-text">Performance</span>
            </div>
            <!-- Settings & Support -->
            <div class="menu-item">
                <i class="fas fa-cog"></i>
                <span class="menu-text">Settings</span>
            </div>
            <div class="menu-item">
                <i class="fas fa-question-circle"></i>
                <span class="menu-text">Help & Support</span>
            </div>
            
            <!-- Logout at the bottom -->
            <div class="menu-item logout-item" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">Logout</span>
            </div>
        </div>
        
        <div class="main-content">
            <div class="greeting-section">
                <div class="greeting-content">
                    <div class="greeting-header">
                        <h2>
                            <span class="greeting-text"><?php echo $greeting; ?>,</span>
                            <span class="user-name-text"><?php echo $user_data['username']; ?></span>
                            <?php if ($is_punched_in): ?>
                                <span class="status-badge">
                                    <i class="fas fa-circle"></i> Online
                                </span>
                            <?php endif; ?>
                            <div class="notification-wrapper">
                                <div class="notification-icon">
                                    <i class="fas fa-bell"></i>
                                    <span class="notification-badge">3</span>
                                </div>
                            </div>
                        </h2>
                    </div>
                    <div class="datetime-info">
                        <span class="date-display">
                            <i class="far fa-calendar-alt"></i>
                            <?php echo $current_date; ?>
                        </span>
                        <span class="time-divider">|</span>
                        <span class="time-display">
                            <i class="far fa-clock"></i>
                            <span id="currentTime"><?php echo $current_time; ?></span> IST
                        </span>
                        <?php if ($is_punched_in): ?>
                            <span class="time-divider">|</span>
                            <span class="shift-timer" id="shiftTimerContainer">
                                <i class="fas fa-hourglass-half"></i>
                                <span id="remainingTime" style="color: #ffffff; font-weight: bold;">Loading...</span>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="attendance-action">
                    <?php
                    // Get current date in Y-m-d format
                    $today = date('Y-m-d');
                    
                    // Check today's attendance status
                    $check_punch = $conn->prepare("SELECT punch_in, punch_out FROM attendance WHERE user_id = ? AND date = ?");
                    $check_punch->bind_param("is", $user_id, $today);
                    $check_punch->execute();
                    $result = $check_punch->get_result();
                    $attendance = $result->fetch_assoc();
                    
                    // Determine punch status
                    $is_punched_in = false;
                    $is_punched_out = false;
                    
                    if ($attendance) {
                        $is_punched_in = ($attendance['punch_in'] != null);
                        $is_punched_out = ($attendance['punch_out'] != null);
                    }
                    ?>

                    <?php if (!$is_punched_out): ?>
                        <?php if ($is_punched_in): ?>
                            <button class="punch-btn punch-out" onclick="punchOut()">
                                <i class="fas fa-sign-out-alt"></i>
                                Punch Out
                            </button>
                        <?php else: ?>
                            <button class="punch-btn punch-in" onclick="punchIn()">
                                <i class="fas fa-sign-in-alt"></i>
                                Punch In
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>


                    <div class="user-avatar-wrapper">
                        <div class="user-avatar" onclick="toggleProfileMenu()">
                            <?php if (!empty($user_data['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($user_data['profile_picture']); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <i class="fas fa-user-circle"></i>
                            <?php endif; ?>
                        </div>
                        <div class="profile-dropdown" id="profileDropdown">
                            <div class="profile-header">
                                <div class="profile-info">
                                    <span class="user-name"><?php echo $user_data['username']; ?></span>
                                    <span class="user-role"><?php echo $user_data['role']; ?></span>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i>
                                My Profile
                            </a>
                            <a href="logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>

           <!-- Task Overview Section -->
            <div class="container">
<div class="project-metrics-dashboard">
    <div class="metrics-header">
        <h2 class="metrics-title">Project Metrics</h2>
        <div class="metrics-filters">
            <div class="metrics-date-range">
                <div class="metrics-date-input">
                    <label>From:</label>
                    <input type="date" id="metricsStartDate">
                </div>
                <div class="metrics-date-input">
                    <label>To:</label>
                    <input type="date" id="metricsEndDate">
                </div>
                <button class="metrics-apply-btn">Apply Filter</button>
                <button class="metrics-reset-btn">Reset</button>
            </div>
        </div>
    </div>

    <div class="pmd-metrics-cards-wrapper">
        <!-- Active Projects Overview Card -->
        <div class="pmd-metrics-card active-projects">
            <div class="metrics-card-header">
                <h3>Active Projects Overview</h3>
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="metrics-data-grid">
                <?php
                // Database connection
                $host = 'localhost';
                $username = 'root';
                $password = '';
                $database = 'crm'; // replace with your database name

                // Create connection
                $db = new mysqli($host, $username, $password, $database);

                // Check connection
                if ($db->connect_error) {
                    die("Connection failed: " . $db->connect_error);
                }

                // Get total projects assigned to current user
                $totalProjects = $db->query("
                    SELECT COUNT(*) as count 
                    FROM projects 
                    WHERE deleted_at IS NULL 
                    AND assigned_to = '$user_id'"
                )->fetch_object()->count;

                // Get in progress projects assigned to current user
                $inProgress = $db->query("
                    SELECT COUNT(*) as count 
                    FROM projects 
                    WHERE deleted_at IS NULL 
                    AND status = 'in_progress'
                    AND assigned_to = '$user_id'"
                )->fetch_object()->count;

                // Get due projects assigned to current user
                $due = $db->query("
                    SELECT COUNT(*) as count 
                    FROM projects 
                    WHERE deleted_at IS NULL 
                    AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                    AND status != 'completed'
                    AND assigned_to = '$user_id'"
                )->fetch_object()->count;

                // Get overdue projects assigned to current user
                $overdue = $db->query("
                    SELECT COUNT(*) as count 
                    FROM projects 
                    WHERE deleted_at IS NULL 
                    AND end_date < CURDATE() 
                    AND status != 'completed'
                    AND assigned_to = '$user_id'"
                )->fetch_object()->count;
                ?>

                <div class="metric-item small">
                    <div class="metric-icon total-active">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <div class="metric-info">
                        <span class="metric-label">Total Projects</span>
                        <span class="metric-value" id="totalProjects"><?php echo $totalProjects; ?></span>
                    </div>
                </div>
                <div class="metric-item small">
                    <div class="metric-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="metric-info">
                        <span class="metric-label">In Progress</span>
                        <span class="metric-value" id="inProgressProjects"><?php echo $inProgress; ?></span>
                    </div>
                </div>
                <div class="metric-item small">
                    <div class="metric-icon due">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="metric-info">
                        <span class="metric-label">Due Soon</span>
                        <span class="metric-value" id="dueProjects"><?php echo $due; ?></span>
                    </div>
                </div>
                <div class="metric-item small">
                    <div class="metric-icon overdue">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="metric-info">
                        <span class="metric-label">Overdue</span>
                        <span class="metric-value" id="overdueProjects"><?php echo $overdue; ?></span>
                    </div>
                </div>
            </div>
            <div class="metrics-chart-container">
                <?php
                // Get data for chart
                $chartData = $db->query("
                    SELECT 
                        status,
                        COUNT(*) as count
                    FROM projects 
                    WHERE deleted_at IS NULL 
                    GROUP BY status
                ")->fetch_all(MYSQLI_ASSOC);
                ?>
                <canvas id="projectStatusChart"></canvas>
                <script>
                    // Initialize the chart
                    const ctx = document.getElementById('projectStatusChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: <?php echo json_encode(array_column($chartData, 'status')); ?>,
                            datasets: [{
                                data: <?php echo json_encode(array_column($chartData, 'count')); ?>,
                                backgroundColor: [
                                    '#4CAF50', // Active
                                    '#FFC107', // Pending
                                    '#2196F3', // Due
                                    '#DC3545'  // Overdue
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 20,
                                        font: {
                                            size: 12
                                        }
                                    }
                                }
                            }
                        }
                    });
                </script>
            </div>
        </div>

        <!-- Upcoming Project Stages Card -->
<div class="pmd-metrics-card upcoming-stages">
    <div class="metrics-card-header">
        <h3>Upcoming Project Stages</h3>
        <i class="fas fa-ellipsis-v"></i>
    </div>
    <div class="pmd-upcoming-stages-list" id="upcomingStagesList">
        <?php
        // Get upcoming stages assigned to current user
        $upcomingStages = $db->query("
            SELECT 
                ps.*, 
                p.title as project_title
            FROM project_stages ps
            JOIN projects p ON p.id = ps.project_id
            WHERE ps.deleted_at IS NULL 
            AND ps.assigned_to = '$user_id'
            AND ps.status IN ('in_progress', 'not_started')
            ORDER BY ps.end_date ASC
            LIMIT 5"
        );

        while ($stage = $upcomingStages->fetch_object()) {
            ?>
            <div class="pmd-stage-item">
                <div class="pmd-stage-markers">
                    <div class="pmd-marker-yellow"></div>
                </div>
                <div class="stage-content">
                    <div class="stage-main">
                        <h4><?php echo htmlspecialchars($stage->project_title); ?></h4>
                        <div class="stage-info">
                            <div class="due-date">
                                <i class="far fa-calendar-alt"></i>
                                <span><?php echo date('M d, Y', strtotime($stage->end_date)); ?></span>
                            </div>
                            <span class="status-badge <?php echo $stage->status; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $stage->status)); ?>
                            </span>
                        </div>
                        <div class="stage-phase">Stage <?php echo $stage->stage_number; ?></div>
                    </div>
                </div>
            </div>
            <?php
        }
        
        if ($upcomingStages->num_rows == 0) {
            echo '<div class="no-stages">No upcoming stages found</div>';
        }
        ?>
    </div>
</div>
        <div class="pmd-milestone-container">
    <!-- Header Section -->
    <div class="pmd-milestone-header">
        <div class="pmd-header-left">
            <h2 class="pmd-milestone-title">Project Substages</h2>
            <span class="pmd-milestone-count">
                <?php 
                // Get count of pending substages for current user only
                $pending_count_query = "SELECT COUNT(*) as count 
                    FROM project_substages 
                    WHERE (status = 'pending' OR status = 'not_started') 
                    AND deleted_at IS NULL 
                    AND assigned_to = '$user_id'";  // Add filter for current user
                $pending_result = $db->query($pending_count_query);
                $count_row = $pending_result->fetch_assoc();
                echo $count_row['count'] . ' pending';
                ?>
            </span>
        </div>
    </div>

    <!-- Substage Items -->
    <div class="pmd-milestone-list">
        <?php
        // Get current user ID (assuming you have it in a session or similar)
        $current_user_id = $_SESSION['user_id']; // Adjust this according to how you store user sessions

        // Updated query to show only substages assigned to current user
        $substages_query = "
            SELECT ps.*, u.username as assignee_name 
            FROM project_substages ps
            LEFT JOIN users u ON ps.assigned_to = u.id 
            WHERE ps.status IN ('pending', 'not_started') 
            AND ps.deleted_at IS NULL 
            AND ps.assigned_to = $current_user_id  /* Added this line to filter by user */
            ORDER BY ps.end_date ASC
        ";

        // Update the count query as well
        $pending_count_query = "
            SELECT COUNT(*) as count 
            FROM project_substages 
            WHERE (status = 'pending' OR status = 'not_started') 
            AND deleted_at IS NULL 
            AND assigned_to = $current_user_id  /* Added this line to filter by user */
        ";

        $pending_result = $db->query($pending_count_query);
        $substages_result = $db->query($substages_query);

        while ($substage = $substages_result->fetch_assoc()):
            $progress = 0;
            if ($substage['status'] === 'pending') {
                $progress = 30; // You can adjust this or fetch actual progress
            }
        ?>
        <div class="pmd-milestone-item">
            <div class="pmd-milestone-main">
                <div class="pmd-milestone-info">
                    <h3 class="pmd-milestone-item-title">
                        <?php echo htmlspecialchars($substage['title']); ?>
                    </h3>
                    <p class="pmd-milestone-item-subtitle">
                        <?php echo htmlspecialchars($substage['substage_identifier']); ?> - 
                        Assigned to: <?php echo htmlspecialchars($substage['assignee_name']); ?>
                    </p>
                </div>
                
                <div class="pmd-milestone-status-group">
                    <div class="pmd-due-date">
                        <i class="far fa-calendar-alt"></i>
                        <span><?php echo date('M d, Y', strtotime($substage['end_date'])); ?></span>
                    </div>
                    <span class="pmd-status-badge <?php echo $substage['status']; ?>">
                        <?php echo ucfirst($substage['status']); ?>
                    </span>
                </div>
            </div>
            
            <div class="pmd-progress-wrapper">
                <div class="pmd-progress-bar">
                    <div class="pmd-progress-fill <?php echo $progress === 0 ? 'empty' : ''; ?>" 
                         style="width: <?php echo $progress; ?>%">
                    </div>
                </div>
                <span class="pmd-progress-value"><?php echo $progress; ?>%</span>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>
        
    </div>
</div>
</div>
            

            <div class="kanban-board">
                <div class="kanban-header">
                    <div class="kanban-title">Daily Tasks</div>
                    <div class="board-actions">
                        <!-- Year Filter -->
                        <div class="year-filter" id="yearFilter">
                            <i class="fas fa-calendar-alt"></i>
                            <span><?php echo date('Y'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <!-- Month Filter -->
                        <div class="month-filter" id="monthFilter">
                            <i class="fas fa-filter"></i>
                            <span><?php echo date('F'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <!-- Year Dropdown -->
                        <div class="year-dropdown" id="yearDropdown">
                            <?php
                            $current_year = intval(date('Y'));
                            // Show 5 years back and 2 years forward
                            for ($year = $current_year - 5; $year <= $current_year + 2; $year++) {
                                $selected = ($year == $current_year) ? ' selected' : '';
                                echo "<div class='year-option{$selected}' data-year='{$year}'>{$year}</div>";
                            }
                            ?>
                        </div>
                        <!-- Month Dropdown -->
                        <div class="month-dropdown" id="monthDropdown">
                            <div class="month-option" data-month="all">All Months</div>
                            <?php
                            $current_month = intval(date('m')) - 1;
                            $months = array(
                                "0" => "January", "1" => "February", "2" => "March",
                                "3" => "April", "4" => "May", "5" => "June",
                                "6" => "July", "7" => "August", "8" => "September",
                                "9" => "October", "10" => "November", "11" => "December"
                            );
                            
                            foreach ($months as $num => $name) {
                                $selected = ($num == $current_month) ? ' selected' : '';
                                echo "<div class='month-option{$selected}' data-month='{$num}'>{$name}</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="kanban-columns">
                    <!-- To Do Column -->
                    <div class="kanban-column">
                        <div class="column-header">
                            <span class="column-dot dot-todo"></span>
                            <h3 class="column-title">To Do List</h3>
                        </div>
                        
                        <div class="kanban-cards-container">
                            <?php
                            // Query to get pending and not started projects for the current user
                            $todo_query = "SELECT p.*, 
                                          COUNT(DISTINCT ps.id) as total_stages,
                                          COUNT(DISTINCT pss.id) as total_substages,
                                          u.username as creator_name
                                   FROM projects p
                                   LEFT JOIN project_stages ps ON p.id = ps.project_id
                                   LEFT JOIN project_substages pss ON ps.id = pss.stage_id
                                   LEFT JOIN users u ON p.created_by = u.id
                                   WHERE p.assigned_to = ? 
                                   AND p.status IN ('pending', 'not_started')
                                   AND p.deleted_at IS NULL
                                   GROUP BY p.id
                                   ORDER BY p.created_at DESC";
                            
                            $stmt = $conn->prepare($todo_query);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $todo_result = $stmt->get_result();

                            if ($todo_result->num_rows > 0) {
                                $counter = 0;
                                while ($project = $todo_result->fetch_assoc()) {
                                    $counter++;
                                    // Format the due date
                                    $due_date = date('M d', strtotime($project['end_date']));
                                    ?>
                                    
                                    <div class="kanban-card project-card" 
                                         data-project-id="<?php echo $project['id']; ?>"
                                         data-project-type="<?php echo strtolower($project['project_type']); ?>">
                                        <div class="card-tags">
                                            <div class="tag-container">
                                                <span class="card-tag tag-<?php echo strtolower($project['project_type']); ?>">
                                                    <?php echo htmlspecialchars($project['project_type']); ?>
                                                </span>
                                            </div>
                                            <span class="meta-status <?php echo $project['status']; ?>">
                                                <?php echo ucfirst($project['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <h4 class="task-title"><?php echo htmlspecialchars($project['title']); ?></h4>
                                        <p class="task-description">
                                            <?php 
                                            echo htmlspecialchars(substr($project['description'], 0, 100)) . 
                                                 (strlen($project['description']) > 100 ? '...' : ''); 
                                            ?>
                                        </p>
                                        <div class="project-stats">
                                            <span class="stat-item">
                                                <i class="fas fa-layer-group"></i>
                                                <?php echo $project['total_stages']; ?> Stages
                                            </span>
                                            <span class="stat-item">
                                                <i class="fas fa-tasks"></i>
                                                <?php echo $project['total_substages']; ?> Substages
                                            </span>
                                        </div>
                                        <div class="card-meta">
                                            <span class="meta-date">
                                                <i class="far fa-calendar"></i>
                                                Due: <?php echo $due_date; ?>
                                            </span>
                                            <span class="meta-assigned">
                                                <i class="far fa-user"></i>
                                                By: <?php echo htmlspecialchars($project['creator_name']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php
                                }
                            } else {
                                ?>
                                <div class="empty-state">
                                    <i class="fas fa-clipboard-list"></i>
                                    <p>No pending projects found</p>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>

                    <!-- In Progress Column -->
                    <div class="kanban-column">
                        <div class="column-header">
                            <span class="column-dot dot-progress"></span>
                            <h3 class="column-title">In Progress</h3>
                        </div>
                        
                        <div class="kanban-cards-container">
                            <?php
                            // Query to get projects with in-progress stages or substages
                            $progress_query = "SELECT DISTINCT 
                                                p.*,
                                                COUNT(DISTINCT ps.id) as total_stages,
                                                COUNT(DISTINCT pss.id) as total_substages,
                                                COUNT(DISTINCT CASE WHEN ps.status = 'in_progress' THEN ps.id END) as in_progress_stages,
                                                COUNT(DISTINCT CASE WHEN pss.status = 'in_progress' THEN pss.id END) as in_progress_substages,
                                                u.username as creator_name
                                            FROM projects p
                                            LEFT JOIN project_stages ps ON p.id = ps.project_id
                                            LEFT JOIN project_substages pss ON ps.id = pss.stage_id
                                            LEFT JOIN users u ON p.created_by = u.id
                                            WHERE p.assigned_to = ? 
                                            AND p.deleted_at IS NULL
                                            AND (ps.status = 'in_progress' OR pss.status = 'in_progress')
                                            GROUP BY p.id
                                            ORDER BY p.updated_at DESC";
                            
                            $stmt = $conn->prepare($progress_query);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $progress_result = $stmt->get_result();

                            if ($progress_result->num_rows > 0) {
                                while ($project = $progress_result->fetch_assoc()) {
                                    // Calculate progress percentage
                                    $total_items = $project['total_stages'] + $project['total_substages'];
                                    $in_progress_items = $project['in_progress_stages'] + $project['in_progress_substages'];
                                    $progress_percentage = $total_items > 0 ? 
                                        round(($in_progress_items / $total_items) * 100) : 0;
                                    
                                    // Format the due date
                                    $due_date = date('M d', strtotime($project['end_date']));
                                    ?>
                                    
                                    <div class="kanban-card project-card" 
                                         data-project-id="<?php echo $project['id']; ?>"
                                         data-project-type="<?php echo strtolower($project['project_type']); ?>">
                                        <div class="card-tags">
                                            <div class="tag-container">
                                                <span class="card-tag tag-<?php echo strtolower($project['project_type']); ?>">
                                                    <?php echo htmlspecialchars($project['project_type']); ?>
                                                </span>
                                            </div>
                                            <span class="meta-status in_progress">In Progress</span>
                                        </div>
                                        
                                        <h4 class="task-title"><?php echo htmlspecialchars($project['title']); ?></h4>
                                        <p class="task-description">
                                            <?php 
                                            echo htmlspecialchars(substr($project['description'], 0, 100)) . 
                                                 (strlen($project['description']) > 100 ? '...' : ''); 
                                            ?>
                                        </p>
                                        
                                        <div class="task-progress">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
                                            </div>
                                            <span class="progress-text"><?php echo $progress_percentage; ?>%</span>
                                        </div>
                                        
                                        <div class="project-stats">
                                            <span class="stat-item">
                                                <i class="fas fa-layer-group"></i>
                                                <?php echo $project['in_progress_stages']; ?>/<?php echo $project['total_stages']; ?> Stages
                                            </span>
                                            <span class="stat-item">
                                                <i class="fas fa-tasks"></i>
                                                <?php echo $project['in_progress_substages']; ?>/<?php echo $project['total_substages']; ?> Substages
                                            </span>
                                        </div>
                                        
                                        <div class="card-meta">
                                            <span class="meta-date">
                                                <i class="far fa-calendar"></i>
                                                Due: <?php echo $due_date; ?>
                                            </span>
                                            <span class="meta-assigned">
                                                <i class="far fa-user"></i>
                                                By: <?php echo htmlspecialchars($project['creator_name']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php
                                }
                            } else {
                                ?>
                                <div class="empty-state">
                                    <i class="fas fa-tasks"></i>
                                    <p>No projects in progress</p>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>

                    <!-- In Review Column -->
                    <div class="kanban-column">
                        <div class="column-header">
                            <span class="column-dot dot-review"></span>
                            <h3 class="column-title">In Review</h3>
                        </div>
                        
                        <div class="kanban-cards-container">
                            <?php
                            // Query to get substages in review status
                            $review_query = "SELECT 
                                                p.id as project_id,
                                                p.title as project_title,
                                                p.project_type,
                                                ps.id as stage_id,
                                                ps.stage_number,
                                                pss.id as substage_id,
                                                pss.title as substage_title,
                                                pss.substage_number,
                                                pss.end_date,
                                                u.username as reviewer_name
                                            FROM project_substages pss
                                            JOIN project_stages ps ON pss.stage_id = ps.id
                                            JOIN projects p ON ps.project_id = p.id
                                            LEFT JOIN users u ON pss.assigned_to = u.id
                                            WHERE pss.status = 'in_review'
                                            AND pss.assigned_to = ?
                                            AND p.deleted_at IS NULL
                                            AND ps.deleted_at IS NULL
                                            AND pss.deleted_at IS NULL
                                            ORDER BY pss.updated_at DESC";
                            
                            $stmt = $conn->prepare($review_query);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $review_result = $stmt->get_result();

                            if ($review_result->num_rows > 0) {
                                while ($substage = $review_result->fetch_assoc()) {
                                    // Format the due date
                                    $due_date = date('M d', strtotime($substage['end_date']));
                                    ?>
                                    
                                    <div class="kanban-card project-card" 
                                         data-project-id="<?php echo $substage['project_id']; ?>"
                                         data-substage-id="<?php echo $substage['substage_id']; ?>">
                                        <div class="card-tags">
                                            <div class="tag-container">
                                                <span class="card-tag tag-<?php echo strtolower($substage['project_type']); ?>">
                                                    <?php echo htmlspecialchars($substage['project_type']); ?>
                                                </span>
                                            </div>
                                            <span class="meta-status in_review">In Review</span>
                                        </div>
                                        
                                        <h4 class="task-title">
                                            <?php echo htmlspecialchars($substage['project_title']); ?>
                                        </h4>
                                        <p class="task-description">
                                            Stage <?php echo $substage['stage_number']; ?> > 
                                            Substage <?php echo $substage['substage_number']; ?>: 
                                            <?php echo htmlspecialchars($substage['substage_title']); ?>
                                        </p>
                                        
                                        <div class="review-info">
                                            <span class="review-status">
                                                <i class="fas fa-clock"></i>
                                                Awaiting Review
                                            </span>
                                        </div>
                                        
                                        <div class="card-meta">
                                            <span class="meta-date">
                                                <i class="far fa-calendar"></i>
                                                Due: <?php echo $due_date; ?>
                                            </span>
                                            <span class="meta-assigned">
                                                <i class="far fa-user"></i>
                                                Reviewer: <?php echo htmlspecialchars($substage['reviewer_name']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php
                                }
                            } else {
                                ?>
                                <div class="empty-state">
                                    <i class="fas fa-clipboard-check"></i>
                                    <p>No substages in review</p>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Done Column -->
                    <div class="kanban-column">
                        <div class="column-header">
                            <span class="column-dot dot-done"></span>
                            <h3 class="column-title">Done</h3>
                        </div>

                        <div class="kanban-cards-container">
                            <?php
                            // Query to get completed projects where all stages and substages are completed
                            $done_query = "SELECT 
                                            p.*,
                                            COUNT(DISTINCT ps.id) as total_stages,
                                            COUNT(DISTINCT pss.id) as total_substages,
                                            COUNT(DISTINCT CASE WHEN ps.status = 'completed' THEN ps.id END) as completed_stages,
                                            COUNT(DISTINCT CASE WHEN pss.status = 'completed' THEN pss.id END) as completed_substages,
                                            u.username as creator_name,
                                            MAX(GREATEST(ps.updated_at, pss.updated_at)) as last_completed_at
                                        FROM projects p
                                        LEFT JOIN project_stages ps ON p.id = ps.project_id AND ps.deleted_at IS NULL
                                        LEFT JOIN project_substages pss ON ps.id = pss.stage_id AND pss.deleted_at IS NULL
                                        LEFT JOIN users u ON p.created_by = u.id
                                        WHERE p.assigned_to = ?
                                        AND p.deleted_at IS NULL
                                        GROUP BY p.id
                                        HAVING 
                                            (total_stages = completed_stages AND total_stages > 0)
                                            AND (total_substages = completed_substages AND total_substages > 0)
                                        ORDER BY last_completed_at DESC
                                        LIMIT 10"; // Limiting to most recent 10 completed projects
                            
                            $stmt = $conn->prepare($done_query);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $done_result = $stmt->get_result();

                            if ($done_result->num_rows > 0) {
                                while ($project = $done_result->fetch_assoc()) {
                                    // Format the completion date
                                    $completion_date = date('M d', strtotime($project['last_completed_at']));
                                    ?>
                                    
                                    <div class="kanban-card project-card" 
                                         data-project-id="<?php echo $project['id']; ?>"
                                         data-project-type="<?php echo strtolower($project['project_type']); ?>">
                                        <div class="card-tags">
                                            <div class="tag-container">
                                                <span class="card-tag tag-<?php echo strtolower($project['project_type']); ?>">
                                                    <?php echo htmlspecialchars($project['project_type']); ?>
                                                </span>
                                            </div>
                                            <span class="meta-status completed">Completed</span>
                                        </div>
                                        
                                        <h4 class="task-title"><?php echo htmlspecialchars($project['title']); ?></h4>
                                        <p class="task-description">
                                            <?php 
                                            echo htmlspecialchars(substr($project['description'], 0, 100)) . 
                                                 (strlen($project['description']) > 100 ? '...' : ''); 
                                            ?>
                                        </p>
                                        
                                        <div class="completion-info">
                                            <div class="completion-stats">
                                                <span class="stat-item">
                                                    <i class="fas fa-layer-group"></i>
                                                    <?php echo $project['total_stages']; ?> Stages
                                                </span>
                                                <span class="stat-item">
                                                    <i class="fas fa-tasks"></i>
                                                    <?php echo $project['total_substages']; ?> Substages
                                                </span>
                                            </div>
                                            <div class="completion-indicator">
                                                <i class="fas fa-check-circle"></i>
                                                100% Complete
                                            </div>
                                        </div>
                                        
                                        <div class="card-meta">
                                            <span class="meta-date">
                                                <i class="far fa-calendar-check"></i>
                                                Completed: <?php echo $completion_date; ?>
                                            </span>
                                            <span class="meta-assigned">
                                                <i class="far fa-user"></i>
                                                By: <?php echo htmlspecialchars($project['creator_name']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php
                                }
                            } else {
                                ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle"></i>
                                    <p>No completed projects yet</p>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                    
                </div>
                
            </div>
            <!-- Add Forwarded Tasks Section here, after task-overview-section -->
            <div class="forwarded-tasks-section">
                <div class="section-header">
                    <h2><i class="fas fa-share-square"></i> Forwarded Tasks</h2>
                    <div class="header-actions">
                        <button class="refresh-btn" onclick="taskManager.fetchForwardedTasks()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="forwarded-tasks-container">
                    <!-- Tasks will be loaded here dynamically -->
                    <div class="loading-spinner">
                        <i class="fas fa-circle-notch fa-spin"></i>
                    </div>
                </div>
            </div>

            <div class="chat-widget">
                <div class="chat-container" id="chatContainer">
                    <!-- Left Panel - Chat List -->
                    <div class="chat-sidebar">
                        <!-- Header with user profile -->
                        <div class="sidebar-header">
                            <div class="user-profile">
                                <div class="user-avatar">
                                    <?php if (!empty($user_data['profile_picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($user_data['profile_picture']); ?>" alt="Profile">
                                    <?php else: ?>
                                        <i class="fas fa-user-circle"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="header-actions">
                                <button class="action-btn" title="Status">
                                    <i class="fas fa-circle-notch"></i>
                                </button>
                                <button class="action-btn" title="New Chat" onclick="startNewChat()">
                                    <i class="fas fa-message"></i>
                                </button>
                                <button class="action-btn" title="Menu">
                                    <i class="fas fa-ellipsis-vertical"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Search Bar -->
                        <div class="search-container">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" placeholder="Search or start new chat" id="chatSearch">
                            </div>
                        </div>

                        <!-- Chat List -->
                        <div class="chat-list" id="chatList">
                            <!-- Chat items will be dynamically added here -->
                        </div>
                    </div>

                    <!-- Right Panel - Chat Area -->
                    <div class="chat-area" id="chatArea">
                        <!-- Default welcome screen -->
                        <div class="welcome-screen" id="welcomeScreen">
                            <div class="welcome-content">
                                <div class="welcome-image">
                                    <img src="assets/images/chat-welcome.png" alt="Welcome">
                                </div>
                                <h1>Keep your phone connected</h1>
                                <p>Send and receive messages without keeping your phone online.</p>
                            </div>
                        </div>

                        <!-- Active chat screen (hidden by default) -->
                        <div class="active-chat" id="activeChat" style="display: none;">
                            <!-- Chat Header -->
                            <div class="chat-header">
                                <div class="chat-contact-info">
                                    <div class="contact-avatar">
                                        <img src="" alt="" id="activeChatAvatar">
                                    </div>
                                    <div class="contact-details">
                                        <h3 id="activeChatName"></h3>
                                        <span class="online-status" id="activeChatStatus"></span>
                                    </div>
                                </div>
                                <div class="chat-actions">
                                    <button class="action-btn" title="Search">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <button class="action-btn" title="Menu">
                                        <i class="fas fa-ellipsis-vertical"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Chat Messages -->
                            <div class="chat-messages" id="chatMessages">
                                <!-- Messages will be dynamically added here -->
                            </div>

                            <!-- Message Input -->
                            <div class="message-input-container">
                                <button class="action-btn" title="Emoji">
                                    <i class="far fa-smile"></i>
                                </button>
                                <button class="action-btn" title="Attach">
                                    <i class="fas fa-paperclip"></i>
                                </button>
                                <div class="message-input-wrapper">
                                    <input type="text" id="messageInput" placeholder="Type a message">
                                </div>
                                <button class="action-btn voice-btn" title="Voice Message">
                                    <i class="fas fa-microphone"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chat Toggle Button -->
                <div class="chat-button" onclick="toggleChat()">
                    <i class="fas fa-comments"></i>
                    <span class="unread-badge" style="display: none;">0</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePanel() {
            const panel = document.getElementById('leftPanel');
            const icon = document.getElementById('toggleIcon');
            panel.classList.toggle('collapsed');
            icon.classList.toggle('fa-chevron-left');
            icon.classList.toggle('fa-chevron-right');
        }
        
        // Add this to your existing JavaScript
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                // Don't add active class to logout item
                if (!this.classList.contains('logout-item')) {
                    document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });

        function toggleProfileMenu() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('profileDropdown');
            const avatar = document.querySelector('.user-avatar');
            
            if (!avatar.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        function punchIn() {
            Swal.fire({
                title: 'Punching In...',
                text: 'Please wait while we process your request',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('punch.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'punch_in'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        html: `${data.message}<br><br>${data.shift_time}`,
                        showConfirmButton: true,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while processing your request.'
                });
            });
        }

        function punchOut() {
            Swal.fire({
                title: '<span style="color: #1e293b; font-size: 24px; font-weight: 600;">Daily Work Report</span>',
                html: `
                    <div class="punch-out-form">
                        <textarea id="workReport" 
                            class="swal2-textarea" 
                            placeholder="Please provide details about your work today..."
                            rows="6"
                        ></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Submit & Punch Out',
                cancelButtonText: 'Cancel',
                customClass: {
                    popup: 'punch-out-popup',
                    confirmButton: 'punch-out-confirm-btn',
                    cancelButton: 'punch-out-cancel-btn'
                },
                preConfirm: () => {
                    const workReport = document.getElementById('workReport').value.trim();
                    if (!workReport) {
                        Swal.showValidationMessage('Please enter your work report');
                        return false;
                    }
                    return workReport;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Punching Out...',
                        text: 'Please wait while we process your request',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Send punch out request with work report
                    fetch('punch.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'punch_out',
                            work_report: result.value
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            let messageHtml = `
                                <div class="punch-out-summary">
                                    <p class="punch-time">${data.message}</p>
                                    <div class="time-details">
                                        ${data.working_hours.split('\n').map(line => 
                                            `<p class="${line.toLowerCase().includes('overtime') ? 'overtime-hours' : 'regular-hours'}">${line}</p>`
                                        ).join('')}
                                    </div>
                                    <div class="work-report-summary">
                                        <h4>Work Report:</h4>
                                        <p>${result.value}</p>
                                    </div>
                                </div>
                            `;

                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                html: messageHtml,
                                showConfirmButton: true,
                                confirmButtonText: 'OK',
                                customClass: {
                                    popup: 'punch-out-popup'
                                }
                            }).then(() => {
                                window.location.reload();
                            });
                        } else if (data.auto_punch_out) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Auto Punch Out Detected',
                                html: `
                                    <div class="auto-punch-summary">
                                        <p>${data.message}</p>
                                        <p>Punch out time: ${data.punch_out_time}</p>
                                        <div class="warning-note">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <span>Please remember to punch out before leaving.</span>
                                        </div>
                                    </div>
                                `,
                                confirmButtonText: 'Understood'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message
                            });
                        }
                    });
                }
            });
        }

        // Add this to your existing JavaScript
        <?php if ($is_punched_in): ?>
            let remainingSeconds = <?php echo $remaining_seconds; ?>;
            let isOvertime = <?php echo $is_overtime ? 'true' : 'false'; ?>;
            let overtimeSeconds = <?php echo $overtime_seconds; ?>;
            
            function updateTimer() {
                const timerContainer = document.getElementById('shiftTimerContainer');
                const timerDisplay = document.getElementById('remainingTime');
                
                if (isOvertime) {
                    // Update overtime display
                    overtimeSeconds++;
                    const hours = Math.floor(overtimeSeconds / 3600);
                    const minutes = Math.floor((overtimeSeconds % 3600) / 60);
                    const seconds = overtimeSeconds % 60;
                    
                    timerContainer.style.background = 'rgba(239, 68, 68, 0.2)';
                    timerContainer.style.borderColor = 'rgba(239, 68, 68, 0.3)';
                    timerDisplay.innerHTML = 
                        `Overtime: ${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                } else {
                    // Update remaining time display
                    if (remainingSeconds <= 0) {
                        // Switch to overtime mode
                        isOvertime = true;
                        overtimeSeconds = 0;
                        return updateTimer();
                    }
                    
                    const hours = Math.floor(remainingSeconds / 3600);
                    const minutes = Math.floor((remainingSeconds % 3600) / 60);
                    const seconds = remainingSeconds % 60;
                    
                    timerDisplay.innerHTML = 
                        `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')} remaining`;
                    
                    remainingSeconds--;
                }
            }
            
            // Update timer immediately and then every second
            updateTimer();
            setInterval(updateTimer, 1000);
        <?php endif; ?>
        
        function toggleChat() {
            const chatContainer = document.getElementById('chatContainer');
            chatContainer.classList.toggle('active');
        }

        function createNewChat() {
            Swal.fire({
                title: 'Start New Chat',
                html: `
                    <input type="text" id="searchUser" class="swal2-input" placeholder="Search for users...">
                    <div id="userList" style="margin-top: 10px;"></div>
                `,
                showCancelButton: true,
                showConfirmButton: false,
                didOpen: () => {
                    const searchInput = document.getElementById('searchUser');
                    searchInput.addEventListener('input', debounce(searchUsers, 300));
                }
            });
        }

        function createNewGroup() {
            Swal.fire({
                title: 'Create New Group',
                html: `
                    <input type="text" id="groupName" class="swal2-input" placeholder="Group name">
                    <input type="text" id="searchMembers" class="swal2-input" placeholder="Search for members...">
                    <div id="selectedMembers" style="margin-top: 10px;"></div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Create Group',
                preConfirm: () => {
                    const groupName = document.getElementById('groupName').value;
                    // Add validation and group creation logic here
                }
            });
        }

        function sendMessage() {
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (message) {
                // Add your message sending logic here
                // This should integrate with your backend
                console.log('Sending message:', message);
                messageInput.value = '';
            }
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function searchUsers(query) {
            // Add your user search logic here
            // This should integrate with your backend
            console.log('Searching users:', query);
        }
        
    </script>
    
    <script src="assets/js/simple-chat.js"></script>
   
    <!-- Update the initialization script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            try {
                window.taskManager = new TaskOverviewManager();
                console.log('TaskOverviewManager initialized successfully');
            } catch (error) {
                console.error('Error initializing TaskOverviewManager:', error);
            }
        });
    </script>
    
    
    <script>
    function updateCurrentTime() {
        const timeElement = document.getElementById('currentTime');
        const now = new Date();
        
        // Format time as HH:MM:SS AM/PM
        let hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        
        // Convert to 12-hour format
        hours = hours % 12;
        hours = hours ? hours : 12; // If hours is 0, make it 12
        hours = hours.toString().padStart(2, '0');
        
        timeElement.textContent = `${hours}:${minutes}:${seconds} ${ampm}`;
    }

    // Update time immediately and then every second
    updateCurrentTime();
    setInterval(updateCurrentTime, 1000);
    </script>
    
   
    <script>
    let taskManager;
    document.addEventListener('DOMContentLoaded', () => {
        taskManager = new TaskOverviewManager();
        // Make it globally available
        window.taskManager = taskManager;
    });
</script>   
</body>
</html>