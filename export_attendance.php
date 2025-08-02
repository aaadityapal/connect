<?php
session_start();

// Add authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db_connect.php';

// Create exports directory if it doesn't exist
$exports_dir = 'exports';
if (!is_dir($exports_dir)) {
    mkdir($exports_dir, 0777, true);
}

// Get selected month (define this first)
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Get all active users for dropdown
$users_query = "SELECT id, unique_id, username FROM users WHERE deleted_at IS NULL AND status = 'active' ORDER BY username";
$users_stmt = $conn->prepare($users_query);
$users_stmt->execute();
$users_result = $users_stmt->get_result();
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Get selected user (define this after users are loaded)
$unique_id = isset($_GET['id']) ? $_GET['id'] : 'all';

// Process export request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    $unique_id = $_POST['user_id'];
    $export_month = $_POST['month'];
    
    // Define the date range for the month
    $month_start = $export_month . '-01';
    $month_end = date('Y-m-t', strtotime($month_start));
    $month_year = date('F_Y', strtotime($month_start));
    
    if ($unique_id === 'all') {
        // Export attendance for all employees
        
        // Get all days in the month
        $days = [];
        $current_date = new DateTime($month_start);
        $end_date = new DateTime($month_end);
        
        while ($current_date <= $end_date) {
            $days[] = [
                'date' => $current_date->format('Y-m-d'),
                'day_name' => $current_date->format('l'),
                'formatted_date' => $current_date->format('d-m-Y')
            ];
            $current_date->modify('+1 day');
        }
        
        // Generate filename for the CSV file
        $filename = 'attendance_all_employees_' . $export_month . '.csv';
        $filepath = $exports_dir . '/' . $filename;
        
        // Open file for writing
        $file = fopen($filepath, 'w');
        
        // Add UTF-8 BOM for proper Excel CSV encoding
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write header row with month
        fputcsv($file, ["Attendance Report - " . date('F Y', strtotime($month_start))]);
        fputcsv($file, []); // Empty line
        
        // Write column headers
        $header_row = ['Days'];
        
        // Add user names and subheaders for each user
        foreach ($users as $user) {
            $header_row[] = $user['username'];
            $header_row[] = 'Overtime';
            $header_row[] = 'Half day';
        }
        
        fputcsv($file, $header_row);
        
        // Get attendance data for all users for the month
        $all_attendance_data = [];
        
        foreach ($users as $user) {
            $user_id = $user['id'];
            
            // Fetch attendance for this user
            $attendance_query = "SELECT 
                date, 
                status, 
                TIME(punch_in) as punch_in_time, 
                TIME(punch_out) as punch_out_time,
                shift_time,
                overtime_hours,
                is_weekly_off
            FROM attendance 
            WHERE user_id = ? AND date BETWEEN ? AND ?
            ORDER BY date";
            
            $attendance_stmt = $conn->prepare($attendance_query);
            $attendance_stmt->bind_param('iss', $user_id, $month_start, $month_end);
            $attendance_stmt->execute();
            $attendance_result = $attendance_stmt->get_result();
            
            $user_attendance = [];
            while ($row = $attendance_result->fetch_assoc()) {
                $user_attendance[date('Y-m-d', strtotime($row['date']))] = $row;
            }
            
            $all_attendance_data[$user_id] = $user_attendance;
        }
        
        // Write daily attendance rows
        foreach ($days as $day) {
            $row_data = [$day['formatted_date'] . ' (' . substr($day['day_name'], 0, 3) . ')'];
            
            foreach ($users as $user) {
                $user_id = $user['id'];
                $record = $all_attendance_data[$user_id][$day['date']] ?? null;
                
                // Format the attendance info
                if ($record) {
                    if ($record['status'] === 'present') {
                        if ($record['punch_in_time'] && $record['punch_out_time']) {
                            // Convert 24-hour format to 12-hour format
                            $punch_in_12hr = date("h:i A", strtotime($record['punch_in_time']));
                            $punch_out_12hr = date("h:i A", strtotime($record['punch_out_time']));
                            $attendance_info = $punch_in_12hr . ' - ' . $punch_out_12hr;
                        } else {
                            $attendance_info = "Present";
                        }
                    } elseif ($record['status'] === 'absent') {
                        $attendance_info = "ABSENT";
                    } elseif ($record['status'] === 'leave') {
                        $attendance_info = "LEAVE";
                    } elseif ($record['status'] === 'holiday') {
                        $attendance_info = "HOLIDAY";
                    } else {
                        $attendance_info = ucfirst($record['status']);
                    }
                    
                    if ($record['is_weekly_off']) {
                        $attendance_info = "Weekly OFF";
                    }
                } else {
                    $attendance_info = "";
                }
                
                // Get overtime hours
                $overtime = '';
                if ($record && !empty($record['overtime_hours']) && $record['overtime_hours'] != '00:00:00') {
                    $overtime_parts = explode(':', $record['overtime_hours']);
                    if (count($overtime_parts) >= 2) {
                        // Just take hours and minutes
                        $hours = intval($overtime_parts[0]);
                        $minutes = intval($overtime_parts[1]);
                        
                        if ($minutes === 30) {
                            $overtime = $hours . '.5';
                        } else {
                            $overtime = $hours;
                        }
                    }
                }
                
                // Get half-day status
                $half_day = '';
                // This would need proper half-day logic from your system
                // For now, I'm leaving it empty. You might need to adjust this
                // based on your business rules
                
                $row_data[] = $attendance_info;
                $row_data[] = $overtime;
                $row_data[] = $half_day;
            }
            
            fputcsv($file, $row_data);
        }
        
        // Close the file
        fclose($file);
        
        // Success message and download link
        $success = "Attendance data for all employees has been exported successfully!";
        $download_link = $filepath;
        
    } else {
        // Export attendance for a single employee
        
        // Get user info
        $user_query = "SELECT id, username FROM users WHERE unique_id = ?";
        $user_stmt = $conn->prepare($user_query);
        $user_stmt->bind_param('s', $unique_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result()->fetch_assoc();
        
        if (!$user_result) {
            $error = "User not found.";
        } else {
            $user_id = $user_result['id'];
            $username = $user_result['username'];
            
            // Fetch attendance records
            $attendance_query = "SELECT 
                date, 
                status, 
                TIME(punch_in) as punch_in_time, 
                TIME(punch_out) as punch_out_time,
                shift_time,
                overtime_hours,
                is_weekly_off
            FROM attendance 
            WHERE user_id = ? AND date BETWEEN ? AND ?
            ORDER BY date";
            
            $attendance_stmt = $conn->prepare($attendance_query);
            $attendance_stmt->bind_param('iss', $user_id, $month_start, $month_end);
            $attendance_stmt->execute();
            $attendance_result = $attendance_stmt->get_result();
            
            $attendance_records = [];
            while ($row = $attendance_result->fetch_assoc()) {
                $attendance_records[date('Y-m-d', strtotime($row['date']))] = $row;
            }
            
            // Generate filename for the CSV file
            $filename = 'attendance_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $username) . '_' . $export_month . '.csv';
            $filepath = $exports_dir . '/' . $filename;
            
            // Open file for writing
            $file = fopen($filepath, 'w');
            
            // Add UTF-8 BOM for proper Excel CSV encoding
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Write header row with employee name and month
            fputcsv($file, ["Employee Name: " . $username]);
            fputcsv($file, ["Month: " . date('F Y', strtotime($month_start))]);
            fputcsv($file, []); // Empty line
            
            // Write column headers
            fputcsv($file, ['Date', 'Day', 'Status', 'Punch In', 'Punch Out', 'Shift Time', 'Overtime Hours', 'Weekly Off']);
            
            // Fill in attendance data
            $current_date = new DateTime($month_start);
            $end_date = new DateTime($month_end);
            
            while ($current_date <= $end_date) {
                $date_str = $current_date->format('Y-m-d');
                $day_name = $current_date->format('l');
                $record = $attendance_records[$date_str] ?? null;
                
                $row_data = [
                    $current_date->format('d-m-Y'),
                    $day_name,
                    $record ? ucfirst($record['status']) : 'Not Recorded',
                    $record ? ($record['punch_in_time'] ? date("h:i A", strtotime($record['punch_in_time'])) : '') : '',
                    $record ? ($record['punch_out_time'] ? date("h:i A", strtotime($record['punch_out_time'])) : '') : '',
                    $record ? ($record['shift_time'] ?? '') : '',
                    $record ? ($record['overtime_hours'] ?? '') : '',
                    $record ? ($record['is_weekly_off'] ? 'Yes' : 'No') : 'No'
                ];
                
                fputcsv($file, $row_data);
                
                $current_date->modify('+1 day');
            }
            
            // Close the file
            fclose($file);
            
            // Success message and download link
            $success = "Attendance data has been exported successfully!";
            $download_link = $filepath;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Export Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <style>
        /* Root Variables */
        :root {
            --primary: #4361ee;
            --primary-light: #eef2ff;
            --primary-dark: #3a56d4;
            --secondary: #3f37c9;
            --success: #10b981;
            --success-light: #ecfdf5;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --warning: #f59e0b;
            --warning-light: #fffbeb;
            --info: #3b82f6;
            --info-light: #eff6ff;
            --dark: #1f2937;
            --light: #f9fafb;
            --border: #e5e7eb;
            --text: #111827;
            --text-secondary: #4b5563;
            --text-light: #6b7280;
            --shadow: rgba(0, 0, 0, 0.05);
            --shadow-hover: rgba(0, 0, 0, 0.1);
            --shadow-card: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
            --sidebar-width: 280px;
            --border-radius: 10px;
            --transition: all 0.3s ease;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background-color: #f5f8fa;
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
            min-height: 100vh;
            font-size: 15px;
        }

        /* Sidebar Styles */
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
            color: var(--primary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            height: calc(100% - 10px);
        }

        .sidebar nav a {
            text-decoration: none;
        }

        .nav-link {
            color: var(--gray);
            padding: 0.875rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-link:hover, 
        .nav-link.active {
            color: #4361ee;
            background-color: #F3F4FF;
        }

        .nav-link.active {
            background-color: #F3F4FF;
            font-weight: 500;
        }

        .nav-link:hover i,
        .nav-link.active i {
            color: #4361ee;
        }

        .nav-link i {
            margin-right: 0.75rem;
        }

        /* Logout Link */
        .logout-link {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
            color: black!important;
            background-color: #D22B2B;
        }

        .logout-link:hover {
            background-color: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545 !important;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin 0.3s ease;
            padding: 2rem;
            width: calc(100% - var(--sidebar-width));
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }

        /* Toggle Button */
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
            background: var(--primary);
            color: white;
        }

        .toggle-sidebar.collapsed {
            left: 1rem;
        }

        .toggle-sidebar .bi {
            transition: transform 0.3s ease;
        }

        .toggle-sidebar.collapsed .bi {
            transform: rotate(180deg);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }

            .toggle-sidebar {
                left: 1rem;
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }

        /* Export Form Styles */
        .export-container {
            background: white;
            padding: 35px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-card);
            max-width: 800px;
            margin: 25px auto;
            transition: var(--transition);
            border: 1px solid var(--border);
        }

        .export-container:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
        }

        .page-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .page-header h1 {
            margin: 0;
            font-size: 1.9rem;
            color: var(--text);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.5px;
        }

        .page-header i {
            color: var(--primary);
            font-size: 1.75rem;
        }

        .back-link {
            text-decoration: none;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            margin-right: 25px;
            padding: 8px 15px;
            border-radius: 8px;
            background-color: #f9fafb;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--primary);
            background-color: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .back-link i {
            transition: transform 0.2s ease;
        }

        .back-link:hover i {
            transform: translateX(-3px);
        }

        .form-group {
            margin-bottom: 28px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text);
            font-size: 15px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.25s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            background-color: var(--light);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.15);
            outline: none;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%234b5563' width='24px' height='24px'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 40px;
        }

        .option-group {
            padding: 8px 12px;
            background-color: var(--primary-light);
            color: var(--primary-dark);
            font-size: 13px;
            letter-spacing: 0.5px;
        }

        .btn {
            padding: 14px 28px;
            border-radius: var(--border-radius);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            letter-spacing: 0.3px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.1);
        }

        .alert {
            padding: 18px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 500;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.03);
        }

        .alert-success {
            background-color: var(--success-light);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background-color: var(--danger-light);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert i {
            font-size: 20px;
        }

        .download-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 24px;
            background: var(--success);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .download-link:hover {
            background: #0da271;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .download-link i {
            font-size: 18px;
        }

        form {
            position: relative;
            transition: var(--transition);
        }

        .export-container::before {
            content: '';
            position: absolute;
            top: -10px;
            left: 30px;
            right: 30px;
            height: 10px;
            background-color: var(--primary);
            border-radius: 10px 10px 0 0;
            opacity: 0.8;
            display: none;
        }

        .export-container {
            position: relative;
            overflow: hidden;
        }

        .export-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--info) 100%);
            display: block;
        }

        form::after {
            content: '';
            display: block;
            margin-top: 30px;
            height: 1px;
            background: var(--border);
            margin-bottom: 20px;
        }

        select.form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <i class="bi bi-hexagon-fill"></i>
            HR Portal
        </div>
        
        <nav>
            <a href="hr_dashboard.php" class="nav-link">
                <i class="bi bi-grid-1x2-fill"></i>
                Dashboard
            </a>
            <a href="employee.php" class="nav-link">
                <i class="bi bi-people-fill"></i>
                Employees
            </a>
            <a href="hr_attendance_report.php" class="nav-link active">
                <i class="bi bi-calendar-check-fill"></i>
                Attendance
            </a>
            <a href="shifts.php" class="nav-link">
                <i class="bi bi-clock-history"></i>
                Shifts
            </a>
            <a href="manager_payouts.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Managers Payout
            </a>
            <a href="company_analytics_dashboard.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Company Stats
            </a>
            <a href="salary_overview.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Leave Request
            </a>
            <a href="admin/manage_geofence_locations.php" class="nav-link">
                <i class="bi bi-geo-alt-fill"></i>
                Geofence Locations
            </a>
            <a href="travelling_allowanceh.php" class="nav-link">
                <i class="bi bi-car-front-fill"></i>
                Travel Expenses
            </a>
            <a href="hr_overtime_approval.php" class="nav-link">
                <i class="bi bi-clock-history"></i>
                Overtime Approval
            </a>
            <a href="hr_password_reset.php" class="nav-link">
                <i class="bi bi-key-fill"></i>
                Password Reset
            </a>
            <a href="hr_settings.php" class="nav-link">
                <i class="bi bi-gear-fill"></i>
                Settings
            </a>
            <!-- Logout Button -->
            <a href="logout.php" class="nav-link logout-link">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </nav>
    </div>

    <!-- Toggle Sidebar Button -->
    <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <a href="salary_overview.php?month=<?php echo $selected_month; ?>" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back
            </a>
            <h1>
                <i class="fas fa-file-export"></i>
                Export Attendance Data
            </h1>
        </div>

        <div class="export-container">
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
                <a href="<?php echo $download_link; ?>" class="download-link">
                    <i class="fas fa-download"></i>
                    Download CSV File
                </a>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="user_id">Select Employee</label>
                    <select name="user_id" id="user_id" class="form-control" required>
                        <option value="all" <?php echo $unique_id === 'all' ? 'selected' : ''; ?>>All Employees</option>
                        <optgroup label="Individual Employees" class="option-group">
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['unique_id']); ?>"
                                    <?php echo $user['unique_id'] === $unique_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <div class="form-group">
                    <label for="month">Select Month</label>
                    <input type="month" name="month" id="month" class="form-control" 
                           value="<?php echo htmlspecialchars($selected_month); ?>" required>
                </div>

                <button type="submit" name="export" class="btn btn-primary">
                    <i class="fas fa-file-export"></i>
                    Export Attendance
                </button>
            </form>
        </div>
    </div>

    <!-- Add Sidebar Toggle Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar functionality
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                sidebarToggle.classList.toggle('collapsed');
                
                // Change icon direction
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('bi-chevron-left');
                    icon.classList.add('bi-chevron-right');
                } else {
                    icon.classList.remove('bi-chevron-right');
                    icon.classList.add('bi-chevron-left');
                }
            });
            
            // Handle responsive behavior
            function checkWidth() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    sidebarToggle.classList.add('collapsed');
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                    sidebarToggle.classList.remove('collapsed');
                }
            }
            
            // Check on load
            checkWidth();
            
            // Check on resize
            window.addEventListener('resize', checkWidth);
            
            // Handle click outside on mobile
            document.addEventListener('click', function(e) {
                const isMobile = window.innerWidth <= 768;
                
                if (isMobile && !sidebar.contains(e.target) && !sidebar.classList.contains('collapsed')) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    sidebarToggle.classList.add('collapsed');
                }
            });
        });
    </script>
</body>
</html>
