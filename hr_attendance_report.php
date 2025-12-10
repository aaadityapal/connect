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
        $stmt = $pdo->prepare("SELECT id, username, unique_id FROM users WHERE 
        deleted_at IS NULL AND 
        (status = 'active' OR 
         (status = 'inactive' AND 
          DATE_FORMAT(status_changed_date, '%Y-%m') >= :month))
        ORDER BY username");
        $stmt->execute(['month' => $month]);
        $users = $stmt->fetchAll();
    }// Fetch all active users for the dropdown
$users_query = "SELECT id, username, unique_id FROM users WHERE 
    deleted_at IS NULL AND 
    (status = 'active' OR 
     (status = 'inactive' AND 
      DATE_FORMAT(status_changed_date, '%Y-%m') >= :month))
    ORDER BY username";
$users_stmt = $pdo->prepare($users_query);
$users_stmt->execute(['month' => $month]);
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
        a.overtime_hours,
        a.punch_in_photo,
        a.punch_out_photo,
        a.address as punch_in_address,
        a.punch_out_address,
        a.work_report
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
    
    // Return all overtime regardless of duration
    return $overtime_hours;
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

// Add variables for Short Leaves adjustment
$total_late_arrivals = 0;
$adjusted_late_count = 0;
$short_leaves_used = 0;
$short_leaves_available = 2; // Default maximum short leaves per month

// Fetch approved Short Leaves for the selected month (simplified version)
if ($user_id !== 'all') {
    $leave_query = "
        SELECT COUNT(*) as leave_count
        FROM leave_request lr
        JOIN leave_types lt ON lr.leave_type = lt.id
        WHERE lr.user_id = :user_id
        AND lt.name = 'Short Leave'
        AND lr.status = 'approved'
        AND DATE_FORMAT(lr.start_date, '%Y-%m') = :month
    ";
    
    $leave_stmt = $pdo->prepare($leave_query);
    $leave_stmt->execute([
        'user_id' => $user_id,
        'month' => $month
    ]);
    $leave_result = $leave_stmt->fetch();
    $short_leaves_used = $leave_result ? $leave_result['leave_count'] : 0;
    $short_leaves_available = max(0, 2 - $short_leaves_used);
}

// Calculate working days considering user's weekly offs
$month_start = date('Y-m-01', strtotime($month));
$month_end = date('Y-m-t', strtotime($month));
$current_date = $month_start;
$weekends = 0;
$user_weekly_offs = 0;

// Default weekend calculation (Saturday and Sunday)
while (strtotime($current_date) <= strtotime($month_end)) {
    $day_of_week = date('N', strtotime($current_date));
    if ($day_of_week >= 6) { // 6 and 7 are weekend days (Saturday and Sunday)
        $weekends++;
    }
    $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
}

// For specific user, get their weekly offs and count occurrences in the month
if ($user_id !== 'all') {
    // Get weekly offs for the specific user
    $weekly_offs_query = "
        SELECT weekly_offs 
        FROM user_shifts 
        WHERE user_id = :user_id 
        AND effective_from <= :end_date
        AND (effective_to IS NULL OR effective_to >= :start_date)
        ORDER BY effective_from DESC 
        LIMIT 1
    ";
    
    $weekly_offs_stmt = $pdo->prepare($weekly_offs_query);
    $weekly_offs_stmt->execute([
        'user_id' => $user_id,
        'start_date' => $month_start,
        'end_date' => $month_end
    ]);
    
    $weekly_offs_result = $weekly_offs_stmt->fetch();
    $weekly_offs = $weekly_offs_result ? $weekly_offs_result['weekly_offs'] : '';
    
    if (!empty($weekly_offs)) {
        $weekly_offs_array = explode(',', $weekly_offs);
        
        // Reset and count user's weekly offs in this month
        $current_date = $month_start;
        $user_weekly_offs = 0;
        
        while (strtotime($current_date) <= strtotime($month_end)) {
            $day_name = date('l', strtotime($current_date));
            if (in_array($day_name, $weekly_offs_array)) {
                $user_weekly_offs++;
            }
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        // For a specific user, use their weekly offs instead of default weekends
        $working_days = $days_in_month - $user_weekly_offs;
    } else {
        // If no weekly offs defined, use the default weekend calculation
        $working_days = $days_in_month - $weekends;
    }
} else {
    // For all users view, use the default weekend calculation
    $working_days = $days_in_month - $weekends;
}

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
    // Calculate working hours consistently
    $working_hours_decimal = convertTimeToDecimal($record['working_hours']);
    $total_working_hours += $working_hours_decimal;
    
    // Calculate overtime consistently with the table display
    $overtime_minutes = 0;
    if ($record['punch_in'] && $record['punch_out'] && $record['shift_end']) {
        // Extract just the time portions
        $punch_out_time_parts = explode(':', date('H:i', strtotime($record['punch_out'])));
        $shift_end_time_parts = explode(':', date('H:i', strtotime($record['shift_end'])));
        
        // Convert to minutes since start of day
        $punch_out_minutes = (intval($punch_out_time_parts[0]) * 60) + intval($punch_out_time_parts[1]);
        $shift_end_minutes = (intval($shift_end_time_parts[0]) * 60) + intval($shift_end_time_parts[1]);
        
        // Calculate overtime in minutes
        $overtime_minutes = max(0, $punch_out_minutes - $shift_end_minutes);
        
        // Only count overtime if it's at least 90 minutes, and round to nearest half hour
        if ($overtime_minutes >= 90) {
            $hours = floor($overtime_minutes / 60);
            $remaining_minutes = $overtime_minutes % 60;
            
            // Round minutes down to 0 or 30
            if ($remaining_minutes < 30) {
                $overtime_minutes = $hours * 60;
            } else {
                $overtime_minutes = ($hours * 60) + 30;
            }
            
            // Add to total overtime (in hours)
            $total_overtime += $overtime_minutes / 60;
        }
    }
    
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
            $total_late_arrivals++;
            
            // Only count this as a late arrival if we don't have available short leaves
            // Short leave adjustment only applies when viewing a single user, not in the "all users" view
            if ($user_id !== 'all' && $short_leaves_available > 0) {
                // If short leaves are available, use one to cover this late arrival
                $short_leaves_available--;
                $adjusted_late_count++;
            }
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

// Calculate final late arrivals count (adjusted for short leaves)
$late_arrivals = $total_late_arrivals - $adjusted_late_count;

// Get the count of present days
if ($user_id !== 'all') {
    // Single user - show total days present
    $present_days_count = count($present_days);
    $days_present_label = 'Days Present';
    $employee_count = 1;
    // Cap at 100% to handle cases where present days could exceed working days
    $attendance_rate = min(100, ($present_days_count / max(1, $working_days)) * 100);
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
    // Cap at 100% to handle cases where present days could exceed working days
    $attendance_rate = min(100, ($present_days_count / max(1, $working_days)) * 100);
    
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
            --sidebar-width: 280px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: var(--text);
            background-color: #f5f8fa;
            padding: 0;
            margin: 0;
            overflow-x: hidden;
        }

        .container {
            max-width: 100%;
            padding: 20px;
            transition: all 0.3s ease;
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
            font-family: 'Inter', sans-serif;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

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

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary);
            background: rgba(79, 70, 229, 0.1);
        }

        .nav-link i {
            margin-right: 0.75rem;
        }

        /* Logout button styles */
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

        /* Update nav container to allow for margin-top: auto on logout */
        .sidebar nav {
            display: flex;
            flex-direction: column;
            height: calc(100% - 10px); /* Adjust based on your logo height */
        }

        .container {
            max-width: 100%;
            padding: 20px;
            transition: all 0.3s ease;
        }

        @media (max-width: 768px) {
            .container {
                margin-left: 0;
            }
        }

        /* Photo link styling */
        .photo-link {
            margin-left: 8px;
            display: inline-block;
            vertical-align: middle;
        }
        
        .photo-link .fas {
            color: #4361ee;
            font-size: 14px;
            transition: transform 0.2s ease;
        }
        
        .photo-link:hover .fas {
            transform: scale(1.2);
            color: #3f37c9;
        }
        
        /* Modal styling */
        .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            background-color: var(--primary-light);
            border-bottom: 1px solid var(--border);
            border-radius: 10px 10px 0 0;
            padding: 15px 20px;
        }
        
        .modal-footer {
            border-top: 1px solid var(--border);
            border-radius: 0 0 10px 10px;
            padding: 15px 20px;
            display: flex;
            justify-content: center;
        }
        
        #punchPhotoImage {
            max-height: 70vh;
            max-width: 100%;
            border-radius: 5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
        }
        
        .modal-body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        
        /* Ensure modal is centered properly */
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100% - 1rem);
            margin: 0 auto;
        }
        
        /* Fix modal position */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1050;
            display: none;
            overflow: hidden;
            outline: 0;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal.fade .modal-dialog {
            transform: translate(0, -50px);
            transition: transform 0.3s ease-out;
        }
        
        .modal.show .modal-dialog {
            transform: translate(0, 0);
        }
        
        .modal-title {
            font-weight: 600;
            color: var(--primary);
        }
        
        .btn-close {
            background-color: transparent;
            border: none;
            font-size: 1.5rem;
            padding: 0.5rem;
            cursor: pointer;
            opacity: 0.5;
            transition: opacity 0.2s;
            color: #dc3545;
        }
        
        .btn-close:hover {
            opacity: 1;
            color: #c82333;
        }
        
        .btn-close span {
            font-size: 1.75rem;
            line-height: 1;
            display: block;
        }
        
        @media (min-width: 576px) {
            .modal-dialog-centered {
                min-height: calc(100% - 3.5rem);
            }
            
            .modal-dialog {
                max-width: 700px;
                margin: 1.75rem auto;
            }
        }

        .btn-secondary {
            background-color: #4361ee;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            min-width: 120px;
        }
        
        .btn-secondary:hover {
            background-color: #3f37c9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .btn-secondary:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .modal-footer {
            border-top: 1px solid var(--border);
            border-radius: 0 0 10px 10px;
            padding: 15px 20px;
            display: flex;
            justify-content: center;
        }

        .mobile-close-btn {
            display: none;
            margin-top: 15px;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            border: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        @media (max-width: 576px) {
            .mobile-close-btn {
                display: block;
            }
            
            .modal-footer {
                display: none;
            }
        }

        /* Address column styling */
        .address-column {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .address-column:hover {
            white-space: normal;
            overflow: visible;
            position: relative;
            z-index: 1;
            background-color: #f8f9fa;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 5px;
            border-radius: 4px;
            max-width: 300px;
        }
        
        /* Excel Export Button */
        .excel-export-link {
            display: inline-block;
            color: #1D6F42; /* Excel green color */
            transition: transform 0.2s ease;
        }
        
        .excel-export-link:hover {
            transform: scale(1.2);
            color: #2E8555;
        }
        
        /* Work Report Column Styling */
        .work-report-column {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .work-report-column:hover {
            white-space: normal;
            overflow: visible;
            position: relative;
            z-index: 1;
            background-color: #f8f9fa;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 5px;
            border-radius: 4px;
            max-width: 300px;
        }
        
        @media (max-width: 1200px) {
            .address-column, .work-report-column {
                max-width: 150px;
            }
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
            <!-- Added Logout Button -->
            <a href="logout.php" class="nav-link logout-link">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </nav>
    </div>

    <!-- Add this button after the sidebar div -->
    <button class="toggle-sidebar" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>

    <div class="main-content" id="mainContent">
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
                            <p>
                            <?php 
                                // Format working hours in HH:MM format
                                $working_hours = floor($total_working_hours);
                                $working_minutes = round(($total_working_hours - $working_hours) * 60);
                                echo sprintf("%02d:%02d", $working_hours, $working_minutes); 
                            ?>
                            </p>
                        </div>
                        <div class="summary-item overtime">
                            <div class="tooltip">
                                Total overtime hours (>1.5h beyond shift end).<br>
                                Only counts overtime of 1.5+ hours, rounded to nearest half hour.<br>
                                Percentage of total hours: <?php echo $total_working_hours > 0 ? number_format(($total_overtime / $total_working_hours) * 100, 1) : 0; ?>%
                            </div>
                            <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                            <h3>Total Overtime</h3>
                            <p class="overtime">
                            <?php 
                                // Format overtime hours in HH:MM format
                                $overtime_hours = floor($total_overtime);
                                $overtime_minutes = round(($total_overtime - $overtime_hours) * 60);
                                echo sprintf("%02d:%02d", $overtime_hours, $overtime_minutes); 
                            ?>
                            </p>
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
                                Formula: (Days Present / <?php echo $working_days; ?> Working Days) × 100<br>
                                Present days: <?php echo $present_days_count; ?><br>
                                Working days: <?php echo $working_days; ?>
                            </div>
                            <div class="icon"><i class="fas fa-percentage"></i></div>
                            <h3>Attendance Rate</h3>
                            <p><?php echo number_format($attendance_rate, 1) . '%'; ?></p>
                        </div>
                        
                        <div class="summary-item">
                            <div class="tooltip">
                                Number of times employees arrived more than 15 minutes after their shift start time.<br>
                                <?php if ($user_id !== 'all'): ?>
                                    Total late arrivals: <?php echo $total_late_arrivals; ?><br>
                                    Covered by short leaves: <?php echo $adjusted_late_count; ?><br>
                                    Short leaves used: <?php echo $short_leaves_used; ?>/2<br>
                                    Short leaves available: <?php echo $short_leaves_available; ?><br>
                                <?php endif; ?>
                                Rate: <?php echo count($records) > 0 ? number_format(($late_arrivals / count($records)) * 100, 1) : 0; ?>% of all punch-ins
                            </div>
                            <div class="icon"><i class="fas fa-user-clock"></i></div>
                            <h3>Late Arrivals</h3>
                            <p><?php echo $late_arrivals; ?></p>
                            <?php if ($user_id !== 'all' && $adjusted_late_count > 0): ?>
                                <small style="display: block; font-size: 0.6875rem; color: var(--text-muted);">
                                    <?php echo $total_late_arrivals; ?> total - <?php echo $adjusted_late_count; ?> covered = <?php echo $late_arrivals; ?> counted
                                </small>
                            <?php elseif ($user_id !== 'all' && $short_leaves_available > 0): ?>
                                <small style="display: block; font-size: 0.6875rem; color: var(--text-muted);">
                                    <?php echo $short_leaves_available; ?> Short Leave<?php echo $short_leaves_available > 1 ? 's' : ''; ?> available
                                </small>
                            <?php endif; ?>
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
                                <?php if ($user_id !== 'all' && !empty($weekly_offs)): ?>
                                    Personalized working days for this employee.<br>
                                    Total days: <?php echo $days_in_month; ?><br>
                                    Weekly offs: <?php echo implode(', ', $weekly_offs_array); ?><br>
                                    Weekly off days this month: <?php echo $user_weekly_offs; ?>
                                <?php else: ?>
                                    Number of business days in the month, excluding weekends.<br>
                                    Weekends count: <?php echo $weekends; ?> days
                                <?php endif; ?>
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
                            <p>
                            <?php 
                                // Format average hours in HH:MM format
                                $avg_hours = floor($avg_hours_per_day);
                                $avg_minutes = round(($avg_hours_per_day - $avg_hours) * 60);
                                echo sprintf("%02d:%02d", $avg_hours, $avg_minutes); 
                            ?>
                            </p>
                        </div>
                        <div class="summary-item">
                            <div class="tooltip">
                                Maximum hours worked in a single day: <?php 
                                    $max_hours = floor($max_hours_day['hours']);
                                    $max_minutes = round(($max_hours_day['hours'] - $max_hours) * 60);
                                    echo sprintf("%02d:%02d", $max_hours, $max_minutes);
                                ?> hours<br>
                                Date: <?php echo date('d M Y', strtotime($max_hours_day['date'])); ?>
                            </div>
                            <div class="icon"><i class="fas fa-award"></i></div>
                            <h3>Max Hours Day</h3>
                            <p>
                            <?php 
                                // Format max hours in HH:MM format
                                $max_hours = floor($max_hours_day['hours']);
                                $max_minutes = round(($max_hours_day['hours'] - $max_hours) * 60);
                                echo sprintf("%02d:%02d", $max_hours, $max_minutes); 
                            ?>
                            </p>
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
                                <th>Punch In Address</th>
                                <th>Punch Out</th>
                                <th>Punch Out Address</th>
                                <th>Working Hours</th>
                                <th>Overtime</th>
                                <th>
                                    Work Report
                                    <a href="#" id="exportExcel" class="excel-export-link ms-2" title="Export to Excel">
                                        <i class="fas fa-file-excel"></i>
                                    </a>
                                </th>
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
                                        <td colspan="11">
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
                                    <td><?php echo $record['punch_in'] ? date('h:i A', strtotime($record['punch_in'])) : '-'; ?>
                                        <?php if ($record['punch_in'] && !empty($record['punch_in_photo'])): ?>
                                            <a href="#" class="photo-link" data-bs-toggle="modal" data-bs-target="#punchPhotoModal" 
                                               data-photo="<?php echo htmlspecialchars($record['punch_in_photo']); ?>" 
                                               data-title="Punch In Photo - <?php echo htmlspecialchars($record['username']); ?> (<?php echo date('d M Y h:i A', strtotime($record['punch_in'])); ?>)">
                                                <i class="fas fa-folder text-primary ml-2"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="address-column" title="<?php echo htmlspecialchars($record['punch_in_address']); ?>">
                                        <?php echo $record['punch_in'] ? htmlspecialchars($record['punch_in_address']) : '-'; ?>
                                    </td>
                                    <td><?php echo $record['punch_out'] ? date('h:i A', strtotime($record['punch_out'])) : '-'; ?>
                                        <?php if ($record['punch_out'] && !empty($record['punch_out_photo'])): ?>
                                            <a href="#" class="photo-link" data-bs-toggle="modal" data-bs-target="#punchPhotoModal" 
                                               data-photo="<?php echo htmlspecialchars($record['punch_out_photo']); ?>" 
                                               data-title="Punch Out Photo - <?php echo htmlspecialchars($record['username']); ?> (<?php echo date('d M Y h:i A', strtotime($record['punch_out'])); ?>)">
                                                <i class="fas fa-folder text-primary ml-2"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="address-column" title="<?php echo htmlspecialchars($record['punch_out_address']); ?>">
                                        <?php echo $record['punch_out'] ? htmlspecialchars($record['punch_out_address']) : '-'; ?>
                                    </td>
                                    <td><?php 
                                        if (!empty($record['working_hours'])) {
                                            // Convert decimal hours to hours and minutes
                                            $decimal_hours = convertTimeToDecimal($record['working_hours']);
                                            $hours = floor($decimal_hours);
                                            $minutes = round(($decimal_hours - $hours) * 60);
                                            echo sprintf("%02d:%02d", $hours, $minutes);
                                        } else {
                                            echo '-';
                                        }
                                    ?></td>
                                    <td><?php 
                                        // Calculate overtime directly from punch times and shift end
                                        if ($record['punch_in'] && $record['punch_out'] && $record['shift_end']) {
                                            // Extract just the time portions without the date
                                            $punch_out_time_parts = explode(':', date('H:i', strtotime($record['punch_out'])));
                                            $shift_end_time_parts = explode(':', date('H:i', strtotime($record['shift_end'])));
                                            
                                            // Convert to minutes since start of day
                                            $punch_out_minutes = (intval($punch_out_time_parts[0]) * 60) + intval($punch_out_time_parts[1]);
                                            $shift_end_minutes = (intval($shift_end_time_parts[0]) * 60) + intval($shift_end_time_parts[1]);
                                            
                                            // Calculate overtime in minutes
                                            $overtime_minutes = max(0, $punch_out_minutes - $shift_end_minutes);
                                            
                                            // Only show if overtime is at least 1 hour and 30 minutes (90 minutes)
                                            if ($overtime_minutes >= 90) {
                                                // Round down to nearest half hour
                                                $hours = floor($overtime_minutes / 60);
                                                $remaining_minutes = $overtime_minutes % 60;
                                                
                                                // Round minutes down to 0 or 30
                                                if ($remaining_minutes < 30) {
                                                    $minutes = 0;
                                                } else {
                                                    $minutes = 30;
                                                }
                                                
                                                echo sprintf("%02d:%02d", $hours, $minutes);
                                            } else {
                                                echo '-';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                    ?></td>
                                    <td class="work-report-column" title="<?php echo htmlspecialchars($record['work_report']); ?>">
                                        <?php echo !empty($record['work_report']) ? htmlspecialchars($record['work_report']) : '-'; ?>
                                    </td>
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
            
            // Add Excel export functionality
            const exportButton = document.getElementById('exportExcel');
            if (exportButton) {
                exportButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Get current month and user_id from the form
                    const month = document.getElementById('month').value;
                    const userFilter = document.getElementById('user_filter');
                    const user_id = userFilter ? userFilter.value : 'all';
                    
                    // Get the clicked element's ID to determine which export to run
                    if (e.currentTarget.id === 'exportExcel') {
                        // Create export URL with parameters
                        const exportUrl = `export_work_report.php?month=${month}&user_id=${user_id}`;
                        
                        // Navigate to the export URL
                        window.location.href = exportUrl;
                    }
                });
            }
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
            
            // Convert working hours to hours and minutes format for consistency
            $decimal_hours = convertTimeToDecimal($record['working_hours']);
            $workingHours[] = $decimal_hours;
            
            // Calculate overtime using the same method as in the table display
            $overtime_minutes = 0;
            if ($record['punch_in'] && $record['punch_out'] && $record['shift_end']) {
                // Extract just the time portions
                $punch_out_time_parts = explode(':', date('H:i', strtotime($record['punch_out'])));
                $shift_end_time_parts = explode(':', date('H:i', strtotime($record['shift_end'])));
                
                // Convert to minutes since start of day
                $punch_out_minutes = (intval($punch_out_time_parts[0]) * 60) + intval($punch_out_time_parts[1]);
                $shift_end_minutes = (intval($shift_end_time_parts[0]) * 60) + intval($shift_end_time_parts[1]);
                
                // Calculate overtime in minutes
                $overtime_minutes = max(0, $punch_out_minutes - $shift_end_minutes);
                
                // Only count overtime if it's at least 90 minutes
                if ($overtime_minutes < 90) {
                    $overtime_minutes = 0;
                } else {
                    // Round down to nearest half hour
                    $hours = floor($overtime_minutes / 60);
                    $remaining_minutes = $overtime_minutes % 60;
                    
                    // Round minutes down to 0 or 30
                    if ($remaining_minutes < 30) {
                        $overtime_minutes = $hours * 60;
                    } else {
                        $overtime_minutes = ($hours * 60) + 30;
                    }
                }
            }
            
            // Convert minutes to hours for the chart
            $overtimeHours[] = $overtime_minutes / 60;
        }
        
        // Data for Overtime Pie Chart
        $overtime_distribution = [
            'No Overtime' => 0,
            '1.5-2 Hours' => 0,
            '2-3 Hours' => 0,
            '3+ Hours' => 0
        ];
        
        foreach ($records as $record) {
            // Calculate overtime using the same method as in the table display
            $overtime_minutes = 0;
            if ($record['punch_in'] && $record['punch_out'] && $record['shift_end']) {
                // Extract just the time portions
                $punch_out_time_parts = explode(':', date('H:i', strtotime($record['punch_out'])));
                $shift_end_time_parts = explode(':', date('H:i', strtotime($record['shift_end'])));
                
                // Convert to minutes since start of day
                $punch_out_minutes = (intval($punch_out_time_parts[0]) * 60) + intval($punch_out_time_parts[1]);
                $shift_end_minutes = (intval($shift_end_time_parts[0]) * 60) + intval($shift_end_time_parts[1]);
                
                // Calculate overtime in minutes
                $overtime_minutes = max(0, $punch_out_minutes - $shift_end_minutes);
            }
            
            // Convert to hours for categorization
            $overtime_hours = $overtime_minutes / 60;
            
            if ($overtime_hours < 1.5) {
                $overtime_distribution['No Overtime']++;
            } elseif ($overtime_hours <= 2) {
                $overtime_distribution['1.5-2 Hours']++;
            } elseif ($overtime_hours <= 3) {
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                sidebarToggle.classList.toggle('collapsed');
                
                // Change icon direction based on sidebar state
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
            
            // For mobile: click outside to close expanded sidebar
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

    <!-- Punch Photo Modal -->
    <div class="modal fade" id="punchPhotoModal" tabindex="-1" aria-labelledby="punchPhotoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="punchPhotoModalLabel">Punch Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <img id="punchPhotoImage" src="" class="img-fluid" alt="Punch Photo">
                    <div class="mobile-close-btn">
                        <button type="button" class="btn btn-danger mt-3" data-bs-dismiss="modal">
                            Close
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times-circle mr-2"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize photo modal functionality
            const photoLinks = document.querySelectorAll('.photo-link');
            
            // Check if Bootstrap 5 is being used
            const isBootstrap5 = typeof bootstrap !== 'undefined';
            
            photoLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const photoUrl = this.getAttribute('data-photo');
                    const photoTitle = this.getAttribute('data-title');
                    
                    // Set modal content
                    document.getElementById('punchPhotoModalLabel').textContent = photoTitle;
                    document.getElementById('punchPhotoImage').src = photoUrl;
                    
                    // Show modal using appropriate method
                    const modalElement = document.getElementById('punchPhotoModal');
                    if (isBootstrap5) {
                        const modal = new bootstrap.Modal(modalElement);
                        modal.show();
                    } else {
                        // Fallback for older Bootstrap or manual implementation
                        modalElement.classList.add('show');
                        modalElement.style.display = 'block';
                        document.body.classList.add('modal-open');
                        
                        // Add backdrop if it doesn't exist
                        let backdrop = document.querySelector('.modal-backdrop');
                        if (!backdrop) {
                            backdrop = document.createElement('div');
                            backdrop.className = 'modal-backdrop fade show';
                            document.body.appendChild(backdrop);
                        }
                    }
                });
            });
            
            // Close modal when close button is clicked
            const closeButtons = document.querySelectorAll('[data-bs-dismiss="modal"]');
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const modalElement = document.getElementById('punchPhotoModal');
                    if (isBootstrap5) {
                        const modalInstance = bootstrap.Modal.getInstance(modalElement);
                        if (modalInstance) modalInstance.hide();
                    } else {
                        // Fallback for older Bootstrap or manual implementation
                        modalElement.classList.remove('show');
                        modalElement.style.display = 'none';
                        document.body.classList.remove('modal-open');
                        
                        // Remove backdrop
                        const backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) backdrop.remove();
                    }
                });
            });
        });
    </script>
</body>
</html>         