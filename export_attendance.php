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
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --dark: #343a40;
            --light: #f8f9fa;
            --border: #e9ecef;
            --text: #212529;
            --text-muted: #6c757d;
            --shadow: rgba(0, 0, 0, 0.05);
            --shadow-hover: rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
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
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 20px auto;
        }

        .page-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .page-header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: var(--text);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header i {
            color: var(--primary);
        }

        .back-link {
            text-decoration: none;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            margin-right: 20px;
        }

        .back-link:hover {
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            outline: none;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Alert Styles */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: #e6f7e6;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .download-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: var(--success);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            margin-top: 15px;
            transition: all 0.3s ease;
        }

        .download-link:hover {
            background: #37b6e3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Option group styles */
        .option-group {
            border-top: 1px solid var(--border);
            font-weight: bold;
            color: var(--primary);
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
            <a href="salary_overview.php" class="nav-link">
                <i class="bi bi-cash-coin"></i>
                Salary
            </a>
            <a href="edit_leave.php" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                Leave Request
            </a>
            <a href="manage_leave_balance.php" class="nav-link">
                <i class="bi bi-briefcase-fill"></i>
                Recruitment
            </a>
            <a href="#" class="nav-link">
                <i class="bi bi-file-earmark-text-fill"></i>
                Reports
            </a>
            <a href="generate_agreement.php" class="nav-link">
                <i class="bi bi-chevron-contract"></i>
                Contracts
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
