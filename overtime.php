<?php
// Database connection and fetching usernames
require_once 'config/db_connect.php'; // Assuming this file contains the database connection

// Initialize data arrays
$usernames = array();
$user_shifts = array(); // To store user shift data
$user_punch_data = array(); // To store punch out data
$attendance_records = array(); // To store all attendance records

// Get the most recent month with attendance data
$most_recent_query = "SELECT YEAR(date) as year, MONTH(date) as month 
                      FROM attendance 
                      WHERE punch_out IS NOT NULL 
                      ORDER BY date DESC LIMIT 1";
$most_recent_result = mysqli_query($conn, $most_recent_query);
$most_recent_month = date('n'); // Current month as fallback
$most_recent_year = date('Y'); // Current year as fallback

if ($most_recent_result && mysqli_num_rows($most_recent_result) > 0) {
    $most_recent_data = mysqli_fetch_assoc($most_recent_result);
    $most_recent_month = $most_recent_data['month'];
    $most_recent_year = $most_recent_data['year'];
}

// Debug info
error_log("Most recent month with data: $most_recent_month/$most_recent_year");

// Fetch ALL attendance records (not just for most recent month)
$attendance_records = array();
$all_attendance_query = "SELECT a.date, a.punch_out, a.punch_in, a.work_report, u.username, u.id as user_id
                       FROM attendance a
                       JOIN users u ON a.user_id = u.id
                       WHERE a.punch_out IS NOT NULL
                       ORDER BY a.date DESC
                       LIMIT 1000"; // Add reasonable limit to avoid loading too much data
                    
$all_attendance_result = mysqli_query($conn, $all_attendance_query);

if ($all_attendance_result) {
    error_log("Fetched " . mysqli_num_rows($all_attendance_result) . " attendance records");
    
    while ($row = mysqli_fetch_assoc($all_attendance_result)) {
        // Format the date and time properly for JavaScript
        $date_obj = new DateTime($row['date']);
        $formatted_date = $date_obj->format('Y-m-d');
        
        $punch_out_obj = new DateTime($row['punch_out']);
        $formatted_punch_out = $punch_out_obj->format('Y-m-d H:i:s');
        
        $punch_in_obj = new DateTime($row['punch_in']);
        $formatted_punch_in = $punch_in_obj->format('Y-m-d H:i:s');
        
        // Create a record with properly formatted dates
        $record = [
            'date' => $formatted_date,
            'punch_out' => $formatted_punch_out,
            'punch_in' => $formatted_punch_in,
            'work_report' => $row['work_report'],
            'username' => $row['username'],
            'user_id' => $row['user_id']
        ];
        
        // Store in main array
        $attendance_records[] = $record;
        
        // Store this record in user-specific array as well
        $username = $row['username'];
        if (!isset($user_punch_data[$username])) {
            $user_punch_data[$username] = [];
        }
        $user_punch_data[$username][] = $record;
    }
    
    error_log("Processed all attendance records");
} else {
    error_log("Error fetching attendance: " . mysqli_error($conn));
}

// Fetch usernames from the database
$username_query = "SELECT id, username FROM users";
$result = mysqli_query($conn, $username_query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $username = $row['username'];
        $usernames[] = $username;
        
        // Store user id with username for shift lookup
        $user_id = $row['id'];
        
        // Query to get the current shift for each user
        $shift_query = "SELECT s.end_time, s.shift_name 
                        FROM shifts s 
                        JOIN user_shifts us ON s.id = us.shift_id 
                        WHERE us.user_id = $user_id 
                        AND (us.effective_to IS NULL OR us.effective_to >= CURDATE())
                        AND us.effective_from <= CURDATE()
                        ORDER BY us.effective_from DESC
                        LIMIT 1";
                        
        $shift_result = mysqli_query($conn, $shift_query);
        
        if ($shift_result && mysqli_num_rows($shift_result) > 0) {
            $shift_data = mysqli_fetch_assoc($shift_result);
            $user_shifts[$username] = [
                'end_time' => $shift_data['end_time'],
                'shift_name' => $shift_data['shift_name']
            ];
        } else {
            // Default if no shift is found
            $user_shifts[$username] = [
                'end_time' => 'Not assigned',
                'shift_name' => 'No shift'
            ];
        }
        
        // Query to get all punch out times for each user (for most recent month with data)
        $month_year = sprintf("%04d-%02d", $most_recent_year, $most_recent_month);
        $attendance_query = "SELECT date, punch_out, punch_in, work_report
                           FROM attendance 
                           WHERE user_id = $user_id 
                           AND punch_out IS NOT NULL
                           ORDER BY date DESC";
                           
        $attendance_result = mysqli_query($conn, $attendance_query);
        
        if ($attendance_result && mysqli_num_rows($attendance_result) > 0) {
            $attendance_data = [];
            while ($att_row = mysqli_fetch_assoc($attendance_result)) {
                // Format the date and time properly for JavaScript
                $date_obj = new DateTime($att_row['date']);
                $formatted_date = $date_obj->format('Y-m-d');
                
                $punch_out_obj = null;
                $formatted_punch_out = null;
                if ($att_row['punch_out']) {
                    $punch_out_obj = new DateTime($att_row['punch_out']);
                    $formatted_punch_out = $punch_out_obj->format('Y-m-d H:i:s');
                }
                
                $punch_in_obj = null;
                $formatted_punch_in = null;
                if ($att_row['punch_in']) {
                    $punch_in_obj = new DateTime($att_row['punch_in']);
                    $formatted_punch_in = $punch_in_obj->format('Y-m-d H:i:s');
                }
                
                $attendance_data[] = [
                    'date' => $formatted_date,
                    'punch_out' => $formatted_punch_out,
                    'punch_in' => $formatted_punch_in,
                    'work_report' => $att_row['work_report']
                ];
            }
            $user_punch_data[$username] = $attendance_data;
        } else {
            // Default if no attendance is found
            $user_punch_data[$username] = [];
        }
    }
} else {
    // Handle database query error
    $error = mysqli_error($conn);
    error_log("Error fetching usernames: " . $error);
}

// Initialize data arrays for the initial table load
$initial_data = [];

// Let's also explicitly check for March data (since you mentioned missing March data)
$march_count = 0;
$other_count = 0;

foreach ($attendance_records as $record) {
    $username = $record['username'];
    $date = $record['date'];
    $punch_out = $record['punch_out'];
    $shift_end = isset($user_shifts[$username]) ? $user_shifts[$username]['end_time'] : 'Not assigned';
    
    // Check if this is March data (for debugging)
    $date_obj = new DateTime($date);
    $month = (int)$date_obj->format('n');
    $year = (int)$date_obj->format('Y');
    
    if ($month === 3) { 
        $march_count++;
    } else {
        $other_count++;
    }
    
    $initial_data[] = [
        'username' => $username,
        'date' => $date,
        'punch_out' => $punch_out,
        'shift_end' => $shift_end,
        'work_report' => $record['work_report']
    ];
}

// Log counts for debugging
error_log("March records count: $march_count");
error_log("Other months records count: $other_count");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --gray-color: #94a3b8;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f1f5f9;
            color: var(--dark-color);
            line-height: 1.6;
        }

        /* Modern Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            transition: transform 0.3s ease;
            z-index: 1000;
            padding: 2rem;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            height: calc(100% - 60px);
        }

        .nav-link {
            color: #64748b;
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary-color);
            background: rgba(79, 70, 229, 0.08);
        }

        .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
            font-size: 1rem;
        }

        .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(226, 232, 240, 0.8);
            padding-top: 1rem;
            color: white !important;
            background-color: #ef4444;
        }

        .logout-link:hover {
            background-color: #dc2626 !important;
            color: white !important;
        }

        .toggle-sidebar {
            position: fixed;
            left: calc(var(--sidebar-width) - 16px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 1001;
            background: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .toggle-sidebar:hover {
            background: var(--primary-color);
            color: white;
        }

        .toggle-sidebar .bi {
            transition: transform 0.3s ease;
        }

        .toggle-sidebar.collapsed {
            left: 1rem;
        }

        .toggle-sidebar.collapsed .bi {
            transform: rotate(180deg);
        }

        @media (max-width: 768px) {
        .sidebar {
                transform: translateX(-100%);
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }
        
        /* Header Styles */
        .main-header {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: 80px;
            background-color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: left 0.3s ease;
            z-index: 900;
            padding: 0 2rem;
            display: flex;
            align-items: center;
        }

        .main-header.expanded {
            left: 0;
        }
        
        .header-content {
            width: 100%;
        }
        
        .main-header h1 {
            color: var(--primary-color);
            font-size: 1.75rem;
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .main-header {
                left: 0;
                padding: 0 1rem;
            }
        }
        
        /* Main Container Styles */
        .main-container {
            margin-left: var(--sidebar-width);
            padding: 100px 2rem 2rem;
            transition: margin-left 0.3s ease;
        }
        
        .main-container.expanded {
            margin-left: 0;
        }
        
        /* Overtime Stats Section */
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #1e293b;
        }
        
        .container-card {
            background-color: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .container-card-header {
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .container-card-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }
        
        .container-card-header .badge {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .stats-section {
            margin-bottom: 1rem;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid #e2e8f0;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-color);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .stat-header span {
            font-weight: 500;
            color: #64748b;
            font-size: 0.95rem;
        }
        
        .stat-header i {
            font-size: 1.25rem;
            padding: 0.75rem;
            border-radius: 10px;
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: #0f172a;
            display: flex;
            align-items: baseline;
        }
        
        .stat-unit {
            font-size: 0.9rem;
            font-weight: 500;
            color: #64748b;
            margin-left: 0.35rem;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 0.75rem;
        }
        
        .progress-bar {
            height: 6px;
            background-color: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.6s ease;
        }
        
        @media (max-width: 1200px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin-left: 0;
                padding: 100px 1rem 1rem;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
        
        /* Filter Section */
        .filter-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .filter-controls {
            display: flex;
            gap: 1rem;
        }
        
        .filter-select {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background-color: white;
            font-size: 0.95rem;
            color: #0f172a;
            min-width: 120px;
            cursor: pointer;
            transition: all 0.2s;
            outline: none;
        }
        
        .filter-select:hover {
            border-color: var(--primary-color);
        }

        .filter-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.1);
        }
        
        .filter-button {
            padding: 0.5rem 1rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-button:hover {
            background-color: var(--secondary-color);
        }
        
        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-align: center;
        }
        
        .status-pending {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }
        
        .status-approved {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        .status-rejected {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        /* Action buttons */
        .action-buttons-container {
            display: flex;
            gap: 4px;
            justify-content: center;
        }
        
        .action-button {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-size: 0.75rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .approve-button {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }
        
        .approve-button:hover {
            background-color: var(--success-color);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .reject-button {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }
        
        .reject-button:hover {
            background-color: var(--danger-color);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .stats-summary {
            font-size: 0.95rem;
            color: #64748b;
            font-weight: 500;
        }

        /* Table Styles */
        .section-subtitle {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
            margin-top: 1.5rem;
        }
        
        .table-container {
            overflow-x: auto;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .overtime-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.95rem;
        }
        
        .overtime-table thead {
            background-color: #f8fafc;
        }
        
        .overtime-table th {
            padding: 1rem;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        
        .overtime-table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }
        
        .overtime-table tbody tr:hover {
            background-color: #f1f5f9;
        }
        
        .overtime-table th:first-child,
        .overtime-table td:first-child {
            padding-left: 1.5rem;
        }
        
        .overtime-table th:last-child,
        .overtime-table td:last-child {
            padding-right: 1.5rem;
        }
        
        /* Column widths */
        .overtime-table th:nth-child(1) { width: 5%; }  /* S.No. */
        .overtime-table th:nth-child(2) { width: 15%; } /* Date */
        .overtime-table th:nth-child(3) { width: 20%; } /* Username */
        .overtime-table th:nth-child(4) { width: 10%; } /* Shift End Time */
        .overtime-table th:nth-child(5) { width: 10%; } /* Punch Out Time */
        .overtime-table th:nth-child(6) { width: 10%; } /* Overtime Duration */
        .overtime-table th:nth-child(7) { width: 12%; } /* Work Report */
        .overtime-table th:nth-child(8) { width: 10%; } /* Manager Status */
        .overtime-table th:nth-child(9) { width: 10%; } /* HR Status */
        .overtime-table th:nth-child(10) { width: 8%; } /* Actions */
        
        @media (max-width: 768px) {
            .table-container {
                overflow-x: scroll;
            }
            
            .overtime-table {
                min-width: 800px; /* Ensure table can be scrolled on small screens */
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <i class="fas fa-hexagon"></i>
            HR Portal
        </div>
        
        <nav>
            <a href="hr_dashboard.php" class="nav-link">
                <i class="fas fa-th-large"></i>
                Dashboard
            </a>
            <a href="employee.php" class="nav-link">
                <i class="fas fa-users"></i>
                Employees
            </a>
            <a href="hr_attendance_report.php" class="nav-link">
                <i class="fas fa-calendar-check"></i>
                Attendance
            </a>
            <a href="shifts.php" class="nav-link">
                <i class="fas fa-history"></i>
                Shifts
            </a>
            <a href="payouts.php" class="nav-link">
                <i class="fas fa-money-bill-alt"></i>
                Manager Payouts
            </a>
            <a href="salary_overview.php" class="nav-link">
                <i class="fas fa-dollar-sign"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="nav-link">
                <i class="fas fa-calendar-alt"></i>
                Leave Request
            </a>
            <a href="overtime.php" class="nav-link active">
                <i class="fas fa-clock"></i>
                Overtime
            </a>
            <a href="construction_site_overview.php" class="nav-link">
                <i class="fas fa-briefcase"></i>
                Recruitment
            </a>
            <a href="hr_travel_expenses.php" class="nav-link">
                <i class="fas fa-car"></i>
                Travel Expenses
            </a>
            <a href="generate_agreement.php" class="nav-link">
                <i class="fas fa-file-contract"></i>
                Contracts
            </a>
            <a href="hr_password_reset.php" class="nav-link">
                <i class="fas fa-key"></i>
                Password Reset
            </a>
            <a href="hr_settings.php" class="nav-link">
                <i class="fas fa-cog"></i>
                Settings
            </a>
            <!-- Added Logout Button -->
            <a href="logout.php" class="nav-link logout-link">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </nav>
            </div>

    <!-- Add toggle sidebar button -->
    <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
        <i class="fas fa-chevron-left"></i>
    </button>

    <!-- Header -->
    <header class="main-header">
        <div class="header-content">
            <h1>Overtime Report</h1>
        </div>
    </header>

    <!-- Main Content Container -->
    <div class="main-container">
        <!-- Main Section Title -->
        <h2 class="section-title">Overtime Analysis</h2>

        <!-- Bordered Container -->
        <div class="container-card">
            <!-- Container Header -->
            <div class="container-card-header">
                <h3>Overtime Records</h3>
                <span class="badge">Current Month</span>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="stats-summary">
                    Showing overtime data for: <strong><span id="current-period">May 2024</span></strong>
                </div>
                <div class="filter-controls">
                    <select id="month-select" class="filter-select">
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5" selected>May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                    <select id="year-select" class="filter-select">
                        <option value="2022">2022</option>
                        <option value="2023">2023</option>
                        <option value="2024" selected>2024</option>
                        <option value="2025">2025</option>
                    </select>
                    <select id="user-select" class="filter-select">
                        <option value="">All Users</option>
                        <?php 
                        // Output username options
                        if (!empty($usernames)) {
                            foreach ($usernames as $username) {
                                echo '<option value="' . htmlspecialchars($username) . '">' . htmlspecialchars($username) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <button id="apply-filter" class="filter-button">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                </div>
            </div>

            <!-- Single Section with Cards in One Row -->
            <section class="stats-section">
                <div class="stats-row">
                    <!-- Total Hours Card -->
                    <div class="stat-card" style="border-left: 4px solid var(--primary-color);">
                        <div class="stat-header">
                            <span>Total Overtime (≥ 1h30m)</span>
                            <i class="fas fa-clock" style="background-color: rgba(67, 97, 238, 0.1); color: var(--primary-color);"></i>
                        </div>
                        <div class="stat-value">48.5 <span class="stat-unit">hrs</span></div>
                        <div class="stat-label">This month</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 80%; background-color: var(--primary-color);"></div>
                    </div>
                    </div>
                    
                    <!-- Approved Hours Card -->
                    <div class="stat-card" style="border-left: 4px solid var(--success-color);">
                        <div class="stat-header">
                            <span>Approved</span>
                            <i class="fas fa-check-circle" style="background-color: rgba(16, 185, 129, 0.1); color: var(--success-color);"></i>
                        </div>
                        <div class="stat-value">32.0 <span class="stat-unit">hrs</span></div>
                        <div class="stat-label">66% of total</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 66%; background-color: var(--success-color);"></div>
                    </div>
                    </div>
                    
                    <!-- Pending Hours Card -->
                    <div class="stat-card" style="border-left: 4px solid var(--warning-color);">
                        <div class="stat-header">
                            <span>Pending</span>
                            <i class="fas fa-hourglass-half" style="background-color: rgba(245, 158, 11, 0.1); color: var(--warning-color);"></i>
                        </div>
                        <div class="stat-value">12.0 <span class="stat-unit">hrs</span></div>
                        <div class="stat-label">25% of total</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 25%; background-color: var(--warning-color);"></div>
                    </div>
                    </div>
                    
                    <!-- Compensation Card -->
                    <div class="stat-card" style="border-left: 4px solid #6366f1;">
                        <div class="stat-header">
                            <span>Compensation</span>
                            <i class="fas fa-rupee-sign" style="background-color: rgba(99, 102, 241, 0.1); color: #6366f1;"></i>
                        </div>
                        <div class="stat-value">₹67,500 <span class="stat-unit">INR</span></div>
                        <div class="stat-label">Estimated earnings</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 70%; background-color: #6366f1;"></div>
                    </div>
                </div>
                    </div>
            </section>
            
            <!-- Overtime Records Table -->
            <section class="table-section">
                <h3 class="section-subtitle">Overtime Records (≥ 1h 30m)</h3>
                <div class="table-container">
                    <table class="overtime-table">
                            <thead>
                                <tr>
                                <th>S.No.</th>
                                    <th>Date</th>
                                <th>Username</th>
                                <th>Shift End Time</th>
                                <th>Punch Out Time</th>
                                <th>Overtime Duration</th>
                                <th>Work Report</th>
                                <th>Manager Status</th>
                                <th>HR Status</th>
                                <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Table records will be loaded dynamically -->
                            </tbody>
                        </table>
                    </div>
            </section>
        </div>
    </div>

    <!-- Work Report Modal -->
    <div class="modal" id="workReportModal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div style="background-color: white; margin: 10% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 800px; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 600; color: var(--primary-color);">Work Report Details</h3>
                <button id="closeWorkReportModal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b;">&times;</button>
            </div>
            <p id="modalWorkReportContent" style="white-space: pre-wrap; line-height: 1.6; color: #334155;"></p>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get PHP data
            const phpUsernames = <?php echo json_encode($usernames); ?>;
            const userShifts = <?php echo json_encode($user_shifts); ?>;
            const userPunchData = <?php echo json_encode($user_punch_data); ?>;
            const initialData = <?php echo json_encode($initial_data); ?>;
            const mostRecentMonth = <?php echo $most_recent_month; ?>;
            const mostRecentYear = <?php echo $most_recent_year; ?>;
            
            // Log data in console for verification
            console.log("Most recent month with data:", mostRecentMonth);
            console.log("Most recent year with data:", mostRecentYear);
            console.log("Initial data:", initialData);
            console.log("Usernames from database:", phpUsernames);
            console.log("User shifts data:", userShifts);
            console.log("User punch data:", userPunchData);
            
            // Filter functionality
            const monthSelect = document.getElementById('month-select');
            const yearSelect = document.getElementById('year-select');
            const userSelect = document.getElementById('user-select');
            const applyFilterBtn = document.getElementById('apply-filter');
            const currentPeriodDisplay = document.getElementById('current-period');
            
            // Month names array for display
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                             'July', 'August', 'September', 'October', 'November', 'December'];
            
            // Set initial month and year for first load
            let initialMonth = mostRecentMonth;
            let initialYear = mostRecentYear;
            
            // Update dropdowns to reflect initial month/year
            if (monthSelect) {
                for (let i = 0; i < monthSelect.options.length; i++) {
                    if (parseInt(monthSelect.options[i].value) === initialMonth) {
                        monthSelect.selectedIndex = i;
                        break;
                    }
                }
            }
            
            if (yearSelect) {
                for (let i = 0; i < yearSelect.options.length; i++) {
                    if (parseInt(yearSelect.options[i].value) === initialYear) {
                        yearSelect.selectedIndex = i;
                        break;
                    }
                }
            }
            
            // Update period display with initial month/year
            currentPeriodDisplay.textContent = `${monthNames[initialMonth-1]} ${initialYear}`;
            
            // Trigger initial filtering on page load
            const initialFiltered = filterAttendanceData(initialMonth, initialYear, '');
            console.log(`Initial filter: ${initialMonth}/${initialYear}, found ${initialFiltered.length} records`);
            
            // Update badge with record count
            const badge = document.querySelector('.container-card-header .badge');
            badge.textContent = `${initialFiltered.length} Record${initialFiltered.length !== 1 ? 's' : ''}`;
            
            // Load filtered data instead of all data
            loadInitialTableData(initialFiltered);
            
            // Make sure statistics are updated on initial load
            updateStatistics(initialFiltered);
            
            // Function to load initial data to the table
            function loadInitialTableData(data) {
                const tableBody = document.querySelector('.overtime-table tbody');
                tableBody.innerHTML = ''; // Clear existing rows
                
                if (!data || data.length === 0) {
                    const noDataRow = document.createElement('tr');
                    const noDataCell = document.createElement('td');
                    noDataCell.colSpan = 10;
                    noDataCell.textContent = 'No attendance records found for the selected period.';
                    noDataCell.style.textAlign = 'center';
                    noDataCell.style.padding = '2rem';
                    noDataCell.style.color = '#64748b';
                    noDataRow.appendChild(noDataCell);
                    tableBody.appendChild(noDataRow);
                    return;
                }
                
                // Create rows for each attendance record
                data.forEach((item, index) => {
                    const row = document.createElement('tr');
                    
                    // Format date
                    const dateObj = new Date(item.date);
                    const formattedDate = dateObj.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                    
                    // Format punch out time
                    let formattedPunchOut = 'Not recorded';
                    if (item.punch_out) {
                        const punchOutDate = new Date(item.punch_out);
                        formattedPunchOut = punchOutDate.toLocaleTimeString('en-US', {
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }
                    
                    // Create cells
                                         // Truncate work report text
                    let workReportText = 'No report';
                    let fullWorkReport = '';
                    if (item.work_report) {
                        fullWorkReport = item.work_report;
                        // Truncate to 30 characters or first newline
                        const newlineIndex = item.work_report.indexOf('\n');
                        const truncateIndex = (newlineIndex > -1 && newlineIndex < 30) ? 
                                              newlineIndex : 
                                              Math.min(30, item.work_report.length);
                        workReportText = item.work_report.substring(0, truncateIndex) + 
                                        (item.work_report.length > truncateIndex ? '...' : '');
                    }
                    
                    const cells = [
                        { text: index + 1 },  // S.No.
                        { text: formattedDate }, // Date
                        { text: item.username }, // Username
                        { text: item.shift_end }, // Shift End Time
                        { text: formattedPunchOut }, // Punch Out Time
                        { text: item.overtime || calculateOvertimeDuration(item.date, item.punch_out, item.shift_end) } // Overtime Duration
                    ];
                    
                    // Add the cells we defined above
                    cells.forEach(cell => {
                        const td = document.createElement('td');
                        td.textContent = cell.text;
                        row.appendChild(td);
                    });
                    
                    // Add work report cell with click handler
                    const workReportTd = document.createElement('td');
                    const workReportSpan = document.createElement('span');
                    workReportSpan.textContent = workReportText;
                    workReportSpan.style.cursor = 'pointer';
                    workReportSpan.style.color = item.work_report ? '#4361ee' : '#94a3b8';
                    workReportSpan.style.textDecoration = item.work_report ? 'underline' : 'none';
                    
                    if (item.work_report) {
                        workReportSpan.addEventListener('click', function() {
                            // Show modal with full work report
                            document.getElementById('modalWorkReportContent').textContent = fullWorkReport;
                            document.getElementById('workReportModal').style.display = 'block';
                        });
                    }
                    
                    workReportTd.appendChild(workReportSpan);
                    row.appendChild(workReportTd);
                    
                    // Add Manager Status column
                    const managerStatusTd = document.createElement('td');
                    const managerStatusBadge = document.createElement('span');
                    managerStatusBadge.className = 'status-badge status-pending';
                    managerStatusBadge.textContent = 'Pending';
                    managerStatusTd.appendChild(managerStatusBadge);
                    row.appendChild(managerStatusTd);
                    
                    // Add HR Status column
                    const hrStatusTd = document.createElement('td');
                    const hrStatusBadge = document.createElement('span');
                    hrStatusBadge.className = 'status-badge status-pending';
                    hrStatusBadge.textContent = 'Pending';
                    hrStatusTd.appendChild(hrStatusBadge);
                    row.appendChild(hrStatusTd);
                    
                    // Add Actions column
                    const actionsTd = document.createElement('td');
                    
                    // Create container for actions
                    const actionsContainer = document.createElement('div');
                    actionsContainer.className = 'action-buttons-container';
                    
                    const approveButton = document.createElement('button');
                    approveButton.className = 'action-button approve-button';
                    approveButton.title = 'Approve';
                    
                    // Create check icon
                    const approveIcon = document.createElement('i');
                    approveIcon.className = 'fas fa-check';
                    approveButton.appendChild(approveIcon);
                    
                    approveButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        // Placeholder for approve action
                        alert(`Approve overtime for ${item.username} on ${formattedDate}`);
                    });
                    
                    const rejectButton = document.createElement('button');
                    rejectButton.className = 'action-button reject-button';
                    rejectButton.title = 'Reject';
                    
                    // Create times icon
                    const rejectIcon = document.createElement('i');
                    rejectIcon.className = 'fas fa-times';
                    rejectButton.appendChild(rejectIcon);
                    
                    rejectButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        // Placeholder for reject action
                        alert(`Reject overtime for ${item.username} on ${formattedDate}`);
                    });
                    
                    // Add buttons to container
                    actionsContainer.appendChild(approveButton);
                    actionsContainer.appendChild(rejectButton);
                    
                    // Add container to cell
                    actionsTd.appendChild(actionsContainer);
                    row.appendChild(actionsTd);
                    
                    tableBody.appendChild(row);
                });
            }
            
            // Sidebar toggle functionality
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mainHeader = document.querySelector('.main-header');
            const mainContainer = document.querySelector('.main-container');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                sidebarToggle.classList.toggle('collapsed');
                mainHeader.classList.toggle('expanded');
                mainContainer.classList.toggle('expanded');
                
                // Store sidebar state in localStorage
                const isCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', isCollapsed);
            });
            
            // Initialize sidebar state from localStorage
            window.addEventListener('DOMContentLoaded', function() {
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                    sidebarToggle.classList.add('collapsed');
                    mainHeader.classList.add('expanded');
                    mainContainer.classList.add('expanded');
                }
            });
            
            // Handle responsive behavior
            function handleResponsiveLayout() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.add('collapsed');
                    sidebarToggle.classList.add('collapsed');
                    mainHeader.classList.add('expanded');
                    mainContainer.classList.add('expanded');
                } else {
                    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                    if (!isCollapsed) {
                        sidebar.classList.remove('collapsed');
                        sidebarToggle.classList.remove('collapsed');
                        mainHeader.classList.remove('expanded');
                        mainContainer.classList.remove('expanded');
                    }
                }
            }
            
            // Run on page load and window resize
            handleResponsiveLayout();
            window.addEventListener('resize', handleResponsiveLayout);
            
            // Work report modal close button
            document.getElementById('closeWorkReportModal').addEventListener('click', function() {
                document.getElementById('workReportModal').style.display = 'none';
            });
            
            // Close modal when clicking outside of it
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('workReportModal');
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
            
            // Animate progress bars with a slight delay
            setTimeout(() => {
                document.querySelectorAll('.progress-fill').forEach(bar => {
                    bar.style.width = bar.style.width || '0%';
                });
            }, 300);
            
            // Update display when filter is applied
            applyFilterBtn.addEventListener('click', function() {
                const selectedMonth = parseInt(monthSelect.value);
                const selectedYear = parseInt(yearSelect.value);
                const selectedUser = userSelect.value;
                
                // Update display text with user info if selected
                if (selectedUser) {
                    currentPeriodDisplay.textContent = `${monthSelect.options[monthSelect.selectedIndex].text} ${selectedYear} (${selectedUser})`;
                } else {
                    currentPeriodDisplay.textContent = `${monthSelect.options[monthSelect.selectedIndex].text} ${selectedYear}`;
                }
                
                // Filter the data
                const filteredData = filterAttendanceData(selectedMonth, selectedYear, selectedUser);
                
                // Update badge to show it's filtered with record count
                const badge = document.querySelector('.container-card-header .badge');
                badge.textContent = `${filteredData.length} Record${filteredData.length !== 1 ? 's' : ''}`;
                badge.style.backgroundColor = 'rgba(99, 102, 241, 0.1)';
                badge.style.color = '#6366f1';
                
                // Display the filtered data
                loadInitialTableData(filteredData);
            });
            
            // Function to filter attendance data based on criteria
            function filterAttendanceData(month, year, user) {
                console.log("Filtering for:", month, year, user);
                
                // Convert parameters to integers for comparison
                month = parseInt(month);
                year = parseInt(year);
                
                // Filter attendance records based on selected criteria
                let filteredData = [];
                
                // Keep track of processed records for debugging
                let totalRecords = 0;
                let monthMatchCount = 0;
                let otherFilterMatchCount = 0;
                
                // Iterate through userPunchData to find matching records
                for (const [username, punchRecords] of Object.entries(userPunchData)) {
                    // Skip if user filter is applied and doesn't match
                    if (user && user !== username) {
                        continue;
                    }
                    
                    // Get user's shift end time for comparison
                    const shiftInfo = userShifts[username] || { end_time: 'Not assigned', shift_name: 'No shift' };
                    
                    // Process each punch record for this user
                    punchRecords.forEach(record => {
                        totalRecords++;
                        // Safer date parsing with explicit check for date format
                        let recordDate;
                        try {
                            recordDate = new Date(record.date);
                            // Ensure the date was parsed correctly
                            if (isNaN(recordDate.getTime())) {
                                console.warn("Invalid date format:", record.date);
                                return; // Skip this record
                            }
                        } catch (e) {
                            console.error("Error parsing date:", record.date, e);
                            return; // Skip this record
                        }
                        
                        // Extract month and year safely
                        const recordMonth = recordDate.getMonth() + 1; // JavaScript months are 0-indexed
                        const recordYear = recordDate.getFullYear();
                        
                        // Debug info
                        console.log("Comparing record date:", recordMonth, recordYear, 
                                    "with filter:", month, year);
                        
                        // Check if record matches selected month and year
                        if (recordMonth === month && recordYear === year) {
                            monthMatchCount++;
                            
                            // Only include records where punch out is after shift end time
                            if (shiftInfo.end_time === 'Not assigned' || !record.punch_out) {
                                // Skip records without a proper shift end time or punch out
                                return;
                            }
                            
                            // Parse shift end time and punch out time for comparison
                            const shiftEndTime = parseTimeString(shiftInfo.end_time, recordDate);
                            const punchOutTime = new Date(record.punch_out);
                            
                            // Skip if either time couldn't be parsed
                            if (!shiftEndTime || !punchOutTime) {
                                console.log("Skipping due to invalid time format");
                                return;
                            }
                            
                            // Get time parts
                            const punchOutHours = punchOutTime.getHours();
                            const punchOutMinutes = punchOutTime.getMinutes();
                            const shiftEndHours = shiftEndTime.getHours();
                            const shiftEndMinutes = shiftEndTime.getMinutes();
                            
                            // Calculate time difference directly in minutes
                            let diffMinutes = (punchOutHours * 60 + punchOutMinutes) - (shiftEndHours * 60 + shiftEndMinutes);
                            
                            // If the punch out is on the next day (after midnight), add 24 hours worth of minutes
                            if (diffMinutes < 0) {
                                diffMinutes += 24 * 60; // Add 24 hours in minutes
                            }
                            
                            // Only include if punch out is at least 1 hour and 30 minutes after shift end time
                            const minimumOvertimeThreshold = 90; // 1 hour and 30 minutes
                            
                            if (diffMinutes >= minimumOvertimeThreshold) {
                                otherFilterMatchCount++;
                                
                                // Calculate rounded minutes
                                let overtimeMinutes = diffMinutes;
                                const overtimeHours = Math.floor(overtimeMinutes / 60);
                                const remainingMinutes = overtimeMinutes % 60;
                                
                                // Round minutes down to nearest 30-minute interval
                                let roundedMinutes = remainingMinutes;
                                if (remainingMinutes > 0 && remainingMinutes < 30) {
                                    roundedMinutes = 0;
                                } else if (remainingMinutes >= 30 && remainingMinutes < 60) {
                                    roundedMinutes = 30;
                                }
                                
                                // Calculate total overtime minutes after rounding
                                const totalRoundedMinutes = (overtimeHours * 60) + roundedMinutes;
                                
                                filteredData.push({
                                    username: username,
                                    date: record.date,
                                    punch_out: record.punch_out,
                                    shift_end: shiftInfo.end_time,
                                    overtime: calculateOvertimeDuration(record.date, record.punch_out, shiftInfo.end_time),
                                    overtimeMinutes: totalRoundedMinutes, // Add the overtime minutes for statistics calculation
                                    work_report: record.work_report
                                });
                                
                                // Debug successful match
                                console.log("Match found for", username, "on", record.date);
                            }
                        }
                    });
                }
                
                console.log(`Filtering stats: Total records: ${totalRecords}, Month matches: ${monthMatchCount}, Final matches: ${filteredData.length}`);
                
                // Sort by date (newest first)
                filteredData.sort((a, b) => new Date(b.date) - new Date(a.date));
                
                // Update statistics cards with real data
                updateStatistics(filteredData);
                
                return filteredData;
            }
            
            // Function to update the statistics cards with real data
            function updateStatistics(data) {
                // Initialize statistics
                let totalOvertimeMinutes = 0;
                let approvedOvertimeMinutes = 0; // Since we don't have approval status, we'll assume 75% are approved for demo
                let pendingOvertimeMinutes = 0;
                
                // Calculate total overtime minutes
                data.forEach(record => {
                    if (record.overtimeMinutes) {
                        totalOvertimeMinutes += record.overtimeMinutes;
                    }
                });
                
                // For demo purposes, assume 75% approved, 25% pending
                approvedOvertimeMinutes = Math.round(totalOvertimeMinutes * 0.75);
                pendingOvertimeMinutes = totalOvertimeMinutes - approvedOvertimeMinutes;
                
                // Convert to hours for display
                const totalOvertimeHours = Math.floor(totalOvertimeMinutes / 60);
                const totalOvertimeRemainingMinutes = totalOvertimeMinutes % 60;
                const totalOvertimeFormatted = `${totalOvertimeHours}:${totalOvertimeRemainingMinutes.toString().padStart(2, '0')}`;
                
                const approvedOvertimeHours = Math.floor(approvedOvertimeMinutes / 60);
                const approvedOvertimeRemainingMinutes = approvedOvertimeMinutes % 60;
                const approvedOvertimeFormatted = `${approvedOvertimeHours}:${approvedOvertimeRemainingMinutes.toString().padStart(2, '0')}`;
                
                const pendingOvertimeHours = Math.floor(pendingOvertimeMinutes / 60);
                const pendingOvertimeRemainingMinutes = pendingOvertimeMinutes % 60;
                const pendingOvertimeFormatted = `${pendingOvertimeHours}:${pendingOvertimeRemainingMinutes.toString().padStart(2, '0')}`;
                
                // Calculate percentages for progress bars
                const approvedPercentage = totalOvertimeMinutes > 0 ? Math.round((approvedOvertimeMinutes / totalOvertimeMinutes) * 100) : 0;
                const pendingPercentage = totalOvertimeMinutes > 0 ? Math.round((pendingOvertimeMinutes / totalOvertimeMinutes) * 100) : 0;
                
                // Calculate estimated compensation (assuming ₹500 per overtime hour for demo purposes)
                const hourlyRate = 500; // ₹500 per hour
                const estimatedCompensation = Math.round((totalOvertimeMinutes / 60) * hourlyRate);
                
                // Update DOM elements
                document.querySelector('.stat-card:nth-child(1) .stat-value').innerHTML = `${totalOvertimeFormatted} <span class="stat-unit">hrs</span>`;
                document.querySelector('.stat-card:nth-child(2) .stat-value').innerHTML = `${approvedOvertimeFormatted} <span class="stat-unit">hrs</span>`;
                document.querySelector('.stat-card:nth-child(2) .stat-label').textContent = `${approvedPercentage}% of total`;
                document.querySelector('.stat-card:nth-child(2) .progress-fill').style.width = `${approvedPercentage}%`;
                
                document.querySelector('.stat-card:nth-child(3) .stat-value').innerHTML = `${pendingOvertimeFormatted} <span class="stat-unit">hrs</span>`;
                document.querySelector('.stat-card:nth-child(3) .stat-label').textContent = `${pendingPercentage}% of total`;
                document.querySelector('.stat-card:nth-child(3) .progress-fill').style.width = `${pendingPercentage}%`;
                
                document.querySelector('.stat-card:nth-child(4) .stat-value').innerHTML = `₹${estimatedCompensation.toLocaleString()} <span class="stat-unit">INR</span>`;
            }
            
            // Helper function to parse time string in various formats to a Date object
            function parseTimeString(timeStr, baseDate) {
                if (!timeStr || timeStr === 'Not assigned') {
                    console.log("Invalid time string:", timeStr);
                    return null;
                }
                
                try {
                    // Create a copy of the base date to avoid modifying it
                    const date = new Date(baseDate);
                    
                    // MySQL time format - if it's a full datetime string
                    if (timeStr.includes('-') && timeStr.includes(':') && timeStr.length > 10) {
                        // Handle full MySQL datetime format
                        const dateTimeObj = new Date(timeStr);
                        if (!isNaN(dateTimeObj.getTime())) {
                            return dateTimeObj;
                        }
                    }
                    
                    // Check if format is "HH:MM:SS" (24-hour format often used in databases)
                    if (timeStr.match(/^\d{1,2}:\d{2}(:\d{2})?$/) && !timeStr.toLowerCase().includes('am') && !timeStr.toLowerCase().includes('pm')) {
                        const timeParts = timeStr.split(':');
                        const hours = parseInt(timeParts[0], 10);
                        const minutes = parseInt(timeParts[1], 10);
                        const seconds = timeParts.length > 2 ? parseInt(timeParts[2], 10) : 0;
                        
                        // Set time components
                        date.setHours(hours, minutes, seconds, 0);
                        return date;
                    }
                    
                    // Check if format is "HH:MM AM/PM"
                    if (timeStr.toLowerCase().includes('am') || timeStr.toLowerCase().includes('pm')) {
                        // Format: "6:00 PM"
                        const [timePart, ampm] = timeStr.split(' ');
                        const [hours, minutes] = timePart.split(':').map(Number);
                        
                        // Set hours based on AM/PM
                        let adjustedHours = hours;
                        if (ampm.toLowerCase() === 'pm' && hours < 12) {
                            adjustedHours += 12;
                        } else if (ampm.toLowerCase() === 'am' && hours === 12) {
                            adjustedHours = 0;
                        }
                        
                        date.setHours(adjustedHours, minutes, 0, 0);
                        return date;
                    }
                    
                    console.warn("Unrecognized time format:", timeStr);
                    return null;
                } catch (error) {
                    console.error("Error parsing time:", error);
                    return null;
                }
            }
            
            // Function to update table data
            function updateTableData(month, year, user) {
                // Get filtered data
                const filteredData = filterAttendanceData(month, year, user);
                
                // Display the filtered data
                loadInitialTableData(filteredData);
            }

            // Function to calculate overtime duration
            function calculateOvertimeDuration(date, punchOut, shiftEndTimeStr) {
                if (!date || !punchOut || !shiftEndTimeStr || shiftEndTimeStr === 'Not assigned') {
                    return 'Not calculated';
                }
                
                try {
                    const baseDate = new Date(date);
                    const punchOutTime = new Date(punchOut);
                    const shiftEndTime = parseTimeString(shiftEndTimeStr, baseDate);
                    
                    if (!shiftEndTime) {
                        return 'Invalid shift time';
                    }
                    
                    // Log for debugging
                    console.log('Punch out time:', punchOutTime);
                    console.log('Shift end time:', shiftEndTime);
                    
                    // Calculate difference in minutes
                    // Ensure both times have the same date part to calculate just the time difference
                    // This prevents issues where dates are different and result in enormous hour values
                    
                    // Clone the dates to not modify original
                    let adjustedPunchOut = new Date(punchOutTime);
                    let adjustedShiftEnd = new Date(shiftEndTime);
                    
                    // Get time parts
                    const punchOutHours = adjustedPunchOut.getHours();
                    const punchOutMinutes = adjustedPunchOut.getMinutes();
                    const shiftEndHours = adjustedShiftEnd.getHours();
                    const shiftEndMinutes = adjustedShiftEnd.getMinutes();
                    
                    // Calculate time difference directly in minutes
                    let diffMinutes = (punchOutHours * 60 + punchOutMinutes) - (shiftEndHours * 60 + shiftEndMinutes);
                    
                    // If the punch out is on the next day (after midnight), add 24 hours worth of minutes
                    if (diffMinutes < 0) {
                        diffMinutes += 24 * 60; // Add 24 hours in minutes
                    }
                    
                    // If punch out is before or equal to shift end, no overtime
                    if (diffMinutes <= 0) {
                        return 'No overtime';
                    }
                    
                    // Calculate total overtime in minutes
                    const minimumOvertimeThreshold = 90; // 1 hour and 30 minutes (90 minutes)
                    
                    // Only count as overtime if the time difference is at least 1 hour and 30 minutes
                    if (diffMinutes < minimumOvertimeThreshold) {
                        return 'Below threshold';
                    }
                    
                    // Calculate hours and minutes
                    let hours = Math.floor(diffMinutes / 60);
                    let minutes = diffMinutes % 60;
                    
                    // Round minutes down to nearest 30-minute interval
                    if (minutes > 0 && minutes < 30) {
                        minutes = 0;
                    } else if (minutes >= 30 && minutes < 60) {
                        minutes = 30;
                    }
                    
                    // Format as HH:MM
                    return `${hours}:${minutes.toString().padStart(2, '0')}`;
                } catch (error) {
                    console.error('Error calculating overtime:', error);
                    return 'Error';
                }
            }
        });
    </script>
</body>
</html>