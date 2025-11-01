<?php
session_start();

// Add authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db_connect.php';

// Get selected month (define this first)
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Get all active users for dropdown, plus users who were active during the selected month
// (includes users who became inactive in the selected month or later)
$users_query = "SELECT id, unique_id, username FROM users WHERE deleted_at IS NULL 
AND (status = 'active' OR 
    (status = 'inactive' AND 
     (DATE_FORMAT(status_changed_date, '%Y-%m') >= ?)))
ORDER BY username";
$users_stmt = $conn->prepare($users_query);
$users_stmt->bind_param('s', $selected_month);
$users_stmt->execute();
$users_result = $users_stmt->get_result();
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Get selected user (define this after users are loaded)
$unique_id = isset($_GET['id']) ? $_GET['id'] : ($users[0]['unique_id'] ?? '');
$month_start = $selected_month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// Get the current date
$current_date_obj = new DateTime();
$current_date_str = $current_date_obj->format('Y-m-d');

// Set end date as either month end or current date, whichever is earlier
$month_end_obj = new DateTime($month_end);
$end_date = ($month_end_obj > $current_date_obj) ? $current_date_obj : $month_end_obj;

// Add this new function for rounding overtime
function roundOvertime($overtime) {
    if (empty($overtime) || $overtime === '00:00:00') {
        return '00:00:00';
    }

    // Convert overtime string to minutes
    list($hours, $minutes) = explode(':', $overtime);
    $total_minutes = ($hours * 60) + $minutes;

    // Get the hour part
    $base_hours = floor($total_minutes / 60);
    $remaining_minutes = $total_minutes % 60;

    // Round to nearest 30 minutes
    if ($remaining_minutes < 30) {
        $rounded_minutes = 0;
    } else {
        $rounded_minutes = 30;
    }

    // Format back to HH:MM:00
    return sprintf('%02d:%02d:00', $base_hours, $rounded_minutes);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        die("Please login to continue.");
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Get the user's ID from unique_id
        $user_stmt = $conn->prepare("SELECT id FROM users WHERE unique_id = ? AND deleted_at IS NULL");
        $user_stmt->bind_param('s', $unique_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user = $user_result->fetch_assoc();
        
        if (!$user) {
            throw new Exception("User not found");
        }
        
        $user_id = $user['id'];

        // First check if record exists
        $check_stmt = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
        
        foreach ($_POST['attendance'] as $date => $data) {
            if (!isset($data['modified']) || $data['modified'] !== 'true') {
                continue;
            }

            // Get the status value - now 'holiday' is a valid ENUM value
            $status = $data['status'];
            
            // Ensure status is one of the valid ENUM values
            if (!in_array($status, ['present', 'absent', 'half_day', 'leave', 'holiday'])) {
                $status = 'present'; // Default to 'present' if invalid
            }

            $punch_in = !empty($data['punch_in']) ? date('Y-m-d H:i:s', strtotime("$date {$data['punch_in']}")) : null;
            $punch_out = !empty($data['punch_out']) ? date('Y-m-d H:i:s', strtotime("$date {$data['punch_out']}")) : null;
            $shift_time = !empty($data['shift_time']) ? $data['shift_time'] : null;
            
            // Calculate overtime based on punch out time
            $overtime = '00:00:00';
            if ($status === 'present' && !empty($punch_out)) {
                $punch_out_parts = explode(':', date('H:i', strtotime($punch_out)));
                $punch_out_minutes = ($punch_out_parts[0] * 60) + $punch_out_parts[1];
                
                // Get the shift end time from employee record instead of hardcoding
                $shift_end_time = !empty($employee['shift_end_time']) ? $employee['shift_end_time'] : '18:00:00';
                $shift_end_parts = explode(':', $shift_end_time);
                $shift_end_minutes = ($shift_end_parts[0] * 60) + ($shift_end_parts[1] ?? 0);
                
                if ($punch_out_minutes > $shift_end_minutes) {
                    $overtime_minutes = $punch_out_minutes - $shift_end_minutes;
                    $overtime_hours = floor($overtime_minutes / 60);
                    $overtime_mins = $overtime_minutes % 60;
                    $overtime = sprintf('%02d:%02d:00', $overtime_hours, $overtime_mins);
                    
                    // Round the overtime
                    $overtime = roundOvertime($overtime);
                }
            }

            // Add debug logging
            error_log("Date: $date, Punch Out: $punch_out, Raw Overtime: " . sprintf('%02d:%02d:00', $overtime_hours, $overtime_mins) . ", Rounded Overtime: $overtime");
            
            $is_weekly_off = isset($data['is_weekly_off']) ? 1 : 0;
            
            // Calculate working hours if both punch in and punch out exist
            $working_hours = null;
            if ($punch_in && $punch_out && $status === 'present') {
                $punch_in_obj = new DateTime($punch_in);
                $punch_out_obj = new DateTime($punch_out);
                $interval = $punch_in_obj->diff($punch_out_obj);
                $working_hours = sprintf('%02d:%02d:00', 
                    $interval->h + ($interval->days * 24), 
                    $interval->i
                );
            }

            // Add weekly off check - get the day of week
            $date_obj = new DateTime($date);
            $day_of_week = $date_obj->format('l'); // Gets day name (Monday, Tuesday, etc.)
            
            // Check if record exists
            $check_stmt->bind_param('is', $user_id, $date);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                // Update existing record
                $update_stmt = $conn->prepare("UPDATE attendance 
                                             SET status = ?,
                                                 punch_in = ?,
                                                 punch_out = ?,
                                                 shift_time = ?,
                                                 overtime_hours = ?,
                                                 working_hours = ?,
                                                 is_weekly_off = ?
                                             WHERE user_id = ? AND date = ?");
                
                $update_stmt->bind_param('ssssssiis', 
                    $status,
                    $punch_in,
                    $punch_out,
                    $shift_time,
                    $overtime,
                    $working_hours,
                    $is_weekly_off,
                    $user_id,
                    $date
                );
                $update_stmt->execute();
                
                if ($update_stmt->error) {
                    throw new Exception("Error updating record for date $date: " . $update_stmt->error);
                }
            } else {
                // Insert new record
                $insert_stmt = $conn->prepare("INSERT INTO attendance 
                                             (user_id, date, status, punch_in, punch_out, 
                                              shift_time, overtime_hours, working_hours, is_weekly_off) 
                                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $insert_stmt->bind_param('isssssssi',
                    $user_id,
                    $date,
                    $status,
                    $punch_in,
                    $punch_out,
                    $shift_time,
                    $overtime,
                    $working_hours,
                    $is_weekly_off
                );
                $insert_stmt->execute();
                
                if ($insert_stmt->error) {
                    throw new Exception("Error inserting record for date $date: " . $insert_stmt->error);
                }
            }
        }

        // Commit transaction
        $conn->commit();
        
        // Set a success message
        $success_message = "Attendance records have been successfully updated.";
        
        // Instead of redirecting, we'll stay on the same page
        // Comment out the redirect:
        // header("Location: salary_overview.php?month=" . $selected_month . "&success=1");
        // exit;

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error = "Error updating attendance: " . $e->getMessage();
    }
}

// Fetch employee details
$stmt = $conn->prepare("
    SELECT 
        u.*,
        s.shift_name,
        s.start_time as shift_start_time,
        s.end_time as shift_end_time,
        us.weekly_offs,
        us.effective_from,
        us.effective_to
    FROM users u
    LEFT JOIN user_shifts us ON (
        u.id = us.user_id 
        AND CURRENT_DATE >= us.effective_from 
        AND (us.effective_to IS NULL OR CURRENT_DATE <= us.effective_to)
    )
    LEFT JOIN shifts s ON COALESCE(us.shift_id, u.shift_id) = s.id
    WHERE u.unique_id = ? 
    AND u.deleted_at IS NULL
    AND (u.status = 'active' OR 
         (u.status = 'inactive' AND 
          (DATE_FORMAT(u.status_changed_date, '%Y-%m') >= ?)))
");
$stmt->bind_param('ss', $unique_id, $selected_month);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

// Add this error handling after fetching employee
if (!$employee) {
    die("Employee not found. Please check the employee ID and try again.");
}

// Parse weekly offs into an array for easier display and handling
$weekly_off_days = [];
if (!empty($employee['weekly_offs'])) {
    $weekly_off_days = explode(',', $employee['weekly_offs']);
}

// Fetch attendance records for the month
$query = "SELECT a.*, a.overtime_hours FROM attendance a 
          WHERE a.user_id = ? AND a.date BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('iss', $employee['id'], $month_start, $month_end);
$stmt->execute();
$result = $stmt->get_result();
$attendance_records = [];
while ($row = $result->fetch_assoc()) {
    $attendance_records[date('Y-m-d', strtotime($row['date']))] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Attendance</title>
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
            --border-radius: 8px;
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

        /* Update attendance form styles for better appearance */
        .attendance-form {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            margin: 20px 0;
            transition: var(--transition);
            border: 1px solid var(--border);
        }

        /* Improve filters container design */
        .filters-container {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            align-items: flex-end;
            background: var(--primary-light);
            padding: 20px;
            border-radius: var(--border-radius);
            border: 1px solid rgba(67, 97, 238, 0.2);
        }

        /* Make the filter controls more user-friendly */
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            min-width: 220px;
            font-size: 14px;
            transition: var(--transition);
        }

        .filter-group select:focus,
        .filter-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
            outline: none;
        }

        /* Update the button styles */
        .apply-filters {
            height: 42px;
            padding: 0 24px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .apply-filters:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        /* Enhance the table appearance */
        .attendance-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .attendance-table th,
        .attendance-table td {
            padding: 15px;
            border: 1px solid #e5e7eb;
        }

        .attendance-table th {
            background-color: var(--primary-light);
            color: var(--dark);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        .attendance-table tr {
            transition: var(--transition);
        }

        .attendance-table tr:hover {
            background-color: rgba(67, 97, 238, 0.03);
        }

        /* Improve the style of the form controls within the table */
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
            outline: none;
        }

        /* Enhance the status select styling */
        .status-select {
            background-color: white;
            color: var(--text);
            font-weight: 500;
        }

        /* Improve weekend and today highlighting */
        .weekend {
            background-color: #FFF9F7; /* Very light pink/peach for weekend days */
        }

        .weekend:hover {
            background-color: #FFF0F0;
        }

        .today {
            background-color: #f0f7ff;
        }

        .today:hover {
            background-color: #e0f0ff;
        }

        /* Style the Weekly Off indicator more attractively */
        .weekly-off-indicator {
            display: block;
            margin: 5px 0;
            padding: 6px 10px;
            background-color: #FFF8DC; /* Cornsilk color similar to the image */
            color: #8B6914; /* Darker gold text color */
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            border: 1px solid #F4E3B2;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            width: 100%;
        }

        /* Make the weekly-offs-display more visually appealing */
        .weekly-offs-display {
            margin-bottom: 25px;
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            font-size: 15px;
            color: var(--dark);
            display: flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .weekly-offs-display i {
            color: var(--primary);
            margin-right: 10px;
            font-size: 18px;
        }

        /* Improve the button group styling */
        .btn-group {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-secondary {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #212529;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Enhance the back button styling */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: var(--text);
            font-weight: 500;
            margin-bottom: 1.5rem;
            padding: 8px 16px;
            border-radius: 6px;
            transition: var(--transition);
            background-color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
        }

        .back-btn:hover {
            color: var(--primary);
            background-color: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .back-btn i {
            transition: var(--transition);
        }

        .back-btn:hover i {
            transform: translateX(-3px);
        }

        /* Make the section title more attractive */
        .section-title {
            font-size: 1.75rem;
            color: var(--dark);
            margin-bottom: 1.5rem;
            font-weight: 600;
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 10px;
        }

        /* Improve checkbox styling */
        input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
            vertical-align: middle;
            margin-left: 8px;
        }

        /* Add custom styles for different status options */
        select.status-select option[value="present"] {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        select.status-select option[value="absent"] {
            background-color: #f8d7da;
            color: #842029;
        }

        select.status-select option[value="leave"] {
            background-color: #cff4fc;
            color: #055160;
        }

        select.status-select option[value="holiday"] {
            background-color: #fff3cd;
            color: #664d03;
        }

        /* Add responsive improvements */
        @media (max-width: 992px) {
            .filters-container {
                flex-wrap: wrap;
            }
            
            .filter-group {
                flex: 1 0 calc(50% - 20px);
                min-width: 200px;
            }
        }

        @media (max-width: 768px) {
            .filters-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .attendance-table {
                display: block;
                overflow-x: auto;
            }
        }

        /* Add a specific style for rows with weekly offs */
        tr.has-weekly-off td:first-child {
            position: relative; /* For positioning the indicator */
        }

        /* Add styling for the weekly off checkbox label */
        .weekly-off-checkbox-label {
            display: inline-block;
            font-size: 12px;
            color: #664d03;
            margin-left: 5px;
            font-weight: 500;
            vertical-align: middle;
        }

        /* Update the checkbox styling to align with the label */
        input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
            vertical-align: middle;
            margin-left: 8px;
        }

        /* Make the weekly off checkbox and label appear more prominently on weekly off days */
        tr.has-weekly-off .weekly-off-checkbox-label {
            font-weight: 600;
            color: #8B6914;
        }

        /* Update the weekly-off-worked container styling */
        .weekly-off-worked {
            display: flex;
            align-items: center;
            margin-top: 8px;
            background-color: #fff9e9;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px dashed #f0d78c;
        }

        /* Update the weekly off checkbox label to be more concise */
        .weekly-off-checkbox-label {
            display: inline-block;
            font-size: 12px;
            color: #8B6914;
            margin-left: 5px;
            font-weight: 600;
            vertical-align: middle;
        }

        /* Add success alert style */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 6px;
        }

        .alert-danger {
            color: #842029;
            background-color: #f8d7da;
            border-color: #f5c2c7;
        }

        .alert-success {
            color: #0f5132;
            background-color: #d1e7dd;
            border-color: #badbcc;
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
        <div class="container">
            <a href="salary_overview.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Overview
            </a>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <div class="attendance-form">
                <!-- Add filters at the top -->
                <form id="filters-form" method="GET" class="filters-container">
                    <div class="filter-group">
                        <label for="employee">Select Employee:</label>
                        <select name="id" id="employee" required>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['unique_id']); ?>"
                                    <?php echo $user['unique_id'] === $unique_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="month">Select Month:</label>
                        <input type="month" 
                               id="month" 
                               name="month" 
                               value="<?php echo htmlspecialchars($selected_month); ?>" 
                               required>
                    </div>

                    <button type="submit" class="apply-filters">Apply Filters</button>
                </form>

                <h2 class="section-title">
                    Edit Attendance - <?php echo htmlspecialchars($employee['username'] ?? 'Unknown Employee'); ?> 
                    (<?php echo date('F Y', strtotime($month_start)); ?>)
                </h2>
                
                <?php if (!empty($weekly_off_days)): ?>
                <div class="weekly-offs-display">
                    <i class="bi bi-calendar-x"></i> Weekly Offs: 
                    <?php echo implode(', ', $weekly_off_days); ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Shift Time</th>
                                <th>Punch In</th>
                                <th>Punch Out</th>
                                <th>Overtime (hrs)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $current_date = new DateTime($month_start);
                            
                            while ($current_date <= $end_date) {
                                $date_str = $current_date->format('Y-m-d');
                                $is_weekend = in_array($current_date->format('N'), [6, 7]); // Saturday or Sunday
                                $is_today = $date_str === date('Y-m-d');
                                $record = $attendance_records[$date_str] ?? null;
                                
                                $row_class = $is_weekend ? 'weekend' : '';
                                $row_class .= $is_today ? ' today' : '';
                            ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td>
                                        <?php echo $current_date->format('d M (D)'); ?>
                                        <?php 
                                        // Show an indicator if this day is a configured weekly off
                                        $day_name = $current_date->format('l');
                                        $is_weekly_off_day = in_array($day_name, $weekly_off_days);
                                        if ($is_weekly_off_day) {
                                            echo '<div class="weekly-off-indicator">Weekly Off</div>';
                                            // Add a class to the row for additional styling
                                            $row_class .= ' has-weekly-off';
                                            
                                            // Only show checkbox for weekly off days
                                            echo '<div class="weekly-off-worked">';
                                            echo '<input type="checkbox" 
                                                  name="attendance[' . $date_str . '][is_weekly_off]" 
                                                  value="1" 
                                                  ' . ((isset($record['is_weekly_off']) && $record['is_weekly_off'] == 1) ? 'checked' : '') . '
                                                  onchange="markAsModified(this)">';
                                            echo '<span class="weekly-off-checkbox-label">Worked?</span>';
                                            echo '</div>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <input type="hidden" name="attendance[<?php echo $date_str; ?>][modified]" class="modified-flag" value="false">
                                        <select name="attendance[<?php echo $date_str; ?>][status]" class="form-control status-select" onchange="markAsModified(this)">
                                            <option value="">-- Select Status --</option>
                                            <option value="present" <?php echo ($record && $record['status'] === 'present') ? 'selected' : ''; ?>>Present</option>
                                            <option value="absent" <?php echo ($record && $record['status'] === 'absent') ? 'selected' : ''; ?>>Absent</option>
                                            <option value="leave" <?php echo ($record && $record['status'] === 'leave') ? 'selected' : ''; ?>>Leave</option>
                                            <option value="half_day" <?php echo ($record && $record['status'] === 'half_day') ? 'selected' : ''; ?>>Half Day</option>
                                            <option value="holiday" <?php echo ($record && $record['status'] === 'holiday') ? 'selected' : ''; ?>>Holiday</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="time" class="form-control" 
                                               name="attendance[<?php echo $date_str; ?>][shift_time]"
                                               value="<?php 
                                                    if ($record && $record['shift_time']) {
                                                        echo $record['shift_time']; 
                                                    } else {
                                                        echo $employee['shift_start_time'] ?? '';
                                                    }
                                               ?>"
                                               data-shift-start="<?php echo $employee['shift_start_time'] ?? ''; ?>"
                                               data-shift-end="<?php echo $employee['shift_end_time'] ?? ''; ?>"
                                               onchange="markAsModified(this)">
                                    </td>
                                    <td>
                                        <input type="time" class="form-control punch-in" 
                                               name="attendance[<?php echo $date_str; ?>][punch_in]"
                                               value="<?php 
                                                    if ($record && $record['punch_in']) {
                                                        $punch_in_time = new DateTime($record['punch_in']);
                                                        echo $punch_in_time->format('H:i');
                                                    }
                                               ?>"
                                               onchange="markAsModified(this)">
                                    </td>
                                    <td>
                                        <input type="time" class="form-control punch-out" 
                                               name="attendance[<?php echo $date_str; ?>][punch_out]"
                                               value="<?php 
                                                    if ($record && $record['punch_out']) {
                                                        // Convert the datetime format to time only
                                                        $punch_out_time = new DateTime($record['punch_out']);
                                                        echo $punch_out_time->format('H:i');
                                                    }
                                               ?>"
                                               <?php echo ($record && $record['status'] !== 'present') ? 'disabled' : ''; ?>
                                               onchange="markAsModified(this)">
                                    </td>
                                    <td>
                                        <input type="time" class="form-control overtime" 
                                               name="attendance[<?php echo $date_str; ?>][overtime]"
                                               value="<?php 
                                                    if (isset($attendance_records[$date_str]['overtime_hours']) && 
                                                        $attendance_records[$date_str]['overtime_hours'] !== '00:00:00' && 
                                                        $attendance_records[$date_str]['overtime_hours'] !== null) {
                                                        echo $attendance_records[$date_str]['overtime_hours'];
                                                    } else {
                                                        echo '00:00:00';
                                                    }
                                               ?>"
                                               step="1"
                                               <?php echo ($record && $record['status'] !== 'present') ? 'disabled' : ''; ?>
                                               onchange="markAsModified(this)">
                                    </td>
                                </tr>
                            <?php
                                $current_date->modify('+1 day');
                            }
                            ?>
                        </tbody>
                    </table>

                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary" onclick="history.back()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
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

            // Attendance functionality
            // Function to mark rows as modified
            function markAsModified(element) {
                const row = element.closest('tr');
                const modifiedFlag = row.querySelector('.modified-flag');
                if (modifiedFlag) {
                    modifiedFlag.value = 'true';
                }
            }

            // Handle status change
            document.querySelectorAll('.status-select').forEach(select => {
                select.addEventListener('change', function() {
                    const row = this.closest('tr');
                    const inputs = row.querySelectorAll('input[type="time"]');
                    const overtimeInput = row.querySelector('.overtime');
                    
                    if (this.value === 'absent' || this.value === 'leave' || this.value === 'half_day' || this.value === 'holiday') {
                        inputs.forEach(input => {
                            input.value = '';
                            input.disabled = true;
                            input.required = false;
                        });
                        if (overtimeInput) {
                            overtimeInput.value = '00:00:00';
                        }
                    } else if (this.value === 'present') {
                        inputs.forEach(input => {
                            input.disabled = false;
                            input.required = false;
                        });
                        
                        const shiftTimeInput = row.querySelector('input[name$="[shift_time]"]');
                        if (shiftTimeInput && !shiftTimeInput.value && shiftTimeInput.dataset.shiftStart) {
                            shiftTimeInput.value = shiftTimeInput.dataset.shiftStart;
                        }
                        
                        calculateOvertime(row);
                    }
                    markAsModified(this);
                });
            });

            function roundOvertime(hours, minutes) {
                // Convert to total minutes
                const totalMinutes = (hours * 60) + minutes;
                
                // Get base hours and remaining minutes
                const baseHours = Math.floor(totalMinutes / 60);
                const remainingMinutes = totalMinutes % 60;
                
                // Round to nearest 30 minutes
                const roundedMinutes = remainingMinutes < 30 ? 0 : 30;
                
                return {
                    hours: baseHours,
                    minutes: roundedMinutes
                };
            }

            function calculateOvertime(row) {
                const punchOut = row.querySelector('.punch-out').value;
                const shiftTime = row.querySelector('input[name$="[shift_time]"]');
                const overtimeInput = row.querySelector('.overtime');
                const status = row.querySelector('.status-select').value;

                if (status === 'present' && punchOut && shiftTime) {
                    // Convert punch out time to minutes since midnight
                    const [punchOutHours, punchOutMinutes] = punchOut.split(':').map(Number);
                    const punchOutInMinutes = (punchOutHours * 60) + punchOutMinutes;
                    
                    // Get shift end time from data attribute instead of hardcoding
                    const shiftEndTime = shiftTime.dataset.shiftEnd || '18:00';
                    const [shiftEndHours, shiftEndMinutes] = shiftEndTime.split(':').map(Number);
                    const shiftEndInMinutes = (shiftEndHours * 60) + (shiftEndMinutes || 0);
                    
                    if (punchOutInMinutes > shiftEndInMinutes) {
                        // Calculate overtime in minutes
                        const overtimeMinutes = punchOutInMinutes - shiftEndInMinutes;
                        
                        // Convert to hours and minutes
                        const rawHours = Math.floor(overtimeMinutes / 60);
                        const rawMinutes = overtimeMinutes % 60;
                        
                        // Round the overtime
                        const rounded = roundOvertime(rawHours, rawMinutes);
                        
                        // Format as HH:MM:00
                        overtimeInput.value = 
                            String(rounded.hours).padStart(2, '0') + ':' +
                            String(rounded.minutes).padStart(2, '0') + ':00';
                    } else {
                        overtimeInput.value = '00:00:00';
                    }
                }
            }

            // Add event listeners for overtime changes
            document.querySelectorAll('.overtime').forEach(input => {
                input.addEventListener('change', function() {
                    markAsModified(this);
                });
            });

            // Add event listeners for punch out changes
            document.querySelectorAll('.punch-out').forEach(input => {
                input.addEventListener('change', function() {
                    calculateOvertime(this.closest('tr'));
                    markAsModified(this);
                });
            });

            // Add event listeners for punch in changes
            document.querySelectorAll('.punch-in').forEach(input => {
                input.addEventListener('change', function() {
                    markAsModified(this);
                });
            });

            // Add event listeners for shift time changes
            document.querySelectorAll('input[name$="[shift_time]"]').forEach(input => {
                input.addEventListener('change', function() {
                    markAsModified(this);
                });
            });

            // Initialize all rows
            document.querySelectorAll('.status-select').forEach(select => {
                select.dispatchEvent(new Event('change'));
            });
        });
    </script>
</body>
</html> 