<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/salary_export_errors.log');

// Start session and check authorization
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    die("Access denied. HR role required.");
}

// Include database connection
require_once '../config/db_connect.php';

// Get the filter month
$selected_filter_month = $_GET['filter_month'] ?? date('Y-m');

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="salary_analytics_' . $selected_filter_month . '.xls"');
header('Cache-Control: max-age=0');

// Simple HTML table format (Excel compatible)
$excel_content = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Salary Analytics Report - ' . date('F Y', strtotime($selected_filter_month)) . '</title>
    <style>
        body { font-family: Calibri, sans-serif; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; vertical-align: top; }
        th { background-color: #4472C4; color: white; font-weight: bold; text-align: center; }
        .title { font-size: 18px; font-weight: bold; text-align: center; margin: 20px 0; }
        .currency { text-align: right; }
        .positive { color: #008000; }
        .negative { color: #FF0000; }
        .center { text-align: center; }
        .right { text-align: right; }
        .header-group { background-color: #70AD47; }
    </style>
</head>
<body>
    <div class="title">Salary Analytics Report - ' . date('F Y', strtotime($selected_filter_month)) . '</div>
    <table>
        <thead>
            <tr>
                <th rowspan="2">Employee Name</th>
                <th rowspan="2">Employee ID</th>
                <th rowspan="2">Email</th>
                <th rowspan="2">Department</th>
                <th rowspan="2">Position</th>
                <th colspan="3" class="header-group">Salary Information</th>
                <th colspan="5" class="header-group">Attendance Details</th>
                <th colspan="5" class="header-group">Leave Details</th>
                <th colspan="4" class="header-group">Deductions</th>
                <th rowspan="2">Total Deductions (₹)</th>
            </tr>
            <tr>
                <th>Base Salary (₹)</th>
                <th>Incremented Salary (₹)</th>
                <th>Working Days</th>
                <th>Present Days</th>
                <th>Carried Forward</th>
                <th>Excess Days</th>
                <th>Late Punch In</th>
                <th>Leave Taken (Days)</th>
                <th>Leave Types</th>
                <th>Short Leave Days</th>
                <th>Half Day Leave</th>
                <th>Casual Leave</th>
                <th>Leave Deduction (₹)</th>
                <th>1 Hour Late (Days)</th>
                <th>1 Hour Late Deduction (₹)</th>
                <th>4th Saturday Penalty (₹)</th>
                <th>Late Deduction (₹)</th>
                <th>Final Monthly Salary (₹)</th>
            </tr>
        </thead>
        <tbody>';

// Fetch detailed user data with all calculations (same logic as dashboard)
$users_table_query = "SELECT 
    u.id,
    u.username,
    u.position,
    u.email,
    u.employee_id,
    u.department,
    u.unique_id,
    u.base_salary,
    CASE 
        WHEN si.salary_after_increment IS NOT NULL 
        THEN si.salary_after_increment 
        ELSE u.base_salary 
    END as current_salary,
    -- Add attendance data for the selected month
    COALESCE(att.present_days, 0) as present_days,
    COALESCE(att.late_days, 0) as late_days
FROM users u 
LEFT JOIN (
    SELECT si1.* 
    FROM salary_increments si1
    INNER JOIN (
        SELECT user_id, MAX(effective_from) as latest_date
        FROM salary_increments
        WHERE status != 'cancelled'
        AND effective_from <= CURDATE()
        GROUP BY user_id
    ) si2 ON si1.user_id = si2.user_id AND si1.effective_from = si2.latest_date
    WHERE si1.status != 'cancelled'
) si ON u.id = si.user_id
LEFT JOIN (
    SELECT 
        a.user_id,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
        COUNT(CASE WHEN a.status = 'present' 
                   AND TIMESTAMPDIFF(MINUTE, 
                       CONCAT(DATE(a.date), ' ', '09:00:00'), 
                       CONCAT(DATE(a.date), ' ', TIME(a.punch_in))
                   ) > 15 THEN 1 END) as late_days
    FROM attendance a
    WHERE DATE_FORMAT(a.date, '%Y-%m') = ?
    GROUP BY a.user_id
) att ON u.id = att.user_id
WHERE u.status = 'active' AND u.deleted_at IS NULL 
ORDER BY u.username ASC";

try {
    $stmt = $pdo->prepare($users_table_query);
    $stmt->execute([$selected_filter_month]);
    $active_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add detailed calculations for each user (same logic as dashboard)
    foreach ($active_users as &$user) {
        // Calculate working days for each user based on their weekly offs
        $working_days = 0;
        $weekly_offs = []; // Default empty array
        
        // Get the selected month's start and end dates
        $month_start = date('Y-m-01', strtotime($selected_filter_month));
        $month_end = date('Y-m-t', strtotime($selected_filter_month));
        
        // Fetch office holidays for the selected month - check if table exists first
        $holidays_query = "SELECT DATE(holiday_date) as holiday_date FROM office_holidays 
                          WHERE DATE(holiday_date) BETWEEN ? AND ?";
        try {
            // Check if office_holidays table exists
            $table_check = "SHOW TABLES LIKE 'office_holidays'";
            $table_result = $pdo->query($table_check);
            
            if ($table_result->rowCount() > 0) {
                $holidays_stmt = $pdo->prepare($holidays_query);
                $holidays_stmt->execute([$month_start, $month_end]);
                $holidays_result = $holidays_stmt->fetchAll(PDO::FETCH_COLUMN);
                $office_holidays = array_flip($holidays_result);
            } else {
                // Table doesn't exist, no holidays
                $office_holidays = [];
            }
        } catch (PDOException $e) {
            error_log("Error fetching office holidays: " . $e->getMessage());
            $office_holidays = []; // Safe fallback
        }
        
        // Loop through each day of the month
        $current_date = new DateTime($month_start);
        $end_date = new DateTime($month_end);
        
        while ($current_date <= $end_date) {
            $day_of_week = $current_date->format('l'); // Get day name (Monday, Tuesday, etc.)
            $current_date_str = $current_date->format('Y-m-d');
            
            // If the day is not a weekly off and not an office holiday, increment the working days counter
            if (!in_array($day_of_week, $weekly_offs) && !isset($office_holidays[$current_date_str])) {
                $working_days++;
            }
            
            // Move to the next day
            $current_date->modify('+1 day');
        }
        
        $user['working_days'] = $working_days;
        
        // Adjust working days for partial days (half days, etc.)
        // If user has half day deductions, reduce working days by 0.5 for each half day
        $half_day_adjustments = 0;
        
        // Check for half day leaves that reduce working days
        $half_day_leave_query = "SELECT 
                                COALESCE(SUM(
                                    CASE 
                                        WHEN lr.duration_type = 'half_day' THEN 0.5
                                        ELSE 0
                                    END), 0) as half_day_count
                                FROM leave_request lr
                                LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                                WHERE lr.user_id = ? 
                                AND lr.status = 'approved'
                                AND (
                                    (lr.start_date BETWEEN ? AND ?) 
                                    OR 
                                    (lr.end_date BETWEEN ? AND ?)
                                    OR 
                                    (lr.start_date <= ? AND lr.end_date >= ?)
                                )";
        $half_day_stmt = $pdo->prepare($half_day_leave_query);
        $half_day_stmt->execute([
            $user['id'], 
            $month_start, $month_end, 
            $month_start, $month_end,
            $month_start, $month_end
        ]);
        $half_day_result = $half_day_stmt->fetch(PDO::FETCH_ASSOC);
        $half_day_adjustments = $half_day_result['half_day_count'] ?? 0;
        
        // Calculate adjusted working days
        $adjusted_working_days = $working_days - ($half_day_adjustments * 0.5);
        $user['adjusted_working_days'] = $adjusted_working_days;
        
        // Calculate adjusted present days (add back the 0.5 for each half day taken)
        // If user took 1 half day, they were still present for 0.5 of that day
        $adjusted_present_days = $user['present_days'] + ($half_day_adjustments * 0.5);
        $user['adjusted_present_days'] = $adjusted_present_days;
        
        // Calculate short leave days for the user in the selected month
        $short_leave_query = "SELECT COALESCE(SUM(
                                CASE 
                                    WHEN lr.duration_type = 'half_day' THEN 0.5 
                                    ELSE 
                                       LEAST(DATEDIFF(
                                           LEAST(lr.end_date, ?), 
                                           GREATEST(lr.start_date, ?)
                                       ) + 1, 
                                       DATEDIFF(?, ?) + 1)
                                END), 0) as short_leave_days
                             FROM leave_request lr
                             LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                             WHERE lr.user_id = ? 
                             AND lr.status = 'approved'
                             AND lt.name = 'Short Leave'
                             AND (
                                 (lr.start_date BETWEEN ? AND ?) 
                                 OR 
                                 (lr.end_date BETWEEN ? AND ?)
                                 OR 
                                 (lr.start_date <= ? AND lr.end_date >= ?)
                             )";
        $short_leave_stmt = $pdo->prepare($short_leave_query);
        $short_leave_stmt->execute([
            $month_end, $month_start, $month_end, $month_start, // For LEAST/GREATEST and DATEDIFF
            $user['id'], 
            $month_start, $month_end, 
            $month_start, $month_end,
            $month_start, $month_end
        ]);
        $short_leave_result = $short_leave_stmt->fetch(PDO::FETCH_ASSOC);
        $short_leave_days = $short_leave_result['short_leave_days'] ?? 0;
        
        // Calculate excess days (when present days exceed working days)
        $excess_days = max(0, $user['present_days'] - $working_days);
        $user['excess_days'] = $excess_days;
        
        // Get previous month's carried forward excess days
        $prev_month = date('Y-m', strtotime($selected_filter_month . ' -1 month'));
        $carried_forward_query = "SELECT excess_days_carried_forward FROM excess_days_carryover 
                                 WHERE user_id = ? AND month = ? ORDER BY created_at DESC LIMIT 1";
        try {
            $cf_stmt = $pdo->prepare($carried_forward_query);
            $cf_stmt->execute([$user['id'], $prev_month]);
            $cf_result = $cf_stmt->fetch(PDO::FETCH_ASSOC);
            $carried_forward_days = $cf_result['excess_days_carried_forward'] ?? 0;
        } catch (PDOException $e) {
            // Table might not exist, that's okay
            $carried_forward_days = 0;
        }
        
        $user['carried_forward_days'] = $carried_forward_days;
        
        // Calculate effective working days for late deduction (only count official working days + carried forward)
        $effective_working_days_for_late = $working_days + $carried_forward_days;
        
        // Only count late days that fall within effective working days (excluding excess days)
        $late_days_for_deduction = min($user['late_days'], $effective_working_days_for_late);
        
        // Calculate late deduction (excluding excess days from penalties)
        $max_short_leave_coverage = min($short_leave_days, 2); // Maximum 2 days can be covered
        $effective_late_days = max(0, $late_days_for_deduction - $max_short_leave_coverage);
        
        // Calculate deduction: every 3 late days = 0.5 day deduction
        $late_deduction_days = floor($effective_late_days / 3) * 0.5;
        
        // Calculate salary deduction amount
        $current_salary = $user['current_salary'] ?? $user['base_salary'] ?? 0;
        $daily_salary = $working_days > 0 ? ($current_salary / $working_days) : 0;
        $late_deduction_amount = $late_deduction_days * $daily_salary;
        
        $user['short_leave_days'] = $short_leave_days;
        $user['effective_late_days'] = $effective_late_days;
        $user['late_deduction_days'] = $late_deduction_days;
        $user['late_deduction_amount'] = $late_deduction_amount;
        
        // Calculate total leave taken for the user in the selected month
        $leave_taken_query = "SELECT 
                              SUM(CASE 
                                  WHEN lr.duration_type = 'half_day' THEN 0.5 
                                  WHEN lr.duration_type = 'full_day' THEN 
                                     LEAST(DATEDIFF(
                                         LEAST(lr.end_date, ?), 
                                         GREATEST(lr.start_date, ?)
                                     ) + 1, 
                                     DATEDIFF(?, ?) + 1)
                                  ELSE 1
                              END) as total_leave_days,
                              GROUP_CONCAT(DISTINCT lt.name SEPARATOR ', ') as leave_types_taken
                             FROM leave_request lr
                             LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                             WHERE lr.user_id = ? 
                             AND lr.status = 'approved'
                             AND (
                                 (lr.start_date BETWEEN ? AND ?) 
                                 OR 
                                 (lr.end_date BETWEEN ? AND ?)
                                 OR 
                                 (lr.start_date <= ? AND lr.end_date >= ?)
                             )
                             GROUP BY lr.user_id";
        $leave_taken_stmt = $pdo->prepare($leave_taken_query);
        $leave_taken_stmt->execute([
            $month_end, $month_start, $month_end, $month_start, // For LEAST/GREATEST and DATEDIFF
            $user['id'], 
            $month_start, $month_end, 
            $month_start, $month_end,
            $month_start, $month_end
        ]);
        $leave_taken_result = $leave_taken_stmt->fetch(PDO::FETCH_ASSOC);
        
        $user['total_leave_days'] = $leave_taken_result['total_leave_days'] ?? 0;
        $user['leave_types_taken'] = $leave_taken_result['leave_types_taken'] ?? 'None';
        
        // Calculate leave deduction based on leave types and specific limits
        // DIRECT APPROACH - Check exact leave type names first
        $leave_check_query = "SELECT lr.*, lt.name as leave_type_name 
                             FROM leave_request lr
                             LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                             WHERE lr.user_id = ? 
                             AND lr.status = 'approved'
                             AND (
                                 (lr.start_date BETWEEN ? AND ?) 
                                 OR 
                                 (lr.end_date BETWEEN ? AND ?)
                                 OR 
                                 (lr.start_date <= ? AND lr.end_date >= ?)
                             )";
        $leave_check_stmt = $pdo->prepare($leave_check_query);
        $leave_check_stmt->execute([
            $user['id'], 
            $month_start, $month_end,
            $month_start, $month_end,
            $month_start, $month_end
        ]);
        $user_leaves = $leave_check_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate deduction manually based on actual leave records
        $leave_deduction_days = 0;
        $half_day_count = 0;
        $short_leave_count = 0;
        $casual_leave_count = 0;
        $compensate_leave_count = 0;
        
        foreach ($user_leaves as $leave) {
            $leave_name = strtolower(trim($leave['leave_type_name']));
            $duration_days = 0;
            
            // Calculate actual days for this leave within the month
            if ($leave['duration_type'] === 'half_day') {
                $duration_days = 0.5;
            } else {
                // Calculate overlapping days with the month
                $leave_start = max($leave['start_date'], $month_start);
                $leave_end = min($leave['end_date'], $month_end);
                $duration_days = (strtotime($leave_end) - strtotime($leave_start)) / (24 * 3600) + 1;
                
                // Special handling: If leave type contains "half day" but duration_type is "full",
                // it should still be treated as 0.5 days
                if (strpos($leave_name, 'half day') !== false || strpos($leave_name, 'half-day') !== false) {
                    $duration_days = 0.5;
                }
            }
            
            // Categorize and count leaves
            if (strpos($leave_name, 'half day') !== false || strpos($leave_name, 'half-day') !== false) {
                $half_day_count += $duration_days;
            } elseif (strpos($leave_name, 'short') !== false) {
                $short_leave_count += $duration_days;
            } elseif (strpos($leave_name, 'casual') !== false) {
                $casual_leave_count += $duration_days;
            } elseif (strpos($leave_name, 'compensate') !== false) {
                $compensate_leave_count += $duration_days;
            }
            // Add other leave types as needed
        }
        
        // Apply deduction rules
        $leave_deduction_days += $half_day_count; // Half day leave: always deduct
        $leave_deduction_days += max(0, $short_leave_count - 2); // Short leave: allow 2 days
        $leave_deduction_days += max(0, $casual_leave_count - 2); // Casual leave: allow 2 days
        // Compensate leave: never deduct (not added to deduction)
        
        $leave_deduction_days = max(0, $leave_deduction_days);
        
        // Calculate salary deduction amount for leaves
        $daily_salary = $working_days > 0 ? (($user['current_salary'] ?? $user['base_salary'] ?? 0) / $working_days) : 0;
        $leave_deduction_amount = $leave_deduction_days * $daily_salary;
        
        $user['leave_deduction_days'] = $leave_deduction_days;
        $user['leave_deduction_amount'] = $leave_deduction_amount;
        $user['half_day_leave_count'] = $half_day_count;
        $user['casual_leave_count'] = $casual_leave_count;
        
        // Calculate 1-hour late punch-ins (more than 1 hour late)
        // Also exclude records that are 15 minutes or less late (grace period)
        // AND exclude excess days from 1-hour late penalties
        $one_hour_late_query = "SELECT 
                                COUNT(*) as one_hour_late_days
                                FROM attendance 
                                WHERE user_id = ? 
                                AND DATE_FORMAT(date, '%Y-%m') = ? 
                                AND status = 'present' 
                                AND punch_in IS NOT NULL
                                AND TIMESTAMPDIFF(MINUTE, 
                                    CONCAT(DATE(date), ' ', '09:00:00'), 
                                    CONCAT(DATE(date), ' ', TIME(punch_in))
                                ) > 60";
        $one_hour_late_stmt = $pdo->prepare($one_hour_late_query);
        $one_hour_late_stmt->execute([$user['id'], $selected_filter_month]);
        $one_hour_late_result = $one_hour_late_stmt->fetch(PDO::FETCH_ASSOC);
        
        $one_hour_late_days = $one_hour_late_result['one_hour_late_days'] ?? 0;
        
        // For now, get checkbox state from database
        $use_short_leave_for_one_hour = false;
        
        // Calculate how short leaves are distributed based on checkbox preference
        $available_short_leave = min($short_leave_days, 2); // Max 2 short leaves per month
        
        // Smart short leave distribution based on user preference
        if ($use_short_leave_for_one_hour && $one_hour_late_days > 0) {
            // Priority 1: Use short leave for 1-hour late deductions first
            $short_leave_for_one_hour = min($available_short_leave, $one_hour_late_days * 0.5);
            $remaining_short_leave = max(0, $available_short_leave - $short_leave_for_one_hour);
            
            // Calculate 1-hour late deduction after short leave coverage
            $one_hour_late_after_short_leave = max(0, ($one_hour_late_days * 0.5) - $short_leave_for_one_hour);
            $one_hour_late_deduction = $one_hour_late_after_short_leave;
            
            // Priority 2: Use remaining short leave for regular late days
            $effective_late_days_for_deduction = max(0, $user['late_days'] - $remaining_short_leave);
        } else {
            // Standard approach: Use short leave only for regular late days
            $effective_late_days_for_deduction = max(0, $user['late_days'] - $available_short_leave);
            // 1-hour late deduction remains unchanged
            $one_hour_late_deduction = $one_hour_late_days * 0.5;
        }
        
        // Calculate regular late deduction: every 3 late days = 0.5 day deduction
        $late_deduction_days_final = floor($effective_late_days_for_deduction / 3) * 0.5;
        $late_deduction_amount_adjusted = $late_deduction_days_final * $daily_salary;
        
        // Calculate 1-hour late deduction amount
        $current_salary_for_calculation = $user['current_salary'] ?? $user['base_salary'] ?? 0;
        $daily_salary_for_one_hour = $working_days > 0 ? ($current_salary_for_calculation / $working_days) : 0;
        $one_hour_late_deduction_amount = $one_hour_late_deduction * $daily_salary_for_one_hour;
        
        // Update user data with new calculations
        $user['one_hour_late_days'] = $one_hour_late_days;
        $user['one_hour_late_deduction'] = $one_hour_late_deduction;
        $user['one_hour_late_deduction_amount'] = $one_hour_late_deduction_amount;
        $user['use_short_leave_for_one_hour'] = $use_short_leave_for_one_hour;
        
        // Update late deduction with adjusted values
        $daily_salary_for_late = $working_days > 0 ? (($user['current_salary'] ?? $user['base_salary'] ?? 0) / $working_days) : 0;
        $user['late_deduction_days'] = $late_deduction_days_final;
        $user['late_deduction_amount'] = $late_deduction_days_final * $daily_salary_for_late;
        
        // Update effective late days for display
        $user['effective_late_days'] = $effective_late_days_for_deduction;
        
        // Calculate 4th Saturday penalty
        $fourth_saturday_penalty = 0;
        $fourth_saturday_penalty_amount = 0;
        
        // Find the 4th Saturday of the selected month
        $month_start = date('Y-m-01', strtotime($selected_filter_month));
        $year = date('Y', strtotime($selected_filter_month));
        $month = date('m', strtotime($selected_filter_month));
        
        // Find all Saturdays in the month
        $saturdays = [];
        $date = new DateTime($month_start);
        $last_day = date('t', strtotime($selected_filter_month));
        
        for ($day = 1; $day <= $last_day; $day++) {
            $current_date = new DateTime("$year-$month-" . sprintf('%02d', $day));
            if ($current_date->format('l') === 'Saturday') {
                $saturdays[] = $current_date->format('Y-m-d');
            }
        }
        
        // Check if there's a 4th Saturday
        if (count($saturdays) >= 4) {
            $fourth_saturday = $saturdays[3]; // 4th Saturday (0-indexed)
            
            // Check if user punched in on 4th Saturday
            $fourth_saturday_attendance_query = "SELECT COUNT(*) as punched_in 
                                               FROM attendance 
                                               WHERE user_id = ? 
                                               AND DATE(date) = ? 
                                               AND punch_in IS NOT NULL";
            $fourth_saturday_stmt = $pdo->prepare($fourth_saturday_attendance_query);
            $fourth_saturday_stmt->execute([$user['id'], $fourth_saturday]);
            $fourth_saturday_result = $fourth_saturday_stmt->fetch(PDO::FETCH_ASSOC);
            
            $punched_in_on_fourth_saturday = $fourth_saturday_result['punched_in'] > 0;
            
            // If user didn't punch in on 4th Saturday, apply 3-day penalty
            if (!$punched_in_on_fourth_saturday) {
                $fourth_saturday_penalty = 3; // 3 days penalty
                $daily_salary_for_penalty = $working_days > 0 ? (($user['current_salary'] ?? $user['base_salary'] ?? 0) / $working_days) : 0;
                $fourth_saturday_penalty_amount = $fourth_saturday_penalty * $daily_salary_for_penalty;
            }
            
            $user['fourth_saturday_date'] = $fourth_saturday;
            $user['punched_in_on_fourth_saturday'] = $punched_in_on_fourth_saturday;
        } else {
            $user['fourth_saturday_date'] = null;
            $user['punched_in_on_fourth_saturday'] = true; // No penalty if no 4th Saturday
        }
        
        $user['fourth_saturday_penalty'] = $fourth_saturday_penalty;
        $user['fourth_saturday_penalty_amount'] = $fourth_saturday_penalty_amount;
        
        // Calculate final monthly salary after all deductions
        $base_salary = $user['current_salary'] ?? $user['base_salary'] ?? 0;
        
        // Calculate proportional salary based on adjusted present days vs adjusted working days
        $daily_rate = $adjusted_working_days > 0 ? ($base_salary / $adjusted_working_days) : 0;
        $proportional_base_salary = $daily_rate * $adjusted_present_days;
        
        // Round to nearest rupee for cleaner display
        $proportional_base_salary = round($proportional_base_salary);
        
        // Ensure proportional salary doesn't exceed base salary
        $proportional_base_salary = min($proportional_base_salary, $base_salary);
        
        // Check for manual incremented salary for this specific month
        $incremented_salary_query = "SELECT salary_after_increment FROM salary_increments 
                                    WHERE user_id = ? 
                                    AND DATE_FORMAT(effective_from, '%Y-%m') = ? 
                                    AND status != 'cancelled'
                                    ORDER BY effective_from DESC 
                                    LIMIT 1";
        $incremented_stmt = $pdo->prepare($incremented_salary_query);
        $incremented_stmt->execute([$user['id'], $selected_filter_month]);
        $incremented_result = $incremented_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Use incremented salary if available for this month, otherwise use proportional salary
        // If incremented salary is available, use it directly without proportional calculation
        if (isset($incremented_result['salary_after_increment'])) {
            $calculation_salary = $incremented_result['salary_after_increment'];
        } else {
            // Otherwise use the proportional calculation based on present days
            $calculation_salary = $proportional_base_salary;
        }
        $user['incremented_salary'] = $calculation_salary;
        
        // Store the proportional base salary for display
        $user['proportional_base_salary'] = $proportional_base_salary;
        
        $total_deductions = $user['late_deduction_amount'] + 
                           $user['leave_deduction_amount'] + 
                           $user['one_hour_late_deduction_amount'] + 
                           $fourth_saturday_penalty_amount;
        
        $monthly_salary_after_deductions = max(0, $calculation_salary - $total_deductions);
        
        $user['total_deductions'] = $total_deductions;
        $user['monthly_salary_after_deductions'] = $monthly_salary_after_deductions;
    }
    
    // Generate HTML table rows
    foreach ($active_users as $user) {
        $excel_content .= '<tr>';
        $excel_content .= '<td>' . htmlspecialchars($user['username']) . '</td>';
        $excel_content .= '<td>' . htmlspecialchars($user['unique_id'] ?? 'N/A') . '</td>';
        $excel_content .= '<td>' . htmlspecialchars($user['email']) . '</td>';
        $excel_content .= '<td>' . htmlspecialchars($user['department'] ?? 'N/A') . '</td>';
        $excel_content .= '<td>' . htmlspecialchars($user['position'] ?? 'N/A') . '</td>';
        $excel_content .= '<td class="currency">' . number_format($user['base_salary'] ?? 0) . '</td>';
        $excel_content .= '<td class="currency">' . number_format($user['incremented_salary']) . '</td>';
        $excel_content .= '<td class="center">' . number_format($user['adjusted_working_days'], 1) . '</td>';
        $excel_content .= '<td class="center">' . number_format($user['adjusted_present_days'], 1) . '</td>';
        $excel_content .= '<td class="center">' . number_format($user['carried_forward_days'], 1) . '</td>';
        $excel_content .= '<td class="center">' . number_format($user['excess_days'], 1) . '</td>';
        $excel_content .= '<td class="center">' . number_format($user['late_days'], 0) . '</td>';
        $excel_content .= '<td class="center">' . number_format($user['total_leave_days'], 1) . '</td>';
        $excel_content .= '<td>' . htmlspecialchars($user['leave_types_taken']) . '</td>';
        $excel_content .= '<td class="center">' . number_format($user['short_leave_days'], 1) . '</td>';
        $excel_content .= '<td class="center">' . number_format($user['half_day_leave_count'], 1) . '</td>';
        $excel_content .= '<td class="center">' . number_format($user['casual_leave_count'], 1) . '</td>';
        $excel_content .= '<td class="currency negative">' . number_format($user['leave_deduction_amount'], 0) . '</td>';
        $excel_content .= '<td class="center">' . number_format($user['one_hour_late_days'], 0) . '</td>';
        $excel_content .= '<td class="currency negative">' . number_format($user['one_hour_late_deduction_amount'], 0) . '</td>';
        $excel_content .= '<td class="currency negative">' . number_format($user['fourth_saturday_penalty_amount'], 0) . '</td>';
        $excel_content .= '<td class="currency negative">' . number_format($user['late_deduction_amount'], 0) . '</td>';
        $excel_content .= '<td class="currency negative">' . number_format($user['total_deductions'], 0) . '</td>';
        $excel_content .= '<td class="currency">' . number_format($user['monthly_salary_after_deductions'], 0) . '</td>';
        $excel_content .= '</tr>';
    }
    
} catch (PDOException $e) {
    error_log("Error fetching users for export: " . $e->getMessage());
    $excel_content .= '<tr><td colspan="20">Error generating report: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
} catch (Exception $e) {
    error_log("General error in export: " . $e->getMessage());
    $excel_content .= '<tr><td colspan="20">Error: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
}

// Close the HTML structure
$excel_content .= '
        </tbody>
    </table>
    <div style="margin-top: 20px; font-size: 12px; color: #666;">
        <p><strong>Note:</strong> This report was generated on ' . date('F j, Y \a\t g:i A') . '</p>
        <p><strong>Filter Month:</strong> ' . date('F Y', strtotime($selected_filter_month)) . '</p>
    </div>
</body>
</html>';

// Output the Excel content
echo $excel_content;
?>