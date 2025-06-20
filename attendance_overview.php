<?php
// Start session for authentication
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has the correct role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Redirect to login page if not logged in
    $_SESSION['error'] = "You must log in to access the dashboard";
    header('Location: login.php');
    exit();
}

// Check if user has the correct role
$allowed_roles = ['Site Manager', 'Senior Manager (Site)', 'Purchase Manager', 'Site Coordinator'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    // Redirect to appropriate page based on role
    $_SESSION['error'] = "You don't have permission to access this page";
    header('Location: login.php');
    exit();
}

// Get the site manager's name from session
$siteManagerName = isset($_SESSION['username']) ? $_SESSION['username'] : "Site Manager";
$userId = $_SESSION['user_id']; // Get the logged-in user's ID

// Get filter parameters
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n'); // Current month if not specified
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y'); // Current year if not specified

// Format time function
function formatTime($timeString) {
    if (empty($timeString) || $timeString == '00:00:00') {
        return '-';
    }
    
    $time = strtotime($timeString);
    return date('h:i A', $time);
}

// Fixed formatHours function to handle both numeric and time string formats
function formatHours($hours) {
    if (empty($hours)) {
        return '-';
    }
    
    // Check if input is a time string (HH:MM:SS)
    if (is_string($hours) && strpos($hours, ':') !== false) {
        $parts = explode(':', $hours);
        if (count($parts) >= 2) {
            $h = intval($parts[0]);
            $m = intval($parts[1]);
            return $h . 'h ' . $m . 'm';
        }
        return '-';
    }
    
    // Handle numeric hours
    if (is_numeric($hours)) {
        $h = floor($hours);
        $m = round(($hours - $h) * 60);
        return $h . 'h ' . $m . 'm';
    }
    
    return '-';
}

// Initialize variables
$attendanceRecords = [];
$totalWorkingDays = 0;
$presentDays = 0;
$absentDays = 0;
$lateDays = 0;

try {
    // Include database connection
    require_once('config/db_connect.php');
    
    // Fetch attendance data for the selected month and year
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = date('Y-m-t', strtotime($startDate)); // Last day of the month

    // Check if tables exist before querying
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'attendance'");
    $attendanceTableExists = $tableCheck->rowCount() > 0;
    
    if ($attendanceTableExists) {
        // Prepare SQL query to fetch attendance data for the logged-in user only
        $query = "SELECT a.*, 
                    u.username, 
                    s.shift_name as shift_name,
                    s.start_time as shift_start,
                    s.end_time as shift_end,
                    us.weekly_offs
                FROM attendance a
                JOIN users u ON a.user_id = u.id
                LEFT JOIN shifts s ON a.shifts_id = s.id
                LEFT JOIN user_shifts us ON u.id = us.user_id
                WHERE a.date BETWEEN :startDate AND :endDate
                AND a.user_id = :userId
                ORDER BY a.date DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':startDate' => $startDate,
            ':endDate' => $endDate,
            ':userId' => $userId
        ]);

        $rawAttendanceRecords = $stmt->fetchAll();
        
        // Remove duplicate records by grouping by date
        $attendanceRecords = [];
        $processedDates = [];
        
        foreach ($rawAttendanceRecords as $record) {
            $recordDate = $record['date'];
            
            // Only keep the first occurrence of each date
            if (!in_array($recordDate, $processedDates)) {
                $attendanceRecords[] = $record;
                $processedDates[] = $recordDate;
            }
        }
        
        // Get user's weekly off day from user_shifts table
        $weeklyOffDay = isset($attendanceRecords[0]['weekly_offs']) ? $attendanceRecords[0]['weekly_offs'] : null;
        
        // Calculate total days in month
        $totalDaysInMonth = date('t', strtotime($startDate));
        
        // Calculate weekly offs in the month
        $weeklyOffsCount = 0;
        if ($weeklyOffDay !== null) {
            // Count occurrences of weekly off day in the month
            for ($day = 1; $day <= $totalDaysInMonth; $day++) {
                $date = new DateTime("$year-$month-$day");
                // weekly_offs column stores the day number directly (e.g., 4 for Tuesday)
                // Convert to 0-6 format (0 = Sunday, 1 = Monday, etc.)
                $dayOfWeek = $date->format('w'); // 0 (Sunday) through 6 (Saturday)
                
                // In the database: Sunday=4, Monday=5, Tuesday=6, Wednesday=12, etc.
                // Need to map these values to standard day numbers
                $dayMapping = [
                    '4' => 0,  // Sunday
                    '5' => 1,  // Monday
                    '6' => 2,  // Tuesday
                    '12' => 3, // Wednesday
                    '11' => 0  // Sunday (another value for Sunday from the image)
                ];
                
                // Check if the current day matches the weekly off day
                if (isset($dayMapping[$weeklyOffDay]) && $dayOfWeek == $dayMapping[$weeklyOffDay]) {
                    $weeklyOffsCount++;
                }
            }
        }
        
        // Calculate actual working days (total days minus weekly offs)
        $totalWorkingDays = $totalDaysInMonth - $weeklyOffsCount;
        
        // Calculate present days (days user was actually present)
        $presentDays = count(array_filter($attendanceRecords, function($record) {
            return $record['status'] == 'Present';
        }));
        
        // Calculate overtime locally based on shift end time
        $totalOvertimeHours = 0;
        $totalWorkingHours = 0;
        
        foreach ($attendanceRecords as $key => $record) {
            // Calculate total working hours
            if (!empty($record['working_hours'])) {
                if (is_numeric($record['working_hours'])) {
                    $totalWorkingHours += $record['working_hours'];
                } else if (strpos($record['working_hours'], ':') !== false) {
                    // Convert HH:MM:SS to hours
                    $parts = explode(':', $record['working_hours']);
                    if (count($parts) >= 2) {
                        $hours = intval($parts[0]);
                        $minutes = intval($parts[1]) / 60;
                        $totalWorkingHours += $hours + $minutes;
                    }
                }
            }
            
            // Only calculate overtime if both punch_out and shift_end exist
            if (!empty($record['punch_out']) && !empty($record['shift_end'])) {
                $punchOut = strtotime($record['punch_out']);
                $shiftEnd = strtotime($record['shift_end']);
                
                // Calculate difference in seconds
                $diffSeconds = $punchOut - $shiftEnd;
                
                // Check if worked at least 1 hour and 30 minutes (5400 seconds) after shift end
                if ($diffSeconds >= 5400) {
                    // Convert to hours
                    $overtimeHours = $diffSeconds / 3600;
                    
                    // Round down to nearest half hour
                    $wholeHours = floor($overtimeHours);
                    $fractionalPart = $overtimeHours - $wholeHours;
                    
                    if ($fractionalPart < 0.5) {
                        $roundedOvertime = $wholeHours;
                    } else if ($fractionalPart >= 0.5 && $fractionalPart < 1) {
                        $roundedOvertime = $wholeHours + 0.5;
                    } else {
                        $roundedOvertime = $wholeHours + 1;
                    }
                    
                    $attendanceRecords[$key]['overtime_hours'] = $roundedOvertime;
                    $totalOvertimeHours += $roundedOvertime;
                } else {
                    $attendanceRecords[$key]['overtime_hours'] = 0;
                }
            } else {
                $attendanceRecords[$key]['overtime_hours'] = 0;
            }
        }

        // Calculate statistics
        $absentDays = count(array_filter($attendanceRecords, function($record) {
            return $record['status'] == 'Absent';
        }));
        $lateDays = count(array_filter($attendanceRecords, function($record) {
            return $record['status'] == 'Late';
        }));
    }
} catch (PDOException $e) {
    // Log the error
    error_log("Database Error: " . $e->getMessage());
    
    // For debugging only - remove in production
    $error_message = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Attendance Overview</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Add SheetJS library for Excel export -->
    <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            min-height: 100vh;
            overflow: hidden;
        }
        
        .main-container {
            display: flex;
            height: 100vh;
            overflow: hidden;
            position: relative;
        }
        
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            height: 100vh;
            box-sizing: border-box;
            margin-left: 250px;
            position: relative;
            transition: margin-left 0.3s;
            width: calc(100% - 250px);
        }
        
        .main-content.expanded {
            margin-left: 70px;
            width: calc(100% - 70px);
        }
        
        /* Left panel styles */
        .left-panel {
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
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        
        .left-panel.collapsed {
            width: 70px;
            overflow: visible;
        }
        
        .left-panel.collapsed .menu-text {
            display: none;
        }
        
        .left-panel.collapsed .menu-item i {
            margin-right: 0;
        }
        
        .left-panel.collapsed .menu-item {
            justify-content: center;
        }
        
        /* Overlay for mobile */
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
        
        /* Hamburger menu for mobile */
        .hamburger-menu {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 40px;
            height: 40px;
            background-color: #3498db;
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
            
            .left-panel {
                width: 0;
                overflow: hidden;
                transform: translateX(-100%);
                transition: transform 0.3s, width 0.3s;
            }
            
            .left-panel.mobile-open {
                width: 250px;
                transform: translateX(0);
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
                gap: 15px;
            }
            
            .filter-group {
                flex-direction: column;
                align-items: flex-start;
                width: 100%;
            }
            
            select {
                width: 100%;
            }
            
            .user-info {
                display: none; /* Hide user info on mobile to save space */
            }
            
            header {
                margin-top: 20px; /* Add space for hamburger menu */
                padding-top: 20px;
            }
            
            .container {
                padding: 0 10px;
            }
            
            .chart-card {
                height: 350px; /* Slightly smaller charts on mobile */
            }
            
            .daily-trend {
                height: 400px;
            }
            
            /* Ensure filters are stacked properly */
            .filters {
                padding: 15px;
            }
            
            .filter-group div {
                width: 100%;
            }
            
            /* Make the apply button full width */
            .filters button {
                width: 100%;
                margin-top: 10px;
            }
        }
        
        .container {
            max-width: 1600px;
            width: 100%;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        h1 {
            color: #2c3e50;
            font-size: 28px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
        }
        
        .filters {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        select, button {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: white;
            font-size: 14px;
        }
        
        button {
            background-color: #3498db;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        button:hover {
            background-color: #2980b9;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .stat-card h3 {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .stat-card p {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .present {
            color: #2ecc71 !important;
        }
        
        .absent {
            color: #e74c3c !important;
        }
        
        .late {
            color: #f39c12 !important;
        }
        
        .overtime {
            color: #8e44ad !important;
        }
        
        .attendance-table-container {
            margin-top: 30px;
            margin-bottom: 30px;
            overflow-x: auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            width: 100%;
        }
        
        .chart-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            height: 400px;
            width: 100%;
        }
        
        .chart-card h2 {
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 18px;
        }
        
        .chart-container {
            position: relative;
            height: calc(100% - 50px);
            width: 100%;
        }
        
        .daily-trend {
            grid-column: span 2;
            height: 450px;
        }
        
        .bottom-section {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        @media (min-width: 1921px) {
            /* For very large screens */
            .container {
                max-width: 1800px;
            }
            
            .charts-container {
                grid-template-columns: 2fr 1fr;
            }
            
            .chart-card {
                height: 500px;
            }
            
            .daily-trend {
                height: 550px;
            }
        }
        
        @media (max-width: 1600px) {
            .container {
                max-width: 1400px;
            }
        }
        
        @media (max-width: 1366px) {
            .container {
                max-width: 1200px;
            }
        }
        
        @media (max-width: 1200px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .charts-container {
                grid-template-columns: 1fr;
            }
            
            .daily-trend {
                grid-column: span 1;
            }
            
            .bottom-section {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 992px) {
            .container {
                padding: 0 10px;
            }
            
            .main-content {
                padding: 15px;
            }
            
            .chart-card {
                height: 350px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .hamburger-menu {
                display: flex;
            }
            
            .left-panel {
                width: 0;
                transform: translateX(-100%);
            }
            
            .left-panel.mobile-open {
                width: 250px;
                transform: translateX(0);
            }
            
            .stats-container {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            
            .chart-card {
                height: 300px;
            }
        }
        
        @media (max-width: 576px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .chart-card {
                height: 280px;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                flex-direction: column;
                width: 100%;
            }
            
            .filter-group div {
                width: 100%;
                margin-bottom: 10px;
            }
            
            select, button {
                width: 100%;
            }
        }
        
        /* Ensure table is scrollable on all devices */
        .attendance-table-container > div {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
        }
        
        /* Make sure charts resize properly */
        .chart-container {
            position: relative;
            height: calc(100% - 50px);
            width: 100%;
            min-height: 200px;
        }
        
        /* Menu item styles */
        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
            border-left: 3px solid transparent;
        }
        
        .menu-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-left-color: #3498db;
        }
        
        .menu-item.active {
            background-color: rgba(255, 255, 255, 0.15);
            border-left-color: #3498db;
        }
        
        .menu-item i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
        }
        
        .menu-item.section-start {
            margin-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 15px;
            font-weight: 600;
            cursor: default;
        }
        
        .menu-item.section-start:hover {
            background-color: transparent;
            border-left-color: transparent;
        }
        
        .menu-item.logout-item {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }
        
        /* Toggle button styles */
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
        
        /* Brand logo styles */
        .brand-logo {
            padding: 15px 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .left-panel.collapsed .brand-logo img {
            max-width: 40px;
        }
        
        /* Add animation for panel toggle */
        .left-panel, .main-content {
            transition: all 0.3s ease-in-out;
        }
        
        /* Improve toggle button visibility */
        .toggle-btn {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        
        /* Add needs-scrolling class styles */
        .left-panel.needs-scrolling {
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        
        /* Hide scrollbar for Chrome, Safari and Opera */
        .left-panel::-webkit-scrollbar {
            display: none;
        }
        
        /* Ensure the needs-scrolling class doesn't override the hidden scrollbar */
        .left-panel.needs-scrolling::-webkit-scrollbar {
            display: none;
        }
        
        /* iPhone XR (828 × 1792) and XS (1125 × 2436) specific styles */
        @media only screen and (min-device-width: 375px) and (max-device-width: 812px), 
               only screen and (min-device-width: 414px) and (max-device-width: 896px),
               only screen and (device-width: 375px) and (device-height: 812px),
               only screen and (device-width: 414px) and (device-height: 896px) {
            .main-content {
                padding: 15px 10px;
            }
            
            header {
                margin-bottom: 20px;
                padding-bottom: 10px;
            }
            
            h1 {
                font-size: 22px;
            }
            
            .filters {
                padding: 12px;
                margin-bottom: 20px;
            }
            
            .stats-container {
                gap: 12px;
                margin-bottom: 20px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-card p {
                font-size: 20px;
            }
            
            .chart-card {
                height: 300px;
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .daily-trend {
                height: 350px;
            }
            
            .chart-card h2 {
                font-size: 16px;
                margin-bottom: 15px;
            }
            
            /* Fix for notch on iPhone X series */
            .left-panel {
                padding-top: env(safe-area-inset-top);
            }
            
            .hamburger-menu {
                top: calc(15px + env(safe-area-inset-top));
            }
        }
        
        /* General responsive improvements */
        @media (max-width: 480px) {
            .main-content {
                padding: 15px 10px;
            }
            
            .hamburger-menu {
                top: 15px;
                left: 15px;
                width: 36px;
                height: 36px;
            }
            
            header {
                margin-top: 15px;
                padding-top: 15px;
            }
            
            h1 {
                font-size: 20px;
            }
            
            .chart-card {
                height: 280px;
            }
            
            .daily-trend {
                height: 320px;
            }
            
            /* Better touch targets for mobile */
            select, button {
                padding: 12px;
                font-size: 16px; /* Better for touch */
            }
            
            /* Ensure charts are properly sized */
            .chart-container {
                height: calc(100% - 40px);
            }
        }
        
        /* Landscape orientation adjustments */
        @media (max-width: 896px) and (orientation: landscape) {
            .main-content {
                margin-left: 0;
            }
            
            .left-panel {
                transform: translateX(-100%);
                width: 0;
            }
            
            .left-panel.mobile-open {
                transform: translateX(0);
                width: 250px;
            }
            
            .hamburger-menu {
                display: flex;
            }
            
            .stats-container {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .charts-container {
                grid-template-columns: 1fr 1fr;
            }
            
            .chart-card {
                height: 220px;
            }
            
            .daily-trend {
                grid-column: span 2;
                height: 250px;
            }
        }
        
        /* Improve chart responsiveness */
        .chart-container {
            position: relative;
            height: calc(100% - 50px);
            width: 100%;
            min-height: 200px; /* Ensure minimum height */
        }
        
        /* Ensure proper spacing in smaller screens */
        .stat-card h3 {
            font-size: calc(12px + 0.3vw);
            margin-bottom: 8px;
        }
        
        .stat-card p {
            font-size: calc(18px + 0.5vw);
        }
        
        /* Improve touch targets for menu items on mobile */
        @media (max-width: 896px) {
            .menu-item {
                padding: 14px 20px;
            }
            
            .toggle-btn {
                width: 36px;
                height: 36px;
            }
        }
        
        /* Safe area insets for modern iOS devices */
        @supports (padding-top: env(safe-area-inset-top)) {
            .main-content {
                padding-left: calc(10px + env(safe-area-inset-left));
                padding-right: calc(10px + env(safe-area-inset-right));
                padding-bottom: env(safe-area-inset-bottom);
            }
            
            .left-panel {
                padding-top: env(safe-area-inset-top);
                padding-bottom: env(safe-area-inset-bottom);
            }
        }
        
        /* Improve notification positioning for notched devices */
        #panel-notification {
            bottom: calc(20px + env(safe-area-inset-bottom));
        }
        
        /* Update table styles to match the screenshot */
        .attendance-table-container {
            margin-top: 30px;
            margin-bottom: 30px;
            overflow-x: auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            width: 100%;
        }
        
        .table-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .table-header h2 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 18px;
            font-weight: 600;
        }
        
        .table-actions {
            display: flex;
            gap: 10px;
        }
        
        .table-actions button {
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-actions button i {
            margin-right: 5px;
        }
        
        #export-csv {
            background-color: #3498db;
            color: white;
        }
        
        #print-table {
            background-color: #f8f9fa;
            color: #2c3e50;
            border: 1px solid #ddd;
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .attendance-table th {
            background-color: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .attendance-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .attendance-table tr:hover {
            background-color: #f5f7fa;
        }
        
        .attendance-table a {
            color: #3498db;
            text-decoration: none;
        }
        
        .attendance-table a:hover {
            text-decoration: underline;
        }
        
        /* Add these styles to your existing CSS */
        .attendance-table td a .fa-folder {
            transition: color 0.2s;
        }
        
        .attendance-table td a:hover .fa-folder {
            color: #2980b9 !important;
        }
        
        /* Style for the photo modal */
        .photo-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }
        
        .photo-modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 90%;
            max-height: 90%;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        /* Responsive design for the photo modal */
        @media (max-width: 768px) {
            .photo-modal-content {
                max-width: 95%;
                max-height: 95%;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($error_message)): ?>
    <div style="background-color: #f8d7da; color: #721c24; padding: 10px; margin: 10px; border-radius: 5px;">
        <strong>Error:</strong> <?php echo $error_message; ?>
        <p>Please check your database configuration and ensure all tables exist.</p>
    </div>
    <?php endif; ?>

    <!-- Overlay for mobile menu -->
    <div class="overlay" id="overlay"></div>
    
    <!-- Hamburger menu for mobile -->
    <div class="hamburger-menu" id="hamburgerMenu">
        <i class="fas fa-bars"></i>
    </div>

    <div class="main-container">
        <!-- Include left panel -->
        <?php include_once('includes/manager_panel.php'); ?>
        
        <!-- Main Content Area -->
        <div class="main-content" id="mainContent">
            <div class="container">
                <header>
                    <h1>Attendance Overview</h1>
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php 
                            // Display first letter of username
                            echo substr($siteManagerName, 0, 1); 
                            ?>
                        </div>
                        <div>
                            <p><?php echo $siteManagerName; ?></p>
                            <small>Role: <?php echo $_SESSION['role']; ?></small>
                        </div>
                    </div>
                </header>
                
                <div class="filters">
                    <div class="filter-group">
                        <div>
                            <label for="month">Month</label>
                            <select id="month">
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>
                        <div>
                            <label for="year">Year</label>
                            <select id="year">
                                <option value="2023">2023</option>
                                <option value="2024" selected>2024</option>
                                <option value="2025">2025</option>
                            </select>
                        </div>
                    </div>
                    <button id="apply-filters">Apply Filters</button>
                </div>
                
                <div class="stats-container">
                    <div class="stat-card">
                        <h3>Total Working Days</h3>
                        <p><?php echo $totalWorkingDays; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Present Days</h3>
                        <p class="present"><?php echo $presentDays; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Total Working Hours</h3>
                        <p><?php echo formatHours($totalWorkingHours); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Total Overtime Hours</h3>
                        <p class="overtime"><?php echo formatHours($totalOvertimeHours); ?></p>
                    </div>
                </div>
                
                <!-- Attendance Table -->
                <div class="attendance-table-container">
                    <div class="table-header">
                        <h2>Detailed Attendance Records</h2>
                        <div class="table-actions">
                            <button id="export-all-work-reports"><i class="fas fa-file-excel"></i> Export Work Reports</button>
                            <button id="export-csv"><i class="fas fa-file-excel"></i> Export Simple Format</button>
                            <button id="print-table"><i class="fas fa-print"></i> Print</button>
                        </div>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Username</th>
                                    <th>Shift</th>
                                    <th>Weekly Off</th>
                                    <th>Punch In</th>
                                    <th>Punch In Address</th>
                                    <th>Punch Out</th>
                                    <th>Punch Out Address</th>
                                    <th>Working Hours</th>
                                    <th>Overtime</th>
                                    <th>Status</th>
                                    <th>Work Report</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($error_message)): ?>
                                <tr>
                                    <td colspan="12" style="text-align: center; color: #721c24;">
                                        Database error occurred. Please contact the administrator.
                                    </td>
                                </tr>
                                <?php elseif (empty($attendanceRecords)): ?>
                                <tr>
                                    <td colspan="12" style="text-align: center;">
                                        No attendance records found for this period
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($attendanceRecords as $record): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d', strtotime($record['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($record['username']); ?></td>
                                        <td><?php echo htmlspecialchars($record['shift_name'] ?? 'Regular'); ?></td>
                                        <td><?php echo isset($record['is_weekly_off']) && $record['is_weekly_off'] ? 'Yes' : 'No'; ?></td>
                                        <td>
                                            <?php echo formatTime($record['punch_in']); ?>
                                            <?php if (!empty($record['punch_in_photo'])): ?>
                                                <a href="#" onclick="viewAttendancePhoto('<?php echo htmlspecialchars($record['punch_in_photo']); ?>', 'Punch In', '<?php echo date('Y-m-d', strtotime($record['date'])); ?>')">
                                                    <i class="fas fa-folder" style="margin-left: 5px; color: #3498db;"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['address'] ?? '-'); ?></td>
                                        <td>
                                            <?php echo formatTime($record['punch_out']); ?>
                                            <?php if (!empty($record['punch_out_photo'])): ?>
                                                <a href="#" onclick="viewAttendancePhoto('<?php echo htmlspecialchars($record['punch_out_photo']); ?>', 'Punch Out', '<?php echo date('Y-m-d', strtotime($record['date'])); ?>')">
                                                    <i class="fas fa-folder" style="margin-left: 5px; color: #3498db;"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['punch_out_address'] ?? '-'); ?></td>
                                        <td><?php echo formatHours($record['working_hours']); ?></td>
                                        <td><?php echo formatHours($record['overtime_hours']); ?></td>
                                        <td class="status-<?php echo strtolower($record['status'] ?? 'unknown'); ?>">
                                            <?php echo htmlspecialchars($record['status'] ?? 'Unknown'); ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($record['work_report'])): ?>
                                                <a href="#" onclick="viewWorkReport(<?php echo $record['id']; ?>)">
                                                    <i class="fas fa-file-alt"></i> View
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Set current month as selected
        document.addEventListener('DOMContentLoaded', function() {
            try {
                const currentMonth = new Date().getMonth() + 1;
                const monthSelect = document.getElementById('month');
                if (monthSelect) {
                    monthSelect.value = <?php echo $month; ?>;
                }
                
                const yearSelect = document.getElementById('year');
                if (yearSelect) {
                    yearSelect.value = <?php echo $year; ?>;
                }
                
                // Add event listener for filter button
                const filterBtn = document.getElementById('apply-filters');
                if (filterBtn) {
                    filterBtn.addEventListener('click', function() {
                        const month = document.getElementById('month').value;
                        const year = document.getElementById('year').value;
                        window.location.href = `attendance_overview.php?month=${month}&year=${year}`;
                    });
                }
                
                // Toggle Panel Function
                window.togglePanel = function() {
                    const leftPanel = document.getElementById('leftPanel');
                    const mainContent = document.getElementById('mainContent');
                    const toggleIcon = document.getElementById('toggleIcon');
                    
                    leftPanel.classList.toggle('collapsed');
                    mainContent.classList.toggle('expanded');
                    
                    const isCollapsed = leftPanel.classList.contains('collapsed');
                    localStorage.setItem('leftPanelCollapsed', isCollapsed);
                    
                    if (isCollapsed) {
                        toggleIcon.classList.remove('fa-chevron-left');
                        toggleIcon.classList.add('fa-chevron-right');
                        mainContent.style.marginLeft = '70px';
                        mainContent.style.width = 'calc(100% - 70px)';
                        showPanelNotification('Panel collapsed (Ctrl+B to expand)');
                    } else {
                        toggleIcon.classList.remove('fa-chevron-right');
                        toggleIcon.classList.add('fa-chevron-left');
                        mainContent.style.marginLeft = '250px';
                        mainContent.style.width = 'calc(100% - 250px)';
                        showPanelNotification('Panel expanded (Ctrl+B to collapse)');
                    }
                };
                
                // Add toggle button click handler
                const toggleBtn = document.getElementById('leftPanelToggleBtn');
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        window.togglePanel();
                    });
                }
                
                // Mobile menu functions
                const hamburgerMenu = document.getElementById('hamburgerMenu');
                const leftPanel = document.getElementById('leftPanel');
                const overlay = document.getElementById('overlay');
                
                // Check if we should enable scrolling based on screen height
                function checkPanelScrolling() {
                    if (leftPanel) {
                        if (window.innerHeight < 700 || window.innerWidth <= 768) {
                            leftPanel.classList.add('needs-scrolling');
                        } else {
                            leftPanel.classList.remove('needs-scrolling');
                        }
                    }
                }
                
                // Hamburger menu click handler
                if (hamburgerMenu) {
                    hamburgerMenu.addEventListener('click', function() {
                        leftPanel.classList.toggle('mobile-open');
                        overlay.classList.toggle('active');
                        checkPanelScrolling();
                    });
                }
                
                // Overlay click handler (close menu when clicking outside)
                if (overlay) {
                    overlay.addEventListener('click', function() {
                        leftPanel.classList.remove('mobile-open');
                        overlay.classList.remove('active');
                    });
                }
                
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
                
                // Add keyboard shortcut for toggling panel (Ctrl+B)
                document.addEventListener('keydown', function(e) {
                    if (e.ctrlKey && e.key === 'b') {
                        e.preventDefault();
                        window.togglePanel();
                    }
                });
                
                // Highlight current page in menu
                const currentPage = window.location.pathname.split('/').pop();
                const menuItems = document.querySelectorAll('.menu-item');
                menuItems.forEach(item => {
                    if (item.getAttribute('onclick') && 
                        item.getAttribute('onclick').includes(currentPage)) {
                        item.classList.add('active');
                    }
                });
                
                // Load saved panel state
                const savedPanelState = localStorage.getItem('leftPanelCollapsed');
                if (savedPanelState === 'true') {
                    const leftPanel = document.getElementById('leftPanel');
                    const mainContent = document.getElementById('mainContent');
                    const toggleIcon = document.getElementById('toggleIcon');
                    
                    leftPanel.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    toggleIcon.classList.remove('fa-chevron-left');
                    toggleIcon.classList.add('fa-chevron-right');
                    mainContent.style.marginLeft = '70px';
                }
                
                // Handle orientation changes
                window.addEventListener('orientationchange', function() {
                    // Small delay to ensure DOM updates after orientation change
                    setTimeout(function() {
                        // Check if scrolling is needed
                        checkPanelScrolling();
                    }, 300);
                });
                
                // Add swipe gesture support for the left panel
                let touchStartX = 0;
                let touchEndX = 0;

                document.addEventListener('touchstart', function(e) {
                    touchStartX = e.changedTouches[0].screenX;
                }, false);

                document.addEventListener('touchend', function(e) {
                    touchEndX = e.changedTouches[0].screenX;
                    handleSwipe();
                }, false);

                function handleSwipe() {
                    const leftPanel = document.getElementById('leftPanel');
                    const overlay = document.getElementById('overlay');
                    const swipeThreshold = 70; // Minimum swipe distance
                    
                    // Left to right swipe (open panel)
                    if (touchEndX - touchStartX > swipeThreshold && touchStartX < 50) {
                        leftPanel.classList.add('mobile-open');
                        overlay.classList.add('active');
                    }
                    
                    // Right to left swipe (close panel)
                    if (touchStartX - touchEndX > swipeThreshold && leftPanel.classList.contains('mobile-open')) {
                        leftPanel.classList.remove('mobile-open');
                        overlay.classList.remove('active');
                    }
                }

                // Add this code to check and create the fallback image path if it doesn't exist
                const testImg = new Image();
                testImg.onload = function() {
                    console.log('Fallback image exists');
                };
                testImg.onerror = function() {
                    console.warn('Fallback image does not exist. Using data URL instead.');
                    // Create a simple "No Photo" SVG as a data URL
                    window.noPhotoImage = 'data:image/svg+xml;base64,' + btoa('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#f0f0f0"/><text x="50%" y="50%" font-family="Arial" font-size="16" text-anchor="middle" dominant-baseline="middle" fill="#666">No Photo Available</text></svg>');
                };
                testImg.src = 'assets/images/no-photo.png';
            } catch (e) {
                console.error("JavaScript error:", e);
            }
        });
        


        // Add this function definition after the togglePanel function
        function showPanelNotification(message) {
            // Create notification element if it doesn't exist
            let notification = document.getElementById('panel-notification');
            if (!notification) {
                notification = document.createElement('div');
                notification.id = 'panel-notification';
                notification.style.position = 'fixed';
                notification.style.bottom = '20px';
                notification.style.right = '20px';
                notification.style.padding = '10px 15px';
                notification.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
                notification.style.color = 'white';
                notification.style.borderRadius = '4px';
                notification.style.zIndex = '9999';
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.3s';
                document.body.appendChild(notification);
            }
            
            // Set message and show notification
            notification.textContent = message;
            notification.style.opacity = '1';
            
            // Hide after 2 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
            }, 2000);
        }

        // Export and print functionality
        document.getElementById('export-csv').addEventListener('click', function() {
            exportSimplifiedWorkReports();
        });

        document.getElementById('print-table').addEventListener('click', function() {
            window.print();
        });

        // Add event listener for the export all work reports button
        document.getElementById('export-all-work-reports').addEventListener('click', function() {
            exportSimplifiedWorkReports();
        });

        // Replace the existing viewWorkReport function with this improved version
        function viewWorkReport(attendanceId) {
            fetch('get_work_report.php?id=' + attendanceId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Create a modal to display the work report
                        const modal = document.createElement('div');
                        modal.id = 'work-report-modal-' + Date.now(); // Add unique ID
                        modal.style.position = 'fixed';
                        modal.style.top = '0';
                        modal.style.left = '0';
                        modal.style.width = '100%';
                        modal.style.height = '100%';
                        modal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
                        modal.style.display = 'flex';
                        modal.style.justifyContent = 'center';
                        modal.style.alignItems = 'center';
                        modal.style.zIndex = '10000';
                        
                        const modalContent = document.createElement('div');
                        modalContent.style.backgroundColor = 'white';
                        modalContent.style.padding = '20px';
                        modalContent.style.borderRadius = '8px';
                        modalContent.style.maxWidth = '80%';
                        modalContent.style.maxHeight = '80%';
                        modalContent.style.overflow = 'auto';
                        modalContent.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.2)';
                        
                        const modalHeader = document.createElement('div');
                        modalHeader.style.display = 'flex';
                        modalHeader.style.justifyContent = 'space-between';
                        modalHeader.style.alignItems = 'center';
                        modalHeader.style.marginBottom = '15px';
                        modalHeader.style.borderBottom = '1px solid #eee';
                        modalHeader.style.paddingBottom = '10px';
                        
                        const titleContainer = document.createElement('div');
                        titleContainer.style.display = 'flex';
                        titleContainer.style.alignItems = 'center';
                        titleContainer.style.gap = '10px';
                        
                        const modalTitle = document.createElement('h3');
                        modalTitle.textContent = 'Work Report - ' + data.date;
                        modalTitle.style.margin = '0';
                        
                        // Add Excel export icon
                        const exportIcon = document.createElement('i');
                        exportIcon.className = 'fas fa-file-excel';
                        exportIcon.style.color = '#217346'; // Excel green color
                        exportIcon.style.fontSize = '18px';
                        exportIcon.style.cursor = 'pointer';
                        exportIcon.title = 'Export to Excel';
                        exportIcon.onclick = function(e) {
                            e.stopPropagation();
                            exportWorkReportToExcel(data);
                        };
                        
                        titleContainer.appendChild(modalTitle);
                        titleContainer.appendChild(exportIcon);
                        
                        const closeBtn = document.createElement('button');
                        closeBtn.innerHTML = '&times;';
                        closeBtn.style.width = '30px';
                        closeBtn.style.height = '30px';
                        closeBtn.style.color = 'white';
                        closeBtn.style.background = 'red';
                        closeBtn.style.border = 'none';
                        closeBtn.style.fontSize = '24px';
                        closeBtn.style.cursor = 'pointer';
                        closeBtn.style.padding = '0 5px';
                        closeBtn.onclick = function() {
                            if (modal.parentNode) {
                                modal.parentNode.removeChild(modal);
                            }
                        };
                        
                        const modalBody = document.createElement('div');
                        modalBody.style.whiteSpace = 'pre-wrap'; // Preserve line breaks
                        modalBody.textContent = data.report;
                        
                        modalHeader.appendChild(titleContainer);
                        modalHeader.appendChild(closeBtn);
                        
                        modalContent.appendChild(modalHeader);
                        modalContent.appendChild(modalBody);
                        
                        modal.appendChild(modalContent);
                        document.body.appendChild(modal);
                        
                        // Close modal when clicking outside
                        modal.addEventListener('click', function(e) {
                            if (e.target === modal && modal.parentNode) {
                                modal.parentNode.removeChild(modal);
                            }
                        });
                        
                        // Add keyboard support for closing modal with Escape key
                        const escHandler = function(e) {
                            if (e.key === 'Escape' && modal.parentNode) {
                                modal.parentNode.removeChild(modal);
                                document.removeEventListener('keydown', escHandler);
                            }
                        };
                        
                        document.addEventListener('keydown', escHandler);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to fetch work report. Please try again later.');
                });
        }

        // Function to sanitize Excel sheet names
        function sanitizeSheetName(name) {
            // Excel sheet names cannot contain: \ / ? * [ ] : 
            // Also limited to 31 characters
            return name.replace(/[\\\/\?\*\[\]\:]/g, '_').substring(0, 31);
        }

        // Function to export work report to Excel
        function exportWorkReportToExcel(data) {
            // Get the current month and year from the filters
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;
            const monthName = document.getElementById('month').options[month-1].text;
            
            // Create a new workbook
            const workbook = XLSX.utils.book_new();
            
            // Format the date and get day name
            const reportDate = new Date(data.date);
            const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const dayName = dayNames[reportDate.getDay()];
            const formattedDate = reportDate.toLocaleDateString();
            
            // Create worksheet data
            const wsData = [
                ['Work Report'],
                ['Employee', data.username],
                ['Month', monthName + ' ' + year],
                ['Date', formattedDate],
                ['Day', dayName],
                [''],
                ['Work Report Details'],
                [data.report]
            ];
            
            // Create worksheet
            const ws = XLSX.utils.aoa_to_sheet(wsData);
            
            // Set column widths
            const wscols = [
                {wch: 15}, // A
                {wch: 50}  // B
            ];
            ws['!cols'] = wscols;
            
            // Add worksheet to workbook with sanitized name
            XLSX.utils.book_append_sheet(workbook, ws, sanitizeSheetName('Work Report'));
            
            // Generate Excel file and trigger download with unique filename
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            XLSX.writeFile(workbook, `Work_Report_${data.username.replace(/[\\\/\?\*\[\]\:]/g, '_')}_${formattedDate.replace(/[\\\/\?\*\[\]\:]/g, '_')}_${timestamp}.xlsx`);
        }

        // Function to export all work reports for the month
        function exportAllWorkReports() {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;
            const monthName = document.getElementById('month').options[month-1].text;
            
            // Show loading indicator
            const loadingIndicator = document.createElement('div');
            loadingIndicator.id = 'work-report-loading';
            loadingIndicator.style.position = 'fixed';
            loadingIndicator.style.top = '0';
            loadingIndicator.style.left = '0';
            loadingIndicator.style.width = '100%';
            loadingIndicator.style.height = '100%';
            loadingIndicator.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
            loadingIndicator.style.display = 'flex';
            loadingIndicator.style.justifyContent = 'center';
            loadingIndicator.style.alignItems = 'center';
            loadingIndicator.style.zIndex = '10000';
            
            const loadingContent = document.createElement('div');
            loadingContent.style.backgroundColor = 'white';
            loadingContent.style.padding = '20px';
            loadingContent.style.borderRadius = '8px';
            loadingContent.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.2)';
            loadingContent.style.textAlign = 'center';
            loadingContent.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 10px;"></i><p>Exporting work reports...</p>';
            
            loadingIndicator.appendChild(loadingContent);
            document.body.appendChild(loadingIndicator);
            
            // Helper function to safely remove the loading indicator
            function removeLoadingIndicator() {
                const indicator = document.getElementById('work-report-loading');
                if (indicator && indicator.parentNode) {
                    indicator.parentNode.removeChild(indicator);
                }
            }
            
            // Fetch all work reports for the month
            fetch(`export_work_reports.php?month=${month}&year=${year}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Remove loading indicator
                    removeLoadingIndicator();
                    
                    if (data.success) {
                        // Create a new workbook
                        const workbook = XLSX.utils.book_new();
                        
                        // Process each work report
                        data.reports.forEach(report => {
                            // Format the date and get day name
                            const reportDate = new Date(report.date);
                            const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                            const dayName = dayNames[reportDate.getDay()];
                            const formattedDate = reportDate.toLocaleDateString();
                            
                            // Create worksheet data
                            const wsData = [
                                ['Work Report'],
                                ['Employee', report.username],
                                ['Month', monthName + ' ' + year],
                                ['Date', formattedDate],
                                ['Day', dayName],
                                [''],
                                ['Work Report Details'],
                                [report.work_report || 'No work report submitted']
                            ];
                            
                            // Create worksheet
                            const ws = XLSX.utils.aoa_to_sheet(wsData);
                            
                            // Set column widths
                            const wscols = [
                                {wch: 15}, // A
                                {wch: 50}  // B
                            ];
                            ws['!cols'] = wscols;
                            
                            // Add worksheet to workbook - use sanitized date as sheet name
                            const sheetName = sanitizeSheetName(formattedDate);
                            XLSX.utils.book_append_sheet(workbook, ws, sheetName);
                        });
                        
                        // Generate Excel file and trigger download with unique timestamp
                        const safeMonthName = monthName.replace(/[\\\/\?\*\[\]\:]/g, '_');
                        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
                        XLSX.writeFile(workbook, `All_Work_Reports_${safeMonthName}_${year}_${timestamp}.xlsx`);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    // Remove loading indicator
                    removeLoadingIndicator();
                    
                    console.error('Error:', error);
                    alert('Failed to export work reports. Please try again later.');
                });
        }

        // Function to export simplified work reports with just date, day, and work report
        function exportSimplifiedWorkReports() {
            const month = document.getElementById('month').value;
            const year = document.getElementById('year').value;
            const monthName = document.getElementById('month').options[month-1].text;
            
            // Show loading indicator
            const loadingIndicator = document.createElement('div');
            loadingIndicator.id = 'work-report-loading';
            loadingIndicator.style.position = 'fixed';
            loadingIndicator.style.top = '0';
            loadingIndicator.style.left = '0';
            loadingIndicator.style.width = '100%';
            loadingIndicator.style.height = '100%';
            loadingIndicator.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
            loadingIndicator.style.display = 'flex';
            loadingIndicator.style.justifyContent = 'center';
            loadingIndicator.style.alignItems = 'center';
            loadingIndicator.style.zIndex = '10000';
            
            const loadingContent = document.createElement('div');
            loadingContent.style.backgroundColor = 'white';
            loadingContent.style.padding = '20px';
            loadingContent.style.borderRadius = '8px';
            loadingContent.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.2)';
            loadingContent.style.textAlign = 'center';
            loadingContent.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 10px;"></i><p>Exporting work reports...</p>';
            
            loadingIndicator.appendChild(loadingContent);
            document.body.appendChild(loadingIndicator);
            
            // Helper function to safely remove the loading indicator
            function removeLoadingIndicator() {
                const indicator = document.getElementById('work-report-loading');
                if (indicator && indicator.parentNode) {
                    indicator.parentNode.removeChild(indicator);
                }
            }
            
            // Fetch all work reports for the month
            fetch(`export_work_reports.php?month=${month}&year=${year}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Remove loading indicator
                    removeLoadingIndicator();
                    
                    if (data.success) {
                        // Create a new workbook
                        const workbook = XLSX.utils.book_new();
                        
                        // Create a single worksheet with all reports
                        const wsData = [
                            ['Date', 'Day', 'Work Report']
                        ];
                        
                        // Sort reports by date
                        data.reports.sort((a, b) => new Date(a.date) - new Date(b.date));
                        
                        // Add each report as a row
                        data.reports.forEach(report => {
                            const reportDate = new Date(report.date);
                            const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                            const dayName = dayNames[reportDate.getDay()];
                            const formattedDate = reportDate.toLocaleDateString();
                            
                            wsData.push([
                                formattedDate,
                                dayName,
                                report.work_report || 'No work report submitted'
                            ]);
                        });
                        
                        // Create worksheet
                        const ws = XLSX.utils.aoa_to_sheet(wsData);
                        
                        // Set column widths
                        const wscols = [
                            {wch: 15}, // Date
                            {wch: 10}, // Day
                            {wch: 80}  // Work Report
                        ];
                        ws['!cols'] = wscols;
                        
                        // Add worksheet to workbook
                        XLSX.utils.book_append_sheet(workbook, ws, sanitizeSheetName('Work Reports'));
                        
                        // Generate Excel file and trigger download with unique filename
                        const safeMonthName = monthName.replace(/[\\\/\?\*\[\]\:]/g, '_');
                        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
                        XLSX.writeFile(workbook, `Work_Reports_${safeMonthName}_${year}_${timestamp}.xlsx`);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    // Remove loading indicator
                    removeLoadingIndicator();
                    
                    console.error('Error:', error);
                    alert('Failed to export work reports. Please try again later.');
                });
        }



        // Add this function to display attendance photos in a modal
        function viewAttendancePhoto(photoPath, type, date) {
            // Create a modal to display the photo
            const modal = document.createElement('div');
            modal.id = 'photo-modal-' + Date.now(); // Add unique ID
            modal.style.position = 'fixed';
            modal.style.top = '0';
            modal.style.left = '0';
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
            modal.style.display = 'flex';
            modal.style.justifyContent = 'center';
            modal.style.alignItems = 'center';
            modal.style.zIndex = '10000';
            
            const modalContent = document.createElement('div');
            modalContent.style.backgroundColor = 'white';
            modalContent.style.padding = '20px';
            modalContent.style.borderRadius = '8px';
            modalContent.style.maxWidth = '90%';
            modalContent.style.maxHeight = '90%';
            modalContent.style.overflow = 'hidden';
            modalContent.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.2)';
            modalContent.style.position = 'relative';
            modalContent.style.display = 'flex';
            modalContent.style.flexDirection = 'column';
            
            const modalHeader = document.createElement('div');
            modalHeader.style.display = 'flex';
            modalHeader.style.justifyContent = 'space-between';
            modalHeader.style.alignItems = 'center';
            modalHeader.style.marginBottom = '15px';
            modalHeader.style.borderBottom = '1px solid #eee';
            modalHeader.style.paddingBottom = '10px';
            
            const modalTitle = document.createElement('h3');
            modalTitle.textContent = type + ' Photo - ' + date;
            modalTitle.style.margin = '0';
            
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.background = 'none';
            closeBtn.style.border = 'none';
            closeBtn.style.fontSize = '24px';
            closeBtn.style.cursor = 'pointer';
            closeBtn.style.padding = '0 5px';
            closeBtn.onclick = function() {
                if (modal.parentNode) {
                    modal.parentNode.removeChild(modal);
                }
            };
            
            const modalBody = document.createElement('div');
            modalBody.style.display = 'flex';
            modalBody.style.justifyContent = 'center';
            modalBody.style.alignItems = 'center';
            modalBody.style.overflow = 'auto';
            modalBody.style.maxHeight = 'calc(90vh - 80px)';
            
            const img = document.createElement('img');
            // Fix the path construction to avoid duplication
            if (photoPath.startsWith('http')) {
                img.src = photoPath;
            } else if (photoPath.includes('uploads/attendance/')) {
                img.src = photoPath; // Path already includes the directory
            } else {
                img.src = 'uploads/attendance/' + photoPath;
            }
            
            img.style.maxWidth = '100%';
            img.style.maxHeight = '100%';
            img.style.objectFit = 'contain';
            img.onerror = function() {
                this.onerror = null;
                
                // Use the data URL if the fallback image doesn't exist
                if (window.noPhotoImage) {
                    this.src = window.noPhotoImage;
                } else {
                    this.src = 'assets/images/no-photo.png';
                }
                
                const errorText = document.createElement('p');
                errorText.textContent = 'Photo could not be loaded';
                errorText.style.color = 'red';
                errorText.style.textAlign = 'center';
                errorText.style.marginTop = '10px';
                modalBody.appendChild(errorText);
            };
            
            modalHeader.appendChild(modalTitle);
            modalHeader.appendChild(closeBtn);
            
            modalBody.appendChild(img);
            
            modalContent.appendChild(modalHeader);
            modalContent.appendChild(modalBody);
            
            modal.appendChild(modalContent);
            document.body.appendChild(modal);
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(e) {
                if (e.target === modal && modal.parentNode) {
                    modal.parentNode.removeChild(modal);
                }
            });
            
            // Add keyboard support for closing modal with Escape key
            const escHandler = function(e) {
                if (e.key === 'Escape' && modal.parentNode) {
                    modal.parentNode.removeChild(modal);
                    document.removeEventListener('keydown', escHandler);
                }
            };
            
            document.addEventListener('keydown', escHandler);
        }
    </script>
</body>
</html>