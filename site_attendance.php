<?php
// Include database connection
require_once 'config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has the right role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Redirect to login page
    header("Location: login.php");
    exit;
}

// Only allow Senior Manager (Site) role to access this page
if ($_SESSION['role'] !== 'Senior Manager (Site)' && $_SESSION['role'] !== 'Purchase Manager') {
    // Redirect to unauthorized page or dashboard
    header("Location: unauthorized.php");
    exit;
}

// Get date from query parameter or use current date
$currentDate = date('Y-m-d'); // Always use current date

// Get filter parameters
$filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$filterUser = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0; // 0 means all users

// For backward compatibility with any code still using $trendDays
$trendDays = isset($_GET['trend_days']) ? (int)$_GET['trend_days'] : 30;

// Validate month and year
if ($filterMonth < 1 || $filterMonth > 12) {
    $filterMonth = (int)date('m');
}
if ($filterYear < 2000 || $filterYear > (int)date('Y')) {
    $filterYear = (int)date('Y');
}

// Calculate start and end dates for the selected month
$startDate = sprintf('%04d-%02d-01', $filterYear, $filterMonth);
$endDate = date('Y-m-t', strtotime($startDate)); // t gets the last day of the month

$attendanceData = [];
$trendData = [];

try {
    // Query to get today's attendance summary
    $summaryQuery = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'Present' THEN 1 END) as present_count,
            COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent_count,
            COUNT(CASE WHEN status = 'Late' THEN 1 END) as late_count,
            COUNT(DISTINCT user_id) as total_employees
        FROM attendance 
        WHERE date = :currentDate
    ");
    $summaryQuery->execute([':currentDate' => $currentDate]);
    $summary = $summaryQuery->fetch();
    
    // Query to get total employees from users table
    $employeesQuery = $pdo->query("
        SELECT COUNT(*) as total_count
        FROM users
        WHERE status = 'Active'
    ");
    $employeesResult = $employeesQuery->fetch();
    $totalEmployees = $employeesResult['total_count'] ?? 0;
    
    // Fetch all users for the dropdown
    $usersQuery = $pdo->query("
        SELECT id, username, role
        FROM users
        WHERE status = 'Active'
        ORDER BY username ASC
    ");
    $allUsers = $usersQuery->fetchAll();
    
    // If no data for today, use default values
    $presentCount = $summary['present_count'] ?? 128;
    $absentCount = $summary['absent_count'] ?? 8;
    $lateCount = $summary['late_count'] ?? 6;
    
    // Calculate attendance percentages
    $presentPercentage = $totalEmployees > 0 ? round(($presentCount / $totalEmployees) * 100) : 0;
    $absentPercentage = $totalEmployees > 0 ? round(($absentCount / $totalEmployees) * 100) : 0;
    $latePercentage = $totalEmployees > 0 ? round(($lateCount / $totalEmployees) * 100) : 0;
    
    // Query to get attendance records with user details for the selected month and year
    $params = [];
    $filters = [];
    
    // Filter by month and year
    $monthStartDate = sprintf('%04d-%02d-01', $filterYear, $filterMonth);
    $monthEndDate = date('Y-m-t', strtotime($monthStartDate));
    
    $filters[] = "a.date BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $monthStartDate;
    $params[':end_date'] = $monthEndDate;
    
    // Filter by user if specified
    if ($filterUser > 0) {
        $filters[] = "a.user_id = :user_id";
        $params[':user_id'] = $filterUser;
    }
    
    // Combine all filters
    $whereClause = implode(' AND ', $filters);
    
    $recordsQuery = $pdo->prepare("
        SELECT 
            a.id,
            u.username as name,
            u.role,
            u.department,
            a.date,
            a.punch_in as check_in,
            a.punch_out as check_out,
            a.working_hours,
            a.overtime_hours,
            a.status,
            a.address as punch_in_address,
            a.punch_out_address,
            a.punch_in_photo,
            a.punch_out_photo,
            a.remarks,
            u.id as user_id
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        WHERE $whereClause
        ORDER BY a.date DESC, a.punch_in DESC
        LIMIT 40
    ");
    $recordsQuery->execute($params);
    $attendanceData = $recordsQuery->fetchAll();
    
    // Query to get attendance trend data for the selected month
    // $startDate and $endDate are already set based on the month/year filter
    
    $trendQuery = $pdo->prepare("
        SELECT 
            date,
            COUNT(CASE WHEN status = 'Present' THEN 1 END) as present_count,
            COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent_count,
            COUNT(CASE WHEN status = 'Late' THEN 1 END) as late_count
        FROM attendance
        WHERE date BETWEEN :startDate AND :endDate
        GROUP BY date
        ORDER BY date ASC
    ");
    
    $trendQuery->execute([
        ':startDate' => $startDate,
        ':endDate' => $endDate
    ]);
    
    $trendData = $trendQuery->fetchAll();
    
    // Fill in missing dates with zero values
    $dateRange = [];
    $presentData = [];
    $absentData = [];
    $lateData = [];
    $labels = [];
    
    // Create a date period for all days in the selected month
    $period = new DatePeriod(
        new DateTime($startDate),
        new DateInterval('P1D'),
        new DateTime(date('Y-m-d', strtotime($endDate . ' +1 day')))
    );
    
    // Initialize arrays with zeros for all dates
    foreach ($period as $date) {
        $dateStr = $date->format('Y-m-d');
        $dateRange[$dateStr] = [
            'present' => 0,
            'absent' => 0,
            'late' => 0
        ];
        $labels[] = $date->format('d');
    }
    
    // Fill in actual values from the database
    foreach ($trendData as $day) {
        if (isset($dateRange[$day['date']])) {
            $dateRange[$day['date']]['present'] = (int)$day['present_count'];
            $dateRange[$day['date']]['absent'] = (int)$day['absent_count'];
            $dateRange[$day['date']]['late'] = (int)$day['late_count'];
        }
    }
    
    // Extract data for the chart
    foreach ($dateRange as $data) {
        $presentData[] = $data['present'];
        $absentData[] = $data['absent'];
        $lateData[] = $data['late'];
    }
    
} catch (PDOException $e) {
    // Log error but don't expose details to user
    error_log("Attendance data fetch error: " . $e->getMessage());
    // Use default data if database query fails
    $attendanceData = [];
    $daysInMonth = date('t', mktime(0, 0, 0, $filterMonth, 1, $filterYear));
    $labels = array_map(function($i) { return $i; }, range(1, $daysInMonth));
    $presentData = array_fill(0, $daysInMonth, 0);
    $absentData = array_fill(0, $daysInMonth, 0);
    $lateData = array_fill(0, $daysInMonth, 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Overview</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --warning-color: #f8961e;
            --danger-color: #f72585;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --white-color: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            display: flex;
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
        }

        .left-panel {
            background: #23336a;
            color: #fff;
            border-right: none;
            box-shadow: none;
            padding: 0;
            width: 240px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
            scrollbar-width: none; /* Firefox */
        }

        .left-panel::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        .left-panel.collapsed {
            width: 60px;
        }
        
        /* Icon-only sidebar styling */
        .left-panel.collapsed .menu-item,
        .left-panel.collapsed .menu-item.section-start {
            padding: 14px 0;
            justify-content: center;
            height: 46px;
        }
        
        .left-panel.collapsed .menu-text {
            display: none;
        }
        
        .left-panel.collapsed .menu-item.section-start {
            height: 10px;
            padding: 0;
            margin: 10px 0;
            opacity: 0.3;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .left-panel.collapsed .menu-item.section-start i,
        .left-panel.collapsed .menu-item.section-start .menu-text {
            display: none;
        }
        
        .left-panel.collapsed .menu-item.active {
            background: #3952a3;
            border-left: none;
            position: relative;
        }
        
        .left-panel.collapsed .menu-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: #4cc9f0;
        }
        
        /* Ensure icons are centered in collapsed state */
        .left-panel.collapsed .menu-item i {
            margin: 0;
            font-size: 18px;
        }
        
        /* Adjust brand logo in collapsed state */
        .left-panel.collapsed .brand-logo {
            justify-content: center;
            padding: 15px 0;
        }
        
        /* Adjust logo size in collapsed state */
        .left-panel.collapsed .brand-logo img {
            max-width: 30px;
        }

        .main-content {
            margin-left: 240px;
            width: calc(100% - 240px);
            transition: all 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 60px;
            width: calc(100% - 60px);
        }

        .container {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 30px 30px 20px 30px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .header-left h1 {
            font-size: 28px;
            color: var(--primary-color);
            font-weight: 600;
        }

        .header-left p {
            color: var(--gray-color);
            font-size: 14px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .date-picker {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background-color: var(--white-color);
            cursor: pointer;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background-color: var(--primary-color);
            color: var(--white-color);
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: var(--white-color);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-card .stat-title {
            font-size: 14px;
            color: var(--gray-color);
            font-weight: 500;
        }

        .stat-card .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white-color);
        }

        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card .stat-change {
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stat-card .stat-change.positive {
            color: #2ecc71;
        }

        .stat-card .stat-change.negative {
            color: #e74c3c;
        }

        .card {
            background-color: var(--white-color);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-color);
        }

        .card-actions {
            display: flex;
            gap: 10px;
        }

        .chart-container {
            height: 300px;
            position: relative;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table th {
            text-align: left;
            padding: 12px 15px;
            background-color: #f1f3f9;
            font-weight: 600;
            color: var(--dark-color);
        }

        .attendance-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        .attendance-table tr:last-child td {
            border-bottom: none;
        }

        .attendance-table tr:hover td {
            background-color: #f8f9fa;
        }

        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .status-present {
            background-color: #e3f9e5;
            color: #2ecc71;
        }

        .status-absent {
            background-color: #feeaea;
            color: #e74c3c;
        }

        .status-late {
            background-color: #fff3e0;
            color: #f39c12;
        }

        .status-leave {
            background-color: #e3f2fd;
            color: #3498db;
        }

        .pagination {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination-btn {
            width: 35px;
            height: 35px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--white-color);
            border: 1px solid #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pagination-btn:hover {
            background-color: var(--primary-color);
            color: var(--white-color);
            border-color: var(--primary-color);
        }

        .pagination-btn.active {
            background-color: var(--primary-color);
            color: var(--white-color);
            border-color: var(--primary-color);
        }
        
        .photo-icon {
            margin-left: 5px;
            cursor: pointer;
            color: var(--primary-color);
            transition: color 0.2s, transform 0.2s;
        }
        
        .photo-icon:hover {
            color: var(--secondary-color);
            transform: scale(1.2);
        }
        
        .overtime-hours {
            color: #e74c3c;
            font-weight: 600;
            background-color: rgba(231, 76, 60, 0.1);
        }

        .search-filter {
            display: flex;
            justify-content: flex-start;
            margin-bottom: 20px;
            gap: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            align-items: center;
        }

        .search-filter label {
            font-weight: 500;
            color: var(--dark-color);
            margin-right: -5px;
        }

        .filter-dropdown {
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background-color: var(--white-color);
            cursor: pointer;
            margin-left: 8px;
            min-width: 150px;
            font-size: 14px;
        }

        #userFilter {
            min-width: 250px;
            max-width: 350px;
            flex-grow: 1;
        }

        .filter-group {
            display: flex;
            align-items: center;
        }
        
        .card-actions {
            display: flex;
            align-items: center;
        }
        
        .card-actions .filter-dropdown {
            min-width: 100px;
        }

        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }

            .search-filter {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .filter-group {
                width: 100%;
            }
            
            .filter-dropdown {
                flex-grow: 1;
            }
        }

        /* Manager Panel Sidebar Custom Styles */
        .left-panel {
            background: #23336a;
            color: #fff;
            border-right: none;
            box-shadow: none;
            padding: 0;
            width: 240px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
            scrollbar-width: none; /* Firefox */
        }

        .left-panel .brand-logo {
            background: #23336a;
            border-bottom: 1px solid #2d437c;
            margin-bottom: 0;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            padding: 15px;
            height: 60px;
        }
        
        .left-panel.collapsed {
            width: 60px;
        }
        
        /* Menu items in all states */
        .left-panel .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            font-size: 16px;
            color: #fff;
            cursor: pointer;
            transition: background 0.2s;
            border: none;
            background: none;
            justify-content: flex-start;
            position: relative;
            height: 46px;
            box-sizing: border-box;
        }
        
        .left-panel .menu-item i {
            font-size: 18px;
            min-width: 24px;
            text-align: center;
        }
        
        /* Text always visible in default state */
        .left-panel .menu-text {
            flex: 1;
            font-size: 15px;
            font-weight: 400;
            display: block;
            margin-left: 15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Active state */
        .left-panel .menu-item.active {
            background: #3952a3;
            color: #fff;
            font-weight: 500;
            border-left: none;
        }
        
        /* Section headers */
        .left-panel .menu-item.section-start {
            background: #23336a;
            color: #9aa4c8;
            font-size: 15px;
            font-weight: 500;
            padding: 15px 15px 8px 15px;
            cursor: default;
            border-radius: 0;
            border-bottom: none;
            height: 36px;
        }
        
        .left-panel .menu-item.section-start i {
            color: #9aa4c8;
        }
        
        /* Hover effect */
        .left-panel .menu-item:not(.section-start):hover {
            background: #2d437c;
            color: #fff;
        }
        
        /* Section divider */
        .left-panel .menu-item.section-start:not(:first-child) {
            border-top: 1px solid #2d437c;
            margin-top: 8px;
            padding-top: 18px;
        }

        .left-panel img {
            filter: brightness(0) invert(1);
        }
        .left-panel .toggle-btn {
            background: transparent;
            color: #fff;
            border: none;
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 10;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 16px;
        }
        .left-panel .toggle-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .left-panel .menu-item.logout-item {
            margin-top: 30px;
            color: #f72585;
        }
        .left-panel .menu-item.logout-item:hover {
            background: #2d437c;
            color: #fff;
        }
        /* Scrollbar for overflow */
        .left-panel {
            overflow-y: auto;
        }
        /* Hide default outline on click */
        .left-panel .menu-item:focus {
            outline: none;
        }
        /* Responsive for sidebar */
        @media (max-width: 900px) {
            .left-panel {
                width: 60px;
            }
            .main-content {
                margin-left: 60px;
                width: calc(100% - 60px);
            }
            .left-panel .brand-logo {
                padding: 10px 5px;
            }
        }

        /* Mobile responsive styles */
        @media (max-width: 576px) {
            .left-panel {
                width: 0;
                transform: translateX(-100%);
                z-index: 1050;
                box-shadow: none;
                transition: transform 0.3s ease, width 0.3s ease;
            }
            
            .left-panel.mobile-open {
                width: 240px;
                transform: translateX(0);
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
            }
            
            .left-panel.mobile-open .menu-text {
                display: block;
                margin-left: 15px;
            }
            
            .left-panel.mobile-open .menu-item {
                justify-content: flex-start;
                padding-left: 24px;
                padding-right: 24px;
            }
            
            .left-panel.mobile-open .menu-item.section-start {
                padding-left: 24px;
                padding-right: 24px;
                height: auto;
                opacity: 1;
                border-bottom: none;
                margin: 0;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
                transition: margin 0.3s ease;
            }
            
            .mobile-menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 40px;
                height: 40px;
                background: var(--primary-color);
                color: white;
                border: none;
                border-radius: 4px;
                margin-right: 15px;
                cursor: pointer;
                font-size: 18px;
            }
            
            .mobile-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                transition: opacity 0.3s ease;
                opacity: 0;
            }
            
            .mobile-overlay.active {
                display: block;
                opacity: 1;
            }
            
            .header-left {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
            }

            .header-left h1 {
                font-size: 22px;
                margin-right: 10px;
            }

            .header-left p {
                font-size: 12px;
                width: 100%;
                margin-top: 5px;
            }

            body.menu-open {
                overflow: hidden;
            }

            .header-right {
                flex-wrap: wrap;
                gap: 8px;
            }

            .btn {
                padding: 6px 12px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/manager_panel.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="mobile-overlay" id="mobileOverlay"></div>
    <div class="container">
        <header>
            <div class="header-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                <h1>Attendance Overview</h1>
                <p>Monitor and manage employee attendance records</p>
            </div>
            <div class="header-right">
                <button class="btn btn-outline">
                    <i class="fas fa-download"></i> Export
                </button>
                <button class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Record
                </button>
            </div>
        </header>

        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Total Employees</span>
                    <div class="stat-icon" style="background-color: var(--primary-color);">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                    <div class="stat-value"><?php echo $totalEmployees; ?></div>
                    <div class="stat-change">
                        <i class="fas fa-user-check"></i> Active employees in system
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Present Today</span>
                    <div class="stat-icon" style="background-color: var(--success-color);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                    <div class="stat-value"><?php echo $presentCount; ?></div>
                <div class="stat-change positive">
                        <i class="fas fa-percentage"></i> <?php echo $presentPercentage; ?>% of total employees
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Absent Today</span>
                    <div class="stat-icon" style="background-color: var(--danger-color);">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
                    <div class="stat-value"><?php echo $absentCount; ?></div>
                <div class="stat-change negative">
                        <i class="fas fa-percentage"></i> <?php echo $absentPercentage; ?>% of total employees
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Late Arrivals</span>
                    <div class="stat-icon" style="background-color: var(--warning-color);">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                    <div class="stat-value"><?php echo $lateCount; ?></div>
                    <div class="stat-change">
                        <i class="fas fa-percentage"></i> <?php echo $latePercentage; ?>% of total employees
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                    <h2 class="card-title">Attendance Trend (<?php echo date('F Y', strtotime($startDate)); ?>)</h2>
                <div class="card-actions">
                        <select class="filter-dropdown" id="filterMonth">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $filterMonth == $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                        </select>
                        <select class="filter-dropdown" id="filterYear">
                            <?php for ($y = date('Y'); $y >= date('Y')-3; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $filterYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="chart-container" id="attendanceChart">
                <!-- Chart will be rendered here -->
                <canvas id="chartCanvas" style="width: 100%; height: 100%;"></canvas>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                    <h2 class="card-title">Attendance Records (<?php echo date('F Y', strtotime($monthStartDate)); ?>)</h2>
                <div class="card-actions">
                        <button class="btn btn-outline" onclick="window.location.href='export_attendance.php?month=<?php echo $filterMonth; ?>&year=<?php echo $filterYear; ?>&user_id=<?php echo $filterUser; ?>'">
                            <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>

            <div class="search-filter">
                    <div class="filter-group">
                        <label for="userFilter">Employee:</label>
                        <select class="filter-dropdown" id="userFilter">
                            <option value="0">All Users</option>
                            <?php foreach ($allUsers as $user): ?>
                                                            <option value="<?php echo $user['id']; ?>" <?php echo ($filterUser == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?> 
                                <?php echo !empty($user['role']) ? '(' . htmlspecialchars($user['role']) . ')' : ''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                </div>
                    <div class="filter-group">
                        <label for="recordFilterMonth">Month:</label>
                        <select class="filter-dropdown" id="recordFilterMonth">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $filterMonth == $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0, 0, 0, $m, 1)); ?></option>
                            <?php endfor; ?>
                </select>
                    </div>
                    <div class="filter-group">
                        <label for="recordFilterYear">Year:</label>
                        <select class="filter-dropdown" id="recordFilterYear">
                            <?php for ($y = date('Y'); $y >= date('Y')-3; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $filterYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                </select>
                    </div>
            </div>

            <div class="table-responsive">
                <table class="attendance-table">
                                            <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Role</th>
                                <th>Date</th>
                                <th>Punch In</th>
                                <th>Punch Out</th>
                                <th>Hours</th>
                                <th>Overtime</th>
                                <th>Punch In Address</th>
                                <th>Punch Out Address</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                    <tbody id="attendanceTableBody">
                            <?php if (!empty($attendanceData)): ?>
                                <?php foreach ($attendanceData as $record): ?>
                                    <?php 
                                        // Determine status class and text
                                        $statusClass = '';
                                        switch(strtolower($record['status'])) {
                                            case 'present':
                                                $statusClass = 'status-present';
                                                break;
                                            case 'absent':
                                                $statusClass = 'status-absent';
                                                break;
                                            case 'late':
                                                $statusClass = 'status-late';
                                                break;
                                            case 'leave':
                                            case 'on leave':
                                                $statusClass = 'status-leave';
                                                break;
                                        }
                                    ?>
                                    <tr data-id="<?php echo $record['id']; ?>">
                                        <td><?php echo htmlspecialchars($record['name']); ?></td>
                                        <td><?php echo htmlspecialchars($record['role'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($record['date']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($record['check_in'] ?: '-'); ?>
                                            <?php if (!empty($record['punch_in_photo'])): ?>
                                                <i class="fas fa-folder photo-icon" title="View Punch In Photo" 
                                                   onclick="showAttendancePhoto('<?php echo htmlspecialchars($record['punch_in_photo']); ?>', 'Punch In Photo')"
                                                   data-photo-path="<?php echo htmlspecialchars($record['punch_in_photo']); ?>"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($record['check_out'] ?: '-'); ?>
                                            <?php if (!empty($record['punch_out_photo'])): ?>
                                                <i class="fas fa-folder photo-icon" title="View Punch Out Photo"
                                                   onclick="showAttendancePhoto('<?php echo htmlspecialchars($record['punch_out_photo']); ?>', 'Punch Out Photo')"
                                                   data-photo-path="<?php echo htmlspecialchars($record['punch_out_photo']); ?>"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['working_hours'] ?: '0'); ?></td>
                                        <td <?php if (!empty($record['overtime_hours']) && $record['overtime_hours'] > 0): ?>class="overtime-hours"<?php endif; ?>>
                                            <?php echo htmlspecialchars($record['overtime_hours'] ?: '0'); ?>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($record['punch_in_address'] ?: 'N/A'); ?>"><?php echo htmlspecialchars(substr($record['punch_in_address'] ?: 'N/A', 0, 20) . (strlen($record['punch_in_address']) > 20 ? '...' : '')); ?></td>
                                        <td title="<?php echo htmlspecialchars($record['punch_out_address'] ?: 'N/A'); ?>"><?php echo htmlspecialchars(substr($record['punch_out_address'] ?: 'N/A', 0, 20) . (strlen($record['punch_out_address']) > 20 ? '...' : '')); ?></td>
                                        <td><span class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($record['status']); ?></span></td>
                                        <td>
                                            <button class="btn-icon" title="View Details" onclick="viewAttendanceDetails(<?php echo $record['id']; ?>)"><i class="fas fa-eye"></i></button>
                                            <button class="btn-icon" title="Edit" onclick="editAttendance(<?php echo $record['id']; ?>)"><i class="fas fa-edit"></i></button>
                                            <button class="btn-icon" title="Delete" onclick="deleteAttendance(<?php echo $record['id']; ?>)"><i class="fas fa-trash-alt"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" style="text-align: center;">No attendance records found for today.</td>
                                </tr>
                            <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <div class="pagination-btn"><i class="fas fa-chevron-left"></i></div>
                <div class="pagination-btn active">1</div>
                <div class="pagination-btn">2</div>
                <div class="pagination-btn">3</div>
                <div class="pagination-btn"><i class="fas fa-chevron-right"></i></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Photo Modal -->
    <div id="photoModal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.8); justify-content: center; align-items: center;">
        <div style="position: relative; background-color: #fefefe; margin: auto; padding: 20px; border-radius: 10px; width: 80%; max-width: 700px;">
            <span style="position: absolute; top: 15px; right: 20px; color: #333; font-size: 28px; font-weight: bold; cursor: pointer;" onclick="closePhotoModal()">&times;</span>
            <h3 id="photoModalTitle" style="margin-top: 0; margin-bottom: 20px; color: var(--dark-color);"></h3>
            <div style="text-align: center;">
                <img id="photoModalImage" src="" alt="Attendance Photo" style="max-width: 100%; max-height: 70vh; border-radius: 5px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                <button id="toggleDebugInfo" style="margin-top: 10px; background: #f0f0f0; border: 1px solid #ddd; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px;">Show Debug Info</button>
                <div id="photoPathInfo" style="margin-top: 10px; font-size: 12px; color: #666; display: none; text-align: left; background: #f5f5f5; padding: 10px; border-radius: 5px;">
                    <p><strong>Debug Info:</strong></p>
                    <p id="originalPath"></p>
                    <p id="attemptedPaths" style="white-space: pre-line;"></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Function to show attendance photo modal - defined in global scope
    function showAttendancePhoto(photoPath, title) {
        const modal = document.getElementById('photoModal');
        const modalImg = document.getElementById('photoModalImage');
        const modalTitle = document.getElementById('photoModalTitle');
        const photoPathInfo = document.getElementById('photoPathInfo');
        const originalPathElement = document.getElementById('originalPath');
        const attemptedPathsElement = document.getElementById('attemptedPaths');
        
        modalTitle.textContent = title;
        
        // Initially hide debug info
        photoPathInfo.style.display = 'none';
        originalPathElement.textContent = 'Original path: ' + photoPath;
        
        // Set up toggle button for debug info
        const toggleDebugBtn = document.getElementById('toggleDebugInfo');
        toggleDebugBtn.onclick = function() {
            if (photoPathInfo.style.display === 'none') {
                photoPathInfo.style.display = 'block';
                toggleDebugBtn.textContent = 'Hide Debug Info';
            } else {
                photoPathInfo.style.display = 'none';
                toggleDebugBtn.textContent = 'Show Debug Info';
            }
        };
        
        // Track attempted paths
        const attempts = [];
        function addAttempt(path) {
            attempts.push(path);
            attemptedPathsElement.textContent = 'Attempted paths: \n' + attempts.join('\n');
        }
        
        console.log('Original photo path:', photoPath);
        
        // Function to create a data URL for a no-image fallback
        function createNoImageFallback() {
            return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
                '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">' +
                '<rect width="200" height="200" fill="#f0f0f0"/>' +
                '<text x="50%" y="50%" font-family="Arial" font-size="20" text-anchor="middle" fill="#999">Image Not Found</text>' +
                '</svg>'
            );
        }
        
        // Set up error handling before setting src
        modalImg.onerror = function() {
            console.error('Failed to load image from path:', this.src);
            addAttempt(this.src + ' ❌');
            
            // Try different paths in order
            const baseFileName = photoPath.split('/').pop().split('\\').pop();
            
            if (!this.hasTriedWithoutDirectory) {
                this.hasTriedWithoutDirectory = true;
                addAttempt(baseFileName + ' (trying...)');
                this.src = baseFileName;
                return;
            }
            
            if (!this.hasTriedUploads) {
                this.hasTriedUploads = true;
                addAttempt('uploads/' + baseFileName + ' (trying...)');
                this.src = 'uploads/' + baseFileName;
                return;
            }
            
            if (!this.hasTriedImages) {
                this.hasTriedImages = true;
                addAttempt('images/' + baseFileName + ' (trying...)');
                this.src = 'images/' + baseFileName;
                return;
            }
            
            if (!this.hasTriedAssets) {
                this.hasTriedAssets = true;
                addAttempt('assets/images/' + baseFileName + ' (trying...)');
                this.src = 'assets/images/' + baseFileName;
                return;
            }
            
            // All attempts failed, use data URL fallback
            addAttempt('Fallback image');
            this.src = createNoImageFallback();
            addAttempt('⚠️ All attempts failed. Please check server path configuration.');
        };
        
        // Calculate initial path to try
        let initialPath;
        if (photoPath.startsWith('http')) {
            initialPath = photoPath; // Use as-is for full URLs
        } else if (photoPath.includes('/') || photoPath.includes('\\')) {
            initialPath = photoPath; // Use as-is if it contains path separators
        } else {
            initialPath = 'uploads/attendance/' + photoPath;
        }
        
        addAttempt(initialPath + ' (trying first...)');
        modalImg.src = initialPath;
        
        modal.style.display = 'flex';
        
        // Prevent body scrolling when modal is open
        document.body.style.overflow = 'hidden';
    }
    
    // Function to close photo modal - defined in global scope
    function closePhotoModal() {
        const modal = document.getElementById('photoModal');
        modal.style.display = 'none';
        
        // Re-enable body scrolling
        document.body.style.overflow = 'auto';
    }
    
        document.addEventListener('DOMContentLoaded', function() {
            // Restore scroll position if available
            const savedScrollPosition = sessionStorage.getItem('attendancePageScrollPosition');
            if (savedScrollPosition) {
                window.scrollTo(0, parseInt(savedScrollPosition));
                // Clear the saved position after restoring
                sessionStorage.removeItem('attendancePageScrollPosition');
            }
            
            // Set up modal event listeners
            // Close modal when clicking outside of the modal content
            document.getElementById('photoModal').addEventListener('click', function(event) {
                if (event.target === this) {
                    closePhotoModal();
                }
            });
            
            // Close modal with escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && document.getElementById('photoModal').style.display === 'flex') {
                    closePhotoModal();
                }
            });

            // Use real attendance trend data from PHP
            const ctx = document.getElementById('chartCanvas').getContext('2d');
            const attendanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [
                        {
                            label: 'Present',
                            data: <?php echo json_encode($presentData); ?>,
                            borderColor: '#4cc9f0',
                            backgroundColor: 'rgba(76, 201, 240, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Absent',
                            data: <?php echo json_encode($absentData); ?>,
                            borderColor: '#f72585',
                            backgroundColor: 'rgba(247, 37, 133, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Late',
                            data: <?php echo json_encode($lateData); ?>,
                            borderColor: '#f8961e',
                            backgroundColor: 'rgba(248, 150, 30, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                title: function(context) {
                                    // Add month name to day number
                                    const monthNames = ["January", "February", "March", "April", "May", "June", 
                                                      "July", "August", "September", "October", "November", "December"];
                                    return context[0].label + ' ' + monthNames[<?php echo $filterMonth - 1; ?>] + ' <?php echo $filterYear; ?>';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                maxTicksLimit: 15, // Show fewer ticks for better readability
                                callback: function(value, index) {
                                    // Show every nth label depending on number of days
                                    const daysInMonth = <?php echo date('t', mktime(0, 0, 0, $filterMonth, 1, $filterYear)); ?>;
                                    const step = Math.ceil(daysInMonth / 15); // Show max 15 labels
                                    return index % step === 0 ? this.getLabelForValue(value) : '';
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 5
                            }
                        }
                    }
                }
            });

            // Table is already populated with PHP
            const tableBody = document.getElementById('attendanceTableBody');

            // Add event listeners for pagination buttons
            const paginationButtons = document.querySelectorAll('.pagination-btn');
            paginationButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    paginationButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button if it's a number
                    if (!isNaN(parseInt(this.textContent))) {
                        this.classList.add('active');
                    }
                    
                    // Here you would typically fetch new data for the selected page
                    // For this example, we'll just log the page number
                    console.log(`Page ${this.textContent} selected`);
                });
            });

            // Add panel toggle functionality
            const leftPanel = document.getElementById('leftPanel');
            const mainContent = document.getElementById('mainContent');
            const toggleBtn = document.getElementById('leftPanelToggleBtn');
            const toggleIcon = document.getElementById('toggleIcon');

            if (toggleBtn && leftPanel && mainContent && toggleIcon) {
                toggleBtn.addEventListener('click', function() {
                    leftPanel.classList.toggle('collapsed');
                    mainContent.classList.toggle('expanded');
                    toggleIcon.classList.toggle('fa-chevron-left');
                    toggleIcon.classList.toggle('fa-chevron-right');
                });
            }

            // Keyboard shortcut for panel toggle
            document.addEventListener('keydown', function(e) {
                if (toggleBtn && e.ctrlKey && e.key === 'b') {
                    e.preventDefault();
                    toggleBtn.click();
                }
            });

            // Mobile menu toggle functionality
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const mobileOverlay = document.getElementById('mobileOverlay');
            
            function toggleMobileMenu() {
                leftPanel.classList.toggle('mobile-open');
                mobileOverlay.classList.toggle('active');
                document.body.classList.toggle('menu-open');
            }
            
            if (mobileMenuToggle && mobileOverlay) {
                mobileMenuToggle.addEventListener('click', toggleMobileMenu);
                mobileOverlay.addEventListener('click', toggleMobileMenu);
            }
            
            // Close mobile menu on window resize if screen becomes larger
            window.addEventListener('resize', function() {
                if (window.innerWidth > 576 && leftPanel.classList.contains('mobile-open')) {
                    leftPanel.classList.remove('mobile-open');
                    mobileOverlay.classList.remove('active');
                    document.body.classList.remove('menu-open');
                }
            });
            
            // Save scroll position before unload (for back button)
            window.addEventListener('beforeunload', function() {
                // Only save if not already navigating via our filter functions
                if (!sessionStorage.getItem('attendancePageScrollPosition')) {
                    sessionStorage.setItem('attendancePageScrollPosition', window.pageYOffset.toString());
                }
            });
            
            // Function to save scroll position and navigate
            function saveScrollAndNavigate(url) {
                // Save current scroll position to session storage
                sessionStorage.setItem('attendancePageScrollPosition', window.pageYOffset.toString());
                // Navigate to the URL
                window.location.href = url;
            }
            
            // Handle month and year filter changes for chart
            document.getElementById('filterMonth').addEventListener('change', function() {
                const month = this.value;
                const year = document.getElementById('filterYear').value;
                const userId = document.getElementById('userFilter').value;
                saveScrollAndNavigate(`site_attendance.php?month=${month}&year=${year}&user_id=${userId}`);
            });
            
            document.getElementById('filterYear').addEventListener('change', function() {
                const year = this.value;
                const month = document.getElementById('filterMonth').value;
                const userId = document.getElementById('userFilter').value;
                saveScrollAndNavigate(`site_attendance.php?month=${month}&year=${year}&user_id=${userId}`);
            });
            
            // Handle user filter changes
            document.getElementById('userFilter').addEventListener('change', function() {
                const userId = this.value;
                const month = document.getElementById('filterMonth').value;
                const year = document.getElementById('filterYear').value;
                saveScrollAndNavigate(`site_attendance.php?month=${month}&year=${year}&user_id=${userId}`);
            });
            
            // Handle month and year filter changes for records
            document.getElementById('recordFilterMonth').addEventListener('change', function() {
                const month = this.value;
                const year = document.getElementById('recordFilterYear').value;
                const userId = document.getElementById('userFilter').value;
                saveScrollAndNavigate(`site_attendance.php?month=${month}&year=${year}&user_id=${userId}`);
            });
            
            document.getElementById('recordFilterYear').addEventListener('change', function() {
                const year = this.value;
                const month = document.getElementById('recordFilterMonth').value;
                const userId = document.getElementById('userFilter').value;
                saveScrollAndNavigate(`site_attendance.php?month=${month}&year=${year}&user_id=${userId}`);
            });

            // Attendance record management functions
            function viewAttendanceDetails(id) {
                // Save scroll position before redirecting
                sessionStorage.setItem('attendancePageScrollPosition', window.pageYOffset.toString());
                // Redirect to view details page
                window.location.href = 'view_attendance_details.php?id=' + id;
            }
            
            function editAttendance(id) {
                // Save scroll position before redirecting
                sessionStorage.setItem('attendancePageScrollPosition', window.pageYOffset.toString());
                // Redirect to edit page or show modal
                window.location.href = 'edit_attendance.php?id=' + id;
            }
            
            function deleteAttendance(id) {
                if (confirm('Are you sure you want to delete this attendance record?')) {
                    // Send AJAX request to delete
                    fetch('delete_attendance.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'id=' + id
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remove row from table
                            const row = document.querySelector(`tr[data-id="${id}"]`);
                            if (row) row.remove();
                            alert('Record deleted successfully');
                        } else {
                            alert('Failed to delete record: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while deleting the record');
                    });
                }
            }
            
            // Date picker removed
        });
    </script>
</body>
</html>