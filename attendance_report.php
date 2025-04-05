<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Define allowed roles
$allowed_roles = ['HR', 'Senior Manager (Studio)'];
$has_access = (in_array($_SESSION['role'], $allowed_roles) || isset($_SESSION['temp_admin_access']));

if (!$has_access) {
    header('Location: unauthorized.php');
    exit();
}

// Update the is_hr variable to include Senior Manager access
$is_hr = ($_SESSION['role'] === 'HR' || $_SESSION['role'] === 'Senior Manager (Studio)' || isset($_SESSION['temp_admin_access']));

// Set default filter values
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];
$show_all = ($is_hr && $user_id === 'all');

// Fetch users for HR view
$users = [];
if ($is_hr) {
    $stmt = $pdo->query("SELECT id, username, unique_id FROM users WHERE deleted_at IS NULL ORDER BY username");
    $users = $stmt->fetchAll();
}

// Fetch all active users for the dropdown
$users_query = "SELECT id, username, unique_id FROM users WHERE deleted_at IS NULL ORDER BY username";
$users_stmt = $pdo->query($users_query);
$all_users = $users_stmt->fetchAll();

// Fetch attendance records
$query = "
    SELECT 
        a.*,
        u.username,
        u.unique_id,
        s.shift_name,
        s.start_time as shift_start,
        s.end_time as shift_end,
        us.weekly_offs,
        us.effective_from as shift_effective_from,
        us.effective_to as shift_effective_to,
        a.overtime_hours
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN user_shifts us ON (
        u.id = us.user_id 
        AND a.date >= us.effective_from 
        AND (us.effective_to IS NULL OR a.date <= us.effective_to)
    )
    LEFT JOIN shifts s ON us.shift_id = s.id
    WHERE DATE_FORMAT(a.date, '%Y-%m') = :month
    " . ($user_id !== 'all' ? "AND a.user_id = :user_id" : "") . "
    ORDER BY a.date DESC, u.username ASC
";

$params = ['month' => $month];
if ($user_id !== 'all') {
    $params['user_id'] = $user_id;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Add this new function to calculate overtime based on shift times
function calculateOvertimeFromPunchTimes($punch_in, $punch_out, $shift_end) {
    if (empty($punch_in) || empty($punch_out) || empty($shift_end)) {
        return '00:00:00';
    }

    $punch_in_time = strtotime($punch_in);
    $punch_out_time = strtotime($punch_out);
    $shift_end_time = strtotime($shift_end);

    // If punched in after shift end, all time is overtime
    if ($punch_in_time > $shift_end_time) {
        $overtime_seconds = $punch_out_time - $punch_in_time;
    } else {
        // Only count time after shift end as overtime
        $overtime_seconds = max(0, $punch_out_time - $shift_end_time);
    }

    // Convert to hours:minutes:seconds format
    $hours = floor($overtime_seconds / 3600);
    $minutes = floor(($overtime_seconds % 3600) / 60);
    $seconds = $overtime_seconds % 60;

    return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
}

// Add this new function to calculate valid overtime
function calculateValidOvertime($overtime_hours, $punch_in = null, $punch_out = null, $shift_end = null) {
    if (empty($overtime_hours)) return '00:00:00';
    
    // If we have punch times and shift end, recalculate overtime
    if ($punch_in && $punch_out && $shift_end) {
        $overtime_hours = calculateOvertimeFromPunchTimes($punch_in, $punch_out, $shift_end);
    }
    
    // Convert overtime to decimal if it's in HH:MM:SS format
    $overtime_decimal = is_numeric($overtime_hours) ? 
        $overtime_hours : 
        convertTimeToDecimal($overtime_hours);
    
    // Only count overtime if it's >= 1.5 hours (1 hour 30 minutes)
    return $overtime_decimal >= 1.5 ? $overtime_hours : '00:00:00';
}

// Update the monthly totals calculation
$total_working_hours = 0;
$total_overtime = 0;
$present_days = [];
$present_counts_by_user = [];
$weekly_off_worked_count = 0;
$employee_count = 0;
$days_in_month = date('t', strtotime($month . '-01'));
$attendance_by_day = [];
$late_arrivals = 0;
$early_departures = 0;
$full_attendance_users = 0;
$zero_attendance_users = 0;
$weekend_hours = 0;
$working_days = 0;
$no_punch_out_count = 0;
$max_hours_day = ['date' => '', 'hours' => 0];

// Calculate working days (excluding weekends)
$month_start = date('Y-m-01', strtotime($month));
$month_end = date('Y-m-t', strtotime($month));
$current_date = $month_start;
$weekends = 0;

while (strtotime($current_date) <= strtotime($month_end)) {
    $day_of_week = date('N', strtotime($current_date));
    if ($day_of_week >= 6) { // 6 and 7 are weekend days (Saturday and Sunday)
        $weekends++;
    }
    $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
}

$working_days = $days_in_month - $weekends;

if ($user_id === 'all') {
    // Unique users to check full/zero attendance
    $unique_users = [];
    foreach ($all_users as $user) {
        $unique_users[$user['id']] = [
            'present_days' => 0,
            'username' => $user['username']
        ];
    }
}

foreach ($records as $record) {
    // Calculate working hours
    $working_hours_decimal = is_numeric($record['working_hours']) ? 
        $record['working_hours'] : 
        convertTimeToDecimal($record['working_hours']);
    
    $total_working_hours += $working_hours_decimal;
    
    // Calculate valid overtime using shift end time
    $shift_end_datetime = $record['date'] . ' ' . $record['shift_end'];
    $valid_overtime = calculateValidOvertime(
        $record['overtime_hours'],
        $record['punch_in'],
        $record['punch_out'],
        $shift_end_datetime
    );
    $total_overtime += is_numeric($valid_overtime) ? 
        $valid_overtime : 
        convertTimeToDecimal($valid_overtime);
    
    // Check if day is a weekly off
    $weekly_offs = getWeeklyOffsForDate($pdo, $record['user_id'], $record['date']);
    $is_weekly_off = isWeeklyOff($record['date'], $weekly_offs);
    
    // Count weekly offs that were worked
    if ($is_weekly_off && $record['punch_in']) {
        $weekly_off_worked_count++;
        $weekend_hours += $working_hours_decimal;
    }
    
    // Check for maximum hours in a day
    if ($working_hours_decimal > $max_hours_day['hours']) {
        $max_hours_day['hours'] = $working_hours_decimal;
        $max_hours_day['date'] = $record['date'];
    }
    
    // Count late arrivals
    if ($record['punch_in'] && $record['shift_start']) {
        $punch_in_time = strtotime($record['punch_in']);
        $shift_start = strtotime($record['date'] . ' ' . $record['shift_start']);
        $grace_period = 15 * 60; // 15 minutes in seconds
        
        if ($punch_in_time > ($shift_start + $grace_period)) {
            $late_arrivals++;
        }
    }
    
    // Count early departures
    if ($record['punch_in'] && $record['punch_out'] && $record['shift_end']) {
        $punch_out_time = strtotime($record['punch_out']);
        $shift_end = strtotime($record['date'] . ' ' . $record['shift_end']);
        $early_threshold = 15 * 60; // 15 minutes in seconds
        
        if ($punch_out_time < ($shift_end - $early_threshold)) {
            $early_departures++;
        }
    }
    
    // Count no punch out instances
    if ($record['punch_in'] && !$record['punch_out']) {
        $no_punch_out_count++;
    }
    
    // Track attendance by day of week for heatmap
    $day_of_week = date('l', strtotime($record['date']));
    if (!isset($attendance_by_day[$day_of_week])) {
        $attendance_by_day[$day_of_week] = 0;
    }
    $attendance_by_day[$day_of_week]++;
    
    // Only count days with status 'present'
    if (strtolower($record['status']) === 'present') {
        if ($user_id !== 'all') {
            // Single user view - just count unique dates
            $present_days[$record['date']] = true;
        } else {
            // All users view - count unique user-date combinations
            $key = $record['user_id'] . '_' . $record['date'];
            $present_days[$key] = true;
            
            // Track present days by user for average and full attendance calculation
            $user_key = $record['user_id'];
            $date_key = $record['date'];
            if (!isset($present_counts_by_user[$user_key])) {
                $present_counts_by_user[$user_key] = [];
            }
            $present_counts_by_user[$user_key][$date_key] = true;
            
            // Update present days count for this user
            if (isset($unique_users[$user_key])) {
                $unique_users[$user_key]['present_days']++;
            }
        }
    }
}

// Get the count of present days
if ($user_id !== 'all') {
    // Single user - show total days present
    $present_days_count = count($present_days);
    $days_present_label = 'Days Present';
    $employee_count = 1;
    $attendance_rate = ($present_days_count / $working_days) * 100;
} else {
    // All users - calculate average days present per user
    $total_present_days = 0;
    $employee_count = count($present_counts_by_user);
    
    // Sum up unique days for each user
    foreach ($present_counts_by_user as $user_days) {
        $total_present_days += count($user_days);
    }
    
    // Calculate average (handle division by zero)
    $present_days_count = $employee_count > 0 ? round($total_present_days / $employee_count, 1) : 0;
    $days_present_label = 'Avg. Days Present';
    $attendance_rate = ($present_days_count / $working_days) * 100;
    
    // Count users with full or zero attendance
    foreach ($unique_users as $uid => $user_data) {
        if ($user_data['present_days'] >= $working_days) {
            $full_attendance_users++;
        }
        if ($user_data['present_days'] == 0) {
            $zero_attendance_users++;
        }
    }
}

// Find most active day
$most_active_day = !empty($attendance_by_day) ? array_search(max($attendance_by_day), $attendance_by_day) : 'N/A';
$avg_hours_per_day = $working_days > 0 ? round($total_working_hours / ($present_days_count > 0 ? $present_days_count : 1), 2) : 0;

// Add this helper function
function convertTimeToDecimal($timeString) {
    if (empty($timeString)) return 0;
    
    if (strpos($timeString, ':') !== false) {
        $parts = explode(':', $timeString);
        if (count($parts) >= 2) {
            $hours = (int)$parts[0];
            $minutes = (int)$parts[1];
            $seconds = isset($parts[2]) ? (int)$parts[2] : 0;
            
            return $hours + ($minutes / 60) + ($seconds / 3600);
        }
    }
    
    return 0;
}

// Add function to get weekly offs for a specific date and user
function getWeeklyOffsForDate($pdo, $user_id, $date) {
    $stmt = $pdo->prepare("
        SELECT weekly_offs 
        FROM user_shifts 
        WHERE user_id = ? 
        AND effective_from <= ?
        AND (effective_to IS NULL OR effective_to >= ?)
        ORDER BY effective_from DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id, $date, $date]);
    $result = $stmt->fetch();
    return $result ? $result['weekly_offs'] : '';
}

// Add function to check if a date is a weekly off
function isWeeklyOff($date, $weekly_offs) {
    if (empty($weekly_offs)) return false;
    
    $weekly_offs_array = explode(',', $weekly_offs);
    $day_of_week = date('l', strtotime($date));
    return in_array($day_of_week, $weekly_offs_array);
}

// Add this helper function near the top of the file with other functions
function formatHoursAndMinutes($timeString) {
    if (empty($timeString)) return '-';
    
    // If it's already a decimal number, just format it
    if (is_numeric($timeString)) {
        return number_format((float)$timeString, 2);
    }
    
    // Handle HH:MM:SS format
    if (strpos($timeString, ':') !== false) {
        $parts = explode(':', $timeString);
        if (count($parts) >= 2) {
            $hours = (int)$parts[0];
            $minutes = (int)$parts[1];
            $seconds = isset($parts[2]) ? (int)$parts[2] : 0;
            
            $decimal = $hours + ($minutes / 60) + ($seconds / 3600);
            return number_format($decimal, 2);
        }
    }
    
    // If we can't parse it, return the original string
    return $timeString;
}

// Add this new helper function
function formatDecimalToTime($timeString) {
    if (empty($timeString)) return '-';
    
    // If it's already in HH:MM:SS format, just format it to HH:MM
    if (strpos($timeString, ':') !== false) {
        $parts = explode(':', $timeString);
        if (count($parts) >= 2) {
            return sprintf("%02d:%02d", (int)$parts[0], (int)$parts[1]);
        }
        return $timeString;
    }
    
    // Handle decimal format
    if (is_numeric($timeString)) {
        $hours = floor($timeString);
        $minutes = round(($timeString - $hours) * 60);
        
        return sprintf("%02d:%02d", $hours, $minutes);
    }
    
    return $timeString;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Roboto', sans-serif;
            line-height: 1.6;
            color: var(--text);
            background-color: #f5f8fa;
            padding: 0;
            margin: 0;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 15px;
        }

        .page-header h2 {
            font-size: 28px;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .filters-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
            margin-bottom: 30px;
            transition: all 0.3s ease;
            border: 1px solid var(--border);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            align-items: end;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-item label {
            font-weight: 500;
            color: var(--dark);
            font-size: 15px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
            background-color: var(--light);
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            font-size: 14px;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px var(--shadow-hover);
        }

        .button-container {
            display: flex;
            align-items: flex-end;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 8px 16px var(--shadow-hover);
            transform: translateY(-2px);
        }

        .card-header {
            padding: 20px 25px;
            background-color: white;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        .card-body {
            padding: 25px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .summary-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 25px 20px;
            background: var(--primary-light);
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
            position: relative;
            cursor: help;
        }

        .summary-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px var(--shadow);
        }
        
        .summary-item .tooltip {
            visibility: hidden;
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.8);
            color: white;
            text-align: center;
            padding: 10px 15px;
            border-radius: 6px;
            width: 250px;
            font-size: 13px;
            z-index: 100;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .summary-item .tooltip::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: rgba(0, 0, 0, 0.8) transparent transparent transparent;
        }
        
        .summary-item:hover .tooltip {
            visibility: visible;
            opacity: 1;
        }

        .summary-item h3 {
            margin: 0 0 8px 0;
            color: var(--text-muted);
            font-size: 15px;
            font-weight: 500;
        }

        .summary-item p {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
            color: var(--primary);
        }

        .summary-item.overtime p {
            color: var(--danger);
        }

        .summary-item .icon {
            font-size: 20px;
            margin-bottom: 15px;
            color: var(--primary);
            background: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px var(--shadow);
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .chart-container {
            height: 400px;
            position: relative;
        }

        .attendance-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            margin-bottom: 30px;
        }

        .attendance-table th,
        .attendance-table td {
            padding: 15px;
            text-align: left;
        }

        .attendance-table th {
            background-color: var(--primary-light);
            color: var(--dark);
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
            font-size: 14px;
            border-bottom: 1px solid var(--border);
        }

        .attendance-table tr {
            transition: all 0.2s;
        }

        .attendance-table tr:hover {
            background-color: var(--primary-light);
        }

        .attendance-table tbody tr:not(.user-row) {
            border-bottom: 1px solid var(--border);
        }

        .attendance-table tbody tr:last-child {
            border-bottom: none;
        }

        .weekly-off-row {
            background-color: #FFF9F7;
        }

        .user-row td {
            background-color: #f1f5f9;
            font-weight: 600;
            padding: 18px 15px;
            color: var(--secondary);
            border-top: 2px solid var(--border);
        }

        .badge {
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            margin: 2px;
        }

        .badge.weekly-off {
            background-color: #f1f5f9;
            color: var(--text-muted);
        }

        .badge.worked {
            background-color: #fff3cd;
            color: #664d03;
        }

        .badge.working-day {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-badge {
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.present {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-badge.absent {
            background-color: #f8d7da;
            color: #842029;
        }

        .status-badge.leave {
            background-color: #cff4fc;
            color: #055160;
        }

        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-name {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-name i {
            background-color: var(--primary-light);
            color: var(--primary);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .weekly-offs-info {
            font-size: 13px;
            color: var(--text-muted);
            background-color: var(--light);
            padding: 5px 10px;
            border-radius: 4px;
        }

        .table-wrapper {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 4px 12px var(--shadow);
            margin-bottom: 30px;
        }

        .summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
        }

        .summary-table th,
        .summary-table td {
            padding: 15px;
            text-align: left;
        }

        .summary-table th {
            background-color: var(--primary-light);
            color: var(--dark);
            font-weight: 600;
            font-size: 14px;
        }

        .summary-table tr {
            transition: all 0.2s;
            border-bottom: 1px solid var(--border);
        }

        .summary-table tr:hover {
            background-color: var(--primary-light);
        }

        .summary-table tbody tr:last-child {
            border-bottom: none;
        }

        .alert {
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
            background-color: #cff4fc;
            color: #055160;
            border-left: 4px solid #0aa2c0;
        }

        .alert-info {
            background-color: #cff4fc;
            color: #055160;
        }

        @media (max-width: 1024px) {
            .row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .filters-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .row {
                gap: 15px;
                margin-bottom: 15px;
            }
        }

        @media (max-width: 1200px) {
            .summary-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 992px) {
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h2><i class="fas fa-calendar-check"></i> Attendance Report</h2>
        </div>

        <div class="filters-container">
            <form method="GET">
                <div class="filters-grid">
                    <div class="filter-item">
                        <label for="month">Select Month</label>
                        <input type="month" id="month" name="month" class="form-control" 
                               value="<?php echo htmlspecialchars($month); ?>">
                    </div>
                    
                    <div class="filter-item">
                        <label for="user_filter">Select Employee</label>
                        <select name="user_id" id="user_filter" class="form-control">
                            <?php if ($is_hr): ?>
                                <option value="all" <?php echo $user_id === 'all' ? 'selected' : ''; ?>>All Employees</option>
                            <?php endif; ?>
                            
                            <?php foreach ($all_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                    <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username'] . ' (' . $user['unique_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-item button-container">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Attendance Summary</h3>
            </div>
            <div class="card-body">
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="tooltip">
                            Total hours worked by all employees during the selected period. This represents the overall work volume.
                        </div>
                        <div class="icon"><i class="fas fa-clock"></i></div>
                        <h3>Total Working Hours</h3>
                        <p><?php echo number_format($total_working_hours, 2); ?></p>
                    </div>
                    <div class="summary-item overtime">
                        <div class="tooltip">
                            Total overtime hours (>1.5h beyond shift end). Value: <?php echo formatDecimalToTime($total_overtime); ?><br>
                            Percentage of total hours: <?php echo $total_working_hours > 0 ? number_format((convertTimeToDecimal($total_overtime) / $total_working_hours) * 100, 1) : 0; ?>%
                        </div>
                        <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                        <h3>Total Overtime</h3>
                        <p class="overtime"><?php echo formatDecimalToTime($total_overtime); ?></p>
                    </div>
                    <div class="summary-item">
                        <div class="tooltip">
                            <?php if ($user_id !== 'all'): ?>
                                Total days marked as present out of <?php echo $working_days; ?> working days.<br>
                                Percentage: <?php echo number_format($attendance_rate, 1); ?>%
                            <?php else: ?>
                                Average number of days each employee was present during the month.<br>
                                Total present entries: <?php echo count($present_days); ?>
                            <?php endif; ?>
                        </div>
                        <div class="icon"><i class="fas fa-calendar-check"></i></div>
                        <h3><?php echo $days_present_label; ?></h3>
                        <p><?php echo $present_days_count; ?></p>
                    </div>
                    <div class="summary-item">
                        <div class="tooltip">
                            Attendance rate based on working days in the month.<br>
                            Formula: (Days Present / <?php echo $working_days; ?> Working Days) × 100
                        </div>
                        <div class="icon"><i class="fas fa-percentage"></i></div>
                        <h3>Attendance Rate</h3>
                        <p><?php echo number_format($attendance_rate, 1); ?>%</p>
                    </div>
                    
                    <div class="summary-item">
                        <div class="tooltip">
                            Number of times employees arrived more than 15 minutes after their shift start time.<br>
                            Rate: <?php echo count($records) > 0 ? number_format(($late_arrivals / count($records)) * 100, 1) : 0; ?>% of all punch-ins
                        </div>
                        <div class="icon"><i class="fas fa-user-clock"></i></div>
                        <h3>Late Arrivals</h3>
                        <p><?php echo $late_arrivals; ?></p>
                    </div>
                    <div class="summary-item">
                        <div class="tooltip">
                            Number of times employees left more than 15 minutes before their shift end time.<br>
                            Rate: <?php echo count($records) > 0 ? number_format(($early_departures / count($records)) * 100, 1) : 0; ?>% of all punch-outs
                        </div>
                        <div class="icon"><i class="fas fa-sign-out-alt"></i></div>
                        <h3>Early Departures</h3>
                        <p><?php echo $early_departures; ?></p>
                    </div>
                    <div class="summary-item">
                        <div class="tooltip">
                            Number of weekly off days on which employees worked.<br>
                            These are days worked outside of regular schedule.
                        </div>
                        <div class="icon"><i class="fas fa-moon"></i></div>
                        <h3>Weekly Offs Worked</h3>
                        <p><?php echo $weekly_off_worked_count; ?></p>
                    </div>
                    <div class="summary-item">
                        <div class="tooltip">
                            Total hours worked during weekly offs or weekends.<br>
                            Percentage of total: <?php echo $total_working_hours > 0 ? number_format(($weekend_hours / $total_working_hours) * 100, 1) : 0; ?>%
                        </div>
                        <div class="icon"><i class="fas fa-briefcase"></i></div>
                        <h3>Weekend Hours</h3>
                        <p><?php echo number_format($weekend_hours, 2); ?></p>
                    </div>
                    
                    <div class="summary-item">
                        <div class="tooltip">
                            Total number of calendar days in the selected month.<br>
                            Including working days and weekends.
                        </div>
                        <div class="icon"><i class="fas fa-calendar-day"></i></div>
                        <h3>Days in Month</h3>
                        <p><?php echo $days_in_month; ?></p>
                    </div>
                    <div class="summary-item">
                        <div class="tooltip">
                            Number of business days in the month, excluding weekends.<br>
                            Weekends count: <?php echo $weekends; ?> days
                        </div>
                        <div class="icon"><i class="fas fa-business-time"></i></div>
                        <h3>Working Days</h3>
                        <p><?php echo $working_days; ?></p>
                    </div>
                    <div class="summary-item">
                        <div class="tooltip">
                            Average number of hours worked per day when present.<br>
                            Formula: Total Hours / Days Present
                        </div>
                        <div class="icon"><i class="fas fa-calculator"></i></div>
                        <h3>Avg. Hours/Day</h3>
                        <p><?php echo number_format($avg_hours_per_day, 2); ?></p>
                    </div>
                    <div class="summary-item">
                        <div class="tooltip">
                            Maximum hours worked in a single day: <?php echo number_format($max_hours_day['hours'], 2); ?> hours<br>
                            Date: <?php echo date('d M Y', strtotime($max_hours_day['date'])); ?>
                        </div>
                        <div class="icon"><i class="fas fa-award"></i></div>
                        <h3>Max Hours Day</h3>
                        <p><?php echo number_format($max_hours_day['hours'], 2); ?></p>
                    </div>
                    
                    <?php if ($user_id === 'all'): ?>
                    <div class="summary-item">
                        <div class="tooltip">
                            Total number of employees with attendance records in the selected period.<br>
                            Active employees in system: <?php echo count($all_users); ?>
                        </div>
                        <div class="icon"><i class="fas fa-users"></i></div>
                        <h3>Total Employees</h3>
                        <p><?php echo $employee_count; ?></p>
                    </div>
                    <div class="summary-item">
                        <div class="tooltip">
                            Number of employees with 100% attendance during working days.<br>
                            Percentage: <?php echo $employee_count > 0 ? number_format(($full_attendance_users / $employee_count) * 100, 1) : 0; ?>% of total employees
                        </div>
                        <div class="icon"><i class="fas fa-trophy"></i></div>
                        <h3>100% Attendance</h3>
                        <p><?php echo $full_attendance_users; ?></p>
                    </div>
                    <div class="summary-item">
                        <div class="tooltip">
                            Number of employees with no attendance records this month.<br>
                            Percentage: <?php echo $employee_count > 0 ? number_format(($zero_attendance_users / $employee_count) * 100, 1) : 0; ?>% of total employees
                        </div>
                        <div class="icon"><i class="fas fa-user-slash"></i></div>
                        <h3>Zero Attendance</h3>
                        <p><?php echo $zero_attendance_users; ?></p>
                    </div>
                    <?php else: ?>
                    <div class="summary-item">
                        <div class="tooltip">
                            Number of instances where an employee punched in but did not punch out.<br>
                            These may need to be manually adjusted by HR.
                        </div>
                        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <h3>Missing Punch-Outs</h3>
                        <p><?php echo $no_punch_out_count; ?></p>
                    </div>
                    <?php endif; ?>
                    <div class="summary-item">
                        <div class="tooltip">
                            Day of the week with the highest attendance count.<br>
                            Count for <?php echo $most_active_day; ?>: <?php echo isset($attendance_by_day[$most_active_day]) ? $attendance_by_day[$most_active_day] : 0; ?> entries
                        </div>
                        <div class="icon"><i class="fas fa-star"></i></div>
                        <h3>Most Active Day</h3>
                        <p><?php echo $most_active_day; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Working Hours Trend</h3>
                </div>
                <div class="card-body chart-container">
                    <canvas id="workingHoursChart"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Overtime Distribution</h3>
                </div>
                <div class="card-body chart-container">
                    <canvas id="overtimePieChart"></canvas>
                </div>
            </div>
        </div>

        <?php if ($show_all): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-building"></i> Department-wise Summary</h3>
                </div>
                <div class="card-body table-wrapper">
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Total Employees</th>
                                <th>Total Working Hours</th>
                                <th>Total Overtime</th>
                                <th>Average Working Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $dept_summary = [];
                            foreach ($records as $record) {
                                $dept = $record['department'] ?? 'Unassigned';
                                if (!isset($dept_summary[$dept])) {
                                    $dept_summary[$dept] = [
                                        'employees' => [],
                                        'working_hours' => 0,
                                        'overtime' => 0
                                    ];
                                }
                                $dept_summary[$dept]['employees'][$record['user_id']] = true;
                                
                                // Convert working hours if needed
                                $working_hours = is_numeric($record['working_hours']) ? 
                                    $record['working_hours'] : 
                                    convertTimeToDecimal($record['working_hours']);
                                
                                // Calculate valid overtime
                                $shift_end_datetime = $record['date'] . ' ' . $record['shift_end'];
                                $valid_overtime = calculateValidOvertime(
                                    $record['overtime_hours'],
                                    $record['punch_in'],
                                    $record['punch_out'],
                                    $shift_end_datetime
                                );
                                $overtime = is_numeric($valid_overtime) ? 
                                    $valid_overtime : 
                                    convertTimeToDecimal($valid_overtime);
                                
                                $dept_summary[$dept]['working_hours'] += $working_hours;
                                $dept_summary[$dept]['overtime'] += $overtime;
                            }

                            foreach ($dept_summary as $dept => $summary):
                                $emp_count = count($summary['employees']);
                                $avg_hours = $emp_count > 0 ? $summary['working_hours'] / $emp_count : 0;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dept); ?></td>
                                    <td><?php echo $emp_count; ?></td>
                                    <td><?php echo number_format($summary['working_hours'], 2); ?></td>
                                    <td class="overtime"><?php echo number_format($summary['overtime'], 2); ?></td>
                                    <td><?php echo number_format($avg_hours, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Attendance Details</h3>
            </div>
            <div class="card-body table-wrapper">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Shift</th>
                            <th>Weekly Off Status</th>
                            <th>Punch In</th>
                            <th>Punch Out</th>
                            <th>Working Hours</th>
                            <th>Overtime</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $current_user = null;
                        foreach ($records as $record):
                            // Get weekly offs for this specific date
                            $weekly_offs = getWeeklyOffsForDate($pdo, $record['user_id'], $record['date']);
                            $is_weekly_off = isWeeklyOff($record['date'], $weekly_offs);
                            
                            if ($show_all && $current_user !== $record['username']):
                                $current_user = $record['username'];
                        ?>
                                <tr class="user-row">
                                    <td colspan="9">
                                        <div class="user-header">
                                            <span class="user-name">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($record['username'] . ' (' . $record['unique_id'] . ')'); ?>
                                            </span>
                                            <span class="weekly-offs-info">
                                                <i class="fas fa-calendar-minus"></i> Weekly Offs: 
                                                <?php 
                                                $current_weekly_offs = getWeeklyOffsForDate($pdo, $record['user_id'], date('Y-m-d'));
                                                echo !empty($current_weekly_offs) 
                                                    ? htmlspecialchars(implode(', ', explode(',', $current_weekly_offs))) 
                                                    : 'Not Set';
                                                ?>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr class="<?php echo $is_weekly_off ? 'weekly-off-row' : ''; ?>">
                                <td><?php echo date('d M Y (D)', strtotime($record['date'])); ?></td>
                                <td><?php echo htmlspecialchars($record['username']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($record['shift_name']); ?>
                                    <br>
                                    <small>
                                        <?php 
                                        if ($record['shift_start'] && $record['shift_end']) {
                                            echo date('h:i A', strtotime($record['shift_start'])) . ' - ' . 
                                                 date('h:i A', strtotime($record['shift_end']));
                                        } else {
                                            echo 'No Shift';
                                        }
                                        ?>
                                    </small>
                                </td>
                                <td class="weekly-off-status">
                                    <?php if ($is_weekly_off): ?>
                                        <span class="badge weekly-off"><i class="fas fa-moon"></i> Weekly Off</span>
                                        <?php if ($record['punch_in']): ?>
                                            <span class="badge worked"><i class="fas fa-briefcase"></i> Worked</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge working-day"><i class="fas fa-sun"></i> Working Day</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $record['punch_in'] ? date('h:i A', strtotime($record['punch_in'])) : '-'; ?></td>
                                <td><?php echo $record['punch_out'] ? date('h:i A', strtotime($record['punch_out'])) : '-'; ?></td>
                                <td><?php echo formatHoursAndMinutes($record['working_hours']); ?></td>
                                <td><?php 
                                    // Get the shift end time for this record's date
                                    $shift_end_datetime = $record['date'] . ' ' . $record['shift_end'];
                                    $valid_overtime = calculateValidOvertime(
                                        $record['overtime_hours'],
                                        $record['punch_in'],
                                        $record['punch_out'],
                                        $shift_end_datetime
                                    );
                                    echo $valid_overtime !== '00:00:00' ? formatDecimalToTime($valid_overtime) : '-';
                                ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($record['status']); ?>">
                                        <?php echo htmlspecialchars($record['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (empty($records)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No attendance records found for the selected period.
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.querySelector('.filters-container form');
            const filterInputs = filterForm.querySelectorAll('select, input');

            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    filterForm.submit();
                });
            });
        });
    </script>

    <script>
    // Prepare data for charts
    <?php
        // Data for Working Hours Line Chart
        $dates = [];
        $workingHours = [];
        $overtimeHours = [];
        
        // Create a reversed copy of records for chronological order
        $chronological_records = array_reverse($records);
        
        foreach ($chronological_records as $record) {
            $dates[] = date('d M', strtotime($record['date']));
            $workingHours[] = is_numeric($record['working_hours']) ? 
                $record['working_hours'] : 
                convertTimeToDecimal($record['working_hours']);
            
            // Calculate overtime using shift end time
            $shift_end_datetime = $record['date'] . ' ' . $record['shift_end'];
            $valid_overtime = calculateValidOvertime(
                $record['overtime_hours'],
                $record['punch_in'],
                $record['punch_out'],
                $shift_end_datetime
            );
            $overtimeHours[] = convertTimeToDecimal($valid_overtime);
        }
        
        // Data for Overtime Pie Chart
        $overtime_distribution = [
            'No Overtime' => 0,
            '1.5-2 Hours' => 0,
            '2-3 Hours' => 0,
            '3+ Hours' => 0
        ];
        
        foreach ($records as $record) {
            // Calculate overtime using shift end time
            $shift_end_datetime = $record['date'] . ' ' . $record['shift_end'];
            $valid_overtime = calculateValidOvertime(
                $record['overtime_hours'],
                $record['punch_in'],
                $record['punch_out'],
                $shift_end_datetime
            );
            $overtime = convertTimeToDecimal($valid_overtime);
            
            if ($overtime < 1.5) {
                $overtime_distribution['No Overtime']++;
            } elseif ($overtime <= 2) {
                $overtime_distribution['1.5-2 Hours']++;
            } elseif ($overtime <= 3) {
                $overtime_distribution['2-3 Hours']++;
            } else {
                $overtime_distribution['3+ Hours']++;
            }
        }
    ?>

    // Working Hours Line Chart
    const workingHoursChart = new Chart(
        document.getElementById('workingHoursChart'),
        {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [
                    {
                        label: 'Working Hours',
                        data: <?php echo json_encode($workingHours); ?>,
                        borderColor: '#007bff',
                        tension: 0.1
                    },
                    {
                        label: 'Overtime Hours',
                        data: <?php echo json_encode($overtimeHours); ?>,
                        borderColor: '#dc3545',
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,  // Allow chart to fill container
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Hours'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Daily Working Hours and Overtime Trend'
                    }
                }
            }
        }
    );

    // Overtime Distribution Pie Chart
    const overtimePieChart = new Chart(
        document.getElementById('overtimePieChart'),
        {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($overtime_distribution)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($overtime_distribution)); ?>,
                    backgroundColor: [
                        '#6c757d',
                        '#28a745',
                        '#ffc107',
                        '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,  // Allow chart to fill container
                plugins: {
                    title: {
                        display: true,
                        text: 'Overtime Distribution'
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        }
    );
    </script>
</body>
</html>         