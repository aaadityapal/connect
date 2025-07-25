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

// Get current shift details from user_shifts table considering effective dates
$shift_query = "SELECT s.id, s.start_time, s.end_time 
                FROM user_shifts us
                JOIN shifts s ON us.shift_id = s.id
                WHERE us.user_id = ? 
                AND (us.effective_to IS NULL OR us.effective_to >= CURDATE()) 
                AND us.effective_from <= CURDATE()
                ORDER BY us.effective_from DESC 
                LIMIT 1";
$shift_stmt = $conn->prepare($shift_query);
$shift_stmt->bind_param("i", $user_id);
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

// Make sure all PHP code is before any HTML output
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/project-metrics-dashboard.css">
    <link rel="stylesheet" href="assets/css/project-overview.css">
    <link rel="stylesheet" href="assets/css/stage-detail-modal.css">
    <link rel="stylesheet" href="assets/css/project-brief-modal.css">
    <link rel="stylesheet" href="location_styles.css">
    <script src="assets/js/project-brief-modal.js"></script>
    <script src="assets/js/stage-chat.js"></script>
    <script src="assets/js/project-metrics-dashboard.js"></script>
    <script src="assets/js/include-user-id.js"></script>
    <script src="assets/js/project-overview.js"></script>
    <script src="assets/js/stage-detail-modal.js"></script>
    <link rel="stylesheet" href="assets/css/chat.css">
    <script src="assets/js/chat.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/substage-details.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/task-overview.css">
    <script src="assets/js/task-overview-manager.js"></script>
    <link rel="stylesheet" href="assets/css/notification-system.css">
    <script src="assets/js/notification-handler.js" defer></script>

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
        
        /* Add camera styles for punch-in */
        .camera-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 15px 0;
        }

        #camera-stream {
            width: 100%;
            max-width: 400px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            background-color: #f8f9fa;
            margin-bottom: 15px;
        }

        .selfie-controls {
            display: flex;
            justify-content: center;
            margin-top: 15px;
        }

        #capture-btn {
            background: linear-gradient(135deg, #ff4444, #cc0000);
            color: white;
            border: none;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(255, 68, 68, 0.3);
        }

        #capture-btn:hover {
            background: linear-gradient(135deg, #cc0000, #990000);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 68, 68, 0.4);
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
            opacity: 10;
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

        /* Claim Overtime Button Styles */
        .overtime-claim-section {
            margin-top: 15px;
            display: flex;
            justify-content: center;
        }
        
        .claim-overtime-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 6px rgba(255, 152, 0, 0.3);
        }
        
        .claim-overtime-btn:hover {
            background: linear-gradient(135deg, #f57c00, #e65100);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 152, 0, 0.4);
        }
        
        .claim-overtime-btn.disabled {
            background: linear-gradient(135deg, #ccc, #999);
            color: #f1f1f1;
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .claim-overtime-btn.disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .overtime-note {
            margin-top: 8px;
            font-size: 0.8rem;
            color: #666;
            text-align: center;
            font-style: italic;
        }
        
        /* Overtime claim form styles with unique class names */
        .ot-claim-container {
            padding: 20px 5px 10px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            border-top: 3px solid #4299e1;
            border-radius: 8px;
            background: linear-gradient(to bottom, #f8fafc, #ffffff);
        }
        
        .overtime-claim-popup {
            max-width: 480px !important;
            width: 100%;
            border-radius: 12px !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
        }
        
        .ot-modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d3748;
            position: relative;
            display: inline-block;
        }
        
        .ot-modal-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: #4299e1;
            border-radius: 2px;
        }
        
        .ot-form-section {
            margin-bottom: 22px;
            position: relative;
            padding: 0 15px;
        }
        
        .ot-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
            color: #2d3748;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
        }
        
        .ot-label::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 14px;
            background: #4299e1;
            margin-right: 8px;
            border-radius: 2px;
        }
        
        .ot-date-field {
            font-size: 1rem;
            color: #1a202c;
            padding: 12px 16px;
            background-color: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            font-weight: 500;
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .ot-date-field::before {
            content: '\f133';
            font-family: 'Font Awesome 5 Free';
            margin-right: 8px;
            color: #4299e1;
            font-weight: 400;
        }
        
        .ot-duration-display {
            font-size: 1rem;
            color: #1a202c;
            padding: 12px 16px;
            background-color: #ebf8ff;
            border-radius: 8px;
            border: 1px solid #bee3f8;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .ot-duration-display::before {
            content: '\f017';
            font-family: 'Font Awesome 5 Free';
            margin-right: 8px;
            color: #4299e1;
            font-weight: 400;
        }
        
        .ot-duration-display strong {
            color: #2b6cb0;
            font-weight: 600;
            font-size: 1.05rem;
            margin-left: 4px;
        }
        
        /* Checkbox styling with unique classes */
        .ot-checkbox-container {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 18px 0;
            padding: 14px 16px;
            background-color: #edf2f7;
            border-radius: 8px;
            border-left: 4px solid #4299e1;
            transition: all 0.2s ease;
        }
        
        .ot-checkbox-container:hover {
            background-color: #e2e8f0;
        }
        
        .ot-awareness-checkbox {
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: #3182ce;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .ot-checkbox-label {
            font-weight: 500;
            color: #2d3748;
            flex: 1;
            cursor: pointer;
            line-height: 1.4;
            font-size: 0.95rem;
        }
        
        /* Manager dropdown styling with unique class */
        .ot-manager-dropdown {
            width: 100%;
            padding: 14px 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            font-size: 1rem;
            color: #2d3748;
            background-color: #fff;
            transition: all 0.2s ease;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%234299e1' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 16px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            font-weight: 500;
        }
        
        .ot-manager-dropdown:hover {
            border-color: #90cdf4;
        }
        
        .ot-manager-dropdown:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15);
        }
        
        /* Textarea styling with unique class */
        .ot-work-textarea {
            width: 95%;
            height: 100px;
            padding: 14px 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            font-size: 1rem;
            color: #2d3748;
            background-color: #fff;
            transition: all 0.2s ease;
            resize: none;
            font-family: inherit;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            line-height: 1.6;
            margin: 0 auto;
            display: block;
        }
        
        .ot-work-textarea:hover {
            border-color: #90cdf4;
        }
        
        .ot-work-textarea:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15);
        }
        
        .ot-work-textarea::placeholder {
            color: #a0aec0;
            font-style: italic;
        }
        
        /* Send button styling */
        .send-overtime-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 10px !important;
            background: linear-gradient(135deg, #4299e1, #3182ce) !important;
            color: white !important;
            padding: 12px 24px !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 1rem !important;
            letter-spacing: 0.3px !important;
            transition: all 0.3s ease !important;
            border: none !important;
            box-shadow: 0 4px 6px rgba(66, 153, 225, 0.25) !important;
        }
        
        .send-overtime-btn:hover {
            background: linear-gradient(135deg, #3182ce, #2b6cb0) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 6px 8px rgba(66, 153, 225, 0.3) !important;
        }
        
        .send-overtime-btn i {
            font-size: 1.1rem !important;
        }
        
        /* Work report warning styles */
        .work-report-warning {
            color: #e53e3e;
            font-size: 0.9rem;
            margin-top: 8px;
            padding: 8px 12px;
            background-color: #fff5f5;
            border-left: 3px solid #e53e3e;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: fadeIn 0.3s ease;
        }
        
        .work-report-warning i {
            color: #e53e3e;
        }
        
        .swal2-textarea.error {
            border-color: #e53e3e !important;
            box-shadow: 0 0 0 1px #e53e3e !important;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Send overtime button styling */
        .send-overtime-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            background: linear-gradient(135deg, #3498db, #2980b9) !important;
            color: white !important;
            padding: 10px 24px !important;
            border-radius: 8px !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
        }
        
        .send-overtime-btn:hover {
            background: linear-gradient(135deg, #2980b9, #1c6ea4) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(41, 128, 185, 0.4) !important;
        }
        
        .send-overtime-btn i {
            font-size: 1rem !important;
        }
        
        .claim-overtime-btn.claimed {
            background: linear-gradient(135deg, #4caf50, #2e7d32);
            pointer-events: none;
        }
        
        .claim-overtime-btn.claimed:hover {
            transform: none;
            box-shadow: 0 2px 6px rgba(76, 175, 80, 0.3);
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

        .role-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .role-badge.project-owner {
            background-color: #4CAF50;
            color: white;
        }

        .role-badge.stage-owner {
            background-color: #2196F3;
            color: white;
        }

        .role-badge.substage-owner {
            background-color: #FF9800;
            color: white;
        }

        
        <style>
            .year-dropdown,
            .month-dropdown {
                max-height: 250px;
                overflow-y: auto;
                scrollbar-width: thin;
            }
            
            .year-dropdown::-webkit-scrollbar,
            .month-dropdown::-webkit-scrollbar {
                width: 6px;
            }
            
            .year-dropdown::-webkit-scrollbar-track,
            .month-dropdown::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
            }
            
            .year-dropdown::-webkit-scrollbar-thumb,
            .month-dropdown::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 10px;
            }
            
            .year-dropdown::-webkit-scrollbar-thumb:hover,
            .month-dropdown::-webkit-scrollbar-thumb:hover {
                background: #555;
            }
        </style>
    </style>
    
    <!-- Add CSS for kanban filters -->
    <style>
        /* Year and Month Filter Styles */
        .year-filter, .month-filter {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .year-filter:hover, .month-filter:hover {
            background: #f8f9fa;
            transform: translateY(-1px);
        }
        
        .year-dropdown, .month-dropdown {
            position: absolute;
            top: 110%;
            left: 0;
            min-width: 150px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            z-index: 100;
            overflow-y: auto;
            max-height: 250px;
            display: none;
        }
        
        /* Add scrollbar styling for dropdowns */
        .year-dropdown::-webkit-scrollbar,
        .month-dropdown::-webkit-scrollbar {
            width: 6px;
        }
        
        .year-dropdown::-webkit-scrollbar-track,
        .month-dropdown::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .year-dropdown::-webkit-scrollbar-thumb,
        .month-dropdown::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        .year-dropdown::-webkit-scrollbar-thumb:hover,
        .month-dropdown::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .year-option, .month-option {
            padding: 8px 15px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .year-option:hover, .month-option:hover {
            background: #f1f5f9;
        }
        
        .year-option.selected, .month-option.selected {
            background: #e0e7ff;
            color: #4f46e5;
            font-weight: 500;
        }
        
        /* Error notification styles */
        .filter-error-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 9999;
            max-width: 400px;
            animation: slideIn 0.3s ease-out;
        }
        
        .filter-error-notification i {
            font-size: 1.2rem;
            color: #dc3545;
        }
        
        .filter-error-notification span {
            flex: 1;
            font-size: 0.9rem;
        }
        
        .close-notification {
            background: none;
            border: none;
            color: #721c24;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        
        .close-notification:hover {
            opacity: 1;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Loading state for kanban columns */
        .kanban-column.loading .kanban-cards-container {
            position: relative;
            min-height: 100px;
        }
        
        .kanban-column.loading .kanban-cards-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        
        .kanban-column.loading .kanban-cards-container::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            z-index: 11;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        
        /* Improved empty state styling */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
            text-align: center;
            color: #94a3b8;
            background: #f8fafc;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 15px;
            opacity: 0.7;
        }
        
        .empty-state p {
            font-size: 0.9rem;
            margin: 0;
        }
        /* Task Manager Dashboard Styles */
/* Task Manager Dashboard Styles */
.tm-dashboard-overview-section {
    padding: 20px;
    margin: 0 0 25px 0;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
}

.tm-dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
}

.tm-dashboard-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #0f172a;
    margin: 0;
}

.tm-dashboard-filters {
    display: flex;
    gap: 10px;
}

.tm-filter-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 10px;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.tm-filter-item:hover {
    background: #f1f5f9;
    color: #334155;
    transform: translateY(-1px);
}

.tm-filter-item.active {
    background: #0f172a;
    color: #ffffff;
}

.tm-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.tm-metrics-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 18px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    border: 1px solid #f1f5f9;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.tm-metrics-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.tm-card-primary {
    border-top: 3px solid #3b82f6;
}

.tm-card-warning {
    border-top: 3px solid #f59e0b;
}

.tm-card-success {
    border-top: 3px solid #10b981;
}

.tm-card-info {
    border-top: 3px solid #06b6d4;
}

.tm-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.tm-card-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.tm-card-primary .tm-card-icon {
    background: #eff6ff;
    color: #3b82f6;
}

.tm-card-warning .tm-card-icon {
    background: #fffbeb;
    color: #f59e0b;
}

.tm-card-success .tm-card-icon {
    background: #ecfdf5;
    color: #10b981;
}

.tm-card-info .tm-card-icon {
    background: #ecfeff;
    color: #06b6d4;
}

.tm-card-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
    padding-top: 4px;
}

.tm-card-content {
    flex-grow: 1;
    margin-bottom: 16px;
}

.tm-card-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 3px;
}

.tm-card-description {
    color: #64748b;
    font-size: 0.8rem;
}

.tm-card-stats {
    display: flex;
    justify-content: space-between;
    padding-top: 12px;
    border-top: 1px solid #f1f5f9;
}

.tm-stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.tm-stat-label {
    font-size: 0.7rem;
    color: #64748b;
    margin-bottom: 3px;
}

.tm-stat-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: #334155;
}

.tm-deadline-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.tm-deadline-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #f8fafc;
    border-radius: 8px;
    border-left: 3px solid #cbd5e1;
}

.tm-deadline-item.tm-urgent {
    background: #fff1f2;
    border-left: 3px solid #e11d48;
}

.tm-deadline-info {
    display: flex;
    flex-direction: column;
}

.tm-deadline-title {
    font-weight: 500;
    font-size: 0.8rem;
    color: #334155;
    margin-bottom: 2px;
}

.tm-deadline-project {
    font-size: 0.7rem;
    color: #64748b;
}

.tm-deadline-date {
    font-size: 0.75rem;
    font-weight: 500;
    color: #64748b;
}

.tm-deadline-item.tm-urgent .tm-deadline-date {
    color: #e11d48;
    font-weight: 600;
}

.tm-progress-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.tm-progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #64748b;
}

.tm-progress-bar {
    height: 6px;
    background: #f1f5f9;
    border-radius: 3px;
    overflow: hidden;
}

.tm-progress-fill {
    height: 100%;
    background: #10b981;
    border-radius: 3px;
    transition: width 0.5s ease;
}

.tm-team-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.tm-team-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border-radius: 8px;
    transition: background 0.2s ease;
}

.tm-team-item:hover {
    background: #f8fafc;
}

.tm-team-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 1rem;
}

.tm-team-info {
    display: flex;
    flex-direction: column;
}

.tm-team-name {
    font-weight: 500;
    font-size: 0.8rem;
    color: #334155;
    margin-bottom: 2px;
}

.tm-team-count {
    font-size: 0.7rem;
    color: #64748b;
}

.tm-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.tm-section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #0f172a;
    margin: 0;
}

.tm-view-all {
    color: #3b82f6;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    transition: color 0.2s ease;
}

.tm-view-all:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.tm-activity-timeline {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding-left: 12px;
    position: relative;
}

.tm-activity-timeline::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 18px;
    width: 2px;
    background: #e2e8f0;
}

.tm-activity-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    position: relative;
}

.tm-activity-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.9rem;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    z-index: 1;
}

.tm-icon-update {
    background: #eff6ff;
    color: #3b82f6;
}

.tm-icon-comment {
    background: #ecfdf5;
    color: #10b981;
}

.tm-icon-add {
    background: #fffbeb;
    color: #f59e0b;
}

.tm-activity-content {
    flex: 1;
    background: #ffffff;
    border-radius: 10px;
    padding: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    border: 1px solid #f1f5f9;
}

.tm-activity-title {
    font-weight: 600;
    font-size: 0.85rem;
    color: #334155;
    margin-bottom: 4px;
}

.tm-activity-details {
    color: #64748b;
    font-size: 0.75rem;
    margin-bottom: 8px;
}

.tm-activity-meta {
    display: flex;
    justify-content: space-between;
    color: #94a3b8;
    font-size: 0.7rem;
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .tm-dashboard-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .tm-dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .tm-dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .tm-dashboard-filters {
        width: 100%;
        overflow-x: auto;
        padding-bottom: 4px;
    }
        }
    </style>
    
    <!-- External Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Include the Project Brief Modal -->
    <?php include 'include_project_brief_modal.php'; ?>

    <!-- Scrollable upcoming dates styles -->
    <style>
        /* Add scrollable styles for upcoming dates list */
        #upcomingDatesCard .card-content {
            max-height: 200px;
            overflow-y: auto;
            padding-right: 5px;
        }
        
        #upcomingDatesCard .upcoming-dates-list {
            margin-right: 5px;
        }
        
        /* Custom scrollbar styling */
        #upcomingDatesCard .card-content::-webkit-scrollbar {
            width: 6px;
        }
        
        #upcomingDatesCard .card-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        #upcomingDatesCard .card-content::-webkit-scrollbar-thumb {
            background: #c0c0c0;
            border-radius: 10px;
        }
        
        #upcomingDatesCard .card-content::-webkit-scrollbar-thumb:hover {
            background: #a0a0a0;
        }
    </style>
    
    <script>
        // Function to show all upcoming dates in a modal
        function showAllUpcomingDates() {
            // This function will be implemented if needed to show all dates in a larger modal
            console.log("View all upcoming dates clicked");
            // You can implement a modal or redirect to a detailed view page
        }
    </script>
</head>
<body data-user-role="<?php echo htmlspecialchars($_SESSION['user_role'] ?? 'default'); ?>" data-user-id="<?php echo htmlspecialchars($_SESSION['user_id'] ?? ''); ?>">
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
            <div class="menu-item" onclick="window.location.href='std_travel_expenses.php'">
                <i class="fas fa-file-excel"></i>
                <span class="menu-text">Travel Expenses</span>
            </div>
            <div class="menu-item" onclick="window.location.href='employee_overtime.php'">
                <i class="fas fa-clock"></i>
                <span class="menu-text">Overtime</span>
            </div>
            <div class="menu-item" onclick="window.location.href='site_expenses.php'">
                <i class="fas fa-file-excel"></i>
                <span class="menu-text">Site Excel</span>
            </div>
            <div class="menu-item" onclick="window.location.href='site_updates.php'">
                <i class="fas fa-file-alt"></i>
                <span class="menu-text">Site Updates</span>
            </div>
            
            <!-- Work Section -->
            <div class="menu-item">
                <i class="fas fa-tasks"></i>
                <span class="menu-text">My Tasks</span>
            </div>
            <div class="menu-item" onclick="window.location.href='work_sheet.php'">
                <i class="fas fa-file-alt"></i>
                <span class="menu-text">Work Sheet & Attendance</span>
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
                            
                            <!-- Add Notification Icon Here -->
                            <div class="notification-wrapper">
                                <div class="notification-icon">
                                    <i class="fas fa-bell"></i>
                                    <span class="notification-badge">0</span>
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
                <div class="row">
                <div class="col-md-12">
                        <div class="tm-dashboard-overview-section">
                            <div class="tm-dashboard-header">
                                <h4 class="tm-dashboard-title">Task Dashboard</h4>
                                <div class="tm-dashboard-filters">
                                    <div class="tm-filter-item active">
                                        <i class="fas fa-tasks"></i>
                                        <span>All</span>
                </div>
                                    <div class="tm-filter-item">
                                        <i class="fas fa-hourglass-half"></i>
                                        <span>Pending</span>
                </div>
                                    <div class="tm-filter-item">
                                        <i class="fas fa-spinner"></i>
                                        <span>In Progress</span>
            </div>
                                    <div class="tm-filter-item">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Completed</span>
            </div>
        </div>
    </div>

                            <div class="tm-dashboard-grid">
                                <!-- Task Summary Card -->
                                <div class="tm-metrics-card tm-card-primary">
                                    <div class="tm-card-header">
                                        <h3 class="tm-card-title">Task Summary</h3>
                                        <div class="tm-card-icon">
                                            <i class="fas fa-clipboard-list"></i>
            </div>
                                    </div>
                                    <div class="tm-card-content">
                <?php
                                        // Get the current user ID
                                        $userId = $_SESSION['user_id'];
                                        
                                        // Count stages by status
                                        $stageStatusQuery = "SELECT 
                                            status, 
                                            COUNT(*) as count 
                                        FROM 
                                            project_stages 
                                        WHERE 
                                            assigned_to = $userId
                                            AND deleted_at IS NULL
                                        GROUP BY 
                                            status";
                                        
                                        $stageResult = mysqli_query($conn, $stageStatusQuery);
                                        
                                        // Initialize counters for stages
                                        $stageNotStarted = 0;
                                        $stagePending = 0;
                                        $stageInProgress = 0;
                                        $stageCompleted = 0;
                                        $totalStages = 0;
                                        
                                        // Process stage results
                                        while ($stageRow = mysqli_fetch_assoc($stageResult)) {
                                            if ($stageRow['status'] == 'not_started') {
                                                $stageNotStarted += $stageRow['count'];
                                            } else if ($stageRow['status'] == 'pending') {
                                                $stagePending += $stageRow['count'];
                                            } else if ($stageRow['status'] == 'in_progress' || $stageRow['status'] == 'in_review') {
                                                $stageInProgress += $stageRow['count'];
                                            } else if ($stageRow['status'] == 'completed') {
                                                $stageCompleted += $stageRow['count'];
                                            }
                                            $totalStages += $stageRow['count'];
                                        }
                                        
                                        // Count substages by status
                                        $substageStatusQuery = "SELECT 
                                            status, 
                                            COUNT(*) as count 
                                        FROM 
                                            project_substages 
                                        WHERE 
                                            assigned_to = $userId
                                            AND deleted_at IS NULL
                                        GROUP BY 
                                            status";
                                        
                                        $substageResult = mysqli_query($conn, $substageStatusQuery);
                                        
                                        // Initialize counters for substages
                                        $substageNotStarted = 0;
                                        $substagePending = 0;
                                        $substageInProgress = 0;
                                        $substageCompleted = 0;
                                        $totalSubstages = 0;
                                        
                                        // Process substage results
                                        while ($substageRow = mysqli_fetch_assoc($substageResult)) {
                                            if ($substageRow['status'] == 'not_started') {
                                                $substageNotStarted += $substageRow['count'];
                                            } else if ($substageRow['status'] == 'pending') {
                                                $substagePending += $substageRow['count'];
                                            } else if ($substageRow['status'] == 'in_progress' || $substageRow['status'] == 'in_review') {
                                                $substageInProgress += $substageRow['count'];
                                            } else if ($substageRow['status'] == 'completed') {
                                                $substageCompleted += $substageRow['count'];
                                            }
                                            $totalSubstages += $substageRow['count'];
                                        }
                                        
                                        // Calculate totals
                                        $totalNotStarted = $stageNotStarted + $substageNotStarted;
                                        $totalPending = $stagePending + $substagePending;
                                        $totalInProgress = $stageInProgress + $substageInProgress;
                                        $totalCompleted = $stageCompleted + $substageCompleted;
                                        $totalTasks = $totalStages + $totalSubstages;
                                        
                                        // Calculate completion percentage
                                        $completionPercentage = $totalTasks > 0 ? round(($totalCompleted / $totalTasks) * 100) : 0;
                                        ?>
                                        <div class="tm-card-value">
                                            <?php echo $totalTasks; ?>
                                            <span class="tm-completion-badge">
                                                <i class="fas fa-check-circle"></i> <?php echo $completionPercentage; ?>% Complete
                                            </span>
                    </div>
                                        <div class="tm-task-breakdown">
                                            <div class="tm-breakdown-row">
                                                <span class="tm-breakdown-label">Stages:</span>
                                                <span class="tm-breakdown-value"><?php echo $totalStages; ?></span>
                    </div>
                                            <div class="tm-breakdown-row">
                                                <span class="tm-breakdown-label">Substages:</span>
                                                <span class="tm-breakdown-value"><?php echo $totalSubstages; ?></span>
                </div>
                    </div>
                    </div>
                                    <div class="tm-card-stats">
                                        <div class="tm-stat-item">
                                            <div class="tm-stat-header">
                                                <i class="fas fa-hourglass-half tm-stat-icon tm-pending-icon"></i>
                                                <span class="tm-stat-label">Pending</span>
                </div>
                                            <span class="tm-stat-value"><?php echo $totalPending; ?></span>
                                            <div class="tm-stat-progress">
                                                <div class="tm-progress-bar">
                                                    <div class="tm-progress-fill" style="width: <?php echo $totalTasks > 0 ? ($totalPending / $totalTasks) * 100 : 0; ?>%; background-color: #f59e0b;"></div>
                    </div>
                    </div>
                </div>
                                        <div class="tm-stat-item">
                                            <div class="tm-stat-header">
                                                <i class="fas fa-clock tm-stat-icon tm-not-started-icon"></i>
                                                <span class="tm-stat-label">Not Started</span>
                    </div>
                                            <span class="tm-stat-value"><?php echo $totalNotStarted; ?></span>
                                            <div class="tm-stat-progress">
                                                <div class="tm-progress-bar">
                                                    <div class="tm-progress-fill" style="width: <?php echo $totalTasks > 0 ? ($totalNotStarted / $totalTasks) * 100 : 0; ?>%; background-color: #9333ea;"></div>
                    </div>
                </div>
            </div>
                                        <div class="tm-stat-item">
                                            <div class="tm-stat-header">
                                                <i class="fas fa-spinner tm-stat-icon tm-progress-icon"></i>
                                                <span class="tm-stat-label">In Progress</span>
                                            </div>
                                            <span class="tm-stat-value"><?php echo $totalInProgress; ?></span>
                                            <div class="tm-stat-progress">
                                                <div class="tm-progress-bar">
                                                    <div class="tm-progress-fill" style="width: <?php echo $totalTasks > 0 ? ($totalInProgress / $totalTasks) * 100 : 0; ?>%; background-color: #3b82f6;"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tm-stat-item">
                                            <div class="tm-stat-header">
                                                <i class="fas fa-check-circle tm-stat-icon tm-completed-icon"></i>
                                                <span class="tm-stat-label">Completed</span>
                                            </div>
                                            <span class="tm-stat-value"><?php echo $totalCompleted; ?></span>
                                            <div class="tm-stat-progress">
                                                <div class="tm-progress-bar">
                                                    <div class="tm-progress-fill" style="width: <?php echo $totalTasks > 0 ? ($totalCompleted / $totalTasks) * 100 : 0; ?>%; background-color: #10b981;"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <style>
                                    .tm-completion-badge {
                                        font-size: 0.75rem;
                                        background-color: #ecfdf5;
                                        color: #10b981;
                                        padding: 3px 8px;
                                        border-radius: 20px;
                                        margin-left: 10px;
                                        vertical-align: middle;
                                        white-space: nowrap;
                                    }
                                    
                                    .tm-task-breakdown {
                                        display: flex;
                                        flex-direction: column;
                                        gap: 6px;
                                        margin-top: 12px;
                                        font-size: 0.75rem;
                                        color: #64748b;
                                    }
                                    
                                    .tm-breakdown-row {
                                        display: flex;
                                        justify-content: space-between;
                                        align-items: center;
                                    }
                                    
                                    .tm-breakdown-value {
                                        font-weight: 600;
                                        color: #334155;
                                    }
                                    
                                    .tm-stat-header {
                                        display: flex;
                                        align-items: center;
                                        gap: 5px;
                                        margin-bottom: 3px;
                                    }
                                    
                                    .tm-stat-icon {
                                        font-size: 0.9rem;
                                    }
                                    
                                    .tm-pending-icon {
                                        color: #f59e0b;
                                    }
                                    
                                    .tm-not-started-icon {
                                        color: #9333ea;
                                    }
                                    
                                    .tm-progress-icon {
                                        color: #3b82f6;
                                    }
                                    
                                    .tm-completed-icon {
                                        color: #10b981;
                                    }
                                    
                                    .tm-stat-progress {
                                        margin-top: 4px;
                                    }
                                    
                                    .tm-progress-bar {
                                        height: 4px;
                                        background: #f1f5f9;
                                        border-radius: 2px;
                                        overflow: hidden;
                                    }
                                    
                                    .tm-progress-fill {
                                        height: 100%;
                                        border-radius: 2px;
                                        transition: width 0.5s ease;
                                    }
                                    
                                    .tm-card-stats {
                                        display: grid;
                                        grid-template-columns: repeat(2, 1fr);
                                        gap: 15px 10px;
                                        padding-top: 12px;
                                        border-top: 1px solid #f1f5f9;
                                    }
                                    </style>
            </div>
                                <!-- Deadline Card -->
                                <div class="tm-metrics-card tm-card-warning">
                                    <div class="tm-card-header">
                                        <h3 class="tm-card-title">Upcoming Deadlines</h3>
                                        <div class="tm-card-icon">
                                            <i class="fas fa-calendar-alt"></i>
        </div>
    </div>
                                    <div class="tm-card-content">
        <?php 
                                        // Fetch upcoming substages assigned to current user
                                        
                                        // Ensure we have the current date defined
                                        $currentDate = date('Y-m-d');
                                        
                                        // Get total count of upcoming deadlines for pagination
                                        $totalDeadlinesQuery = "SELECT 
                                            COUNT(*) as total_count
                                        FROM 
                                            project_substages ps
                                            JOIN project_stages pst ON ps.stage_id = pst.id
                                            JOIN projects p ON pst.project_id = p.id
                                        WHERE 
                                            ps.assigned_to = $userId
                                            AND ps.end_date >= '$currentDate'
                                            AND ps.deleted_at IS NULL
                                            AND ps.status != 'completed'
                                            AND ps.status != 'cancelled'";
                                        
                                        $totalResult = mysqli_query($conn, $totalDeadlinesQuery);
                                        $totalRow = mysqli_fetch_assoc($totalResult);
                                        $totalDeadlines = $totalRow['total_count'];
                                        
                                        // Set up pagination
                                        $deadlinesPerPage = 5;
                                        $totalPages = ceil($totalDeadlines / $deadlinesPerPage);
                                        
                                        // Get current page from query parameter or default to 1
                                        $currentDeadlinePage = isset($_GET['deadline_page']) ? max(1, intval($_GET['deadline_page'])) : 1;
                                        $currentDeadlinePage = min($currentDeadlinePage, max(1, $totalPages)); // Ensure page is within valid range
                                        
                                        // Calculate offset
                                        $offset = ($currentDeadlinePage - 1) * $deadlinesPerPage;
                                        
                                        // Modify query to include pagination
                                        $upcomingQuery = "SELECT 
                                            ps.id as substage_id,
                                            ps.title as substage_title,
                                            ps.end_date,
                                            p.title as project_title,
                                            p.id as project_id
                                        FROM 
                                            project_substages ps
                                            JOIN project_stages pst ON ps.stage_id = pst.id
                                            JOIN projects p ON pst.project_id = p.id
                                        WHERE 
                                            ps.assigned_to = $userId
                                            AND ps.end_date >= '$currentDate'
                                            AND ps.deleted_at IS NULL
                                            AND ps.status != 'completed'
                                            AND ps.status != 'cancelled'
                                        ORDER BY ps.end_date ASC
                                        LIMIT $offset, $deadlinesPerPage";
        
                                        $upcomingResult = mysqli_query($conn, $upcomingQuery);
                                        $upcomingCount = mysqli_num_rows($upcomingResult);
        ?>
                                        <div class="tm-card-value"><?php echo $totalDeadlines; ?></div>
                                        <div class="tm-card-description">Tasks due soon</div>
                </div>
                                    <div class="tm-deadline-list">
        <?php 
                                        if ($upcomingCount > 0) {
                                            while ($substage = mysqli_fetch_assoc($upcomingResult)) {
                                                // Calculate days until deadline
                                                $endDate = new DateTime($substage['end_date']);
                                                $today = new DateTime($currentDate);
                                                $interval = $today->diff($endDate);
                                                $daysRemaining = $interval->days;
                                                
                                                // Determine if urgent (3 days or less)
                                                $isUrgent = $daysRemaining <= 3 ? 'tm-urgent' : '';
                                                
                                                // Format the date display
                                                if ($daysRemaining == 0) {
                                                    $dueText = "Today";
                                                } elseif ($daysRemaining == 1) {
                                                    $dueText = "Tomorrow";
        } else {
                                                    $dueText = "In $daysRemaining days";
                                                }
                                        ?>
                                                <div class="tm-deadline-item <?php echo $isUrgent; ?>">
                                                    <div class="tm-deadline-info">
                                                        <span class="tm-deadline-title"><?php echo htmlspecialchars($substage['substage_title']); ?></span>
                                                        <span class="tm-deadline-project"><?php echo htmlspecialchars($substage['project_title']); ?></span>
                            </div>
                                                    <div class="tm-deadline-date"><?php echo $dueText; ?></div>
            </div>
        <?php 
                                            }
                                            
                                            // Add pagination controls if there's more than one page
                                            if ($totalPages > 1) {
                                                echo '<div class="tm-pagination">';
                                                
                                                // Previous page link
                                                if ($currentDeadlinePage > 1) {
                                                    echo '<a href="?deadline_page=' . ($currentDeadlinePage - 1) . '" class="tm-pagination-btn prev"><i class="fas fa-chevron-left"></i></a>';
                                                } else {
                                                    echo '<span class="tm-pagination-btn disabled"><i class="fas fa-chevron-left"></i></span>';
                                                }
                                                
                                                // Page indicator
                                                echo '<span class="tm-pagination-info">' . $currentDeadlinePage . ' / ' . $totalPages . '</span>';
                                                
                                                // Next page link
                                                if ($currentDeadlinePage < $totalPages) {
                                                    echo '<a href="?deadline_page=' . ($currentDeadlinePage + 1) . '" class="tm-pagination-btn next"><i class="fas fa-chevron-right"></i></a>';
                                                } else {
                                                    echo '<span class="tm-pagination-btn disabled"><i class="fas fa-chevron-right"></i></span>';
                                                }
                                                
                                                echo '</div>';
                                            }
                                            
        } else {
        ?>
                                            <div class="tm-deadline-item">
                                                <div class="tm-deadline-info">
                                                    <span class="tm-deadline-title">No upcoming deadlines</span>
                                                    <span class="tm-deadline-project">You're all caught up!</span>
        </div>
                                                <div class="tm-deadline-date">-</div>
            </div>
        <?php 
        }
        ?>
    </div>
    
    <!-- Add CSS for the pagination -->
    <style>
        .tm-pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            padding-top: 12px;
            margin-top: 10px;
            border-top: 1px solid #f1f5f9;
            gap: 10px;
        }
        
        .tm-pagination-btn {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            background: #f8fafc;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .tm-pagination-btn:hover {
            background: #f1f5f9;
            color: #334155;
        }
        
        .tm-pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f8fafc;
            color: #cbd5e1;
        }
        
        .tm-pagination-info {
            font-size: 0.8rem;
            color: #64748b;
        }
    </style>
</div>
                                <!-- Efficiency Card -->
                                <div class="tm-metrics-card tm-card-success">
                                    <div class="tm-card-header">
                                        <h3 class="tm-card-title">Task Efficiency</h3>
                                        <div class="tm-card-icon">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                    </div>
                                    <div class="tm-card-content">
                <?php 
                                        // Get the current user ID
                                        $userId = $_SESSION['user_id'];
                                        $currentDate = date('Y-m-d');
                                        
                                        // Calculate completed substages within deadline vs. total completed
                                        $efficiencyQuery = "SELECT 
                                            COUNT(*) as total_completed,
                                            SUM(CASE WHEN updated_at <= end_date THEN 1 ELSE 0 END) as on_time_completed
                                        FROM 
                                            project_substages 
                                        WHERE 
                                            assigned_to = $userId
                                            AND status = 'completed'
                    AND deleted_at IS NULL 
                                            AND updated_at IS NOT NULL";
                                        
                                        $efficiencyResult = mysqli_query($conn, $efficiencyQuery);
                                        $efficiencyData = mysqli_fetch_assoc($efficiencyResult);
                                        
                                        $totalCompleted = $efficiencyData['total_completed'];
                                        $onTimeCompleted = $efficiencyData['on_time_completed'];
                                        
                                        // Calculate efficiency percentage
                                        $efficiencyPercentage = 0;
                                        if ($totalCompleted > 0) {
                                            $efficiencyPercentage = round(($onTimeCompleted / $totalCompleted) * 100);
                                        }
                                        
                                        // Progress towards monthly goal (assumed 90%)
                                        $monthlyGoal = 90;
                                        $progressPercentage = min(($efficiencyPercentage / $monthlyGoal) * 100, 100);
                                        
                                        // Determine color based on efficiency
                                        $efficiencyColor = "#10b981"; // Default green
                                        if ($efficiencyPercentage < 70) {
                                            $efficiencyColor = "#ef4444"; // Red for low efficiency
                                        } else if ($efficiencyPercentage < 85) {
                                            $efficiencyColor = "#f59e0b"; // Orange for medium efficiency
                                        }
                                        ?>
                                        <div class="tm-card-value" style="color: <?php echo $efficiencyColor; ?>">
                                            <?php echo $efficiencyPercentage; ?>%
        </div>
                                        <div class="tm-card-description">On-time completion rate</div>
                                        <div class="tm-efficiency-details">
                                            <div class="tm-details-row">
                                                <span class="tm-details-label">Total Completed:</span>
                                                <span class="tm-details-value"><?php echo $totalCompleted; ?></span>
    </div>
                                            <div class="tm-details-row">
                                                <span class="tm-details-label">On-time Completed:</span>
                                                <span class="tm-details-value"><?php echo $onTimeCompleted; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tm-progress-container">
                                        <div class="tm-progress-label">
                                            <span>Monthly Goal: <?php echo $monthlyGoal; ?>%</span>
                                            <span><?php echo $efficiencyPercentage; ?>/<?php echo $monthlyGoal; ?></span>
                                        </div>
                                        <div class="tm-progress-bar">
                                            <div class="tm-progress-fill" style="width: <?php echo $progressPercentage; ?>%; background-color: <?php echo $efficiencyColor; ?>;"></div>
                                        </div>
                                    </div>
                                    <style>
                                    .tm-efficiency-details {
                                        margin-top: 12px;
                                        font-size: 0.75rem;
                                        color: #64748b;
                                    }
                                    
                                    .tm-details-row {
                                        display: flex;
                                        justify-content: space-between;
                                        margin-bottom: 4px;
                                    }
                                    
                                    .tm-details-value {
                                        font-weight: 600;
                                        color: #334155;
                                    }
                                    </style>
                </div>
                
                                <!-- Team Collaboration Card -->
                                <div class="tm-metrics-card tm-card-info">
                                    <div class="tm-card-header">
                                        <h3 class="tm-card-title">Team Collaboration</h3>
                                        <div class="tm-card-icon">
                                            <i class="fas fa-users"></i>
                    </div>
                </div>
                                    <div class="tm-card-content">
        <?php
                                        // Get projects where the user is collaborating with others
                                        $userId = $_SESSION['user_id'];
                                        
                                        // Find projects that have multiple users assigned to its stages or substages
                                        // including at least one stage/substage assigned to the current user
                                        $collaborationQuery = "SELECT 
                                            p.id as project_id,
                                            p.title as project_title,
                                            COUNT(DISTINCT CASE WHEN ps.assigned_to != $userId THEN ps.assigned_to END) +
                                            COUNT(DISTINCT CASE WHEN pss.assigned_to != $userId THEN pss.assigned_to END) as collaborator_count
                                        FROM 
                                            projects p
                                        LEFT JOIN 
                                            project_stages ps ON p.id = ps.project_id AND ps.deleted_at IS NULL
                                        LEFT JOIN 
                                            project_substages pss ON ps.id = pss.stage_id AND pss.deleted_at IS NULL
                                        WHERE 
                                            p.deleted_at IS NULL AND
                                            (
                                                EXISTS (
                                                    SELECT 1 FROM project_stages 
                                                    WHERE project_id = p.id AND assigned_to = $userId AND deleted_at IS NULL
                                                ) OR 
                                                EXISTS (
                                                    SELECT 1 FROM project_stages ps2
                                                    JOIN project_substages pss2 ON ps2.id = pss2.stage_id
                                                    WHERE ps2.project_id = p.id AND pss2.assigned_to = $userId AND pss2.deleted_at IS NULL
                                                )
                                            )
                                        GROUP BY 
                                            p.id
                                        HAVING 
                                            collaborator_count > 0
                                        ORDER BY 
                                            collaborator_count DESC";
                                        
                                        $collaborationResult = mysqli_query($conn, $collaborationQuery);
                                        $collaborationCount = mysqli_num_rows($collaborationResult);
                                        
                                        // Store all projects in an array for pagination
                                        $allCollaborationProjects = [];
                                        while ($project = mysqli_fetch_assoc($collaborationResult)) {
                                            $allCollaborationProjects[] = $project;
                                        }
                                        
                                        // Pagination settings
                                        $projectsPerPage = 3;
                                        $totalPages = ceil(count($allCollaborationProjects) / $projectsPerPage);
                                        $currentPage = 1; // Start with page 1
                                        ?>
                                        
                                        <div class="tm-card-value"><?php echo $collaborationCount; ?></div>
                                        <div class="tm-card-description">Projects with team collaboration</div>
                </div>
                                    <div class="tm-team-list" id="collaborationProjectsList">
                                        <?php
                                        if ($collaborationCount > 0) {
                                            // Calculate start and end index for current page
                                            $startIdx = 0; // For first page, start at index 0
                                            $endIdx = min($projectsPerPage - 1, count($allCollaborationProjects) - 1);
                                            
                                            // Display projects for the current page
                                            for ($i = $startIdx; $i <= $endIdx; $i++) {
                                                $project = $allCollaborationProjects[$i];
                                                
                                                // Get list of collaborators for this project
                                                $collaboratorsQuery = "SELECT DISTINCT 
                                                    u.id as user_id,
                                                    u.username as user_name,
                                                    u.profile_picture,
                                                    (
                                                        SELECT COUNT(*) FROM project_stages 
                                                        WHERE project_id = {$project['project_id']} AND assigned_to = u.id AND deleted_at IS NULL
                                                    ) +
                                                    (
                                                        SELECT COUNT(*) FROM project_substages pss
                                                        JOIN project_stages ps ON pss.stage_id = ps.id
                                                        WHERE ps.project_id = {$project['project_id']} AND pss.assigned_to = u.id AND pss.deleted_at IS NULL
                                                    ) as task_count
                                                FROM 
                                                    users u
                                                WHERE 
                                                    u.id != $userId AND
                                                    (
                                                        EXISTS (
                                                            SELECT 1 FROM project_stages 
                                                            WHERE project_id = {$project['project_id']} AND assigned_to = u.id AND deleted_at IS NULL
                                                        ) OR 
                                                        EXISTS (
                                                            SELECT 1 FROM project_stages ps2
                                                            JOIN project_substages pss2 ON ps2.id = pss2.stage_id
                                                            WHERE ps2.project_id = {$project['project_id']} AND pss2.assigned_to = u.id AND pss2.deleted_at IS NULL
                                                        )
                                                    )
                                                ORDER BY
                                                    task_count DESC
                                                LIMIT 1";
                                                
                                                $collaboratorsResult = mysqli_query($conn, $collaboratorsQuery);
                                                $collaborator = mysqli_fetch_assoc($collaboratorsResult);
                                                
                                                // Get total collaborator count
                                                $totalCollaboratorsCount = $project['collaborator_count'];
                                                
                                                // Find the first stage in this project assigned to the current user
                                                $userStageQuery = "SELECT id FROM project_stages 
                                                                  WHERE project_id = {$project['project_id']} 
                                                                  AND assigned_to = $userId 
                                                                  AND deleted_at IS NULL 
                                                                  LIMIT 1";
                                                $userStageResult = mysqli_query($conn, $userStageQuery);
                                                $userStage = mysqli_fetch_assoc($userStageResult);
                                                $stageId = $userStage ? $userStage['id'] : null;
                                        ?>
                                                <div class="tm-team-item tm-clickable-project" 
                                                     data-project-id="<?php echo $project['project_id']; ?>"
                                                     data-stage-id="<?php echo $stageId; ?>">
                                                    <div class="tm-team-avatar">
                                                        <?php if (!empty($collaborator['profile_picture'])): ?>
                                                            <img src="<?php echo htmlspecialchars($collaborator['profile_picture']); ?>" alt="<?php echo htmlspecialchars($collaborator['user_name']); ?>">
                                                        <?php else: ?>
                                                            <i class="fas fa-user-circle"></i>
                                                        <?php endif; ?>
                    </div>
                                                    <div class="tm-team-info">
                                                        <span class="tm-team-name"><?php echo htmlspecialchars($project['project_title']); ?></span>
                                                        <span class="tm-team-count">
                                                            <?php 
                                                            echo "With " . htmlspecialchars($collaborator['user_name']);
                                                            if ($totalCollaboratorsCount > 1) {
                                                                echo " + " . ($totalCollaboratorsCount - 1) . " others";
                                                            }
                                                            ?>
                    </span>
                </div>
            </div>
                                        <?php
                                            }
                                            
                                            // Add pagination controls if needed
                                            if ($totalPages > 1) {
                                                echo '<div class="tm-pagination">';
                                                // Previous button - disabled on first page
                                                echo '<button class="tm-pagination-btn tm-pagination-prev' . 
                                                     ($currentPage == 1 ? ' tm-pagination-disabled' : '') . 
                                                     '" ' . ($currentPage == 1 ? 'disabled' : '') . 
                                                     ' data-page="prev"><i class="fas fa-chevron-left"></i></button>';
                                                
                                                // Page indicator
                                                echo '<span class="tm-pagination-info">Page <span id="currentPageIndicator">1</span> of ' . $totalPages . '</span>';
                                                
                                                // Next button - disabled on last page
                                                echo '<button class="tm-pagination-btn tm-pagination-next' . 
                                                     ($currentPage == $totalPages ? ' tm-pagination-disabled' : '') . 
                                                     '" ' . ($currentPage == $totalPages ? 'disabled' : '') . 
                                                     ' data-page="next"><i class="fas fa-chevron-right"></i></button>';
                                                echo '</div>';
                                            }
                                        } else {
                                        ?>
                                            <div class="tm-team-item tm-no-data">
                                                <div class="tm-team-avatar tm-empty-avatar">
                                                    <i class="fas fa-users-slash"></i>
                    </div>
                                                <div class="tm-team-info">
                                                    <span class="tm-team-name">No active collaborations</span>
                                                    <span class="tm-team-count">You're not sharing projects yet</span>
            </div>
        </div>
        <?php 
                                        }
        ?>
            </div>
                                    <style>
                                    .tm-team-avatar img {
                                        width: 100%;
                                        height: 100%;
                                        border-radius: 50%;
                                        object-fit: cover;
                                    }
                                    
                                    .tm-no-data {
                                        opacity: 0.7;
                                    }
                                    
                                    .tm-empty-avatar {
                                        background: #f1f5f9;
                                        color: #94a3b8;
                                    }
                                    
                                    .tm-clickable-project {
                                        cursor: pointer;
                                        transition: transform 0.2s ease, box-shadow 0.2s ease;
                                    }
                                    
                                    .tm-clickable-project:hover {
                                        background-color: #f8fafc;
                                        transform: translateY(-2px);
                                        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
                                    }
                                    
                                    /* Pagination styling */
                                    .tm-pagination {
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        margin-top: 12px;
                                        padding-top: 10px;
                                        border-top: 1px solid #f1f5f9;
                                    }
                                    
                                    .tm-pagination-btn {
                                        width: 28px;
                                        height: 28px;
                                        border-radius: 6px;
                                        border: 1px solid #e2e8f0;
                                        background-color: #f8fafc;
                                        color: #64748b;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        cursor: pointer;
                                        transition: all 0.2s ease;
                                    }
                                    
                                    .tm-pagination-btn:hover:not(.tm-pagination-disabled) {
                                        background-color: #e2e8f0;
                                        color: #334155;
                                    }
                                    
                                    .tm-pagination-info {
                                        font-size: 0.75rem;
                                        color: #64748b;
                                        margin: 0 10px;
                                    }
                                    
                                    .tm-pagination-disabled {
                                        opacity: 0.5;
                                        cursor: not-allowed;
                                    }
                                    </style>
                                    
                                    <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        // Initialize project data for pagination
                                        const allCollaborationProjects = <?php echo json_encode($allCollaborationProjects); ?>;
                                        let currentPage = 1;
                                        const projectsPerPage = <?php echo $projectsPerPage; ?>;
                                        const totalPages = <?php echo $totalPages; ?>;
                                        
                                        // Function to load a specific page of projects
                                        function loadProjectPage(page) {
                                            // If page is out of range, do nothing
                                            if (page < 1 || page > totalPages) return;
                                            
                                            currentPage = page;
                                            
                                            // Calculate start and end indices
                                            const startIdx = (page - 1) * projectsPerPage;
                                            const endIdx = Math.min(startIdx + projectsPerPage - 1, allCollaborationProjects.length - 1);
                                            
                                            // Get container element
                                            const listContainer = document.getElementById('collaborationProjectsList');
                                            
                                            // Clear any existing content except pagination controls
                                            const paginationControls = listContainer.querySelector('.tm-pagination');
                                            listContainer.innerHTML = '';
                                            
                                            // Add back pagination controls if they existed
                                            if (paginationControls) {
                                                listContainer.appendChild(paginationControls);
                                            }
                                            
                                            // Display projects for the current page
                                            for (let i = startIdx; i <= endIdx; i++) {
                                                const project = allCollaborationProjects[i];
                                                
                                                // Fetch collaborator details (this would be via AJAX in a real implementation)
                                                // For demo purposes, we'll use placeholder data
                                                fetchProjectCollaborators(project.project_id)
                                                    .then(collaboratorData => {
                                                        const projectElement = createProjectElement(project, collaboratorData);
                                                        
                                                        // Insert before pagination controls if they exist
                                                        if (paginationControls) {
                                                            listContainer.insertBefore(projectElement, paginationControls);
                                                        } else {
                                                            listContainer.appendChild(projectElement);
                                                        }
                                                    });
                                            }
                                            
                                            // Update pagination state
                                            updatePaginationState();
                                        }
                                        
                                        // Function to create project element
                                        function createProjectElement(project, collaboratorData) {
                                            const projectElement = document.createElement('div');
                                            projectElement.className = 'tm-team-item tm-clickable-project';
                                            projectElement.dataset.projectId = project.project_id;
                                            
                                            // Set stage ID if available
                                            if (collaboratorData.stageId) {
                                                projectElement.dataset.stageId = collaboratorData.stageId;
                                            }
                                            
                                            // Build project HTML
                                            let avatarHTML = '';
                                            if (collaboratorData.profilePicture) {
                                                avatarHTML = `<img src="${collaboratorData.profilePicture}" alt="${collaboratorData.userName}">`;
                                            } else {
                                                avatarHTML = '<i class="fas fa-user-circle"></i>';
                                            }
                                            
                                            let collaboratorText = `With ${collaboratorData.userName}`;
                                            if (collaboratorData.totalCollaborators > 1) {
                                                collaboratorText += ` + ${collaboratorData.totalCollaborators - 1} others`;
                                            }
                                            
                                            projectElement.innerHTML = `
                                                <div class="tm-team-avatar">
                                                    ${avatarHTML}
    </div>
                                                <div class="tm-team-info">
                                                    <span class="tm-team-name">${project.project_title}</span>
                                                    <span class="tm-team-count">${collaboratorText}</span>
</div>
                                            `;
                                            
                                            // Add click event listener
                                            projectElement.addEventListener('click', function() {
                                                const projectId = this.dataset.projectId;
                                                
                                                if (projectId) {
                                                    // First try to use ProjectBriefModal if available
                                                    if (window.projectBriefModal) {
                                                        window.projectBriefModal.openProjectModal(projectId);
                                                    } else if (typeof ProjectBriefModal === 'function') {
                                                        // Initialize if class is available but not initialized
                                                        window.projectBriefModal = new ProjectBriefModal();
                                                        window.projectBriefModal.openProjectModal(projectId);
                                                    } else {
                                                        // Fallback to redirecting to project details page
                                                        window.location.href = `project-details.php?id=${projectId}`;
                                                    }
                                                }
                                            });
                                            
                                            return projectElement;
                                        }
                                        
                                        // Function to update pagination controls
                                        function updatePaginationState() {
                                            const paginationContainer = document.querySelector('.tm-pagination');
                                            if (!paginationContainer) return;
                                            
                                            // Update page indicator
                                            const pageIndicator = document.getElementById('currentPageIndicator');
                                            if (pageIndicator) {
                                                pageIndicator.textContent = currentPage;
                                            }
                                            
                                            // Update previous button state
                                            const prevButton = paginationContainer.querySelector('.tm-pagination-prev');
                                            if (prevButton) {
                                                if (currentPage === 1) {
                                                    prevButton.classList.add('tm-pagination-disabled');
                                                    prevButton.setAttribute('disabled', 'disabled');
                                                } else {
                                                    prevButton.classList.remove('tm-pagination-disabled');
                                                    prevButton.removeAttribute('disabled');
                                                }
                                            }
                                            
                                            // Update next button state
                                            const nextButton = paginationContainer.querySelector('.tm-pagination-next');
                                            if (nextButton) {
                                                if (currentPage === totalPages) {
                                                    nextButton.classList.add('tm-pagination-disabled');
                                                    nextButton.setAttribute('disabled', 'disabled');
                                                } else {
                                                    nextButton.classList.remove('tm-pagination-disabled');
                                                    nextButton.removeAttribute('disabled');
                                                }
                                            }
                                        }
                                        
                                        // Simulated function to fetch collaborator data
                                        // In a real implementation, this would be an AJAX call to the server
                                        async function fetchProjectCollaborators(projectId) {
                                            // In this demo, we'll just reuse the server-side rendered data
                                            // Find the existing element with this project ID
                                            const existingElement = document.querySelector(`.tm-clickable-project[data-project-id="${projectId}"]`);
                                            
                                            if (existingElement) {
                                                const avatarImg = existingElement.querySelector('.tm-team-avatar img');
                                                const userIcon = existingElement.querySelector('.tm-team-avatar i');
                                                const teamCount = existingElement.querySelector('.tm-team-count').textContent;
                                                
                                                // Parse collaborator info
                                                let userName = '';
                                                let totalCollaborators = 1;
                                                
                                                const match = teamCount.match(/With\s+(.+?)(?:\s+\+\s+(\d+)\s+others)?$/);
                                                if (match) {
                                                    userName = match[1];
                                                    if (match[2]) {
                                                        totalCollaborators = parseInt(match[2]) + 1;
                                                    }
                                                }
                                                
                                                return {
                                                    userName: userName,
                                                    profilePicture: avatarImg ? avatarImg.src : null,
                                                    totalCollaborators: totalCollaborators,
                                                    stageId: existingElement.dataset.stageId
                                                };
                                            }
                                            
                                            // Fallback values if element not found
                                            return {
                                                userName: 'Team Member',
                                                profilePicture: null,
                                                totalCollaborators: 1,
                                                stageId: null
                                            };
                                        }
                                        
                                        // Add click handlers for project collaboration items
                                        document.querySelectorAll('.tm-clickable-project').forEach(item => {
                                            item.addEventListener('click', function() {
                                                const projectId = this.dataset.projectId;
                                                
                                                if (projectId) {
                                                    // First try to use ProjectBriefModal if available
                                                    if (window.projectBriefModal) {
                                                        window.projectBriefModal.openProjectModal(projectId);
                                                    } else if (typeof ProjectBriefModal === 'function') {
                                                        // Initialize if class is available but not initialized
                                                        window.projectBriefModal = new ProjectBriefModal();
                                                        window.projectBriefModal.openProjectModal(projectId);
                                                    } else {
                                                        // Fallback to redirecting to project details page
                                                        window.location.href = `project-details.php?id=${projectId}`;
                                                    }
                                                }
                                            });
                                        });
                                        
                                        // Add pagination button handlers
                                        const prevButton = document.querySelector('.tm-pagination-prev');
                                        if (prevButton) {
                                            prevButton.addEventListener('click', function() {
                                                if (currentPage > 1) {
                                                    loadProjectPage(currentPage - 1);
                                                }
                                            });
                                        }
                                        
                                        const nextButton = document.querySelector('.tm-pagination-next');
                                        if (nextButton) {
                                            nextButton.addEventListener('click', function() {
                                                if (currentPage < totalPages) {
                                                    loadProjectPage(currentPage + 1);
                                                }
                                            });
                                        }
                                    });
                                    </script>
    </div>
        
            </div>


    </div>    
    </div>
</div>
</div>
<div class="project-overview-section quick-view-active" id="projectOverviewSection">
    <div class="project-overview-header">
        <h2 class="project-overview-title">Project Overview</h2>
        
        <!-- Add View Toggle -->
        <div class="view-toggle-container">
            <div class="view-toggle-label active" data-view="quick">Quick View</div>
            <div class="view-toggle-label" data-view="calendar">Calendar</div>
        </div>
        
        <div class="overview-filters">
            <!-- Year Filter -->
            <div class="overview-filter-wrapper">
                <div class="overview-filter" id="overviewYearFilter">
                    <i class="fas fa-calendar-alt"></i>
                    <span><?php echo date('Y'); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="overview-filter-dropdown" id="overviewYearDropdown">
                    <?php
                    $current_year = intval(date('Y'));
                    for ($year = $current_year - 2; $year <= $current_year + 2; $year++) {
                        $selected = ($year == $current_year) ? ' selected' : '';
                        echo "<div class='overview-filter-option{$selected}' data-value='{$year}'>{$year}</div>";
                    }
                    ?>
                </div>
            </div>
            
            <!-- Month Filter -->
            <div class="overview-filter-wrapper">
                <div class="overview-filter" id="overviewMonthFilter">
                    <i class="fas fa-filter"></i>
                    <span><?php echo date('F'); ?></span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="overview-filter-dropdown" id="overviewMonthDropdown">
                    <?php
                    $months = array(
                        1 => "January", 2 => "February", 3 => "March",
                        4 => "April", 5 => "May", 6 => "June",
                        7 => "July", 8 => "August", 9 => "September",
                        10 => "October", 11 => "November", 12 => "December"
                    );
                    $current_month = intval(date('n'));
                    foreach ($months as $num => $name) {
                        $selected = ($num == $current_month) ? ' selected' : '';
                        echo "<div class='overview-filter-option{$selected}' data-value='{$num}'>{$name}</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick View Grid -->
    <div class="project-overview-grid">
        <!-- Projects Assigned Card -->
        <div class="overview-card theme-primary" id="projectsCard">
            <div class="card-header">
                <h3 class="card-title">Projects Assigned</h3>
                <div class="card-icon">
                    <i class="fas fa-project-diagram"></i>
                </div>
            </div>
            <div class="card-content">
                <?php
                // Get the count of projects assigned to the current user
                $project_count_query = "SELECT COUNT(*) as total FROM projects WHERE assigned_to = ? AND deleted_at IS NULL";
                $project_count_stmt = $conn->prepare($project_count_query);
                $project_count_stmt->bind_param("i", $user_id);
                $project_count_stmt->execute();
                $project_count_result = $project_count_stmt->get_result();
                $project_count = $project_count_result->fetch_assoc()['total'];
                
                // Get the count of new projects assigned this month
                $current_month = date('m');
                $current_year = date('Y');
                $start_date = "$current_year-$current_month-01";
                $new_projects_query = "SELECT COUNT(*) as total FROM projects 
                                      WHERE assigned_to = ? 
                                      AND deleted_at IS NULL 
                                      AND created_at >= ?";
                $new_projects_stmt = $conn->prepare($new_projects_query);
                $new_projects_stmt->bind_param("is", $user_id, $start_date);
                $new_projects_stmt->execute();
                $new_projects_result = $new_projects_stmt->get_result();
                $new_projects = $new_projects_result->fetch_assoc()['total'];
                ?>
                <div class="card-value"><?php echo $project_count; ?></div>
                <div class="card-description">Total active projects</div>
            </div>
            <div class="card-footer">
                <div class="trend-indicator trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span><?php echo $new_projects; ?> New</span>
                </div>
                <span>this month</span>
            </div>
        </div>

        <!-- Stages Assigned Card -->
        <div class="overview-card theme-info" id="stagesCard">
            <div class="card-header">
                <h3 class="card-title">Stages Assigned</h3>
                <div class="card-icon">
                    <i class="fas fa-layer-group"></i>
                </div>
            </div>
            <div class="card-content">
                <?php
                // Get the count of stages assigned to the current user with assignment_status = 'assigned'
                $stages_count_query = "SELECT COUNT(*) as total FROM project_stages 
                                      WHERE assigned_to = ? 
                                      AND deleted_at IS NULL 
                                      AND assignment_status = 'assigned'";
                $stages_count_stmt = $conn->prepare($stages_count_query);
                $stages_count_stmt->bind_param("i", $user_id);
                $stages_count_stmt->execute();
                $stages_count_result = $stages_count_stmt->get_result();
                $stages_count = $stages_count_result->fetch_assoc()['total'];
                
                // Get the count of new stages assigned this month with assignment_status = 'assigned'
                $new_stages_query = "SELECT COUNT(*) as total FROM project_stages 
                            WHERE assigned_to = ? 
                            AND deleted_at IS NULL 
                            AND created_at >= ?
                            AND assignment_status = 'assigned'";
                $new_stages_stmt = $conn->prepare($new_stages_query);
                $new_stages_stmt->bind_param("is", $user_id, $start_date);
                $new_stages_stmt->execute();
                $new_stages_result = $new_stages_stmt->get_result();
                $new_stages = $new_stages_result->fetch_assoc()['total'];
                ?>
                <div class="card-value"><?php echo $stages_count; ?></div>
                <div class="card-description">Assigned project stages</div>
            </div>
            <div class="card-footer">
                <div class="trend-indicator trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span><?php echo $new_stages; ?> New</span>
                </div>
                <span>this month</span>
            </div>
        </div>

        <!-- Substages Assigned Card -->
        <div class="overview-card theme-success" id="substagesCard">
            <div class="card-header">
                <h3 class="card-title">Substages Assigned</h3>
                <div class="card-icon">
                    <i class="fas fa-tasks"></i>
                </div>
            </div>
            <div class="card-content">
                <?php
                // Get the count of substages assigned to the current user with assignment_status = 'assigned'
                $substages_count_query = "SELECT COUNT(*) as total FROM project_substages 
                                 WHERE assigned_to = ? 
                                 AND deleted_at IS NULL 
                                 AND assignment_status = 'assigned'";
                $substages_count_stmt = $conn->prepare($substages_count_query);
                $substages_count_stmt->bind_param("i", $user_id);
                $substages_count_stmt->execute();
                $substages_count_result = $substages_count_stmt->get_result();
                $substages_count = $substages_count_result->fetch_assoc()['total'];
                
                // Get the count of new substages assigned this month with assignment_status = 'assigned'
                $new_substages_query = "SELECT COUNT(*) as total FROM project_substages 
                                WHERE assigned_to = ? 
                                AND deleted_at IS NULL 
                                AND created_at >= ?
                                AND assignment_status = 'assigned'";
                $new_substages_stmt = $conn->prepare($new_substages_query);
                $new_substages_stmt->bind_param("is", $user_id, $start_date);
                $new_substages_stmt->execute();
                $new_substages_result = $new_substages_stmt->get_result();
                $new_substages = $new_substages_result->fetch_assoc()['total'];
                ?>
                <div class="card-value"><?php echo $substages_count; ?></div>
                <div class="card-description">Assigned substages</div>
            </div>
            <div class="card-footer">
                <div class="trend-indicator trend-up">
                    <i class="fas fa-arrow-up"></i>
                    <span><?php echo $new_substages; ?> New</span>
                </div>
                <span>this month</span>
            </div>
        </div>

        <!-- Stages Due Card -->
        <div class="overview-card theme-warning" id="stagesDueCard">
            <div class="card-header">
                <h3 class="card-title">Stages Due</h3>
                <div class="card-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="card-content">
                <?php
                // Get the current date
                $today = date('Y-m-d');
                $week_later = date('Y-m-d', strtotime('+7 days'));
                $two_days_later = date('Y-m-d', strtotime('+2 days'));
                
                // Get stages due within 7 days
                $stages_due_query = "SELECT COUNT(*) as total FROM project_stages 
                           WHERE assigned_to = ? 
                           AND deleted_at IS NULL 
                           AND assignment_status = 'assigned'
                           AND end_date BETWEEN ? AND ?
                           AND status != 'completed'";
                $stages_due_stmt = $conn->prepare($stages_due_query);
                $stages_due_stmt->bind_param("iss", $user_id, $today, $week_later);
                $stages_due_stmt->execute();
                $stages_due_result = $stages_due_stmt->get_result();
                $stages_due = $stages_due_result->fetch_assoc()['total'];
                
                // Get critical stages (due within 2 days)
                $critical_stages_query = "SELECT COUNT(*) as total FROM project_stages 
                                WHERE assigned_to = ? 
                                AND deleted_at IS NULL 
                                AND assignment_status = 'assigned'
                                AND end_date BETWEEN ? AND ?
                                AND status != 'completed'";
                $critical_stages_stmt = $conn->prepare($critical_stages_query);
                $critical_stages_stmt->bind_param("iss", $user_id, $today, $two_days_later);
                $critical_stages_stmt->execute();
                $critical_stages_result = $critical_stages_stmt->get_result();
                $critical_stages = $critical_stages_result->fetch_assoc()['total'];
                ?>
                <div class="card-value"><?php echo $stages_due; ?></div>
                <div class="card-description">Due within 7 days</div>
            </div>
            <div class="card-footer">
                <div class="trend-indicator trend-neutral">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $critical_stages; ?> Critical</span>
                </div>
                <span>need attention</span>
            </div>
        </div>

        <!-- Substages Due Card -->
        <div class="overview-card theme-danger" id="substagesDueCard">
            <div class="card-header">
                <h3 class="card-title">Substages Due</h3>
                <div class="card-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
            </div>
            <div class="card-content">
                <?php
                // Get the current date
                $today = date('Y-m-d');
                $week_later = date('Y-m-d', strtotime('+7 days'));
                
                // Get substages due within 7 days
                $substages_due_query = "SELECT COUNT(*) as total FROM project_substages 
                              WHERE assigned_to = ? 
                              AND deleted_at IS NULL 
                              AND assignment_status = 'assigned'
                              AND end_date BETWEEN ? AND ?
                              AND status != 'completed'";
                $substages_due_stmt = $conn->prepare($substages_due_query);
                $substages_due_stmt->bind_param("iss", $user_id, $today, $week_later);
                $substages_due_stmt->execute();
                $substages_due_result = $substages_due_stmt->get_result();
                $substages_due = $substages_due_result->fetch_assoc()['total'];
                
                // Get overdue substages
                $overdue_substages_query = "SELECT COUNT(*) as total FROM project_substages 
                                  WHERE assigned_to = ? 
                                  AND deleted_at IS NULL 
                                  AND assignment_status = 'assigned'
                                  AND end_date < ?
                                  AND status != 'completed'";
                $overdue_substages_stmt = $conn->prepare($overdue_substages_query);
                $overdue_substages_stmt->bind_param("is", $user_id, $today);
                $overdue_substages_stmt->execute();
                $overdue_substages_result = $overdue_substages_stmt->get_result();
                $overdue_substages = $overdue_substages_result->fetch_assoc()['total'];
                ?>
                <div class="card-value"><?php echo $substages_due; ?></div>
                <div class="card-description">Due within 7 days</div>
            </div>
            <div class="card-footer">
                <div class="trend-indicator trend-down">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo $overdue_substages; ?> Overdue</span>
                </div>
                <span>need immediate action</span>
            </div>
        </div>

        <!-- Upcoming Due Dates Card -->
        <div class="overview-card theme-purple" id="upcomingDatesCard">
            <div class="card-header">
                <h3 class="card-title">Upcoming Due Dates</h3>
                <div class="card-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
            </div>
            <div class="card-content">
                <div class="upcoming-dates-list">
                    <?php
                    // Get current date
                    $today_date = date('Y-m-d');
                    $tomorrow_date = date('Y-m-d', strtotime('+1 day'));
                    $week_later = date('Y-m-d', strtotime('+7 days'));
                    
                    // Fetch substages due today
                    $today_query = "SELECT title FROM project_substages 
                                  WHERE assigned_to = ? 
                                  AND deleted_at IS NULL 
                                  AND assignment_status = 'assigned' 
                                  AND end_date = ? 
                                  AND status != 'completed'
                                  ORDER BY end_date ASC
                                  LIMIT 5";
                    $today_stmt = $conn->prepare($today_query);
                    $today_stmt->bind_param("is", $user_id, $today_date);
                    $today_stmt->execute();
                    $today_result = $today_stmt->get_result();
                    
                    // Fetch substages due tomorrow
                    $tomorrow_query = "SELECT title FROM project_substages 
                                     WHERE assigned_to = ? 
                                     AND deleted_at IS NULL 
                                     AND assignment_status = 'assigned' 
                                     AND end_date = ? 
                                     AND status != 'completed'
                                     ORDER BY end_date ASC
                                     LIMIT 5";
                    $tomorrow_stmt = $conn->prepare($tomorrow_query);
                    $tomorrow_stmt->bind_param("is", $user_id, $tomorrow_date);
                    $tomorrow_stmt->execute();
                    $tomorrow_result = $tomorrow_stmt->get_result();
                    
                    // Fetch upcoming substages (not today or tomorrow, but within 7 days)
                    $upcoming_query = "SELECT title, end_date FROM project_substages 
                                     WHERE assigned_to = ? 
                                     AND deleted_at IS NULL 
                                     AND assignment_status = 'assigned' 
                                     AND end_date > ? 
                                     AND end_date <= ? 
                                     AND end_date != ?
                                     AND status != 'completed'
                                     ORDER BY end_date ASC
                                     LIMIT 10";
                    $upcoming_stmt = $conn->prepare($upcoming_query);
                    $upcoming_stmt->bind_param("isss", $user_id, $tomorrow_date, $week_later, $tomorrow_date);
                    $upcoming_stmt->execute();
                    $upcoming_result = $upcoming_stmt->get_result();
                    
                    // Display today's substages
                    if ($today_result->num_rows > 0) {
                        while ($today_substage = $today_result->fetch_assoc()) {
                            echo '<div class="upcoming-date-item">
                        <span class="date-badge today">Today</span>
                                    <span class="project-name">' . htmlspecialchars($today_substage['title']) . '</span>
                                </div>';
                        }
                    } else {
                        echo '<div class="upcoming-date-item">
                                <span class="date-badge today">Today</span>
                                <span class="project-name">No substages due today</span>
                            </div>';
                    }
                    
                    // Display tomorrow's substages
                    if ($tomorrow_result->num_rows > 0) {
                        while ($tomorrow_substage = $tomorrow_result->fetch_assoc()) {
                            echo '<div class="upcoming-date-item">
                        <span class="date-badge tomorrow">Tomorrow</span>
                                    <span class="project-name">' . htmlspecialchars($tomorrow_substage['title']) . '</span>
                                </div>';
                        }
                    } else {
                        echo '<div class="upcoming-date-item">
                                <span class="date-badge tomorrow">Tomorrow</span>
                                <span class="project-name">No substages due tomorrow</span>
                            </div>';
                    }
                    
                    // Display upcoming substages
                    if ($upcoming_result->num_rows > 0) {
                        while ($upcoming_substage = $upcoming_result->fetch_assoc()) {
                            $days_until = floor((strtotime($upcoming_substage['end_date']) - strtotime($today_date)) / (60 * 60 * 24));
                            echo '<div class="upcoming-date-item">
                                    <span class="date-badge upcoming">In ' . $days_until . ' days</span>
                                    <span class="project-name">' . htmlspecialchars($upcoming_substage['title']) . '</span>
                                </div>';
                        }
                    } else {
                        echo '<div class="upcoming-date-item">
                                <span class="date-badge upcoming">Upcoming</span>
                                <span class="project-name">No upcoming substages due</span>
                            </div>';
                    }
                    ?>
                </div>
            </div>
            <div class="card-footer">
                <div class="trend-indicator trend-neutral" onclick="showAllUpcomingDates()">
                    <i class="fas fa-calendar-check"></i>
                    <span>View All</span>
                </div>
                <span>upcoming deadlines</span>
            </div>
        </div>
    </div>
    
    <!-- Calendar View -->
    <div class="project-calendar-view">
        <div class="calendar-header">
            <div class="calendar-navigation">
                <button class="calendar-nav-btn" id="prevMonth">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <h3 class="calendar-title" id="calendarTitle">September 2023</h3>
                <button class="calendar-nav-btn" id="nextMonth">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="calendar-grid">
            <!-- Weekday headers -->
            <div class="calendar-weekday">Sun</div>
            <div class="calendar-weekday">Mon</div>
            <div class="calendar-weekday">Tue</div>
            <div class="calendar-weekday">Wed</div>
            <div class="calendar-weekday">Thu</div>
            <div class="calendar-weekday">Fri</div>
            <div class="calendar-weekday">Sat</div>
            
            <!-- Calendar days will be dynamically inserted here -->
            <div class="calendar-days" id="calendarDays"></div>
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
                            // Show 5 years back and 5 years forward
                            for ($year = $current_year - 5; $year <= $current_year + 5; $year++) {
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
                            $todo_query = "SELECT DISTINCT 
                                p.*, 
                                COUNT(DISTINCT ps.id) as total_stages,
                                COUNT(DISTINCT pss.id) as total_substages,
                                u.username as creator_name
                            FROM projects p
                            LEFT JOIN project_stages ps ON p.id = ps.project_id
                            LEFT JOIN project_substages pss ON ps.id = pss.stage_id
                            LEFT JOIN users u ON p.created_by = u.id
                            WHERE (
                                p.assigned_to = ? 
                                OR ps.assigned_to = ?
                                OR pss.assigned_to = ?
                            )
                            AND p.status IN ('pending', 'not_started')
                            AND p.deleted_at IS NULL
                            GROUP BY p.id
                            ORDER BY p.created_at DESC";
                            
                            $stmt = $conn->prepare($todo_query);
                            $stmt->bind_param("iii", $user_id, $user_id, $user_id);
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
                                            <?php if (isset($project['role_type'])): ?>
                                                <span class="role-badge <?php echo strtolower(str_replace(' ', '-', $project['role_type'])); ?>">
                                                    <?php echo $project['role_type']; ?>
                                                </span>
                                            <?php endif; ?>
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
                                            WHERE (
                                                p.assigned_to = ? 
                                                OR ps.assigned_to = ?
                                                OR pss.assigned_to = ?
                                            )
                                            AND p.deleted_at IS NULL
                                            AND (ps.status = 'in_progress' OR pss.status = 'in_progress')
                                            GROUP BY p.id
                                            ORDER BY p.updated_at DESC";
                            
                            $stmt = $conn->prepare($progress_query);
                            $stmt->bind_param("iii", $user_id, $user_id, $user_id);
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
                                            <?php if (isset($project['role_type'])): ?>
                                                <span class="role-badge <?php echo strtolower(str_replace(' ', '-', $project['role_type'])); ?>">
                                                    <?php echo $project['role_type']; ?>
                                                </span>
                                            <?php endif; ?>
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
                            $review_query = "SELECT DISTINCT
                                p.id as project_id,
                                p.title as project_title,
                                p.project_type,
                                ps.id as stage_id,
                                ps.stage_number,
                                ps.assigned_to as stage_assigned_to,
                                pss.id as substage_id,
                                pss.title as substage_title,
                                pss.substage_number,
                                pss.end_date,
                                pss.assigned_to as substage_assigned_to,
                                u.username as reviewer_name,
                                CASE 
                                    WHEN p.assigned_to = ? THEN 'Project Owner'
                                    WHEN ps.assigned_to = ? THEN 'Stage Owner'
                                    WHEN pss.assigned_to = ? THEN 'Substage Owner'
                                END as role_type
                            FROM project_substages pss
                            JOIN project_stages ps ON pss.stage_id = ps.id
                            JOIN projects p ON ps.project_id = p.id
                            LEFT JOIN users u ON pss.assigned_to = u.id
                            WHERE (
                                p.assigned_to = ? 
                                OR ps.assigned_to = ?
                                OR pss.assigned_to = ?
                            )
                            AND pss.status = 'in_review'
                            AND p.deleted_at IS NULL
                            ORDER BY pss.updated_at DESC";
                            
                            $stmt = $conn->prepare($review_query);
                            $stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
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
                                            <?php if (isset($substage['role_type'])): ?>
                                                <span class="role-badge <?php echo strtolower(str_replace(' ', '-', $substage['role_type'])); ?>">
                                                    <?php echo $substage['role_type']; ?>
                                                </span>
                                            <?php endif; ?>
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
                            $done_query = "SELECT DISTINCT
                                p.*,
                                COUNT(DISTINCT ps.id) as total_stages,
                                COUNT(DISTINCT pss.id) as total_substages,
                                COUNT(DISTINCT CASE WHEN ps.status = 'completed' THEN ps.id END) as completed_stages,
                                COUNT(DISTINCT CASE WHEN pss.status = 'completed' THEN pss.id END) as completed_substages,
                                u.username as creator_name,
                                MAX(GREATEST(ps.updated_at, pss.updated_at)) as last_completed_at
                            FROM projects p
                            LEFT JOIN project_stages ps ON p.id = ps.project_id
                            LEFT JOIN project_substages pss ON ps.id = pss.stage_id
                            LEFT JOIN users u ON p.created_by = u.id
                            WHERE (
                                p.assigned_to = ? 
                                OR ps.assigned_to = ?
                                OR pss.assigned_to = ?
                            )
                            AND p.deleted_at IS NULL
                            GROUP BY p.id
                            HAVING 
                                (total_stages = completed_stages AND total_stages > 0)
                                AND (total_substages = completed_substages AND total_substages > 0)
                            ORDER BY last_completed_at DESC
                            LIMIT 10"; // Limiting to most recent 10 completed projects
                            
                            $stmt = $conn->prepare($done_query);
                            $stmt->bind_param("iii", $user_id, $user_id, $user_id);
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
                                            <?php if (isset($project['role_type'])): ?>
                                                <span class="role-badge <?php echo strtolower(str_replace(' ', '-', $project['role_type'])); ?>">
                                                    <?php echo $project['role_type']; ?>
                                                </span>
                                            <?php endif; ?>
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
                            <!-- Chat Icon -->
<div class="floating-chat-trigger">
    <div class="chat-bubble-wrapper">
        <span class="chat-notification-bubble">3</span>
        <button class="chat-bubble-button" onclick="toggleChatInterface()">
            <i class="fas fa-comments"></i>
        </button>
    </div>
</div>

<!-- Chat Interface -->
<div class="floating-chat-interface">
    <div class="chat-interface-header">
        <h3>Chats</h3>
        <div class="header-actions">
            <button class="new-chat-button">
                <i class="fas fa-edit"></i>
            </button>
            <button class="new-group-button">
                <i class="fas fa-users"></i>
            </button>
            <button class="chat-close-button" onclick="toggleChatInterface()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <div class="chat-search">
        <div class="search-container">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search or start new chat">
        </div>
    </div>

    <div class="chat-users-list">
        <!-- Sarah Johnson -->
        <div class="chat-user-item">
            <div class="user-avatar">
                <img src="assets/default-avatar.png" alt="Sarah Johnson">
            </div>
            <div class="user-info">
                <div class="user-name">Sarah Johnson</div>
                <div class="message-preview">Hi, how are you doing?</div>
            </div>
            <div class="message-metadata">
                <div class="message-time">10:30 AM</div>
                <div class="unread-count">2</div>
            </div>
        </div>

        <!-- John Smith -->
        <div class="chat-user-item">
            <div class="user-avatar">
                <img src="assets/default-avatar.png" alt="John Smith">
            </div>
            <div class="user-info">
                <div class="user-name">John Smith</div>
                <div class="message-preview">Can we meet tomorrow?</div>
            </div>
            <div class="message-metadata">
                <div class="message-time">9:45 AM</div>
            </div>
        </div>

        <!-- Emily Wilson -->
        <div class="chat-user-item">
            <div class="user-avatar">
                <img src="assets/default-avatar.png" alt="Emily Wilson">
            </div>
            <div class="user-info">
                <div class="user-name">Emily Wilson</div>
                <div class="message-preview">Thanks for your help!</div>
            </div>
            <div class="message-metadata">
                <div class="message-time">Yesterday</div>
            </div>
        </div>
        
        <!-- Michael Brown -->
        <div class="chat-user-item">
            <div class="user-avatar">
                <img src="assets/default-avatar.png" alt="Michael Brown">
            </div>
            <div class="user-info">
                <div class="user-name">Michael Brown</div>
                <div class="message-preview">I sent you the files</div>
            </div>
            <div class="message-metadata">
                <div class="message-time">Yesterday</div>
                <div class="unread-count">1</div>
            </div>
        </div>
        
        <!-- Jessica Taylor -->
        <div class="chat-user-item">
            <div class="user-avatar">
                <img src="assets/default-avatar.png" alt="Jessica Taylor">
            </div>
            <div class="user-info">
                <div class="user-name">Jessica Taylor</div>
                <div class="message-preview">Let me know when you are free...</div>
            </div>
            <div class="message-metadata">
                <div class="message-time">Tuesday</div>
            </div>
        </div>
    </div>
    <!-- Add this right after the chat-users-list div -->
<div class="chat-conversation" style="display: none;">
    <div class="chat-conversation-header">
        <button class="back-button">
            <i class="fas fa-arrow-left"></i>
        </button>
        <div class="chat-contact-info">
            <div class="contact-avatar">
                <img src="assets/default-avatar.png" alt="User">
            </div>
            <div class="contact-details">
                <div class="contact-name">User Name</div>
                <div class="contact-status">online</div>
            </div>
        </div>
        <div class="header-actions">
            <button class="chat-close-button" onclick="toggleChatInterface()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <!-- Add this search bar -->
    <div class="chat-conversation-search" style="display: none;">
    <div class="search-input-container">
        <i class="fas fa-arrow-left search-back"></i>
        <i class="fas fa-times search-clear"></i>
        <input type="text" class="conversation-search-input" placeholder="Search...">
    </div>
    <div class="search-results-info">
        <span class="results-count">0/0</span>
        <div class="search-navigation">
            <button class="search-up-btn"><i class="fas fa-chevron-up"></i></button>
            <button class="search-down-btn"><i class="fas fa-chevron-down"></i></button>
        </div>
    </div>
    </div>
    <script>
    // Add this right after the existing header HTML
    document.querySelector('.chat-contact-info').insertAdjacentHTML('afterend', 
        `<button class="search-toggle-btn">
            <i class="fas fa-search"></i>
        </button>`
    );
</script>
    
    
    <div class="chat-messages">
        <!-- Messages will appear here -->
    </div>
    
    <div class="chat-input-area">
        <div class="chat-input-actions">
            <button class="emoji-button">
                <i class="far fa-smile"></i>
            </button>
            <label for="file-input" class="attachment-button">
                <i class="fas fa-paperclip"></i>
            </label>
            <input type="file" id="file-input" style="display: none" multiple>
        </div>
        <div class="input-wrapper">
            <input type="text" class="message-input" placeholder="Type a message">
        </div>
        <button class="send-button">
            <i class="fas fa-paper-plane"></i>
        </button>
        
        <!-- Emoji picker -->
        <div class="emoji-picker" style="display: none;">
            <div class="emoji-categories">
                <button class="category-button active" data-category="smileys">😊</button>
                <button class="category-button" data-category="people">👨</button>
                <button class="category-button" data-category="animals">🐶</button>
                <button class="category-button" data-category="food">🍎</button>
                <button class="category-button" data-category="travel">🚗</button>
                <button class="category-button" data-category="activities">⚽</button>
                <button class="category-button" data-category="objects">💡</button>
                <button class="category-button" data-category="symbols">❤️</button>
            </div>
            <div class="emoji-container">
                <!-- Smileys/Emotions -->
                <div class="emoji-grid" data-category="smileys">
                    <span class="emoji-item">😀</span>
                    <span class="emoji-item">😁</span>
                    <span class="emoji-item">😂</span>
                    <span class="emoji-item">🤣</span>
                    <span class="emoji-item">😃</span>
                    <span class="emoji-item">😄</span>
                    <span class="emoji-item">😅</span>
                    <span class="emoji-item">😆</span>
                    <span class="emoji-item">😉</span>
                    <span class="emoji-item">😊</span>
                    <span class="emoji-item">😋</span>
                    <span class="emoji-item">😎</span>
                    <span class="emoji-item">😍</span>
                    <span class="emoji-item">😘</span>
                    <span class="emoji-item">🥰</span>
                    <span class="emoji-item">😗</span>
                    <span class="emoji-item">😙</span>
                    <span class="emoji-item">😚</span>
                    <span class="emoji-item">🙂</span>
                    <span class="emoji-item">🤗</span>
                </div>
                <!-- More emoji categories would go here -->
            </div>
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
            // First get geolocation
            if (!navigator.geolocation) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Geolocation is not supported by your browser. Please use a modern browser to punch in.'
                });
                return;
            }

            // Get user's location first
            Swal.fire({
                title: 'Getting Location...',
                text: 'Please allow location access to proceed',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    // Location successfully obtained, now show camera
                    const latitude = position.coords.latitude;
                    const longitude = position.coords.longitude;
                    const accuracy = position.coords.accuracy;
                    
                    // Get address from coordinates using reverse geocoding
                    const getAddressFromCoordinates = (lat, lng) => {
                        return new Promise((resolve, reject) => {
                            fetch(`https://geocode.maps.co/reverse?lat=${lat}&lon=${lng}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data && data.display_name) {
                                        resolve(data);
                                    } else {
                                        reject("No address found");
                                    }
                                })
                                .catch(error => {
                                    console.error("Reverse geocoding error:", error);
                                    reject(error);
                                });
                        });
                    };
                    
                    // Now open camera for selfie with location information
                    Swal.fire({
                        title: 'Take a Selfie',
                        html: `
                            <div class="camera-container">
                                <video id="camera-stream" width="100%" autoplay playsinline></video>
                                <canvas id="canvas" style="display:none;"></canvas>
                                <div class="location-details">
                                    <h4><i class="fas fa-map-marker-alt"></i> Location Details</h4>
                                    <div class="coordinates">
                                        <div class="coordinate-item">
                                            <span class="label">Latitude</span>
                                            <span class="value">${latitude.toFixed(6)}</span>
                                        </div>
                                        <div class="coordinate-item">
                                            <span class="label">Longitude</span>
                                            <span class="value">${longitude.toFixed(6)}</span>
                                        </div>
                                    </div>
                                    <div class="accuracy">
                                        <span class="label">Accuracy:</span>
                                        <span class="value ${accuracy <= 20 ? 'accuracy-high' : accuracy <= 100 ? 'accuracy-medium' : 'accuracy-low'}">
                                            ${accuracy.toFixed(1)} meters
                                        </span>
                                    </div>
                                    <div class="address">
                                        <span class="label">Address:</span>
                                        <div class="address-text" id="address-text">
                                            <div class="loading"><i class="fas fa-spinner"></i> Fetching address...</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="selfie-controls">
                                    <button id="capture-btn" class="swal2-styled swal2-confirm">Take Photo</button>
                                </div>
                            </div>
                        `,
                        showConfirmButton: false,
                        showCancelButton: true,
                        cancelButtonText: 'Cancel',
                        allowOutsideClick: false,
                        didOpen: () => {
                            // Initialize camera
                            const video = document.getElementById('camera-stream');
                            const canvas = document.getElementById('canvas');
                            const captureBtn = document.getElementById('capture-btn');
                            const addressText = document.getElementById('address-text');
                            
                            // Access user's camera
                            navigator.mediaDevices.getUserMedia({ video: true, audio: false })
                                .then((stream) => {
                                    video.srcObject = stream;
                                })
                                .catch((error) => {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Camera Error',
                                        text: 'Unable to access camera. Please ensure camera permissions are granted.'
                                    });
                                });
                            
                            // Get and display address
                            getAddressFromCoordinates(latitude, longitude)
                                .then(data => {
                                    if (addressText) {
                                        // Format the address components
                                        let formattedAddress = data.display_name || 'Address not available';
                                        
                                        // Extract important address components if available
                                        const addressComponents = [];
                                        if (data.address) {
                                            if (data.address.road) addressComponents.push(data.address.road);
                                            if (data.address.house_number) addressComponents.push(data.address.house_number);
                                            if (data.address.suburb) addressComponents.push(data.address.suburb);
                                            if (data.address.city || data.address.town) addressComponents.push(data.address.city || data.address.town);
                                            if (data.address.state) addressComponents.push(data.address.state);
                                            if (data.address.postcode) addressComponents.push(data.address.postcode);
                                            if (data.address.country) addressComponents.push(data.address.country);
                                        }
                                        
                                        // Use formatted components if available, otherwise use display_name
                                        const finalAddress = addressComponents.length > 0 ? addressComponents.join(', ') : formattedAddress;
                                        
                                        addressText.innerHTML = finalAddress;
                                    }
                                })
                                .catch(error => {
                                    if (addressText) {
                                        addressText.innerHTML = 'Unable to retrieve address';
                                    }
                                    console.error("Error getting address:", error);
                                });
                            
                            // Handle capture button click
                            captureBtn.addEventListener('click', function() {
                                // Draw video frame to canvas
                                canvas.width = video.videoWidth;
                                canvas.height = video.videoHeight;
                                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                                
                                // Get the image data
                                const imageData = canvas.toDataURL('image/jpeg');
                                
                                // Stop all video streams
                                video.srcObject.getTracks().forEach(track => track.stop());
                                
                                // Submit the punch in request with selfie and location
                                submitPunchIn(imageData, latitude, longitude, position.coords.accuracy);
                            });
                        },
                        willClose: () => {
                            // Make sure to stop the camera stream when dialog closes
                            const video = document.getElementById('camera-stream');
                            if (video && video.srcObject) {
                                video.srcObject.getTracks().forEach(track => track.stop());
                            }
                        }
                    });
                },
                (error) => {
                    // Location error
                    Swal.fire({
                        icon: 'error',
                        title: 'Location Error',
                        text: 'Unable to get your location. Please enable location services and try again.'
                    });
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        }

        function submitPunchIn(selfieImage, latitude, longitude, accuracy) {
            Swal.fire({
                title: 'Punching In...',
                text: 'Please wait while we process your request',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            // Get the current device info and IP automatically from server
            // The location string will be generated on the server from lat/long
            fetch('punch.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'punch_in',
                    punch_in_photo: selfieImage,    // Changed from image_data to punch_in_photo
                    latitude: latitude,         // For the latitude column (decimal)
                    longitude: longitude,       // For the longitude column (decimal)
                    accuracy: accuracy,         // For the accuracy column (float)
                    device_info: navigator.userAgent, // For the device_info column
                    // ip_address will be captured server-side
                    // location string will be generated server-side from coordinates
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
            // First get geolocation
            if (!navigator.geolocation) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Geolocation is not supported by your browser. Please use a modern browser to punch out.'
                });
                return;
            }

            // Get user's location first
            Swal.fire({
                title: 'Getting Location...',
                text: 'Please allow location access to proceed',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    // Location successfully obtained, now show camera
                    const latitude = position.coords.latitude;
                    const longitude = position.coords.longitude;
                    const accuracy = position.coords.accuracy;
                    
                    // Store location info for comparison
                    console.log(`Punch out location: Lat ${latitude}, Long ${longitude}, Accuracy ${accuracy}m`);
                    
                    // Get address from coordinates using reverse geocoding
                    const getAddressFromCoordinates = (lat, lng) => {
                        return new Promise((resolve, reject) => {
                            fetch(`https://geocode.maps.co/reverse?lat=${lat}&lon=${lng}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data && data.display_name) {
                                        resolve(data);
                                    } else {
                                        reject("No address found");
                                    }
                                })
                                .catch(error => {
                                    console.error("Reverse geocoding error:", error);
                                    reject(error);
                                });
                        });
                    };
                    
                    // Now open camera for selfie
                    Swal.fire({
                        title: 'Take a Selfie for Punch Out',
                        html: `
                            <div class="camera-container">
                                <video id="camera-stream" width="100%" autoplay playsinline></video>
                                <canvas id="canvas" style="display:none;"></canvas>
                                <div class="location-details">
                                    <h4><i class="fas fa-map-marker-alt"></i> Location Details</h4>
                                    <div class="coordinates">
                                        <div class="coordinate-item">
                                            <span class="label">Latitude</span>
                                            <span class="value">${latitude.toFixed(6)}</span>
                                        </div>
                                        <div class="coordinate-item">
                                            <span class="label">Longitude</span>
                                            <span class="value">${longitude.toFixed(6)}</span>
                                        </div>
                                    </div>
                                    <div class="accuracy">
                                        <span class="label">Accuracy:</span>
                                        <span class="value ${accuracy <= 20 ? 'accuracy-high' : accuracy <= 100 ? 'accuracy-medium' : 'accuracy-low'}">
                                            ${accuracy.toFixed(1)} meters
                                        </span>
                                    </div>
                                    <div class="address">
                                        <span class="label">Address:</span>
                                        <div class="address-text" id="address-text">
                                            <div class="loading"><i class="fas fa-spinner"></i> Fetching address...</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="selfie-controls">
                                    <button id="capture-btn" class="swal2-styled swal2-confirm">Take Photo</button>
                                </div>
                            </div>
                        `,
                        showConfirmButton: false,
                        showCancelButton: true,
                        cancelButtonText: 'Cancel',
                        allowOutsideClick: false,
                        didOpen: () => {
                            // Initialize camera
                            const video = document.getElementById('camera-stream');
                            const canvas = document.getElementById('canvas');
                            const captureBtn = document.getElementById('capture-btn');
                            const addressText = document.getElementById('address-text');
                            
                            // Access user's camera
                            navigator.mediaDevices.getUserMedia({ video: true, audio: false })
                                .then((stream) => {
                                    video.srcObject = stream;
                                })
                                .catch((error) => {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Camera Error',
                                        text: 'Unable to access camera. Please ensure camera permissions are granted.'
                                    });
                                });
                            
                            // Get and display address
                            getAddressFromCoordinates(latitude, longitude)
                                .then(data => {
                                    if (addressText) {
                                        // Format the address components
                                        let formattedAddress = data.display_name || 'Address not available';
                                        
                                        // Extract important address components if available
                                        const addressComponents = [];
                                        if (data.address) {
                                            if (data.address.road) addressComponents.push(data.address.road);
                                            if (data.address.house_number) addressComponents.push(data.address.house_number);
                                            if (data.address.suburb) addressComponents.push(data.address.suburb);
                                            if (data.address.city || data.address.town) addressComponents.push(data.address.city || data.address.town);
                                            if (data.address.state) addressComponents.push(data.address.state);
                                            if (data.address.postcode) addressComponents.push(data.address.postcode);
                                            if (data.address.country) addressComponents.push(data.address.country);
                                        }
                                        
                                        // Use formatted components if available, otherwise use display_name
                                        const finalAddress = addressComponents.length > 0 ? addressComponents.join(', ') : formattedAddress;
                                        
                                        addressText.innerHTML = finalAddress;
                                    }
                                })
                                .catch(error => {
                                    if (addressText) {
                                        addressText.innerHTML = 'Unable to retrieve address';
                                    }
                                    console.error("Error getting address:", error);
                                });
                            
                            // Handle capture button click
                            captureBtn.addEventListener('click', function() {
                                // Draw video frame to canvas
                                canvas.width = video.videoWidth;
                                canvas.height = video.videoHeight;
                                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                                
                                // Get the image data
                                const imageData = canvas.toDataURL('image/jpeg');
                                
                                // Stop all video streams
                                video.srcObject.getTracks().forEach(track => track.stop());
                                
                                // Continue with work report prompt after photo is taken
                                promptForWorkReport(imageData, latitude, longitude, accuracy);
                            });
                        },
                        willClose: () => {
                            // Make sure to stop the camera stream when dialog closes
                            const video = document.getElementById('camera-stream');
                            if (video && video.srcObject) {
                                video.srcObject.getTracks().forEach(track => track.stop());
                            }
                        }
                    });
                },
                (error) => {
                    // Location error
                    let errorMessage = 'Unable to get your location.';
                    
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage += ' Location access was denied.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage += ' Location information is unavailable.';
                            break;
                        case error.TIMEOUT:
                            errorMessage += ' Location request timed out.';
                            break;
                        default:
                            errorMessage += ' An unknown error occurred.';
                    }
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Location Error',
                        text: errorMessage,
                        footer: 'Please enable location services and try again.'
                    });
                },
                { 
                    enableHighAccuracy: true, 
                    timeout: 15000, 
                    maximumAge: 0 
                }
            );
        }
        
        // Variable to track if we're returning from overtime claim
        let returningFromOvertimeClaim = false;
        
        function promptForWorkReport(selfieImage, latitude, longitude, accuracy, existingWorkReport = '', skipOvertimeClaim = false) {
            // Set the flag when returning from overtime claim
            returningFromOvertimeClaim = skipOvertimeClaim;
            
            // For testing purposes - allow claiming any overtime (removed minimum requirement)
            let hasClaimableOvertime = !skipOvertimeClaim;
            let overtimeMinutes = 0;
            
            // Get current time and calculate overtime if user is punched in
            <?php if ($is_punched_in && isset($shift_details) && $shift_details['end_time']): ?>
                const currentTime = new Date();
                const shiftEndTime = new Date();
                
                // Parse shift end time
                const [endHours, endMinutes] = "<?php echo $shift_details['end_time']; ?>".split(':').map(Number);
                shiftEndTime.setHours(endHours, endMinutes, 0, 0);
                
                // Calculate difference in minutes
                if (currentTime > shiftEndTime) {
                    const diffMs = currentTime - shiftEndTime;
                    overtimeMinutes = Math.floor(diffMs / 60000); // Convert ms to minutes
                    // Always allow claiming overtime for testing
                    hasClaimableOvertime = true;
                } else {
                    // For testing - set some default overtime minutes even if not yet past shift end
                    overtimeMinutes = 45; // Example value for testing
                }
            <?php else: ?>
                // For testing - allow claiming even without shift data
                overtimeMinutes = 60; // Example value for testing
            <?php endif; ?>
            
            // Format overtime for display
            const overtimeHours = Math.floor(overtimeMinutes / 60);
            const remainingMinutes = overtimeMinutes % 60;
            const overtimeText = `${overtimeHours}h ${remainingMinutes}m`;
            
            Swal.fire({
                title: '<span style="color: #1e293b; font-size: 24px; font-weight: 600;">Daily Work Report</span>',
                html: `
                    <div class="punch-out-form">
                        <textarea id="workReport" 
                            class="swal2-textarea" 
                            placeholder="Please provide details about your work today..."
                            rows="6"
                        >${existingWorkReport}</textarea>
                        ${!skipOvertimeClaim ? `
                        <div class="overtime-claim-section">
                            <button type="button" id="claimOvertimeBtn" class="claim-overtime-btn">
                                <i class="fas fa-clock"></i> Claim Overtime (${overtimeText})
                            </button>
                        </div>
                        ` : ''}
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
                didOpen: () => {
                    // Set up claim overtime button functionality
                    const claimOvertimeBtn = document.getElementById('claimOvertimeBtn');
                    const workReportTextarea = document.getElementById('workReport');
                    
                    // Add warning message container below textarea
                    const warningDiv = document.createElement('div');
                    warningDiv.className = 'work-report-warning';
                    warningDiv.style.display = 'none';
                    warningDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Please fill your work report first';
                    workReportTextarea.insertAdjacentElement('afterend', warningDiv);
                    
                    if (claimOvertimeBtn && !claimOvertimeBtn.disabled) {
                        claimOvertimeBtn.addEventListener('click', function() {
                            // Store the current work report content
                            const workReportContent = workReportTextarea.value.trim();
                            
                            // Check if the work sheet is filled
                            if (!workReportContent) {
                                // Show warning and focus on textarea
                                warningDiv.style.display = 'block';
                                workReportTextarea.focus();
                                
                                // Add red border to textarea
                                workReportTextarea.classList.add('error');
                                
                                // Hide warning after 3 seconds
                                setTimeout(() => {
                                    warningDiv.style.display = 'none';
                                    workReportTextarea.classList.remove('error');
                                }, 3000);
                                
                                return;
                            }
                            
                            // Save the current work report content before opening overtime modal
                            const currentWorkReport = workReportTextarea.value.trim();
                            
                            // Store the original SweetAlert configuration
                            const originalTitle = '<span style="color: #1e293b; font-size: 24px; font-weight: 600;">Daily Work Report</span>';
                            const originalHtml = document.querySelector('.punch-out-form').outerHTML;
                            
                            // If work report is filled, proceed with overtime claim
                            Swal.fire({
                                title: '<span class="ot-modal-title">Overtime Claim</span>',
                                html: `
                                    <div class="ot-claim-container">
                                        <div class="ot-form-section">
                                            <label class="ot-label" for="overtimeDate">Date:</label>
                                            <div class="ot-date-field">${new Date().toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'})}</div>
                                        </div>
                                        
                                        <div class="ot-form-section">
                                            <label class="ot-label">Overtime Duration:</label>
                                            <div class="ot-duration-display"><strong>${overtimeText}</strong></div>
                                        </div>
                                        
                                        <div class="ot-form-section ot-checkbox-container">
                                            <input type="checkbox" id="managerAwareness" class="ot-awareness-checkbox">
                                            <label for="managerAwareness" class="ot-checkbox-label">Do You Have Approval Of Overtime With Your Manager?</label>
                                        </div>
                                        
                                        <div class="ot-form-section">
                                            <label class="ot-label" for="managerSelect">Select Manager:</label>
                                            <select id="managerSelect" class="ot-manager-dropdown">
                                                <option value="">-- Select Manager --</option>
                                                <?php
                                                // Fetch managers with Senior Manager roles
                                                $manager_query = "SELECT id, username FROM users WHERE role IN ('Senior Manager (Studio)', 'Senior Manager (Site)') ORDER BY username ASC";
                                                $manager_result = $conn->query($manager_query);
                                                
                                                if ($manager_result && $manager_result->num_rows > 0) {
                                                    while ($manager = $manager_result->fetch_assoc()) {
                                                        echo '<option value="' . $manager['id'] . '">' . htmlspecialchars($manager['username']) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        
                                        <div class="ot-form-section">
                                            <label class="ot-label" for="overtimeReason">Overtime Work Report:</label>
                                            <textarea id="overtimeReason" class="ot-work-textarea" placeholder="Please explain what work you completed during overtime..." rows="4"></textarea>
                                        </div>
                                    </div>
                                `,
                                customClass: {
                                    confirmButton: 'send-overtime-btn',
                                    popup: 'overtime-claim-popup'
                                },
                                showCancelButton: true,
                                confirmButtonText: '<i class="fas fa-paper-plane"></i> Send Overtime',
                                cancelButtonText: 'Cancel',
                                preConfirm: () => {
                                    const overtimeReason = document.getElementById('overtimeReason').value.trim();
                                    const managerAwareness = document.getElementById('managerAwareness').checked;
                                    const managerId = document.getElementById('managerSelect').value;
                                    
                                    // Validate all required fields
                                    if (!overtimeReason) {
                                        Swal.showValidationMessage('Please provide details about your overtime work');
                                        return false;
                                    }
                                    
                                    if (!managerAwareness) {
                                        Swal.showValidationMessage('Please confirm if your manager was aware of your overtime');
                                        return false;
                                    }
                                    
                                    if (!managerId) {
                                        Swal.showValidationMessage('Please select a manager');
                                        return false;
                                    }
                                    
                                    return {
                                        reason: overtimeReason,
                                        managerAware: managerAwareness,
                                        managerId: managerId
                                    };
                                }
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    // Create overtime data object
                                    const overtimeData = JSON.stringify({
                                        duration: overtimeMinutes,
                                        reason: result.value.reason,
                                        managerAware: result.value.managerAware,
                                        managerId: result.value.managerId,
                                        date: new Date().toISOString().split('T')[0] // Current date in YYYY-MM-DD format
                                    });
                                    
                                    // Show brief confirmation
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Overtime Sent',
                                        text: 'Your overtime has been recorded',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        // Return to the work report modal with the overtime claimed
                                        Swal.fire({
                                            title: originalTitle,
                                            html: `
                                                <div class="punch-out-form">
                                                    <textarea id="workReport" 
                                                        class="swal2-textarea" 
                                                        placeholder="Please provide details about your work today..."
                                                        rows="6"
                                                    >${currentWorkReport}</textarea>
                                                    <div class="overtime-claim-section">
                                                        <button type="button" id="claimOvertimeBtn" class="claim-overtime-btn claimed" disabled>
                                                            <i class="fas fa-check-circle"></i> Overtime Claimed
                                                        </button>
                                                        <input type="hidden" id="overtimeData" value='${overtimeData}'>
                                                    </div>
                                                    <div style="text-align: center; margin-top: 15px;">
                                                        <button type="button" class="direct-punch-out-btn punch-out-confirm-btn" style="background: #e53e3e !important;">
                                                            <i class="fas fa-sign-out-alt"></i> Punch Out Now
                                                        </button>
                                                    </div>
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
                                            didOpen: () => {
                                                // Add event listener for direct punch out button
                                                const directPunchOutBtn = document.getElementById('directPunchOutBtn');
                                                if (directPunchOutBtn) {
                                                    directPunchOutBtn.addEventListener('click', () => {
                                                        // Close the current modal
                                                        Swal.close();
                                                        
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
                                                        
                                                        // Send punch out request with work report and overtime data
                                                        fetch('punch.php', {
                                                            method: 'POST',
                                                            headers: {
                                                                'Content-Type': 'application/json',
                                                            },
                                                            body: JSON.stringify({
                                                                action: 'punch_out',
                                                                work_report: document.getElementById('workReport').value.trim(),
                                                                punch_out_photo: selfieImage,
                                                                latitude: latitude,
                                                                longitude: longitude,
                                                                accuracy: accuracy,
                                                                overtime_data: document.getElementById('overtimeData').value
                                                            })
                                                        })
                                                        .then(response => response.json())
                                                        .then(data => {
                                                            if (data.success) {
                                                                // Create summary HTML with potential location change warning
                                                                let summaryHtml = `
                                                                    <div class="punch-out-summary">
                                                                        <p class="punch-time">${data.message}</p>
                                                                        <div class="time-details">
                                                                            ${data.working_hours.split('\n').map(line => 
                                                                                `<p class="${line.toLowerCase().includes('overtime') ? 'overtime-hours' : 'regular-hours'}">${line}</p>`
                                                                            ).join('')}
                                                                        </div>`;
                                                                            
                                                                // Add location warning if detected
                                                                if (data.location_changed) {
                                                                    summaryHtml += `
                                                                        <div class="location-warning">
                                                                            <p class="warning-title"><i class="fas fa-map-marker-alt"></i> Location Change Detected</p>
                                                                            <p class="warning-message">${data.location_message}</p>
                                                                        </div>`;
                                                                }
                                                                        
                                                                summaryHtml += `
                                                                        <div class="work-report-summary">
                                                                            <h4>Work Report:</h4>
                                                                            <p>${document.getElementById('workReport').value.trim()}</p>
                                                                        </div>
                                                                    </div>`;

                                                                Swal.fire({
                                                                    icon: data.location_changed ? 'warning' : 'success',
                                                                    title: data.location_changed ? 'Success with Warning' : 'Success!',
                                                                    html: summaryHtml,
                                                                    showConfirmButton: true,
                                                                    confirmButtonText: 'OK',
                                                                    customClass: {
                                                                        popup: 'punch-out-popup'
                                                                    }
                                                                }).then(() => {
                                                                    window.location.reload();
                                                                });
                                                            } else {
                                                                Swal.fire({
                                                                    icon: 'error',
                                                                    title: 'Error',
                                                                    text: data.message || 'An error occurred while processing your request.'
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
                                                    });
                                                }
                },
                preConfirm: () => {
                    const workReport = document.getElementById('workReport').value.trim();
                    if (!workReport) {
                        Swal.showValidationMessage('Please enter your work report');
                        return false;
                    }
                                                
                                                // Return both work report and overtime data
                                                return {
                                                    workReport: workReport,
                                                    overtimeData: document.getElementById('overtimeData').value,
                                                    // We need to indicate this is the first submission after overtime claim
                                                    fromOvertimeClaim: true
                                                };
                                            }
                                        });
                                    });
                                } else {
                                    // If cancelled, return to original work report modal
                                    Swal.fire({
                                        title: originalTitle,
                                        html: originalHtml,
                                        showCancelButton: true,
                                        confirmButtonText: 'Submit & Punch Out',
                                        cancelButtonText: 'Cancel',
                                        customClass: {
                                            popup: 'punch-out-popup',
                                            confirmButton: 'punch-out-confirm-btn',
                                            cancelButton: 'punch-out-cancel-btn'
                                        },
                                                                                    didOpen: () => {
                                                // Restore the work report content
                                                document.getElementById('workReport').value = currentWorkReport;
                                                
                                                // Re-attach event listener to the new claim overtime button
                                                const newClaimBtn = document.getElementById('claimOvertimeBtn');
                                                if (newClaimBtn) {
                                                    newClaimBtn.addEventListener('click', arguments.callee);
                                                }
                                                
                                                // Add event listener for direct punch out button
                                                const directPunchOutBtn = document.getElementById('directPunchOutBtn');
                                                if (directPunchOutBtn) {
                                                    directPunchOutBtn.addEventListener('click', () => {
                                                        // Close the current modal
                                                        Swal.close();
                                                        
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
                                                        
                                                        // Send punch out request with work report and overtime data
                                                        fetch('punch.php', {
                                                            method: 'POST',
                                                            headers: {
                                                                'Content-Type': 'application/json',
                                                            },
                                                            body: JSON.stringify({
                                                                action: 'punch_out',
                                                                work_report: currentWorkReport,
                                                                punch_out_photo: selfieImage,
                                                                latitude: latitude,
                                                                longitude: longitude,
                                                                accuracy: accuracy,
                                                                overtime_data: overtimeData
                                                            })
                                                        })
                                                        .then(response => response.json())
                                                        .then(data => {
                                                            if (data.success) {
                                                                // Create summary HTML with potential location change warning
                                                                let summaryHtml = `
                                                                    <div class="punch-out-summary">
                                                                        <p class="punch-time">${data.message}</p>
                                                                        <div class="time-details">
                                                                            ${data.working_hours.split('\n').map(line => 
                                                                                `<p class="${line.toLowerCase().includes('overtime') ? 'overtime-hours' : 'regular-hours'}">${line}</p>`
                                                                            ).join('')}
                                                                        </div>`;
                                                                            
                                                                // Add location warning if detected
                                                                if (data.location_changed) {
                                                                    summaryHtml += `
                                                                        <div class="location-warning">
                                                                            <p class="warning-title"><i class="fas fa-map-marker-alt"></i> Location Change Detected</p>
                                                                            <p class="warning-message">${data.location_message}</p>
                                                                        </div>`;
                                                                }
                                                                        
                                                                summaryHtml += `
                                                                        <div class="work-report-summary">
                                                                            <h4>Work Report:</h4>
                                                                            <p>${currentWorkReport}</p>
                                                                        </div>
                                                                    </div>`;

                                                                Swal.fire({
                                                                    icon: data.location_changed ? 'warning' : 'success',
                                                                    title: data.location_changed ? 'Success with Warning' : 'Success!',
                                                                    html: summaryHtml,
                                                                    showConfirmButton: true,
                                                                    confirmButtonText: 'OK',
                                                                    customClass: {
                                                                        popup: 'punch-out-popup'
                                                                    }
                                                                }).then(() => {
                                                                    window.location.reload();
                                                                });
                                                            } else {
                                                                Swal.fire({
                                                                    icon: 'error',
                                                                    title: 'Error',
                                                                    text: data.message || 'An error occurred while processing your request.'
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
                                                    });
                                                }
                                            }
                                    });
                                }
                            });
                        });
                    }
                },
                preConfirm: () => {
                    const workReport = document.getElementById('workReport').value.trim();
                    if (!workReport) {
                        Swal.showValidationMessage('Please enter your work report');
                        return false;
                    }
                    
                    // Return both work report and overtime data if available
                    const overtimeData = document.getElementById('overtimeData');
                    
                    // If we're returning from overtime claim and this is the second submission,
                    // we should proceed with the actual punch out
                    return {
                        workReport: workReport,
                        overtimeData: overtimeData ? overtimeData.value : null,
                        // Pass the flag to indicate if we're returning from overtime claim
                        // When returningFromOvertimeClaim is true, we're on the second submission and should proceed with punch out
                        fromOvertimeClaim: false
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Check if we're returning from overtime claim
                    if (result.value.fromOvertimeClaim) {
                        // Update the flag to indicate we're now ready to punch out
                        returningFromOvertimeClaim = true;
                        
                        // Just return to the work report without punching out
                        Swal.fire({
                            icon: 'success',
                            title: 'Ready to Punch Out',
                            text: 'Your overtime has been recorded. Click Submit & Punch Out again to complete the process.',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Re-show the work report modal without the overtime claim option
                            promptForWorkReport(selfieImage, latitude, longitude, accuracy, result.value.workReport, true);
                        });
                        return;
                    }
                    
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

                    // Send punch out request with work report and overtime data if available
                    fetch('punch.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'punch_out',
                            work_report: result.value.workReport,
                            punch_out_photo: selfieImage,
                            latitude: latitude,
                            longitude: longitude,
                            accuracy: accuracy,
                            overtime_data: result.value.overtimeData
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Create summary HTML with potential location change warning
                            let summaryHtml = `
                                <div class="punch-out-summary">
                                    <p class="punch-time">${data.message}</p>
                                    <div class="time-details">
                                        ${data.working_hours.split('\n').map(line => 
                                            `<p class="${line.toLowerCase().includes('overtime') ? 'overtime-hours' : 'regular-hours'}">${line}</p>`
                                        ).join('')}
                                    </div>`;
                                        
                            // Add location warning if detected
                            if (data.location_changed) {
                                summaryHtml += `
                                    <div class="location-warning">
                                        <p class="warning-title"><i class="fas fa-map-marker-alt"></i> Location Change Detected</p>
                                        <p class="warning-message">${data.location_message}</p>
                                    </div>`;
                            }
                                    
                            summaryHtml += `
                                    <div class="work-report-summary">
                                        <h4>Work Report:</h4>
                                        <p>${result.value.workReport}</p>
                                    </div>
                                </div>`;

                            Swal.fire({
                                icon: data.location_changed ? 'warning' : 'success',
                                title: data.location_changed ? 'Success with Warning' : 'Success!',
                                html: summaryHtml,
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
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred while processing your request.'
                        });
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
    

   
    <!-- Update the initialization script -->
    <script>
        // Make sure all scripts are loaded first
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a small amount of time to ensure scripts are fully loaded
            setTimeout(function() {
                try {
                    if (typeof TaskOverviewManager === 'undefined') {
                        console.error('TaskOverviewManager class is not defined. Check if task-overview-manager.js is loaded correctly.');
                    } else {
                        window.taskManager = new TaskOverviewManager();
                        console.log('TaskOverviewManager initialized successfully');
                    }
                } catch (error) {
                    console.error('Error initializing TaskOverviewManager:', error);
                }
            }, 500); // Small delay to ensure scripts are fully processed
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
    
    <!-- Add Kanban Board Filter Functionality -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Elements
        const yearFilter = document.getElementById('yearFilter');
        const monthFilter = document.getElementById('monthFilter');
        const yearDropdown = document.getElementById('yearDropdown');
        const monthDropdown = document.getElementById('monthDropdown');
        const projectCards = document.querySelectorAll('.kanban-card');
        
        // State - Initialize to current year and current month
        const currentDate = new Date();
        let selectedYear = currentDate.getFullYear().toString();
        let selectedMonth = currentDate.getMonth().toString(); // Current month (0-indexed)
        
        // Update the initial display of filters to show current month
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        yearFilter.querySelector('span').textContent = selectedYear;
        monthFilter.querySelector('span').textContent = monthNames[parseInt(selectedMonth)];
        
        // Set initial selections in dropdowns
        document.querySelectorAll('.year-option').forEach(option => {
            option.classList.remove('selected');
            if (option.getAttribute('data-year') === selectedYear) {
                option.classList.add('selected');
            }
        });
        
        document.querySelectorAll('.month-option').forEach(option => {
            option.classList.remove('selected');
            if (option.getAttribute('data-month') === selectedMonth) {
                option.classList.add('selected');
            }
        });
        
        // Toggle dropdowns
        yearFilter.addEventListener('click', function(e) {
            e.stopPropagation();
            yearDropdown.style.display = yearDropdown.style.display === 'block' ? 'none' : 'block';
            monthDropdown.style.display = 'none';
        });
        
        monthFilter.addEventListener('click', function(e) {
            e.stopPropagation();
            monthDropdown.style.display = monthDropdown.style.display === 'block' ? 'none' : 'block';
            yearDropdown.style.display = 'none';
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            yearDropdown.style.display = 'none';
            monthDropdown.style.display = 'none';
        });
        
        // Prevent dropdown from closing when clicking inside
        yearDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        monthDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        // Year selection
        document.querySelectorAll('.year-option').forEach(option => {
            option.addEventListener('click', function() {
                selectedYear = this.getAttribute('data-year');
                yearFilter.querySelector('span').textContent = selectedYear;
                
                // Update selected class
                document.querySelectorAll('.year-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.classList.add('selected');
                
                // Hide dropdown
                yearDropdown.style.display = 'none';
                
                // Apply filters
                applyFilters();
            });
        });
        
        // Month selection
        document.querySelectorAll('.month-option').forEach(option => {
            option.addEventListener('click', function() {
                selectedMonth = this.getAttribute('data-month');
                
                // Update display text
                if (selectedMonth === 'all') {
                    monthFilter.querySelector('span').textContent = 'All Months';
                } else {
                    monthFilter.querySelector('span').textContent = monthNames[parseInt(selectedMonth)];
                }
                
                // Update selected class
                document.querySelectorAll('.month-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.classList.add('selected');
                
                // Hide dropdown
                monthDropdown.style.display = 'none';
                
                // Apply filters
                applyFilters();
            });
        });
        
        // Apply filters to cards
        function applyFilters() {
            // Show loading state
            const columns = document.querySelectorAll('.kanban-column');
            columns.forEach(column => {
                column.classList.add('loading');
            });
            
            // Add debugging message
            console.log(`Applying filters - Year: ${selectedYear}, Month: ${selectedMonth}`);
            
            // Ensure the path is correct (fix for relative URL issues)
            const apiUrl = window.location.href.substring(0, window.location.href.lastIndexOf('/')) + '/get_filtered_tasks.php';
            console.log('API URL:', apiUrl);
            
            // Simulate loading (can be removed in production)
            setTimeout(() => {
                // Send AJAX request to get filtered data
                fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        year: selectedYear,
                        month: selectedMonth
                    })
                })
                .then(response => {
                    console.log('Server response status:', response.status);
                    // Check if the response is ok
                    if (!response.ok) {
                        console.error('Server returned error:', response.status);
                        // Show error notification to user
                        showErrorNotification('Failed to filter tasks. Using client-side filtering instead.');
                        filterClientSide();
                        return null;
                    }
                    return response.json().catch(error => {
                        console.error('Error parsing JSON:', error);
                        showErrorNotification('Error parsing server response. Using client-side filtering instead.');
                        filterClientSide();
                        return null;
                    });
                })
                .then(data => {
                    if (data) {
                        console.log('Received filtered data:', data);
                        
                        // Check if we got data or an error message
                        if (data.error) {
                            console.error('Server returned error:', data.error);
                            showErrorNotification('Error filtering tasks: ' + data.error);
                            filterClientSide();
                        } else {
                            try {
                                // Add debug check to validate data structure
                                validateResponseData(data);
                                
                                // Update the DOM with server-side filtered data
                                updateKanbanWithData(data);
                            } catch (error) {
                                console.error('Error processing data:', error);
                                showErrorNotification(error.message);
                                filterClientSide();
                            }
                        }
                    }
                    
                    // Remove loading state
                    columns.forEach(column => {
                        column.classList.remove('loading');
                    });
                })
                .catch(error => {
                    console.error('Error applying filters:', error);
                    showErrorNotification('Error filtering tasks. Using client-side filtering instead.');
                    
                    // Fallback to client-side filtering
                    filterClientSide();
                    
                    // Remove loading state
                    columns.forEach(column => {
                        column.classList.remove('loading');
                    });
                });
            }, 300);
        }
        
        // Validate the response data structure
        function validateResponseData(data) {
            if (!data) {
                throw new Error('No data received from server');
            }
            
            // Check if all required sections exist
            if (!data.todo || !Array.isArray(data.todo)) {
                console.error('Missing or invalid todo data:', data.todo);
                throw new Error('Invalid data structure: todo section missing');
            }
            
            if (!data.in_progress || !Array.isArray(data.in_progress)) {
                console.error('Missing or invalid in_progress data:', data.in_progress);
                throw new Error('Invalid data structure: in_progress section missing');
            }
            
            if (!data.in_review || !Array.isArray(data.in_review)) {
                console.error('Missing or invalid in_review data:', data.in_review);
                throw new Error('Invalid data structure: in_review section missing');
            }
            
            if (!data.done || !Array.isArray(data.done)) {
                console.error('Missing or invalid done data:', data.done);
                throw new Error('Invalid data structure: done section missing');
            }
            
            console.log('Data validation passed');
        }
        
        // Helper function to show error notification
        function showErrorNotification(message) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'filter-error-notification';
            notification.innerHTML = `
                <i class="fas fa-exclamation-circle"></i>
                <span>${message}</span>
                <button class="close-notification"><i class="fas fa-times"></i></button>
            `;
            
            // Add to the page
            document.body.appendChild(notification);
            
            // Add event listener to close button
            notification.querySelector('.close-notification').addEventListener('click', () => {
                notification.remove();
            });
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // Temporary client-side filtering until backend is implemented
        function filterClientSide() {
            projectCards.forEach(card => {
                const dateElement = card.querySelector('.meta-date span');
                if (!dateElement) {
                    card.style.display = 'block';
                    return;
                }
                
                const dateText = dateElement.textContent;
                let cardDate;
                
                try {
                    // Try to parse the date from the card
                    // Format could be "Due: Mar 15" or "Completed: Mar 15"
                    const datePart = dateText.split(':')[1].trim();
                    const fullDate = datePart + ', ' + selectedYear;
                    cardDate = new Date(fullDate);
                    
                    // If invalid date, show the card
                    if (isNaN(cardDate.getTime())) {
                        card.style.display = 'block';
                        return;
                    }
                    
                    // Check if year matches
                    const cardYear = cardDate.getFullYear().toString();
                    const yearMatches = cardYear === selectedYear;
                    
                    // Check if month matches (or if all months selected)
                    const cardMonth = cardDate.getMonth().toString();
                    const monthMatches = selectedMonth === 'all' || cardMonth === selectedMonth;
                    
                    // Show or hide based on filters
                    card.style.display = (yearMatches && monthMatches) ? 'block' : 'none';
                    
                } catch (e) {
                    // If there's any error parsing, show the card
                    card.style.display = 'block';
                }
            });
            
            // Check for empty columns after filtering
            checkEmptyStates();
        }
        
        // Check and display empty states for columns with no visible cards
        function checkEmptyStates() {
            document.querySelectorAll('.kanban-column').forEach(column => {
                const cards = column.querySelectorAll('.kanban-card');
                const visibleCards = Array.from(cards).filter(card => card.style.display !== 'none');
                const emptyStateEl = column.querySelector('.empty-state');
                
                if (visibleCards.length === 0) {
                    if (!emptyStateEl) {
                        // Create empty state if it doesn't exist
                        const emptyState = document.createElement('div');
                        emptyState.className = 'empty-state';
                        
                        const icon = document.createElement('i');
                        icon.className = getEmptyStateIcon(column);
                        
                        const text = document.createElement('p');
                        text.textContent = 'No tasks for selected filters';
                        
                        emptyState.appendChild(icon);
                        emptyState.appendChild(text);
                        
                        column.querySelector('.kanban-cards-container').appendChild(emptyState);
                    } else {
                        emptyStateEl.style.display = 'flex';
                    }
                } else if (emptyStateEl) {
                    emptyStateEl.style.display = 'none';
                }
            });
        }
        
        // Helper to get appropriate icon for empty state based on column
        function getEmptyStateIcon(column) {
            const headerText = column.querySelector('.column-title').textContent.toLowerCase();
            
            if (headerText.includes('to do')) return 'fas fa-clipboard-list';
            if (headerText.includes('progress')) return 'fas fa-tasks';
            if (headerText.includes('review')) return 'fas fa-clipboard-check';
            if (headerText.includes('done')) return 'fas fa-check-circle';
            
            return 'fas fa-clipboard-list';
        }
        
        // Function to update the kanban board with server-side filtered data
        function updateKanbanWithData(data) {
            // Process each section of the kanban board
            updateColumn('todo', data.todo);
            updateColumn('in_progress', data.in_progress);
            updateColumn('in_review', data.in_review);
            updateColumn('done', data.done);
        }
        
        // Helper function to update a single column with new data
        function updateColumn(columnType, items) {
            // Find the correct column
            let columnIndex;
            switch(columnType) {
                case 'todo': columnIndex = 0; break;
                case 'in_progress': columnIndex = 1; break;
                case 'in_review': columnIndex = 2; break;
                case 'done': columnIndex = 3; break;
                default: return;
            }
            
            const column = document.querySelectorAll('.kanban-column')[columnIndex];
            const container = column.querySelector('.kanban-cards-container');
            
            // Clear existing cards
            container.innerHTML = '';
            
            // If no items, show empty state
            if (items.length === 0) {
                const emptyState = document.createElement('div');
                emptyState.className = 'empty-state';
                
                const icon = document.createElement('i');
                icon.className = getEmptyStateIcon(column);
                
                const text = document.createElement('p');
                text.textContent = 'No tasks for selected filters';
                
                emptyState.appendChild(icon);
                emptyState.appendChild(text);
                
                container.appendChild(emptyState);
                return;
            }
            
            // Create and append cards based on the column type
            items.forEach(item => {
                let cardHTML = '';
                
                if (columnType === 'todo') {
                    cardHTML = `
                        <div class="kanban-card project-card" 
                             data-project-id="${item.id}"
                             data-project-type="${item.project_type.toLowerCase()}">
                            <div class="card-tags">
                                <div class="tag-container">
                                    <span class="card-tag tag-${item.project_type.toLowerCase()}">
                                        ${item.project_type}
                                    </span>
                                </div>
                                <span class="meta-status ${item.status}">
                                    ${item.status.charAt(0).toUpperCase() + item.status.slice(1)}
                                </span>
                                ${item.role_type ? 
                                    `<span class="role-badge ${item.role_type.toLowerCase().replace(' ', '-')}">
                                        ${item.role_type}
                                    </span>` : ''}
                            </div>
                            
                            <h4 class="task-title">${item.title}</h4>
                            <p class="task-description">
                                ${item.description.length > 100 ? 
                                    item.description.substring(0, 100) + '...' : 
                                    item.description}
                            </p>
                            <div class="project-stats">
                                <span class="stat-item">
                                    <i class="fas fa-layer-group"></i>
                                    ${item.total_stages} Stages
                                </span>
                                <span class="stat-item">
                                    <i class="fas fa-tasks"></i>
                                    ${item.total_substages} Substages
                                </span>
                            </div>
                            <div class="card-meta">
                                <span class="meta-date">
                                    <i class="far fa-calendar"></i>
                                    Due: ${item.due_date}
                                </span>
                                <span class="meta-assigned">
                                    <i class="far fa-user"></i>
                                    By: ${item.creator_name}
                                </span>
                            </div>
                        </div>
                    `;
                } else if (columnType === 'in_progress') {
                    cardHTML = `
                        <div class="kanban-card project-card" 
                             data-project-id="${item.id}"
                             data-project-type="${item.project_type.toLowerCase()}">
                            <div class="card-tags">
                                <div class="tag-container">
                                    <span class="card-tag tag-${item.project_type.toLowerCase()}">
                                        ${item.project_type}
                                    </span>
                                </div>
                                <span class="meta-status in_progress">In Progress</span>
                                ${item.role_type ? 
                                    `<span class="role-badge ${item.role_type.toLowerCase().replace(' ', '-')}">
                                        ${item.role_type}
                                    </span>` : ''}
                            </div>
                            
                            <h4 class="task-title">${item.title}</h4>
                            <p class="task-description">
                                ${item.description.length > 100 ? 
                                    item.description.substring(0, 100) + '...' : 
                                    item.description}
                            </p>
                            
                            <div class="task-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: ${item.progress_percentage}%"></div>
                                </div>
                                <span class="progress-text">${item.progress_percentage}%</span>
                            </div>
                            
                            <div class="project-stats">
                                <span class="stat-item">
                                    <i class="fas fa-layer-group"></i>
                                    ${item.in_progress_stages}/${item.total_stages} Stages
                                </span>
                                <span class="stat-item">
                                    <i class="fas fa-tasks"></i>
                                    ${item.in_progress_substages}/${item.total_substages} Substages
                                </span>
                            </div>
                            
                            <div class="card-meta">
                                <span class="meta-date">
                                    <i class="far fa-calendar"></i>
                                    Due: ${item.due_date}
                                </span>
                                <span class="meta-assigned">
                                    <i class="far fa-user"></i>
                                    By: ${item.creator_name}
                                </span>
                            </div>
                        </div>
                    `;
                } else if (columnType === 'in_review') {
                    cardHTML = `
                        <div class="kanban-card project-card" 
                             data-project-id="${item.project_id}"
                             data-substage-id="${item.substage_id}">
                            <div class="card-tags">
                                <div class="tag-container">
                                    <span class="card-tag tag-${item.project_type.toLowerCase()}">
                                        ${item.project_type}
                                    </span>
                                </div>
                                <span class="meta-status in_review">In Review</span>
                                ${item.role_type ? 
                                    `<span class="role-badge ${item.role_type.toLowerCase().replace(' ', '-')}">
                                        ${item.role_type}
                                    </span>` : ''}
                            </div>
                            
                            <h4 class="task-title">
                                ${item.project_title}
                            </h4>
                            <p class="task-description">
                                Stage ${item.stage_number} > 
                                Substage ${item.substage_number}: 
                                ${item.substage_title}
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
                                    Due: ${item.due_date}
                                </span>
                                <span class="meta-assigned">
                                    <i class="far fa-user"></i>
                                    Reviewer: ${item.reviewer_name}
                                </span>
                            </div>
                        </div>
                    `;
                } else if (columnType === 'done') {
                    cardHTML = `
                        <div class="kanban-card project-card" 
                             data-project-id="${item.id}"
                             data-project-type="${item.project_type.toLowerCase()}">
                            <div class="card-tags">
                                <div class="tag-container">
                                    <span class="card-tag tag-${item.project_type.toLowerCase()}">
                                        ${item.project_type}
                                    </span>
                                </div>
                                <span class="meta-status completed">Completed</span>
                                ${item.role_type ? 
                                    `<span class="role-badge ${item.role_type.toLowerCase().replace(' ', '-')}">
                                        ${item.role_type}
                                    </span>` : ''}
                            </div>
                            
                            <h4 class="task-title">${item.title}</h4>
                            <p class="task-description">
                                ${item.description.length > 100 ? 
                                    item.description.substring(0, 100) + '...' : 
                                    item.description}
                            </p>
                            
                            <div class="completion-info">
                                <div class="completion-stats">
                                    <span class="stat-item">
                                        <i class="fas fa-layer-group"></i>
                                        ${item.total_stages} Stages
                                    </span>
                                    <span class="stat-item">
                                        <i class="fas fa-tasks"></i>
                                        ${item.total_substages} Substages
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
                                    Completed: ${item.completion_date}
                                </span>
                                <span class="meta-assigned">
                                    <i class="far fa-user"></i>
                                    By: ${item.creator_name}
                                </span>
                            </div>
                        </div>
                    `;
                }
                
                // Append the card to the container
                container.innerHTML += cardHTML;
            });
        }
        
        // Apply initial styling to the dropdowns
        yearDropdown.style.display = 'none';
        monthDropdown.style.display = 'none';
        
        // Apply initial filters when page loads
        setTimeout(() => {
            applyFilters();
        }, 500);
    });
    </script>
   
</body>
</html>