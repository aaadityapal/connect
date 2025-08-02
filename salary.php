<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if user has HR role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    // Redirect to an access denied page or dashboard
    header("Location: access_denied.php?message=You must have HR role to access this page");
    exit;
}

// Include database connection
require_once 'config/db_connect.php';

// Get total number of active employees
$query = "SELECT COUNT(*) as total_users FROM users WHERE status = 'active' AND deleted_at IS NULL";
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_users = $result['total_users'];
} catch (PDOException $e) {
    error_log("Error fetching total users: " . $e->getMessage());
    $total_users = 0;
}

// Get the selected month from URL parameter or use current month
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Fetch actual users from database with their shift information
$users_query = "SELECT u.id, u.username, u.employee_id, u.department, u.role, u.status, u.created_at, u.base_salary,
                       us.weekly_offs, s.shift_name, s.start_time, s.end_time
                FROM users u
                LEFT JOIN user_shifts us ON u.id = us.user_id AND 
                    (us.effective_to IS NULL OR us.effective_to >= CURDATE())
                LEFT JOIN shifts s ON us.shift_id = s.id
                WHERE u.status = 'active' AND u.deleted_at IS NULL 
                ORDER BY u.username";
try {
    $stmt = $pdo->prepare($users_query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get the selected month's start and end dates
    $month_start = date('Y-m-01', strtotime($selected_month));
    $month_end = date('Y-m-t', strtotime($selected_month));
    
    // Calculate the number of days in the month
    $days_in_month = date('t', strtotime($selected_month));
    
    // Process working days for each user
    foreach ($users as &$user) {
        $weekly_offs = !empty($user['weekly_offs']) ? explode(',', $user['weekly_offs']) : [];
        $working_days_count = 0;
        
        // Loop through each day of the month
        $current_date = new DateTime($month_start);
        $end_date = new DateTime($month_end);
        
        while ($current_date <= $end_date) {
            $day_of_week = $current_date->format('l'); // Get day name (Monday, Tuesday, etc.)
            
            // If the day is not a weekly off, increment the working days counter
            if (!in_array($day_of_week, $weekly_offs)) {
                $working_days_count++;
            }
            
            // Move to the next day
            $current_date->modify('+1 day');
        }
        
        $user['working_days_count'] = $working_days_count;
        $user['total_days'] = $days_in_month;
        
        // Get present days count for this user in the selected month
        $attendance_query = "SELECT COUNT(*) as present_days 
                             FROM attendance 
                             WHERE user_id = ? 
                             AND DATE(date) BETWEEN ? AND ? 
                             AND status = 'present'";
        $att_stmt = $pdo->prepare($attendance_query);
        $att_stmt->execute([$user['id'], $month_start, $month_end]);
        $attendance_result = $att_stmt->fetch(PDO::FETCH_ASSOC);
        
        $user['present_days'] = $attendance_result['present_days'] ?? 0;
        
        // Calculate late punch-ins
        // Get the shift start time for the user
        $shift_start = !empty($user['start_time']) ? $user['start_time'] : '09:00:00'; // Default to 9 AM if no shift assigned
        
        // Add 15 minutes grace period to shift start time
        $grace_time = date('H:i:s', strtotime($shift_start . ' +15 minutes'));
        
        // Calculate 1 hour after shift start for half-day calculation
        $one_hour_late = date('H:i:s', strtotime($shift_start . ' +1 hour'));
        
        // Count late punch-ins (only between grace period and 1 hour late)
        $late_query = "SELECT COUNT(*) as late_days 
                       FROM attendance 
                       WHERE user_id = ? 
                       AND DATE(date) BETWEEN ? AND ? 
                       AND status = 'present' 
                       AND TIME(punch_in) > ?
                       AND TIME(punch_in) <= ?";  // Not more than 1 hour late
        $late_stmt = $pdo->prepare($late_query);
        $late_stmt->execute([$user['id'], $month_start, $month_end, $grace_time, $one_hour_late]);
        $late_result = $late_stmt->fetch(PDO::FETCH_ASSOC);
        
        $user['late_days'] = $late_result['late_days'] ?? 0;
        
        // Count 1-hour late punch-ins (half days)
        $half_day_query = "SELECT COUNT(*) as half_days,
                           GROUP_CONCAT(DATE(date)) as half_day_dates 
                           FROM attendance 
                           WHERE user_id = ? 
                           AND DATE(date) BETWEEN ? AND ? 
                           AND status = 'present' 
                           AND TIME(punch_in) > ?";
        $half_day_stmt = $pdo->prepare($half_day_query);
        $half_day_stmt->execute([$user['id'], $month_start, $month_end, $one_hour_late]);
        $half_day_result = $half_day_stmt->fetch(PDO::FETCH_ASSOC);
        
        $user['half_days'] = $half_day_result['half_days'] ?? 0;
        $user['half_day_dates'] = $half_day_result['half_day_dates'] ?? '';
        
        // Calculate late deductions
        $late_days = $user['late_days'];
        $half_days = $user['half_days'];
        $base_salary = $user['base_salary'] ?? 0;
        $working_days = $user['working_days_count'];
        
        // Calculate daily salary based on working days
        $daily_salary = $working_days > 0 ? $base_salary / $working_days : 0;
        $half_day_salary = $daily_salary / 2;
        
        // Calculate salary based on present days
        $present_days_salary = $daily_salary * $user['present_days'];
        
        // Calculate deductions based on late days
        $deduction_days = 0;
        if ($late_days >= 3) {
            // Initial half-day for first 3 late days
            $deduction_days = 0.5;
            
            // Additional half-day for every 3 more late days
            $additional_late_days = $late_days - 3;
            if ($additional_late_days > 0) {
                $additional_half_days = floor($additional_late_days / 3);
                $deduction_days += ($additional_half_days * 0.5);
            }
        }
        
        // Example: 3 late days = 0.5 day deduction
        //          6 late days = 1.0 day deduction (0.5 + 0.5)
        //          9 late days = 1.5 day deduction (0.5 + 0.5 + 0.5)
        
        $total_deduction = round($deduction_days * $daily_salary, 2);
        $user['late_deduction'] = $total_deduction;
        $user['late_deduction_days'] = $deduction_days;
        
        // Calculate 1-hour half day deductions (each counts as half day)
        $half_day_deduction_amount = round($half_days * $half_day_salary, 2);
        $user['half_day_deduction'] = $half_day_deduction_amount;
        
        // Calculate final monthly salary after all deductions
        $total_deductions = $total_deduction + $half_day_deduction_amount;
        $leave_deduction = 0; // This will be calculated later in the loop after leave info is processed
        
        // Store the deduction breakdown for the modal
        $user['deduction_breakdown'] = [
            'base_salary' => $base_salary,
            'present_days_salary' => $present_days_salary,
            'absence_deduction' => $base_salary - $present_days_salary,
            'late_deduction' => $total_deduction,
            'half_day_deduction' => $half_day_deduction_amount,
            'working_days' => $working_days,
            'present_days' => $user['present_days'],
            'daily_salary' => $daily_salary
        ];
        
        // Get leave information for the month
        $leave_query = "SELECT lr.leave_type, lt.name as leave_name, lt.color_code, lt.max_days,
                               SUM(CASE 
                                   WHEN lr.duration_type = 'half_day' THEN 0.5 
                                   ELSE 
                                      LEAST(DATEDIFF(
                                          LEAST(lr.end_date, ?), 
                                          GREATEST(lr.start_date, ?)
                                      ) + 1, 
                                      DATEDIFF(?, ?) + 1)
                               END) as total_days
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
                        GROUP BY lr.leave_type, lt.name, lt.color_code, lt.max_days";
                        
        $leave_stmt = $pdo->prepare($leave_query);
        $leave_stmt->execute([
            $month_end, // For LEAST(lr.end_date, ?)
            $month_start, // For GREATEST(lr.start_date, ?)
            $month_end, // For DATEDIFF(?, ?)
            $month_start, // For DATEDIFF(?, ?)
            $user['id'], 
            $month_start, $month_end, 
            $month_start, $month_end,
            $month_start, $month_end
        ]);
        
        $leaves = $leave_stmt->fetchAll(PDO::FETCH_ASSOC);
        $user['leaves'] = $leaves;
        
        // Calculate total leave days taken
        $total_leave_days = 0;
        foreach ($leaves as $leave) {
            $total_leave_days += $leave['total_days'];
        }
        $user['total_leave_days'] = $total_leave_days;

        // After fetching $user['leaves'] and $user['total_leave_days']
        // Define the monthly leave policy
        $leave_policy = [
            'Casual Leave' => 1,
            'Short Leave' => 2,
            'Half Day Leave' => 0,
            'Unpaid Leave' => 0,
            'Sick Leave' => 6,
            'Emergency Leave' => 3,
            'Paternity Leave' => 7,
            'Compensate Leave' => null // As earned
        ];

        // Build a map of taken days by leave type
        $leave_taken_map = [];
        foreach ($user['leaves'] as $leave) {
            $leave_taken_map[$leave['leave_name']] = [
                'taken' => $leave['total_days'],
                'max' => $leave['max_days'],
                'color' => $leave['color_code']
            ];
        }
        $user['leave_policy'] = $leave_policy;
        $user['leave_taken_map'] = $leave_taken_map;
        
        // Calculate leave deduction (after leave information is processed)
        $leave_deduction = 0;
        
        // Process leave deductions for calculating final salary
        if (!empty($user['leave_taken_map'])) {
            // Casual Leave - No deduction for first day
            if (isset($user['leave_taken_map']['Casual Leave'])) {
                $casual_taken = $user['leave_taken_map']['Casual Leave']['taken'];
                $casual_max = 1;
                if ($casual_taken > $casual_max) {
                    $excess_days = $casual_taken - $casual_max;
                    $leave_deduction += $excess_days * $daily_salary;
                }
            }
            
            // Half Day Leave - Deduct half day salary
            if (isset($user['leave_taken_map']['Half Day Leave'])) {
                $half_day_taken = $user['leave_taken_map']['Half Day Leave']['taken'];
                $leave_deduction += $half_day_taken * $half_day_salary;
            }
            
            // Sick Leave - No deduction up to 6 days
            if (isset($user['leave_taken_map']['Sick Leave'])) {
                $sick_taken = $user['leave_taken_map']['Sick Leave']['taken'];
                $sick_max = 6;
                if ($sick_taken > $sick_max) {
                    $excess_days = $sick_taken - $sick_max;
                    $leave_deduction += $excess_days * $daily_salary;
                }
            }
            
            // Emergency Leave - No deduction up to 3 days
            if (isset($user['leave_taken_map']['Emergency Leave'])) {
                $emergency_taken = $user['leave_taken_map']['Emergency Leave']['taken'];
                $emergency_max = 3;
                if ($emergency_taken > $emergency_max) {
                    $excess_days = $emergency_taken - $emergency_max;
                    $leave_deduction += $excess_days * $daily_salary;
                }
            }
            
            // Paternity Leave - No deduction up to 7 days
            if (isset($user['leave_taken_map']['Paternity Leave'])) {
                $paternity_taken = $user['leave_taken_map']['Paternity Leave']['taken'];
                $paternity_max = 7;
                if ($paternity_taken > $paternity_max) {
                    $excess_days = $paternity_taken - $paternity_max;
                    $leave_deduction += $excess_days * $daily_salary;
                }
            }
            
            // Unpaid Leave - Always deduct
            if (isset($user['leave_taken_map']['Unpaid Leave'])) {
                $unpaid_taken = $user['leave_taken_map']['Unpaid Leave']['taken'];
                $leave_deduction += $unpaid_taken * $daily_salary;
            }
        }
        
        // Round leave deduction to 2 decimal places
        $leave_deduction = round($leave_deduction, 2);
        $user['leave_deduction_amount'] = $leave_deduction;
        
        // Update deduction breakdown
        $user['deduction_breakdown']['leave_deduction'] = $leave_deduction;
        
        // Calculate final monthly salary after all deductions
        $total_deductions = $total_deduction + $half_day_deduction_amount + $leave_deduction;
        
        // Use present days salary as the base instead of full base salary
        $monthly_salary = $present_days_salary - $total_deductions;
        $user['monthly_salary'] = round($monthly_salary, 2);
        $user['total_deductions'] = $total_deductions;
        $user['present_days_salary'] = round($present_days_salary, 2);
        
        // Calculate absence deduction (difference between base salary and present days salary)
        $absence_deduction = $base_salary - $present_days_salary;
        $user['absence_deduction'] = round($absence_deduction, 2);
        
        // After the half day deduction calculation, add the 4th Saturday and penalty calculation logic
        
        // Calculate the 4th Saturday of the selected month
        $firstDay = new DateTime($month_start);
        $fourthSaturday = null;
        $saturdayCount = 0;
        
        // Find the fourth Saturday by looping through days
        $currentDay = clone $firstDay;
        while ($currentDay->format('Y-m') === $firstDay->format('Y-m')) {
            if ($currentDay->format('l') === 'Saturday') {
                $saturdayCount++;
                if ($saturdayCount === 4) {
                    $fourthSaturday = clone $currentDay;
                    break;
                }
            }
            $currentDay->modify('+1 day');
        }
        
        // Check if user has a record for 4th Saturday
        $user['fourth_saturday_date'] = $fourthSaturday ? $fourthSaturday->format('Y-m-d') : null;
        $user['fourth_saturday_off'] = false; // Default to not off
        
        // Only check attendance if 4th Saturday exists and is not in the future
        if ($fourthSaturday && $fourthSaturday <= new DateTime('today')) {
            // Check if user has a punch record for 4th Saturday
            $fourth_saturday_query = "SELECT COUNT(*) as record_count 
                                    FROM attendance 
                                    WHERE user_id = ? 
                                    AND DATE(date) = ?";
            $fourth_saturday_stmt = $pdo->prepare($fourth_saturday_query);
            $fourth_saturday_stmt->execute([$user['id'], $fourthSaturday->format('Y-m-d')]);
            $fourth_saturday_result = $fourth_saturday_stmt->fetch(PDO::FETCH_ASSOC);
            
            // If no record found for 4th Saturday, it means it was off
            if ($fourth_saturday_result['record_count'] == 0) {
                $user['fourth_saturday_off'] = true;
            }
        }
        
        // Check for missing punch-in records in the month
        // This will check if there are any days in the month where the user has no punch-in record at all
        $missing_punch_query = "SELECT 
                                  COUNT(*) as working_days_with_no_record
                              FROM 
                                  (
                                      SELECT 
                                          DATE_FORMAT(dates.date, '%Y-%m-%d') as date
                                      FROM 
                                          (
                                              SELECT 
                                                  ADDDATE(?, n) as date
                                              FROM 
                                                  (
                                                      SELECT a.N + b.N * 10 + c.N * 100 as n
                                                      FROM 
                                                          (SELECT 0 as N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
                                                          (SELECT 0 as N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b,
                                                          (SELECT 0 as N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c
                                                  ) numbers
                                              WHERE 
                                                  ADDDATE(?, n) <= ? AND ADDDATE(?, n) <= CURDATE()
                                          ) dates
                                      WHERE 
                                          DAYOFWEEK(dates.date) NOT IN (1, ?) -- Exclude weekly offs: 1 is Sunday, and user's weekly off
                                  ) working_days
                              LEFT JOIN attendance a ON a.user_id = ? AND DATE(a.date) = working_days.date
                              WHERE 
                                  a.id IS NULL";

        // Get user's weekly off day number (SQL DAYOFWEEK function: 1=Sunday, 2=Monday, etc.)
        $weekly_off_day = 1; // Default to Sunday
        if (!empty($user['weekly_offs'])) {
            $weekly_offs = explode(',', $user['weekly_offs']);
            if (in_array('Monday', $weekly_offs)) $weekly_off_day = 2;
            elseif (in_array('Tuesday', $weekly_offs)) $weekly_off_day = 3;
            elseif (in_array('Wednesday', $weekly_offs)) $weekly_off_day = 4;
            elseif (in_array('Thursday', $weekly_offs)) $weekly_off_day = 5;
            elseif (in_array('Friday', $weekly_offs)) $weekly_off_day = 6;
            elseif (in_array('Saturday', $weekly_offs)) $weekly_off_day = 7;
        }
        
        try {
            $missing_punch_stmt = $pdo->prepare($missing_punch_query);
            $missing_punch_stmt->execute([
                $month_start, 
                $month_start, 
                $month_end,
                $month_start,
                $weekly_off_day, 
                $user['id']
            ]);
            $missing_punch_result = $missing_punch_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Only count missing punch for 4th Saturday
            $user['missing_punch_days'] = 0;
            
            // If 4th Saturday exists, is not in the future, and has no attendance record
            if ($fourthSaturday 
                && $fourthSaturday <= new DateTime('today') 
                && $user['fourth_saturday_off']) {
                
                // Check if holidays table exists and if this is a holiday
                $holidays_table_exists = false;
                try {
                    $check_table = "SHOW TABLES LIKE 'holidays'";
                    $table_stmt = $pdo->prepare($check_table);
                    $table_stmt->execute();
                    $holidays_table_exists = $table_stmt->rowCount() > 0;
                } catch (PDOException $e) {
                    error_log("Error checking for holidays table: " . $e->getMessage());
                }
                
                $is_holiday = false;
                if ($holidays_table_exists) {
                    // Check if this is a working day (not a holiday)
                    try {
                        $is_holiday_query = "SELECT COUNT(*) as is_holiday FROM holidays WHERE date = ?";
                        $is_holiday_stmt = $pdo->prepare($is_holiday_query);
                        $is_holiday_stmt->execute([$fourthSaturday->format('Y-m-d')]);
                        $is_holiday_result = $is_holiday_stmt->fetch(PDO::FETCH_ASSOC);
                        $is_holiday = $is_holiday_result['is_holiday'] > 0;
                    } catch (PDOException $e) {
                        error_log("Error checking holiday status: " . $e->getMessage());
                    }
                }
                
                // Apply penalty only if it's not a holiday
                if (!$is_holiday) {
                    $user['missing_punch_days'] = 1; // Only count the 4th Saturday
                }
            }
            
            // Apply the penalty if the 4th Saturday is missing a punch-in
            if ($user['missing_punch_days'] > 0) {
                $penalty_days = 3; // Fixed penalty of 3 days
                $penalty_amount = $daily_salary * $penalty_days;
                $user['penalty_amount'] = round($penalty_amount, 2);
                
                // Update deduction breakdown
                $user['deduction_breakdown']['penalty_deduction'] = $penalty_amount;
                
                // Update total deductions and monthly salary
                $user['total_deductions'] += $penalty_amount;
                $user['monthly_salary'] -= $penalty_amount;
            } else {
                $user['penalty_amount'] = 0;
            }
        } catch (PDOException $e) {
            error_log("Error calculating missing punch-in days: " . $e->getMessage());
            $user['missing_punch_days'] = 0;
            $user['penalty_amount'] = 0;
        }
        
        // Add code to fetch overtime hours for each user - only count if ≥ 1 hour 30 minutes after shift
        $overtime_query = "SELECT 
                            a.date, 
                            a.punch_out,
                            s.end_time as shift_end,
                            TIMESTAMPDIFF(MINUTE, s.end_time, TIME(a.punch_out)) as minutes_after_shift,
                            TIMESTAMPDIFF(HOUR, s.end_time, TIME(a.punch_out)) as hours_after_shift,
                            TIMESTAMPDIFF(MINUTE, s.end_time, TIME(a.punch_out)) % 60 as remaining_minutes
                           FROM attendance a
                           LEFT JOIN user_shifts us ON a.user_id = us.user_id AND (us.effective_to IS NULL OR us.effective_to >= a.date)
                           LEFT JOIN shifts s ON us.shift_id = s.id
                           WHERE a.user_id = ? 
                           AND DATE(a.date) BETWEEN ? AND ?
                           AND a.status = 'present'
                           AND s.end_time IS NOT NULL
                           AND TIMESTAMPDIFF(MINUTE, s.end_time, TIME(a.punch_out)) >= 90";
        try {
            $overtime_stmt = $pdo->prepare($overtime_query);
            $overtime_stmt->execute([$user['id'], $month_start, $month_end]);
            $overtime_results = $overtime_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate total overtime minutes - only when ≥ 1 hour 30 minutes after shift
            $total_overtime_minutes = 0;
            
            foreach ($overtime_results as $record) {
                if (isset($record['minutes_after_shift']) && $record['minutes_after_shift'] >= 90) {
                    // Calculate hours and minutes for this record
                    $record_minutes = (int)$record['minutes_after_shift'];
                    $record_hours = floor($record_minutes / 60);
                    $record_mins = $record_minutes % 60;
                    
                    // Apply rounding rule to each record individually
                    $rounded_mins = $record_mins < 30 ? 0 : 30;
                    
                    // Add to total (converting back to minutes)
                    $total_overtime_minutes += ($record_hours * 60) + $rounded_mins;
                }
            }
            
            // Calculate overtime hours and minutes correctly
            $overtime_hours = floor($total_overtime_minutes / 60);
            $overtime_minutes = $total_overtime_minutes % 60;
            
            // Apply rounding rules for minutes - round down to nearest 30 minute increment
            // 0-29 minutes rounds to 0, 30-59 minutes rounds to 30
            if ($overtime_minutes < 30) {
                $overtime_minutes = 0;
            } else {
                $overtime_minutes = 30;
            }
            
                    // Store the values for display
        $user['total_overtime_minutes'] = $total_overtime_minutes;
        $user['overtime_hours'] = $overtime_hours;
        $user['overtime_minutes'] = $overtime_minutes;
        
        // Calculate overtime amount
        $one_day_salary = $user['working_days_count'] > 0 ? $user['base_salary'] / $user['working_days_count'] : 0;
        
        // Standard shift is 8 hours, but use actual shift hours if available
        $shift_hours = 8; // Default
        if (!empty($user['start_time']) && !empty($user['end_time'])) {
            $start = new DateTime($user['start_time']);
            $end = new DateTime($user['end_time']);
            $interval = $start->diff($end);
            $shift_hours = $interval->h + ($interval->i / 60);
            
            // Ensure we have at least 1 hour to avoid division by zero
            if ($shift_hours < 1) {
                $shift_hours = 8; // Fallback to default
            }
        }
        
        $one_hour_salary = $one_day_salary / $shift_hours;
        
        // Convert total overtime to hours (decimal)
        $total_overtime_hours = $overtime_hours + ($overtime_minutes / 60);
        
        // Calculate overtime amount
        $user['overtime_amount'] = $one_hour_salary * $total_overtime_hours;
        
        // Calculate remaining salary for extra working days
        $user['remaining_salary'] = 0;
        $extra_days = $user['present_days'] - $user['working_days_count'];
        
        if ($extra_days > 0) {
            // If employee worked more days than scheduled, calculate remaining salary
            $user['remaining_salary'] = $extra_days * $one_day_salary;
        }
            
            // Also store the legacy total for backward compatibility
            $legacy_overtime_query = "SELECT SUM(overtime_hours) as total_overtime 
                                     FROM attendance 
                                     WHERE user_id = ? 
                                     AND DATE(date) BETWEEN ? AND ?";
            $legacy_stmt = $pdo->prepare($legacy_overtime_query);
            $legacy_stmt->execute([$user['id'], $month_start, $month_end]);
            $legacy_result = $legacy_stmt->fetch(PDO::FETCH_ASSOC);
            $user['legacy_overtime'] = $legacy_result['total_overtime'] ?? 0;
            
        } catch (PDOException $e) {
            error_log("Error fetching overtime hours: " . $e->getMessage());
            $user['total_overtime_minutes'] = 0;
            $user['overtime_hours'] = 0;
            $user['overtime_minutes'] = 0;
        }
    }
    unset($user); // Break the reference
} catch (PDOException $e) {
    error_log("Error fetching users with shift data: " . $e->getMessage());
    $users = [];
}

// Process salary update if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_salary') {
    if (isset($_POST['user_id']) && isset($_POST['base_salary'])) {
        $user_id = $_POST['user_id'];
        $base_salary = $_POST['base_salary'];
        
        try {
            $update_query = "UPDATE users SET base_salary = ? WHERE id = ?";
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([$base_salary, $user_id]);
            
            // Redirect to prevent form resubmission
            header("Location: salary.php?month=$selected_month&updated=true");
            exit;
        } catch (PDOException $e) {
            error_log("Error updating salary: " . $e->getMessage());
            $update_error = "Failed to update salary. Please try again.";
        }
    }
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=users_' . $selected_month . '.csv');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM to fix Excel CSV encoding
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add CSV header
    fputcsv($output, ['S.No', 'Username', 'Base Salary', 'Working Days', 'Present Days', 'Late Punch In', 'Late Deduction', 'Leave Taken', 'Leave Deduction', '1Hr Half Days', 'Half Day Deduction', '4th Saturday Penalty', 'Monthly Salary', 'Total Overtime Hours', 'Overtime Amount', 'Total Salary', 'Remaining Salary']);
    
    // Add data rows
    $serial = 1;
    foreach ($users as $user) {
        // Prepare leave information for CSV
        $leave_info = $user['total_leave_days'] . ' days';
        if (!empty($user['leaves'])) {
            $leave_types = [];
            foreach ($user['leaves'] as $leave) {
                $leave_types[] = $leave['leave_name'] . ': ' . $leave['total_days'] . '/' . ($leave['max_days'] ?? '0') . ' days';
            }
            $leave_info .= ' (' . implode(', ', $leave_types) . ')';
        }
        
        // Calculate leave deduction for CSV export
        $leave_deduction = 0;
        $daily_salary = $user['working_days_count'] > 0 ? $user['base_salary'] / $user['working_days_count'] : 0;
        $half_day_salary = $daily_salary / 2;
        
        // Process leave deductions for export
        if (!empty($user['leave_taken_map'])) {
            // Casual Leave - No deduction for first day
            if (isset($user['leave_taken_map']['Casual Leave'])) {
                $casual_taken = $user['leave_taken_map']['Casual Leave']['taken'];
                if ($casual_taken > 1) {
                    $leave_deduction += ($casual_taken - 1) * $daily_salary;
                }
            }
            
            // Short Leave - Can reduce late days
            if (isset($user['leave_taken_map']['Short Leave'])) {
                $short_taken = $user['leave_taken_map']['Short Leave']['taken'];
                if ($short_taken > 2) {
                    $leave_deduction += ($short_taken - 2) * $half_day_salary;
                }
            }
            
            // Half Day Leave - Deduct half day salary
            if (isset($user['leave_taken_map']['Half Day Leave'])) {
                $leave_deduction += $user['leave_taken_map']['Half Day Leave']['taken'] * $half_day_salary;
            }
            
            // Sick Leave - No deduction up to 6 days
            if (isset($user['leave_taken_map']['Sick Leave'])) {
                $sick_taken = $user['leave_taken_map']['Sick Leave']['taken'];
                if ($sick_taken > 6) {
                    $leave_deduction += ($sick_taken - 6) * $daily_salary;
                }
            }
            
            // Emergency Leave - No deduction up to 3 days
            if (isset($user['leave_taken_map']['Emergency Leave'])) {
                $emergency_taken = $user['leave_taken_map']['Emergency Leave']['taken'];
                if ($emergency_taken > 3) {
                    $leave_deduction += ($emergency_taken - 3) * $daily_salary;
                }
            }
            
            // Paternity Leave - No deduction up to 7 days
            if (isset($user['leave_taken_map']['Paternity Leave'])) {
                $paternity_taken = $user['leave_taken_map']['Paternity Leave']['taken'];
                if ($paternity_taken > 7) {
                    $leave_deduction += ($paternity_taken - 7) * $daily_salary;
                }
            }
            
            // Unpaid Leave - Always deduct
            if (isset($user['leave_taken_map']['Unpaid Leave'])) {
                $leave_deduction += $user['leave_taken_map']['Unpaid Leave']['taken'] * $daily_salary;
            }
        }
        
        // Round to 2 decimal places
        $leave_deduction = round($leave_deduction, 2);
        
        $row = [
            $serial++,
            $user['username'],
            $user['base_salary'] ?? 0,
            $user['working_days_count'],
            $user['present_days'],
            $user['late_days'],
            $user['late_deduction'],
            $leave_info,
            $leave_deduction,
            $user['half_days'] ?? 0,
            $user['half_day_deduction'] ?? 0,
            $user['penalty_amount'] ?? 0,
            $user['monthly_salary'] ?? $user['base_salary'],
            (isset($user['overtime_hours']) && isset($user['overtime_minutes'])) ? 
                ($user['overtime_hours'] > 0 ? $user['overtime_hours'] . ' hrs ' : '') . 
                ($user['overtime_minutes'] >= 30 ? '30 min' : 
                ($user['overtime_hours'] > 0 ? '' : '0 hrs')) : '0 hrs',
            
            // Add overtime amount
            isset($user['overtime_amount']) ? round($user['overtime_amount'], 2) : 0,
            
            // Add total salary (monthly salary + overtime amount, excluding remaining salary)
            (function($user) {
                $monthly_salary = isset($user['monthly_salary']) ? $user['monthly_salary'] : 0;
                $overtime_amount = isset($user['overtime_amount']) ? $user['overtime_amount'] : 0;
                $extra_days = isset($user['present_days']) && isset($user['working_days_count']) ? $user['present_days'] - $user['working_days_count'] : 0;
                
                if ($extra_days > 0) {
                    $daily_salary = isset($user['working_days_count']) && $user['working_days_count'] > 0 ? $user['base_salary'] / $user['working_days_count'] : 0;
                    $extra_days_salary = $extra_days * $daily_salary;
                    $monthly_salary = $monthly_salary - $extra_days_salary;
                }
                
                return $monthly_salary + $overtime_amount;
            })($user),
            
            // Add remaining salary for extra working days
            isset($user['remaining_salary']) ? round($user['remaining_salary'], 2) : 0
        ];
        fputcsv($output, $row);
    }
    
    // Close the output stream
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Overview</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5;
            --primary-dark: #4338CA;
            --secondary: #7C3AED;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --dark: #111827;
            --gray: #6B7280;
            --light: #F3F4F6;
            --sidebar-width: 280px;
        }

        * {
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        body {
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
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
        
        /* Original salary.php styles */
        .container {
            max-width: 100%;
            margin: 0;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #2c3e50;
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .header-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .filter-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .month-filter {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .month-filter label {
            font-weight: 500;
            color: #4b5563;
        }
        
        .month-filter input[type="month"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .export-btn {
            background-color: #4361ee;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .export-btn:hover {
            background-color: #3a4ecd;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .controls {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .search-box {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 250px;
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
        }
        
        select, button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
            cursor: pointer;
        }
        
        button.primary {
            background-color: #3498db;
            color: white;
            border: none;
        }
        
        button.primary:hover {
            background-color: #2980b9;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .highlight {
            background-color: #fffde7;
        }
        
        .salary-high {
            color: #27ae60;
            font-weight: 600;
        }
        
        .salary-low {
            color: #e74c3c;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        
        .pagination button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background-color: white;
        }
        
        .pagination button.active {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .summary-cards {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .card {
            flex: 1;
            min-width: 200px;
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .card h3 {
            margin-top: 0;
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .card p {
            margin-bottom: 0;
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .card.total {
            border-top: 4px solid #3498db;
        }
        
        .card.average {
            border-top: 4px solid #2ecc71;
        }
        
        .card.highest {
            border-top: 4px solid #e74c3c;
        }
        
        .card.lowest {
            border-top: 4px solid #f39c12;
        }
        
        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
            }
            
            .search-box, .filter-group {
                width: 100%;
            }
            
            th, td {
                padding: 8px 10px;
                font-size: 14px;
            }
        }
        
        .editable-cell {
            position: relative;
        }

        .editable-cell input {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100px;
        }

        .save-btn {
            background-color: var(--success);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            margin-left: 5px;
        }

        .save-btn:hover {
            background-color: #059669;
        }

        .shift-info {
            cursor: pointer;
            margin-left: 5px;
            color: var(--primary);
        }

        .shift-info i {
            font-size: 14px;
        }

        /* Tooltip style */
        [title] {
            position: relative;
        }

        [title]:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 0;
            background: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 100;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        /* Additional modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            position: relative;
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border: 1px solid #ddd;
            border-radius: 10px;
            width: 90%;
            max-width: 1200px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.3s;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 24px 30px;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #f8f9fa;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .modal-title {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
        }
        
        .modal-title::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 24px;
            background-color: var(--primary);
            margin-right: 15px;
            border-radius: 3px;
        }

        .close-modal {
            color: #777;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            background: none;
            border: none;
            padding: 10px;
            margin-left: auto;
            border-radius: 50%;
            line-height: 0.6;
        }

        .close-modal:hover {
            color: var(--primary);
            background-color: rgba(67, 97, 238, 0.1);
        }

        .modal-body {
            padding: 30px;
            max-height: 75vh;
            overflow-y: auto;
        }

        .modal-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.05);
            table-layout: fixed;
        }

        .modal-table th, .modal-table td {
            padding: 14px 15px;
            text-align: left;
            border-bottom: 1px solid #eaeaea;
            word-wrap: break-word;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .modal-table th {
            white-space: nowrap;
        }
        
        /* Apply specific widths to common table columns */
        .modal-table th:nth-child(1), 
        .modal-table td:nth-child(1) {
            width: 100px; /* Date column */
        }
        
        .modal-table th:nth-child(2), 
        .modal-table td:nth-child(2) {
            width: 80px; /* Day column */
        }

        .modal-table th {
            background-color: #f1f5fd;
            font-weight: 600;
            color: var(--primary);
            position: sticky;
            top: 0;
        }

        .modal-table tr:last-child td {
            border-bottom: none;
        }

        .modal-table tr:hover {
            background-color: #f9fbff;
        }
        
        .attendance-info {
            cursor: pointer;
            margin-left: 8px;
            color: var(--primary);
            transition: transform 0.2s ease;
            display: inline-flex;
        }

        .attendance-info:hover {
            transform: scale(1.2);
            color: var(--primary-dark);
        }

        .status-present {
            color: var(--success);
            font-weight: 500;
        }

        .status-absent {
            color: var(--danger);
            font-weight: 500;
        }

        .status-leave {
            color: var(--warning);
            font-weight: 500;
        }

        .status-weekly-off {
            color: #6B7280;
            font-weight: 500;
            font-style: italic;
        }

        .loading-spinner {
            display: block;
            margin: 30px auto;
            width: 40px;
            height: 40px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: var(--text-secondary);
            font-style: italic;
        }
        
        .late-info {
            cursor: pointer;
            margin-left: 8px;
            transition: transform 0.2s ease;
            display: inline-flex;
        }
        
        .late-info:hover {
            transform: scale(1.2);
        }
        
        .deduction-info {
            cursor: pointer;
            margin-left: 8px;
            transition: transform 0.2s ease;
            display: inline-flex;
            color: #6B7280;
        }
        
        .deduction-info:hover {
            transform: scale(1.2);
            color: var(--primary);
        }
        
        .leave-info {
            cursor: pointer;
            margin-left: 8px;
            transition: transform 0.2s ease;
            display: inline-flex;
            color: var(--primary);
        }
        
        .leave-info:hover {
            transform: scale(1.2);
        }
        
        .leave-types-tooltip {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 10px;
            z-index: 100;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            min-width: 200px;
        }
        
        .leave-info:hover + .leave-types-tooltip {
            display: block;
        }
        
        .leave-type {
            display: block;
            padding: 5px 8px;
            margin-bottom: 4px;
            border-radius: 4px;
            color: white;
            font-size: 12px;
            font-weight: 500;
        }
        
        .leave-usage {
            display: inline-block;
            font-weight: 600;
            margin-top: 3px;
            background-color: rgba(255, 255, 255, 0.2);
            padding: 2px 6px;
            border-radius: 3px;
        }
        
        .text-warning {
            color: var(--warning);
        }
        
        .text-danger {
            color: var(--danger);
            font-weight: 500;
        }
        
        /* Leave Allowance Styles */
        .leave-allowance-container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .leave-allowance-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .leave-allowance-list {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .leave-allowance-item {
            flex: 0 0 auto;
        }
        
        .leave-box {
            display: flex;
            flex-direction: column;
            padding: 10px 15px;
            border-radius: 6px;
            color: white;
            min-width: 160px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .leave-type-name {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .leave-type-limit {
            font-size: 16px;
            font-weight: 600;
        }
        
        .leave-box i {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 16px;
            opacity: 0.7;
        }
        
        /* Leave Deduction Modal Styles */
        .leave-deduction-summary {
            margin-bottom: 30px;
        }
        
        .leave-deduction-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        
        .leave-deduction-card {
            color: white;
            padding: 15px;
            border-radius: 8px;
            min-width: 200px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .leave-deduction-card .leave-type-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding-bottom: 5px;
        }
        
        .leave-deduction-card .leave-details {
            font-size: 14px;
            line-height: 1.6;
        }
        
        .leave-deduction-card .leave-deduction-amount {
            margin-top: 8px;
            font-weight: 600;
            background-color: rgba(255, 255, 255, 0.2);
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        
        .total-deduction {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--danger);
        }
        
        .total-deduction h4 {
            margin: 0 0 5px 0;
            color: var(--danger);
        }
        
        .total-deduction p {
            margin: 0;
            color: var(--gray);
            font-size: 14px;
        }
        
        /* Leave type badge in modal table */
        .leave-type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            color: white;
            font-weight: 500;
            font-size: 12px;
        }
        
        .leave-type-no-leave {
            background-color: #6c757d;
            color: white;
            font-style: italic;
            opacity: 0.7;
        }
        
        .leave-type-unspecified {
            background-color: #ffc107;
            color: #343a40;
        }
        
        /* Half Day Styles */
        .half-day-info {
            cursor: pointer;
            margin-left: 8px;
            transition: transform 0.2s ease;
            display: inline-flex;
        }
        
        .half-day-info:hover {
            transform: scale(1.2);
            color: var(--warning);
        }
        
        .half-day-summary {
            margin-bottom: 20px;
        }
        
        .half-day-summary p {
            color: #555;
            margin-top: 10px;
            background-color: #fff9e6;
            padding: 10px;
            border-radius: 5px;
            border-left: 3px solid var(--warning);
        }
        
        /* Salary Info Styles */
        .salary-info {
            cursor: pointer;
            margin-left: 8px;
            transition: transform 0.2s ease;
            display: inline-flex;
            color: var(--primary);
        }
        
        .salary-info:hover {
            transform: scale(1.2);
            color: var(--primary-dark);
        }
        
        .salary-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .salary-card {
            flex: 1;
            min-width: 300px;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        @media (min-width: 1200px) {
            .salary-card {
                flex-basis: calc(33.33% - 20px);
                max-width: calc(33.33% - 20px);
            }
        }
        
        .salary-card h4 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 600;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .salary-card .amount {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            display: inline-block;
            padding: 5px 10px;
            border-radius: 6px;
            background-color: rgba(0,0,0,0.03);
        }
        
        .salary-card .details,
        .salary-card .calculation {
            font-size: 15px;
            color: #6c757d;
            line-height: 1.6;
        }
        
        .base-salary {
            background-color: #f8f9fa;
            border-left: 4px solid var(--primary);
        }
        
        .base-salary .amount {
            color: var(--primary);
        }
        
        .deductions {
            background-color: #fff8f8;
            border-left: 4px solid var(--danger);
        }
        
        .deduction-item {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 15px;
            padding: 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .has-deduction {
            background-color: #ffeeee;
            box-shadow: 0 2px 5px rgba(239, 68, 68, 0.1);
        }
        
        .has-deduction:hover {
            background-color: #ffe6e6;
            box-shadow: 0 3px 8px rgba(239, 68, 68, 0.15);
        }
        
        .deduction-label {
            flex: 2;
            font-weight: 600;
            font-size: 16px;
        }
        
        .deduction-amount {
            flex: 1;
            text-align: right;
            font-weight: 700;
            font-size: 16px;
            color: var(--danger);
        }
        
        .deduction-details {
            flex: 0 0 100%;
            font-size: 14px;
            color: #6c757d;
            margin-top: 8px;
            border-top: 1px dashed rgba(0,0,0,0.05);
            padding-top: 8px;
        }
        
        .deduction-total {
            margin-top: 20px;
            padding: 15px;
            border-radius: 6px;
            background-color: #fff0f0;
            display: flex;
            flex-wrap: wrap;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.15);
        }
        
        .final-salary {
            background-color: #f0f8ff;
            border-left: 4px solid #10B981;
        }
        
        .final-salary .amount {
            color: #10B981;
        }
        
        .attendance-summary {
            background-color: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }
        
        .attendance-summary h4 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 600;
            border-bottom: 2px solid #eee;
            padding-bottom: 12px;
            color: var(--primary);
        }
        
        .attendance-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .stat-item {
            flex: 1;
            min-width: 150px;
            padding: 16px 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }
        
        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .stat-label {
            font-size: 15px;
            color: #6c757d;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            background-color: rgba(67, 97, 238, 0.1);
        }
        
        .late-punch-explanation {
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }
        
        .late-punch-explanation p {
            margin-top: 0;
            margin-bottom: 10px;
            color: #333;
        }
        
        .late-punch-explanation ul {
            margin-bottom: 0;
            padding-left: 20px;
        }
        
        .late-punch-explanation li {
            margin-bottom: 5px;
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
            <a href="hr_attendance_report.php" class="nav-link">
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
            <a href="salary_overview.php" class="nav-link active">
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
            <div class="header-controls">
                <h1 class="page-title">Salary Overview</h1>
                <div class="filter-actions">
                    <div class="month-filter">
                        <label for="monthYearFilter">Month:</label>
                        <input type="month" id="monthYearFilter" value="<?php echo $selected_month; ?>">
                    </div>
                    <a href="salary.php?month=<?php echo $selected_month; ?>&export=csv" class="export-btn">
                        <i class="bi bi-file-earmark-excel"></i>
                        Export CSV
                    </a>
                </div>
            </div>
        
        <div class="summary-cards">
            <div class="card total">
                <h3>Total Employees</h3>
                <p id="totalEmployees"><?php echo $total_users; ?></p>
            </div>
            <div class="card monthly-outstanding" style="border-top: 4px solid #10b981;">
                <h3>Monthly Outstanding</h3>
                <p id="monthlyOutstanding">
                    <?php 
                        $total_monthly_salary = 0;
                        foreach ($users as $user) {
                            $total_monthly_salary += isset($user['monthly_salary']) ? $user['monthly_salary'] : 0;
                        }
                        echo '₹' . number_format($total_monthly_salary, 2);
                    ?>
                </p>
            </div>
            <div class="card" style="border-top: 4px solid #3b82f6;">
                <h3>Total Overtime</h3>
                <p id="totalOvertime">
                    <?php 
                        $total_overtime_amount = 0;
                        foreach ($users as $user) {
                            $total_overtime_amount += isset($user['overtime_amount']) ? $user['overtime_amount'] : 0;
                        }
                        echo '₹' . number_format($total_overtime_amount, 2);
                    ?>
                </p>
            </div>
            <div class="card" style="border-top: 4px solid #ec4899;">
                <h3>Total Payable Amount</h3>
                <p id="totalPayable">
                    <?php
                        $total_payable = 0;
                        foreach ($users as $user) {
                            // Monthly salary + overtime amount
                            $monthly_salary = isset($user['monthly_salary']) ? $user['monthly_salary'] : 0;
                            $overtime_amount = isset($user['overtime_amount']) ? $user['overtime_amount'] : 0;
                            
                            // Calculate total payable for this employee
                            // Adjust for extra days if needed
                            $extra_days = isset($user['present_days']) && isset($user['working_days_count']) ? 
                                          $user['present_days'] - $user['working_days_count'] : 0;
                            
                            if ($extra_days > 0) {
                                // Don't include extra days in current month's payable
                                $daily_salary = isset($user['working_days_count']) && $user['working_days_count'] > 0 ? 
                                              $user['base_salary'] / $user['working_days_count'] : 0;
                                $extra_days_salary = $extra_days * $daily_salary;
                                $monthly_salary = $monthly_salary - $extra_days_salary;
                            }
                            
                            $total_payable += $monthly_salary + $overtime_amount;
                        }
                        echo '₹' . number_format($total_payable, 2);
                    ?>
                </p>
            </div>
        </div>
            
            <div class="leave-allowance-container">
                <h3 class="leave-allowance-title">Monthly Leave Allowance</h3>
                <div class="leave-allowance-list">
                    <div class="leave-allowance-item">
                        <div class="leave-box" style="background-color: #ef4444;">
                            <span class="leave-type-name">Sick Leave</span>
                            <span class="leave-type-limit">6.0 days</span>
                            <i class="bi bi-calendar"></i>
                        </div>
                    </div>
                    <div class="leave-allowance-item">
                        <div class="leave-box" style="background-color: #4361ee;">
                            <span class="leave-type-name">Casual Leave</span>
                            <span class="leave-type-limit">1.0 days</span>
                            <i class="bi bi-calendar"></i>
                        </div>
                    </div>
                    <div class="leave-allowance-item">
                        <div class="leave-box" style="background-color: #0ea5e9;">
                            <span class="leave-type-name">Short Leave</span>
                            <span class="leave-type-limit">2.0 days</span>
                            <i class="bi bi-calendar"></i>
                        </div>
                    </div>
                    <div class="leave-allowance-item">
                        <div class="leave-box" style="background-color: #f59e0b;">
                            <span class="leave-type-name">Half Day Leave</span>
                            <span class="leave-type-limit">0.0 days</span>
                            <i class="bi bi-calendar"></i>
                        </div>
                    </div>
                    <div class="leave-allowance-item">
                        <div class="leave-box" style="background-color: #8b5cf6;">
                            <span class="leave-type-name">Emergency Leave</span>
                            <span class="leave-type-limit">3.0 days</span>
                            <i class="bi bi-calendar"></i>
                        </div>
                    </div>
                    <div class="leave-allowance-item">
                        <div class="leave-box" style="background-color: #3b82f6;">
                            <span class="leave-type-name">Paternity Leave</span>
                            <span class="leave-type-limit">7.0 days</span>
                            <i class="bi bi-calendar"></i>
                        </div>
                    </div>
                    <div class="leave-allowance-item">
                        <div class="leave-box" style="background-color: #6b7280;">
                            <span class="leave-type-name">Unpaid Leave</span>
                            <span class="leave-type-limit">0.0 days</span>
                            <i class="bi bi-calendar"></i>
                        </div>
                    </div>
                    <div class="leave-allowance-item">
                        <div class="leave-box" style="background-color: #10b981;">
                            <span class="leave-type-name">Compensate Leave</span>
                            <span class="leave-type-limit">As earned</span>
                            <i class="bi bi-calendar"></i>
                        </div>
                    </div>
            </div>
        </div>
        
        <div class="controls">
            <input type="text" class="search-box" placeholder="Search employees..." id="searchInput">
        </div>
        
        <table id="salaryTable">
            <thead>
                <tr>
                        <th>S.No</th>
                        <th>Username</th>
                        <th>Base Salary</th>
                        <th>Working Days</th>
                        <th>Present Days</th>
                        <th>Late Punch In</th>
                        <th>Late Deduction</th>
                        <th>Leave Taken</th>
                        <th>Leave Deduction</th>
                        <th>1Hr Half Days</th>
                        <th>4th Saturday Penalty</th>
                        <th>Monthly Salary</th>
                        <th>Total Overtime Hours</th>
                        <th>Overtime Amount</th>
                        <th>Total Salary</th>
                        <th>Remaining Salary</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                    <?php if (!empty($users)): ?>
                        <?php $serial = 1; ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $serial++; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="editable-cell">
                                    <form method="POST" class="salary-form">
                                        <input type="hidden" name="action" value="update_salary">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="number" name="base_salary" value="<?php echo htmlspecialchars($user['base_salary'] ?? 0); ?>" class="salary-input">
                                        <button type="submit" class="save-btn">Save</button>
                                    </form>
                                </td>
                                <td>
                                    <?php echo $user['working_days_count']; ?> 
                                    <?php if (!empty($user['shift_name']) && !empty($user['weekly_offs'])): ?>
                                        <span class="shift-info" title="<?php echo htmlspecialchars($user['shift_name']); ?>: <?php echo date('h:i A', strtotime($user['start_time'])); ?> - <?php echo date('h:i A', strtotime($user['end_time'])); ?> | Weekly Off: <?php echo htmlspecialchars($user['weekly_offs']); ?>">
                                            <i class="bi bi-info-circle"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['present_days']; ?> 
                                    <span class="attendance-info" data-user-id="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-month="<?php echo $selected_month; ?>">
                                        <i class="bi bi-info-circle"></i>
                                    </span>
                                </td>
                                <td><?php echo $user['late_days']; ?> 
                                    <span class="late-info" data-user-id="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-month="<?php echo $selected_month; ?>" data-shift-start="<?php echo date('h:i A', strtotime($user['start_time'] ?? '09:00:00')); ?>">
                                        <i class="bi bi-exclamation-circle text-warning"></i>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['late_deduction'] > 0): ?>
                                        <span class="text-danger">₹<?php echo $user['late_deduction']; ?></span>
                                        <span class="deduction-info" title="Deduction of <?php echo $user['late_deduction_days']; ?> day(s) salary due to <?php echo $user['late_days']; ?> late punch-ins. Calculated based on <?php echo $user['working_days_count']; ?> working days.">
                                            <i class="bi bi-info-circle"></i>
                                        </span>
                                    <?php else: ?>
                                        ₹0.00
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['total_leave_days'] > 0): ?>
                                        <?php echo $user['total_leave_days']; ?> days
                                        <span class="leave-info" data-user-id="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-month="<?php echo $selected_month; ?>">
                                            <i class="bi bi-calendar-check"></i>
                                        </span>
                                        <div class="leave-types-tooltip">
                                            <?php foreach ($user['leave_policy'] as $type => $max): ?>
                                                <?php 
                                                    $taken = isset($user['leave_taken_map'][$type]) ? $user['leave_taken_map'][$type]['taken'] : 0;
                                                    $color = isset($user['leave_taken_map'][$type]['color']) ? $user['leave_taken_map'][$type]['color'] : '#ddd';
                                                    $max_display = ($max === null) ? 'As earned' : $max . ' days';
                                                ?>
                                                <span class="leave-type" style="background-color: <?php echo $color; ?>">
                                                    <?php echo htmlspecialchars($type); ?>: 
                                                    <span class="leave-usage">
                                                        <?php echo $taken; ?> / <?php echo $max_display; ?>
                                                    </span>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        0 days
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $leave_deduction = 0;
                                    $deduction_explanation = [];
                                    $leave_data = [];
                                    
                                    // Calculate base salary for one day
                                    $daily_salary = $user['working_days_count'] > 0 ? $user['base_salary'] / $user['working_days_count'] : 0;
                                    $half_day_salary = $daily_salary / 2;
                                    
                                    // Process leave deductions based on leave type
                                    if (!empty($user['leave_taken_map'])) {
                                        // Casual Leave - No deduction for first day
                                        if (isset($user['leave_taken_map']['Casual Leave'])) {
                                            $casual_taken = $user['leave_taken_map']['Casual Leave']['taken'];
                                            $casual_max = 1;
                                            
                                            $leave_data['Casual Leave'] = [
                                                'taken' => $casual_taken,
                                                'max' => $casual_max,
                                                'deduction' => 0,
                                                'color' => $user['leave_taken_map']['Casual Leave']['color'] ?? '#4361ee'
                                            ];
                                            
                                            if ($casual_taken > $casual_max) {
                                                $excess_days = $casual_taken - $casual_max;
                                                $casual_deduction = $excess_days * $daily_salary;
                                                $leave_deduction += $casual_deduction;
                                                $deduction_explanation[] = "Excess Casual Leave: $excess_days days";
                                                $leave_data['Casual Leave']['deduction'] = $casual_deduction;
                                                $leave_data['Casual Leave']['excess'] = $excess_days;
                                            }
                                        }
                                        
                                        // Short Leave - Can reduce late days
                                        if (isset($user['leave_taken_map']['Short Leave'])) {
                                            $short_taken = $user['leave_taken_map']['Short Leave']['taken'];
                                            $short_max = 2;
                                            
                                            $leave_data['Short Leave'] = [
                                                'taken' => $short_taken,
                                                'max' => $short_max,
                                                'deduction' => 0,
                                                'color' => $user['leave_taken_map']['Short Leave']['color'] ?? '#0ea5e9'
                                            ];
                                            
                                            // If user has late days and has short leave, reduce late days
                                            if ($user['late_days'] > 0 && $short_taken > 0) {
                                                // Reduce user's late days count by the number of short leaves taken (max 2)
                                                $reduced_late_days = max(0, $user['late_days'] - min($short_taken, $short_max));
                                                $leave_data['Short Leave']['reduced_late_days'] = min($short_taken, $short_max);
                                                
                                                // If there are excess short leaves beyond the max
                                                if ($short_taken > $short_max) {
                                                    $excess_short = $short_taken - $short_max;
                                                    $short_deduction = $excess_short * $half_day_salary;
                                                    $leave_deduction += $short_deduction;
                                                    $deduction_explanation[] = "Excess Short Leave: $excess_short days";
                                                    $leave_data['Short Leave']['deduction'] = $short_deduction;
                                                    $leave_data['Short Leave']['excess'] = $excess_short;
                                                }
                                            }
                                        }
                                        
                                        // Half Day Leave - Deduct half day salary
                                        if (isset($user['leave_taken_map']['Half Day Leave'])) {
                                            $half_day_taken = $user['leave_taken_map']['Half Day Leave']['taken'];
                                            $half_day_deduction = $half_day_taken * $half_day_salary;
                                            $leave_deduction += $half_day_deduction;
                                            
                                            $leave_data['Half Day Leave'] = [
                                                'taken' => $half_day_taken,
                                                'max' => 0,
                                                'deduction' => $half_day_deduction,
                                                'color' => $user['leave_taken_map']['Half Day Leave']['color'] ?? '#f59e0b'
                                            ];
                                            
                                            if ($half_day_taken > 0) {
                                                $deduction_explanation[] = "Half Day Leave: $half_day_taken days";
                                            }
                                        }
                                        
                                        // Sick Leave - No deduction up to 6 days
                                        if (isset($user['leave_taken_map']['Sick Leave'])) {
                                            $sick_taken = $user['leave_taken_map']['Sick Leave']['taken'];
                                            $sick_max = 6;
                                            
                                            $leave_data['Sick Leave'] = [
                                                'taken' => $sick_taken,
                                                'max' => $sick_max,
                                                'deduction' => 0,
                                                'color' => $user['leave_taken_map']['Sick Leave']['color'] ?? '#ef4444'
                                            ];
                                            
                                            if ($sick_taken > $sick_max) {
                                                $excess_days = $sick_taken - $sick_max;
                                                $sick_deduction = $excess_days * $daily_salary;
                                                $leave_deduction += $sick_deduction;
                                                $deduction_explanation[] = "Excess Sick Leave: $excess_days days";
                                                $leave_data['Sick Leave']['deduction'] = $sick_deduction;
                                                $leave_data['Sick Leave']['excess'] = $excess_days;
                                            }
                                        }
                                        
                                        // Emergency Leave - No deduction up to 3 days
                                        if (isset($user['leave_taken_map']['Emergency Leave'])) {
                                            $emergency_taken = $user['leave_taken_map']['Emergency Leave']['taken'];
                                            $emergency_max = 3;
                                            
                                            $leave_data['Emergency Leave'] = [
                                                'taken' => $emergency_taken,
                                                'max' => $emergency_max,
                                                'deduction' => 0,
                                                'color' => $user['leave_taken_map']['Emergency Leave']['color'] ?? '#8b5cf6'
                                            ];
                                            
                                            if ($emergency_taken > $emergency_max) {
                                                $excess_days = $emergency_taken - $emergency_max;
                                                $emergency_deduction = $excess_days * $daily_salary;
                                                $leave_deduction += $emergency_deduction;
                                                $deduction_explanation[] = "Excess Emergency Leave: $excess_days days";
                                                $leave_data['Emergency Leave']['deduction'] = $emergency_deduction;
                                                $leave_data['Emergency Leave']['excess'] = $excess_days;
                                            }
                                        }
                                        
                                        // Paternity Leave - No deduction up to 7 days
                                        if (isset($user['leave_taken_map']['Paternity Leave'])) {
                                            $paternity_taken = $user['leave_taken_map']['Paternity Leave']['taken'];
                                            $paternity_max = 7;
                                            
                                            $leave_data['Paternity Leave'] = [
                                                'taken' => $paternity_taken,
                                                'max' => $paternity_max,
                                                'deduction' => 0,
                                                'color' => $user['leave_taken_map']['Paternity Leave']['color'] ?? '#3b82f6'
                                            ];
                                            
                                            if ($paternity_taken > $paternity_max) {
                                                $excess_days = $paternity_taken - $paternity_max;
                                                $paternity_deduction = $excess_days * $daily_salary;
                                                $leave_deduction += $paternity_deduction;
                                                $deduction_explanation[] = "Excess Paternity Leave: $excess_days days";
                                                $leave_data['Paternity Leave']['deduction'] = $paternity_deduction;
                                                $leave_data['Paternity Leave']['excess'] = $excess_days;
                                            }
                                        }
                                        
                                        // Unpaid Leave - Always deduct
                                        if (isset($user['leave_taken_map']['Unpaid Leave'])) {
                                            $unpaid_taken = $user['leave_taken_map']['Unpaid Leave']['taken'];
                                            $unpaid_deduction = $unpaid_taken * $daily_salary;
                                            
                                            $leave_data['Unpaid Leave'] = [
                                                'taken' => $unpaid_taken,
                                                'max' => 0,
                                                'deduction' => $unpaid_deduction,
                                                'color' => $user['leave_taken_map']['Unpaid Leave']['color'] ?? '#6b7280'
                                            ];
                                            
                                            if ($unpaid_taken > 0) {
                                                $leave_deduction += $unpaid_deduction;
                                                $deduction_explanation[] = "Unpaid Leave: $unpaid_taken days";
                                            }
                                        }
                                    }
                                    
                                    // Round to 2 decimal places
                                    $leave_deduction = round($leave_deduction, 2);
                                    
                                    // Encode leave data for JavaScript
                                    $leave_data_json = htmlspecialchars(json_encode($leave_data), ENT_QUOTES, 'UTF-8');
                                    
                                    if ($leave_deduction > 0):
                                    ?>
                                        <span class="text-danger">₹<?php echo $leave_deduction; ?></span>
                                        <span class="leave-deduction-info" 
                                              data-user-id="<?php echo $user['id']; ?>" 
                                              data-username="<?php echo htmlspecialchars($user['username']); ?>" 
                                              data-month="<?php echo $selected_month; ?>"
                                              data-leave-data="<?php echo $leave_data_json; ?>"
                                              data-daily-salary="<?php echo $daily_salary; ?>">
                                            <i class="bi bi-info-circle"></i>
                                        </span>
                                    <?php else: ?>
                                        ₹0.00
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['half_days'] > 0): ?>
                                        <span class="text-warning"><?php echo $user['half_days']; ?> days</span>
                                        <span class="half-day-info" 
                                              data-user-id="<?php echo $user['id']; ?>" 
                                              data-username="<?php echo htmlspecialchars($user['username']); ?>" 
                                              data-month="<?php echo $selected_month; ?>"
                                              data-shift-start="<?php echo date('h:i A', strtotime($user['start_time'] ?? '09:00:00')); ?>"
                                              data-half-day-dates="<?php echo htmlspecialchars($user['half_day_dates']); ?>"
                                              data-half-day-deduction="<?php echo $user['half_day_deduction']; ?>"
                                              data-daily-salary="<?php echo $daily_salary; ?>">
                                            <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                                        </span>
                                    <?php else: ?>
                                        0 days
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($user['penalty_amount']) && $user['penalty_amount'] > 0): ?>
                                        <span class="text-danger">₹<?php echo $user['penalty_amount']; ?></span>
                                        <span class="penalty-info" 
                                              data-user-id="<?php echo $user['id']; ?>" 
                                              data-username="<?php echo htmlspecialchars($user['username']); ?>" 
                                              data-month="<?php echo $selected_month; ?>"
                                              data-missing-days="<?php echo $user['missing_punch_days']; ?>"
                                              data-penalty-amount="<?php echo $user['penalty_amount']; ?>"
                                              data-fourth-saturday-off="<?php echo $user['fourth_saturday_off'] ? 'yes' : 'no'; ?>"
                                              data-fourth-saturday-date="<?php echo $user['fourth_saturday_date'] ?? ''; ?>"
                                              data-daily-salary="<?php echo $daily_salary; ?>">
                                            <i class="bi bi-exclamation-circle-fill text-danger"></i>
                                        </span>
                                    <?php else: ?>
                                        ₹0.00
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?php echo $user['monthly_salary'] < $user['base_salary'] ? 'text-danger' : 'text-success'; ?>">
                                        ₹<?php echo number_format($user['monthly_salary'], 2); ?>
                                    </span>
                                    <span class="salary-info" 
                                          data-user-id="<?php echo $user['id']; ?>" 
                                          data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                          data-base-salary="<?php echo $user['base_salary']; ?>"
                                          data-present-days-salary="<?php echo $user['present_days_salary']; ?>"
                                          data-absence-deduction="<?php echo $user['absence_deduction']; ?>"
                                          data-late-deduction="<?php echo $user['late_deduction']; ?>"
                                          data-leave-deduction="<?php echo $user['leave_deduction_amount']; ?>"
                                          data-half-day-deduction="<?php echo $user['half_day_deduction']; ?>"
                                          data-penalty-amount="<?php echo $user['penalty_amount'] ?? 0; ?>"
                                          data-monthly-salary="<?php echo $user['monthly_salary']; ?>"
                                          data-total-deductions="<?php echo $user['total_deductions']; ?>"
                                          data-working-days="<?php echo $user['working_days_count']; ?>"
                                          data-present-days="<?php echo $user['present_days']; ?>"
                                          data-late-days="<?php echo $user['late_days']; ?>"
                                          data-half-days="<?php echo $user['half_days']; ?>"
                                          data-missing-days="<?php echo $user['missing_punch_days'] ?? 0; ?>"
                                          data-fourth-saturday-off="<?php echo $user['fourth_saturday_off'] ? 'yes' : 'no'; ?>"
                                          data-fourth-saturday-date="<?php echo $user['fourth_saturday_date'] ?? ''; ?>"
                                          data-daily-salary="<?php echo $user['working_days_count'] > 0 ? $user['base_salary'] / $user['working_days_count'] : 0; ?>">
                                        <i class="bi bi-info-circle"></i>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($user['total_overtime_minutes']) && $user['total_overtime_minutes'] > 0): ?>
                                        <span class="text-primary">
                                            <?php 
                                                // Apply rounding rule to display - round down to nearest 30 minute increment
                                                $display_hours = $user['overtime_hours'];
                                                $display_minutes = $user['overtime_minutes'] < 30 ? 0 : 30;
                                                
                                                if ($display_hours > 0 && $display_minutes > 0) {
                                                    echo $display_hours . ' hrs ' . $display_minutes . ' min';
                                                } elseif ($display_hours > 0) {
                                                    echo $display_hours . ' hrs';
                                                } elseif ($display_minutes > 0) {
                                                    echo $display_minutes . ' min';
                                                } else {
                                                    echo '0 hrs';
                                                }
                                            ?>
                                        </span>
                                        <span class="overtime-info" 
                                              data-user-id="<?php echo $user['id']; ?>" 
                                              data-username="<?php echo htmlspecialchars($user['username']); ?>" 
                                              data-month="<?php echo $selected_month; ?>"
                                              data-total-overtime="<?php echo $user['total_overtime_minutes']; ?>"
                                              data-overtime-hours="<?php echo $user['overtime_hours']; ?>"
                                              data-overtime-minutes="<?php echo $user['overtime_minutes'] < 30 ? 0 : 30; ?>">
                                            <i class="bi bi-clock-history text-primary"></i>
                                        </span>
                                    <?php else: ?>
                                        0 hrs
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($user['overtime_amount']) && $user['overtime_amount'] > 0): ?>
                                        <span class="text-success">
                                            ₹<?php echo number_format($user['overtime_amount'], 2); ?>
                                        </span>
                                        <span class="overtime-amount-info" 
                                              data-user-id="<?php echo $user['id']; ?>" 
                                              data-username="<?php echo htmlspecialchars($user['username']); ?>" 
                                              data-base-salary="<?php echo $user['base_salary']; ?>"
                                              data-working-days="<?php echo $user['working_days_count']; ?>"
                                              data-day-salary="<?php echo $user['working_days_count'] > 0 ? $user['base_salary'] / $user['working_days_count'] : 0; ?>"
                                              data-overtime-hours="<?php echo $user['overtime_hours'] + ($user['overtime_minutes'] < 30 ? 0 : 0.5); ?>"
                                              data-overtime-amount="<?php echo $user['overtime_amount']; ?>">
                                            <i class="bi bi-info-circle text-success"></i>
                                        </span>
                                    <?php else: ?>
                                        ₹0.00
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        // Calculate total salary = monthly salary + overtime amount
                                        $monthly_salary = $user['monthly_salary'] ?? 0;
                                        $overtime_amount = $user['overtime_amount'] ?? 0;
                                        
                                        // Adjust monthly salary for extra days (don't count extra days in current month)
                                        $extra_days = $user['present_days'] - $user['working_days_count'];
                                        $extra_days_salary = 0;
                                        
                                        if ($extra_days > 0) {
                                            $daily_salary = $user['working_days_count'] > 0 ? $user['base_salary'] / $user['working_days_count'] : 0;
                                            $extra_days_salary = $extra_days * $daily_salary;
                                            
                                            // Adjust monthly salary to not include extra days
                                            $monthly_salary = $monthly_salary - $extra_days_salary;
                                        }
                                        
                                        $total_salary = $monthly_salary + $overtime_amount;
                                    ?>
                                    <span class="text-success" style="font-weight: 600;">
                                        ₹<?php echo number_format($total_salary, 2); ?>
                                    </span>
                                    <span class="total-salary-info" 
                                          data-user-id="<?php echo $user['id']; ?>" 
                                          data-username="<?php echo htmlspecialchars($user['username']); ?>" 
                                          data-monthly-salary="<?php echo $monthly_salary; ?>"
                                          data-overtime-amount="<?php echo $overtime_amount; ?>"
                                          data-total-salary="<?php echo $total_salary; ?>">
                                        <i class="bi bi-info-circle text-success"></i>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($user['remaining_salary']) && $user['remaining_salary'] > 0): ?>
                                        <span class="text-primary" style="font-weight: 600;">
                                            ₹<?php echo number_format($user['remaining_salary'], 2); ?>
                                        </span>
                                        <span class="remaining-salary-info" 
                                              data-user-id="<?php echo $user['id']; ?>" 
                                              data-username="<?php echo htmlspecialchars($user['username']); ?>" 
                                              data-working-days="<?php echo $user['working_days_count']; ?>"
                                              data-present-days="<?php echo $user['present_days']; ?>"
                                              data-extra-days="<?php echo $user['present_days'] - $user['working_days_count']; ?>"
                                              data-daily-salary="<?php echo $user['working_days_count'] > 0 ? $user['base_salary'] / $user['working_days_count'] : 0; ?>"
                                              data-remaining-salary="<?php echo $user['remaining_salary']; ?>">
                                            <i class="bi bi-info-circle text-primary"></i>
                                        </span>
                                    <?php else: ?>
                                        ₹0.00
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                              <td colspan="14" style="text-align: center;">No users found</td>
                        </tr>
                    <?php endif; ?>
            </tbody>
        </table>
        
        <div class="pagination" id="pagination">
            <!-- Pagination buttons will be inserted here by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Attendance Details Modal -->
    <div id="attendanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Attendance Details: <span id="modalUsername"></span></h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="attendanceDetails"></div>
            </div>
        </div>
    </div>

    <!-- Late Punch Modal -->
    <div id="latePunchModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Late Punch-In Details: <span id="latePunchUsername"></span></h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="latePunchDetails"></div>
            </div>
        </div>
    </div>
    
    <!-- Leave Deduction Modal -->
    <div id="leaveDeductionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Leave Deduction Details: <span id="leaveDeductionUsername"></span></h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="leaveDeductionDetails"></div>
            </div>
        </div>
    </div>
    
    <!-- Half Day Modal -->
    <div id="halfDayModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">1-Hour Half Day Details: <span id="halfDayUsername"></span></h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="halfDayDetails"></div>
            </div>
        </div>
    </div>
    
    <!-- Salary Breakdown Modal -->
    <div id="salaryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Monthly Salary Breakdown: <span id="salaryUsername"></span></h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="salaryDetails"></div>
            </div>
        </div>
    </div>

    <!-- Missing Punch Penalty Modal -->
    <div id="penaltyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">4th Saturday Missing Penalty: <span id="penaltyUsername"></span></h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="penaltyDetails"></div>
            </div>
        </div>
    </div>

    <!-- Overtime Modal -->
    <div id="overtimeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Overtime Details: <span id="overtimeUsername"></span></h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="overtimeDetails"></div>
            </div>
        </div>
    </div>
    
    <!-- Overtime Amount Modal -->
    <div id="overtimeAmountModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Overtime Amount Calculation: <span id="overtimeAmountUsername"></span></h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="overtimeAmountDetails"></div>
            </div>
        </div>
    </div>
    
    <!-- Total Salary Modal -->
    <div id="totalSalaryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Total Salary Breakdown: <span id="totalSalaryUsername"></span></h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="totalSalaryDetails"></div>
            </div>
        </div>
    </div>
    
    <!-- Remaining Salary Modal -->
    <div id="remainingSalaryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Remaining Salary Details: <span id="remainingSalaryUsername"></span></h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="remainingSalaryDetails"></div>
            </div>
        </div>
    </div>

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
                    
                    const icon = sidebarToggle.querySelector('i');
                    icon.classList.remove('bi-chevron-left');
                    icon.classList.add('bi-chevron-right');
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                    sidebarToggle.classList.remove('collapsed');
                    
                    const icon = sidebarToggle.querySelector('i');
                    icon.classList.remove('bi-chevron-right');
                    icon.classList.add('bi-chevron-left');
                }
            }
            
            // Check on load
            checkWidth();
            
            // Check on resize
            window.addEventListener('resize', checkWidth);

            // Month filter event
            const monthYearFilter = document.getElementById('monthYearFilter');
            
            if (monthYearFilter) {
                monthYearFilter.addEventListener('change', function() {
                    // Update export button URL to include the new month
                    const exportBtn = document.querySelector('.export-btn');
                    if (exportBtn) {
                        exportBtn.href = 'salary.php?month=' + this.value + '&export=csv';
                    }
                    
                    // Update URL with the new month
                    window.location.href = 'salary.php?month=' + this.value;
                });
            }
            
            // Table search functionality
            const searchInput = document.getElementById('searchInput');
            const tableRows = document.querySelectorAll('#tableBody tr');
            
            function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
                
                tableRows.forEach(row => {
                    const username = row.cells[1].textContent.toLowerCase();
                    const salary = row.querySelector('.salary-input')?.value?.toLowerCase() || '';
                    const workingDays = row.cells[3].textContent.toLowerCase();
                    const status = row.cells[4].textContent.toLowerCase();
                    
                const matchesSearch = 
                        username.includes(searchTerm) || 
                        salary.includes(searchTerm) || 
                        workingDays.includes(searchTerm) ||
                        status.includes(searchTerm);
                    
                    if (matchesSearch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            
            if (searchInput) {
                searchInput.addEventListener('input', filterTable);
            }

            // Attendance Modal functionality
            const modal = document.getElementById('attendanceModal');
            const modalUsername = document.getElementById('modalUsername');
            const attendanceDetails = document.getElementById('attendanceDetails');
            const closeModalBtn = document.querySelector('.close-modal');
            
            // Late Punch Modal functionality
            const latePunchModal = document.getElementById('latePunchModal');
            const latePunchUsername = document.getElementById('latePunchUsername');
            const latePunchDetails = document.getElementById('latePunchDetails');
            const closeLatePunchModalBtn = latePunchModal.querySelector('.close-modal');
            
            // Open modal when clicking on attendance info icon
            document.querySelectorAll('.attendance-info').forEach(item => {
                item.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const username = this.getAttribute('data-username');
                    const month = this.getAttribute('data-month');
                    
                    modalUsername.textContent = username + ' - ' + new Date(month + '-01').toLocaleDateString('en-US', {month: 'long', year: 'numeric'});
                    
                    // Show loading spinner
                    attendanceDetails.innerHTML = '<div class="loading-spinner"></div>';
                    modal.style.display = 'block';
                    
                    // Fetch attendance data via AJAX
                    fetch('get_attendance_details.php?user_id=' + userId + '&month=' + month)
                        .then(response => response.json())
                        .then(data => {
                            console.log('Attendance data:', data); // Debug the received data
                            
                            // Log the first record's time details for debugging
                            if (data.length > 0) {
                                console.log('First record time details:', {
                                    punch_in: data[0].punch_in,
                                    punch_in_formatted: data[0].punch_in_formatted,
                                    punch_in_time: data[0].punch_in_time,
                                    punch_out: data[0].punch_out,
                                    punch_out_formatted: data[0].punch_out_formatted,
                                    punch_out_time: data[0].punch_out_time
                                });
                            }
                            
                            if (data.length > 0) {
                                // Create table with attendance details
                                let tableHtml = '<table class="modal-table">';
                                tableHtml += '<thead><tr><th>Date</th><th>Day</th><th>Status</th><th>Punch In</th><th>Punch Out</th><th>Working Hours</th></tr></thead>';
                                tableHtml += '<tbody>';
                                
                                data.forEach(record => {
                                    let formattedDate = '-';
                                    let dayName = '-';
                                    
                                    try {
                                        const date = new Date(record.date);
                                        if (!isNaN(date.getTime())) {
                                            formattedDate = date.toLocaleDateString('en-US', {day: '2-digit', month: 'short'});
                                            dayName = date.toLocaleDateString('en-US', {weekday: 'short'});
                                        }
                                    } catch (e) {
                                        console.error('Error parsing date:', e);
                                    }
                                    
                                    // Format times for display - handle null or invalid formats properly
                                    let punchIn = '-';
                                    let punchOut = '-';
                                    
                                    // First try using the pre-formatted time strings from the server
                                    if (record.punch_in_time) {
                                        // Format the time from HH:MM:SS to a more readable format
                                        try {
                                            // For TIME() format like "09:15:00"
                                            const timeParts = record.punch_in_time.split(':');
                                            if (timeParts.length >= 2) {
                                                const hours = parseInt(timeParts[0]);
                                                const minutes = timeParts[1];
                                                const ampm = hours >= 12 ? 'PM' : 'AM';
                                                const hours12 = hours % 12 || 12; // Convert to 12-hour format
                                                punchIn = `${hours12}:${minutes} ${ampm}`;
                                            } else {
                                                punchIn = record.punch_in_time;
                                            }
                                        } catch (e) {
                                            console.error('Error formatting punch_in_time:', e);
                                            punchIn = record.punch_in_time;
                                        }
                                    } else if (record.punch_in_formatted && record.punch_in_formatted !== '0000-00-00 00:00:00') {
                                        try {
                                            const punchInDate = new Date(record.punch_in_formatted);
                                            if (!isNaN(punchInDate.getTime())) {
                                                punchIn = punchInDate.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                                            }
                                        } catch (e) {
                                            console.error('Error parsing punch in time:', e);
                                        }
                                    } else if (record.punch_in && record.punch_in !== '0000-00-00 00:00:00') {
                                        try {
                                            const punchInDate = new Date(record.punch_in);
                                            if (!isNaN(punchInDate.getTime())) {
                                                punchIn = punchInDate.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                                            }
                                        } catch (e) {
                                            console.error('Error parsing punch in time:', e);
                                        }
                                    }
                                    
                                    if (record.punch_out_time) {
                                        // Format the time from HH:MM:SS to a more readable format
                                        try {
                                            // For TIME() format like "17:30:00"
                                            const timeParts = record.punch_out_time.split(':');
                                            if (timeParts.length >= 2) {
                                                const hours = parseInt(timeParts[0]);
                                                const minutes = timeParts[1];
                                                const ampm = hours >= 12 ? 'PM' : 'AM';
                                                const hours12 = hours % 12 || 12; // Convert to 12-hour format
                                                punchOut = `${hours12}:${minutes} ${ampm}`;
                                            } else {
                                                punchOut = record.punch_out_time;
                                            }
                                        } catch (e) {
                                            console.error('Error formatting punch_out_time:', e);
                                            punchOut = record.punch_out_time;
                                        }
                                    } else if (record.punch_out_formatted && record.punch_out_formatted !== '0000-00-00 00:00:00') {
                                        try {
                                            const punchOutDate = new Date(record.punch_out_formatted);
                                            if (!isNaN(punchOutDate.getTime())) {
                                                punchOut = punchOutDate.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                                            }
                                        } catch (e) {
                                            console.error('Error parsing punch out time:', e);
                                        }
                                    } else if (record.punch_out && record.punch_out !== '0000-00-00 00:00:00') {
                                        try {
                                            const punchOutDate = new Date(record.punch_out);
                                            if (!isNaN(punchOutDate.getTime())) {
                                                punchOut = punchOutDate.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                                            }
                                        } catch (e) {
                                            console.error('Error parsing punch out time:', e);
                                        }
                                    }
                                    
                                    // Format status with class for color
                                    let statusClass = '';
                                    let statusText = record.status ? record.status.charAt(0).toUpperCase() + record.status.slice(1) : 'Not Recorded';
                                    
                                    if (record.status === 'present') statusClass = 'status-present';
                                    else if (record.status === 'absent') statusClass = 'status-absent';
                                    else if (record.status === 'leave') statusClass = 'status-leave';
                                    else if (record.status === 'not recorded') statusClass = '';
                                    
                                    // Handle weekly off
                                    if (record.is_weekly_off == 1) {
                                        statusText = 'Weekly Off';
                                        statusClass = 'status-weekly-off';
                                    }
                                    
                                    tableHtml += `<tr>
                                        <td>${formattedDate}</td>
                                        <td>${dayName}</td>
                                        <td class="${statusClass}">${statusText}</td>
                                        <td>${punchIn}</td>
                                        <td>${punchOut}</td>
                                        <td>${record.working_hours || '-'}</td>
                                    </tr>`;
                                });
                                
                                tableHtml += '</tbody></table>';
                                attendanceDetails.innerHTML = tableHtml;
                            } else {
                                attendanceDetails.innerHTML = '<div class="no-data">No attendance records found for this month.</div>';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching attendance data:', error);
                            attendanceDetails.innerHTML = '<div class="no-data">Error loading attendance data. Please try again.</div>';
                        });
                });
            });
            
            // Close modal when clicking the close button
            closeModalBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            // Open late punch modal when clicking on late info icon
            document.querySelectorAll('.late-info').forEach(item => {
                item.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const username = this.getAttribute('data-username');
                    const month = this.getAttribute('data-month');
                    const shiftStart = this.getAttribute('data-shift-start');
                    
                    latePunchUsername.textContent = username + ' - ' + new Date(month + '-01').toLocaleDateString('en-US', {month: 'long', year: 'numeric'});
                    
                    // Show loading spinner
                    latePunchDetails.innerHTML = '<div class="loading-spinner"></div>';
                    latePunchModal.style.display = 'block';
                                    
                                    // Add explanation about late punch categorization
                                    let explanationHtml = `
                                        <div class="late-punch-explanation">
                                            <p><strong>Note:</strong> Late punch-ins are categorized as follows:</p>
                                            <ul>
                                                <li><strong>Late Punch:</strong> Between 15 minutes and 1 hour after shift start</li>
                                                <li><strong>Half Day:</strong> More than 1 hour after shift start (shown in the "1Hr Half Days" column)</li>
                                            </ul>
                                        </div>
                                    `;
                    
                    // Fetch late punch data via AJAX
                    fetch('get_late_punch_details.php?user_id=' + userId + '&month=' + month)
                        .then(response => response.json())
                        .then(data => {
                            console.log('Late punch data:', data); // Debug the received data
                            
                                                                    // Add the explanation first
                                        latePunchDetails.innerHTML = explanationHtml;
                            
                            if (data.length > 0) {
                                // Create table with late punch details
                                let tableHtml = '<table class="modal-table">';
                                tableHtml += '<thead><tr><th>Date</th><th>Day</th><th>Shift Start</th><th>Grace Period</th><th>Punch In</th><th>Late By</th></tr></thead>';
                                tableHtml += '<tbody>';
                                
                                data.forEach(record => {
                                    try {
                                        const date = new Date(record.date);
                                        const formattedDate = date.toLocaleDateString('en-US', {day: '2-digit', month: 'short'});
                                        const dayName = date.toLocaleDateString('en-US', {weekday: 'short'});
                                        
                                        const shiftStartTime = record.shift_start_time || shiftStart;
                                        const graceEndTime = record.grace_end_time;
                                        const punchInTime = record.punch_in_time;
                                        
                                        // Calculate how late they were
                                        let lateBy = record.late_by || 'Unknown';
                                        
                                        tableHtml += `<tr>
                                            <td>${formattedDate}</td>
                                            <td>${dayName}</td>
                                            <td>${shiftStartTime}</td>
                                            <td>${graceEndTime}</td>
                                            <td class="text-warning">${punchInTime}</td>
                                            <td class="text-danger">${lateBy}</td>
                                        </tr>`;
                                    } catch (e) {
                                        console.error('Error processing late punch record:', e);
                                    }
                                });
                                
                                tableHtml += '</tbody></table>';
                                            latePunchDetails.innerHTML = explanationHtml + tableHtml;
                            } else {
                                            latePunchDetails.innerHTML = explanationHtml + '<div class="no-data">No late punch-in records found for this month.</div>';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching late punch data:', error);
                            latePunchDetails.innerHTML = '<div class="no-data">Error loading late punch data. Please try again.</div>';
                        });
                });
            });
            
            // Close late punch modal when clicking the close button
            closeLatePunchModalBtn.addEventListener('click', function() {
                latePunchModal.style.display = 'none';
            });
            
            // Leave Deduction Modal functionality
            const leaveDeductionModal = document.getElementById('leaveDeductionModal');
            const leaveDeductionUsername = document.getElementById('leaveDeductionUsername');
            const leaveDeductionDetails = document.getElementById('leaveDeductionDetails');
            const closeLeaveDeductionModalBtn = leaveDeductionModal.querySelector('.close-modal');
            
            // Open leave deduction modal when clicking on leave deduction info icon
            document.querySelectorAll('.leave-deduction-info').forEach(item => {
                item.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const username = this.getAttribute('data-username');
                    const month = this.getAttribute('data-month');
                    const leaveData = JSON.parse(this.getAttribute('data-leave-data'));
                    const dailySalary = parseFloat(this.getAttribute('data-daily-salary'));
                    
                    leaveDeductionUsername.textContent = username + ' - ' + new Date(month + '-01').toLocaleDateString('en-US', {month: 'long', year: 'numeric'});
                    
                    // Show loading spinner
                    leaveDeductionDetails.innerHTML = '<div class="loading-spinner"></div>';
                    leaveDeductionModal.style.display = 'block';
                    
                    // Fetch leave details from API
                    fetch('get_attendance_details.php?user_id=' + userId + '&month=' + month)
                        .then(response => response.json())
                        .then(attendanceData => {
                            console.log('Attendance data for leave details:', attendanceData);
                            
                            // Create HTML for leave deduction details
                            let detailsHtml = '';
                            
                            // Add summary section
                            detailsHtml += '<div class="leave-deduction-summary">';
                            detailsHtml += '<h4>Leave Deduction Summary</h4>';
                            detailsHtml += '<div class="leave-deduction-cards">';
                            
                            let totalDeduction = 0;
                            
                            // Add leave type cards with deduction info
                            for (const [leaveType, data] of Object.entries(leaveData)) {
                                if (data.taken > 0) {
                                    let deductionAmount = parseFloat(data.deduction) || 0;
                                    totalDeduction += deductionAmount;
                                    
                                    detailsHtml += `
                                        <div class="leave-deduction-card" style="background-color: ${data.color}">
                                            <div class="leave-type-name">${leaveType}</div>
                                            <div class="leave-details">
                                                <div>Taken: ${data.taken} days</div>
                                                <div>Max Allowed: ${data.max} days</div>
                                                ${data.excess ? `<div>Excess: ${data.excess} days</div>` : ''}
                                                ${data.reduced_late_days ? `<div>Reduced Late Days: ${data.reduced_late_days}</div>` : ''}
                                                <div class="leave-deduction-amount">
                                                    ${deductionAmount > 0 
                                                    ? `Deduction: ₹${deductionAmount.toFixed(2)}` 
                                                    : 'No Deduction'}
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                }
                            }
                            
                            detailsHtml += '</div>'; // close leave-deduction-cards
                            
                            // Add total deduction
                            detailsHtml += `
                                <div class="total-deduction">
                                    <h4>Total Deduction: ₹${totalDeduction.toFixed(2)}</h4>
                                    <p>Daily Salary: ₹${dailySalary.toFixed(2)}</p>
                                </div>
                            `;
                            
                            detailsHtml += '</div>'; // close leave-deduction-summary
                            
                            // Add detailed leave records table if attendance data available
                            if (attendanceData.length > 0) {
                                // Filter attendance records to only show leave days
                                const leaveRecords = attendanceData.filter(record => 
                                    record.status === 'leave' || record.leave_type !== null
                                );
                                
                                if (leaveRecords.length > 0) {
                                    detailsHtml += '<h4>Leave Days Detail</h4>';
                                    detailsHtml += '<table class="modal-table">';
                                    detailsHtml += '<thead><tr><th>Date</th><th>Day</th><th>Leave Type</th><th>Status</th><th>Deduction</th></tr></thead>';
                                    detailsHtml += '<tbody>';
                                    
                                    leaveRecords.forEach(record => {
                                        try {
                                            const date = new Date(record.date);
                                            const formattedDate = date.toLocaleDateString('en-US', {day: '2-digit', month: 'short'});
                                            const dayName = date.toLocaleDateString('en-US', {weekday: 'short'});
                                            
                                            // Determine leave type and deduction status
                                            const leaveType = record.leave_type_name || (record.status === 'leave' ? 'Unspecified Leave' : 'No Leave');
                                            let deductionStatus = 'No Deduction';
                                            let statusClass = '';
                                            
                                            // Find the leave type in our leave data
                                            const matchedLeaveType = Object.entries(leaveData).find(([type]) => 
                                                type.toLowerCase() === leaveType.toLowerCase()
                                            );
                                            
                                            if (matchedLeaveType) {
                                                const [typeName, typeData] = matchedLeaveType;
                                                
                                                // Determine if this specific day has a deduction
                                                if (
                                                    (typeName === 'Half Day Leave') || 
                                                    (typeName === 'Unpaid Leave') ||
                                                    (typeName === 'Casual Leave' && typeData.taken > typeData.max) ||
                                                    (typeName === 'Sick Leave' && typeData.taken > typeData.max) ||
                                                    (typeName === 'Emergency Leave' && typeData.taken > typeData.max) ||
                                                    (typeName === 'Paternity Leave' && typeData.taken > typeData.max)
                                                ) {
                                                    // For excess days, we need to determine if this specific day causes a deduction
                                                    // This is simplified logic - in a real implementation, you'd need to know the exact order
                                                    // of leave days to determine which ones incur deductions
                                                    
                                                    if (typeName === 'Half Day Leave') {
                                                        deductionStatus = `₹${(dailySalary/2).toFixed(2)}`;
                                                        statusClass = 'text-danger';
                                                    } else if (typeName === 'Unpaid Leave') {
                                                        deductionStatus = `₹${dailySalary.toFixed(2)}`;
                                                        statusClass = 'text-danger';
                                                    } else {
                                                        // For excess leave types, show deduction only for days beyond the max allowed
                                                        const index = leaveRecords.filter(r => 
                                                            r.leave_type_name && r.leave_type_name.toLowerCase() === leaveType.toLowerCase()
                                                        ).indexOf(record);
                                                        
                                                        if (index >= typeData.max) {
                                                            deductionStatus = `₹${dailySalary.toFixed(2)}`;
                                                            statusClass = 'text-danger';
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            // Determine leave type display and styling
                                            let leaveTypeDisplay = '';
                                            if (leaveType === 'No Leave') {
                                                leaveTypeDisplay = `<span class="leave-type-badge leave-type-no-leave">${leaveType}</span>`;
                                            } else if (leaveType === 'Unspecified Leave') {
                                                leaveTypeDisplay = `<span class="leave-type-badge leave-type-unspecified">${leaveType}</span>`;
                                            } else {
                                                // Use the leave color if available
                                                const bgColor = record.leave_color || '#6c757d';
                                                leaveTypeDisplay = `<span class="leave-type-badge" style="background-color: ${bgColor}">${leaveType}</span>`;
                                            }
                                            
                                            detailsHtml += `<tr>
                                                <td>${formattedDate}</td>
                                                <td>${dayName}</td>
                                                <td>${leaveTypeDisplay}</td>
                                                <td class="status-leave">${record.status === 'leave' ? 'Leave' : record.status || 'Not Recorded'}</td>
                                                <td class="${statusClass}">${deductionStatus}</td>
                                            </tr>`;
                                        } catch (e) {
                                            console.error('Error processing leave record:', e);
                                        }
                                    });
                                    
                                    detailsHtml += '</tbody></table>';
                                } else {
                                    detailsHtml += '<div class="no-data">No specific leave days found in attendance records.</div>';
                                }
                            } else {
                                detailsHtml += '<div class="no-data">No attendance records available to show leave details.</div>';
                            }
                            
                            // Set the HTML
                            leaveDeductionDetails.innerHTML = detailsHtml;
                        })
                        .catch(error => {
                            console.error('Error fetching leave deduction data:', error);
                            leaveDeductionDetails.innerHTML = '<div class="no-data">Error loading leave deduction data. Please try again.</div>';
                        });
                });
            });
            
            // Close leave deduction modal when clicking the close button
            closeLeaveDeductionModalBtn.addEventListener('click', function() {
                leaveDeductionModal.style.display = 'none';
            });
            
            // Half Day Modal functionality
            const halfDayModal = document.getElementById('halfDayModal');
            const halfDayUsername = document.getElementById('halfDayUsername');
            const halfDayDetails = document.getElementById('halfDayDetails');
            const closeHalfDayModalBtn = halfDayModal.querySelector('.close-modal');
            
            // Open half day modal when clicking on half day info icon
            document.querySelectorAll('.half-day-info').forEach(item => {
                item.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const username = this.getAttribute('data-username');
                    const month = this.getAttribute('data-month');
                    const shiftStart = this.getAttribute('data-shift-start');
                    const halfDayDates = this.getAttribute('data-half-day-dates') || '';
                    const halfDayDeduction = this.getAttribute('data-half-day-deduction') || '0';
                    const dailySalary = parseFloat(this.getAttribute('data-daily-salary')) || 0;
                    
                    halfDayUsername.textContent = username + ' - ' + new Date(month + '-01').toLocaleDateString('en-US', {month: 'long', year: 'numeric'});
                    
                    // Show loading spinner
                    halfDayDetails.innerHTML = '<div class="loading-spinner"></div>';
                    halfDayModal.style.display = 'block';
                    
                    // Fetch late punch details from API for additional info
                    fetch('get_late_punch_details.php?user_id=' + userId + '&month=' + month)
                        .then(response => response.json())
                        .then(lateData => {
                            console.log('Late data for half day details:', lateData);
                            
                            // Create HTML for half day deduction details
                            let detailsHtml = '';
                            
                            // Add summary section
                            detailsHtml += '<div class="half-day-summary">';
                            detailsHtml += '<h4>1-Hour Half Day Summary</h4>';
                            detailsHtml += '<p>When an employee is more than 1 hour late, it counts as a half day and incurs a half-day salary deduction.</p>';
                            
                            // Add deduction info
                            detailsHtml += `
                                <div class="total-deduction">
                                    <h4>Total Half Days: ${halfDayDates.split(',').length > 1 ? halfDayDates.split(',').length : (halfDayDates ? 1 : 0)} days</h4>
                                    <p>Half Day Deduction: ₹${parseFloat(halfDayDeduction).toFixed(2)}</p>
                                    <p>Half Day Salary: ₹${(dailySalary / 2).toFixed(2)}</p>
                                </div>
                            `;
                            
                            detailsHtml += '</div>'; // close half-day-summary
                            
                            // Add detailed half day records table if data available
                            if (lateData && lateData.length > 0) {
                                // Filter records to only show those more than 1 hour late
                                const halfDayRecords = lateData.filter(record => {
                                    if (!record.punch_in_time || !record.shift_start_time) return false;
                                    
                                    // This is a rough check - we rely on the server-side query for accurate filtering
                                    // Here we're just checking if the record dates match our half day dates
                                    const recordDate = new Date(record.date).toISOString().split('T')[0];
                                    return halfDayDates.includes(recordDate);
                                });
                                
                                if (halfDayRecords.length > 0) {
                                    detailsHtml += '<h4>Half Day Details</h4>';
                                    detailsHtml += '<table class="modal-table">';
                                    detailsHtml += '<thead><tr><th>Date</th><th>Day</th><th>Shift Start</th><th>Punch In</th><th>Late By</th><th>Deduction</th></tr></thead>';
                                    detailsHtml += '<tbody>';
                                    
                                    halfDayRecords.forEach(record => {
                                        try {
                                            const date = new Date(record.date);
                                            const formattedDate = date.toLocaleDateString('en-US', {day: '2-digit', month: 'short'});
                                            const dayName = date.toLocaleDateString('en-US', {weekday: 'short'});
                                            
                                            detailsHtml += `<tr>
                                                <td>${formattedDate}</td>
                                                <td>${dayName}</td>
                                                <td>${record.shift_start_time || shiftStart}</td>
                                                <td class="text-danger">${record.punch_in_time}</td>
                                                <td class="text-danger">${record.late_by || '> 1 hour'}</td>
                                                <td class="text-danger">₹${(dailySalary / 2).toFixed(2)}</td>
                                            </tr>`;
                                        } catch (e) {
                                            console.error('Error processing half day record:', e);
                                        }
                                    });
                                    
                                    detailsHtml += '</tbody></table>';
                                } else {
                                    // If no matches in the late data, create a table based on the half day dates
                                    if (halfDayDates) {
                                        const dates = halfDayDates.split(',');
                                        
                                        detailsHtml += '<h4>Half Day Details</h4>';
                                        detailsHtml += '<table class="modal-table">';
                                        detailsHtml += '<thead><tr><th>Date</th><th>Day</th><th>Deduction</th></tr></thead>';
                                        detailsHtml += '<tbody>';
                                        
                                        dates.forEach(dateStr => {
                                            try {
                                                const date = new Date(dateStr);
                                                const formattedDate = date.toLocaleDateString('en-US', {day: '2-digit', month: 'short'});
                                                const dayName = date.toLocaleDateString('en-US', {weekday: 'short'});
                                                
                                                detailsHtml += `<tr>
                                                    <td>${formattedDate}</td>
                                                    <td>${dayName}</td>
                                                    <td class="text-danger">₹${(dailySalary / 2).toFixed(2)}</td>
                                                </tr>`;
                                            } catch (e) {
                                                console.error('Error processing half day date:', e);
                                            }
                                        });
                                        
                                        detailsHtml += '</tbody></table>';
                                    }
                                }
                            } else if (halfDayDates) {
                                // If no late data available but we have dates, create a simple table
                                const dates = halfDayDates.split(',');
                                
                                detailsHtml += '<h4>Half Day Details</h4>';
                                detailsHtml += '<table class="modal-table">';
                                detailsHtml += '<thead><tr><th>Date</th><th>Day</th><th>Deduction</th></tr></thead>';
                                detailsHtml += '<tbody>';
                                
                                dates.forEach(dateStr => {
                                    try {
                                        const date = new Date(dateStr);
                                        const formattedDate = date.toLocaleDateString('en-US', {day: '2-digit', month: 'short'});
                                        const dayName = date.toLocaleDateString('en-US', {weekday: 'short'});
                                        
                                        detailsHtml += `<tr>
                                            <td>${formattedDate}</td>
                                            <td>${dayName}</td>
                                            <td class="text-danger">₹${(dailySalary / 2).toFixed(2)}</td>
                                        </tr>`;
                                    } catch (e) {
                                        console.error('Error processing half day date:', e);
                                    }
                                });
                                
                                detailsHtml += '</tbody></table>';
                            } else {
                                detailsHtml += '<div class="no-data">No half day records found for this month.</div>';
                            }
                            
                            // Set the HTML
                            halfDayDetails.innerHTML = detailsHtml;
                        })
                        .catch(error => {
                            console.error('Error fetching half day data:', error);
                            halfDayDetails.innerHTML = '<div class="no-data">Error loading half day data. Please try again.</div>';
                        });
                });
            });
            
            // Close half day modal when clicking the close button
            closeHalfDayModalBtn.addEventListener('click', function() {
                halfDayModal.style.display = 'none';
            });
            
            // Salary Modal functionality
            const salaryModal = document.getElementById('salaryModal');
            const salaryUsername = document.getElementById('salaryUsername');
            const salaryDetails = document.getElementById('salaryDetails');
            const closeSalaryModalBtn = salaryModal.querySelector('.close-modal');
            
            // Open salary modal when clicking on salary info icon
            document.querySelectorAll('.salary-info').forEach(item => {
                item.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const username = this.getAttribute('data-username');
                    const baseSalary = parseFloat(this.getAttribute('data-base-salary')) || 0;
                    const presentDaysSalary = parseFloat(this.getAttribute('data-present-days-salary')) || 0;
                    const absenceDeduction = parseFloat(this.getAttribute('data-absence-deduction')) || 0;
                    const lateDeduction = parseFloat(this.getAttribute('data-late-deduction')) || 0;
                    const leaveDeduction = parseFloat(this.getAttribute('data-leave-deduction')) || 0;
                    const halfDayDeduction = parseFloat(this.getAttribute('data-half-day-deduction')) || 0;
                    const penaltyAmount = parseFloat(this.getAttribute('data-penalty-amount')) || 0;
                    const monthlySalary = parseFloat(this.getAttribute('data-monthly-salary')) || 0;
                    const totalDeductions = parseFloat(this.getAttribute('data-total-deductions')) || 0;
                    const workingDays = parseInt(this.getAttribute('data-working-days')) || 0;
                    const presentDays = parseInt(this.getAttribute('data-present-days')) || 0;
                    const lateDays = parseInt(this.getAttribute('data-late-days')) || 0;
                    const halfDays = parseInt(this.getAttribute('data-half-days')) || 0;
                    const missingDays = parseInt(this.getAttribute('data-missing-days')) || 0;
                    const fourthSaturdayOff = this.getAttribute('data-fourth-saturday-off') === 'yes';
                    const fourthSaturdayDate = this.getAttribute('data-fourth-saturday-date');
                    const dailySalary = parseFloat(this.getAttribute('data-daily-salary')) || 0;
                    
                    salaryUsername.textContent = username;
                    
                    // Create the salary breakdown details
                    let detailsHtml = '';
                    
                    // Add salary card
                    detailsHtml += `
                        <div class="salary-summary">
                            <div class="salary-card base-salary">
                                <h4>Base Salary</h4>
                                <div class="amount">₹${baseSalary.toFixed(2)}</div>
                                <div class="details">Daily rate: ₹${dailySalary.toFixed(2)} × ${workingDays} working days</div>
                            </div>
                            
                            <div class="salary-card base-salary" style="border-left: 4px solid #3b82f6;">
                                <h4>Present Days Salary</h4>
                                <div class="amount">₹${presentDaysSalary.toFixed(2)}</div>
                                <div class="details">Daily rate: ₹${dailySalary.toFixed(2)} × ${presentDays} present days</div>
                            </div>
                            
                            <div class="salary-card deductions">
                                <h4>Deductions</h4>
                                <div class="deduction-item ${absenceDeduction > 0 ? 'has-deduction' : ''}">
                                    <div class="deduction-label">Absence Deduction:</div>
                                    <div class="deduction-amount">-₹${absenceDeduction.toFixed(2)}</div>
                                    <div class="deduction-details">${workingDays - presentDays} absent days</div>
                                </div>
                                
                                <div class="deduction-item ${lateDeduction > 0 ? 'has-deduction' : ''}">
                                    <div class="deduction-label">Late Punch-in Deduction:</div>
                                    <div class="deduction-amount">-₹${lateDeduction.toFixed(2)}</div>
                                    <div class="deduction-details">${lateDays} late days (15 min - 1 hour)</div>
                                </div>
                                
                                <div class="deduction-item ${halfDayDeduction > 0 ? 'has-deduction' : ''}">
                                    <div class="deduction-label">Half Day Deduction:</div>
                                    <div class="deduction-amount">-₹${halfDayDeduction.toFixed(2)}</div>
                                    <div class="deduction-details">${halfDays} half days (>1 hour late)</div>
                                </div>
                                
                                <div class="deduction-item ${leaveDeduction > 0 ? 'has-deduction' : ''}">
                                    <div class="deduction-label">Leave Deduction:</div>
                                    <div class="deduction-amount">-₹${leaveDeduction.toFixed(2)}</div>
                                    <div class="deduction-details">Unpaid/excess leave deductions</div>
                                </div>
                                
                                <div class="deduction-item ${penaltyAmount > 0 ? 'has-deduction' : ''}">
                                    <div class="deduction-label">4th Saturday Missing Penalty:</div>
                                    <div class="deduction-amount">-₹${penaltyAmount.toFixed(2)}</div>
                                    <div class="deduction-details">${missingDays} days with missing punch-in records (3-day penalty)</div>
                                </div>
                                
                                <div class="deduction-total">
                                    <div class="deduction-label">Total Deductions:</div>
                                    <div class="deduction-amount">-₹${(lateDeduction + halfDayDeduction + leaveDeduction + penaltyAmount).toFixed(2)}</div>
                                    <div class="deduction-details">(Excluding absence deduction)</div>
                                </div>
                            </div>
                            
                            <div class="salary-card final-salary">
                                <h4>Final Monthly Salary</h4>
                                <div class="amount">₹${monthlySalary.toFixed(2)}</div>
                                <div class="calculation">₹${presentDaysSalary.toFixed(2)} - ₹${(lateDeduction + halfDayDeduction + leaveDeduction + penaltyAmount).toFixed(2)}</div>
                            </div>
                        </div>
                        
                        <div class="attendance-summary">
                            <h4>Attendance Summary</h4>
                            <div class="attendance-stats">
                                <div class="stat-item">
                                    <div class="stat-label">Working Days:</div>
                                    <div class="stat-value">${workingDays} days</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Present Days:</div>
                                    <div class="stat-value">${presentDays} days</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Attendance Rate:</div>
                                    <div class="stat-value">${workingDays > 0 ? Math.round((presentDays / workingDays) * 100) : 0}%</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="attendance-summary" style="margin-top: 15px;">
                            <h4>Additional Information</h4>
                            <div class="attendance-stats">
                                <div class="stat-item">
                                    <div class="stat-label">4th Saturday Off:</div>
                                    <div class="stat-value" style="color: ${fourthSaturdayOff ? '#10B981' : '#6B7280'}">
                                        ${fourthSaturdayOff ? 'Yes' : 'No'}
                                        ${fourthSaturdayOff && fourthSaturdayDate ? ' (' + new Date(fourthSaturdayDate).toLocaleDateString('en-US', {day: '2-digit', month: 'short'}) + ')' : ''}
                                    </div>
                                </div>
                                <div class="stat-item ${missingDays > 0 ? 'has-deduction' : ''}">
                                    <div class="stat-label">4th Saturday Missing:</div>
                                    <div class="stat-value" style="color: ${missingDays > 0 ? '#EF4444' : '#10B981'}">
                                        ${missingDays} days
                                        ${missingDays > 0 ? ' <i class="bi bi-exclamation-circle-fill"></i>' : ''}
                                    </div>
                                </div>
                                <div class="stat-item ${missingDays > 0 ? 'has-deduction' : ''}">
                                    <div class="stat-label">4th Saturday Missing:</div>
                                    <div class="stat-value" style="color: ${missingDays > 0 ? '#EF4444' : '#10B981'}">
                                        ${missingDays} days
                                        ${missingDays > 0 ? ' <i class="bi bi-exclamation-circle-fill"></i>' : ''}
                                    </div>
                                </div>
                            </div>
                            ${missingDays > 0 ? `
                            <div class="late-punch-explanation" style="margin-top: 15px; background-color: #FEE2E2; border-left: 4px solid #EF4444;">
                                <p><strong>4th Saturday Missing Policy:</strong> A penalty of 3 days' salary (₹${(dailySalary * 3).toFixed(2)}) is applied when an employee has missing punch-in records.</p>
                            </div>
                            ` : ''}
                        </div>
                    `;
                    
                    // Display the details
                    salaryDetails.innerHTML = detailsHtml;
                    salaryModal.style.display = 'block';
                });
            });
            
            // Close salary modal when clicking the close button
            closeSalaryModalBtn.addEventListener('click', function() {
                salaryModal.style.display = 'none';
            });
            
            // Close modals when clicking outside of them
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
                if (event.target === latePunchModal) {
                    latePunchModal.style.display = 'none';
                }
                if (event.target === leaveDeductionModal) {
                    leaveDeductionModal.style.display = 'none';
                }
                if (event.target === halfDayModal) {
                    halfDayModal.style.display = 'none';
                }
                if (event.target === salaryModal) {
                    salaryModal.style.display = 'none';
                }
                if (event.target === penaltyModal) {
                    penaltyModal.style.display = 'none';
                }
                if (event.target === overtimeModal) {
                    overtimeModal.style.display = 'none';
                }
            });
            
            // Penalty Modal functionality
            const penaltyModal = document.getElementById('penaltyModal');
            const penaltyUsername = document.getElementById('penaltyUsername');
            const penaltyDetails = document.getElementById('penaltyDetails');
            const closePenaltyModalBtn = penaltyModal.querySelector('.close-modal');
            
            // Open penalty modal when clicking on penalty info icon
            document.querySelectorAll('.penalty-info').forEach(item => {
                item.addEventListener('click', function() {
                    const username = this.getAttribute('data-username');
                    const month = this.getAttribute('data-month');
                    const missingDays = parseInt(this.getAttribute('data-missing-days')) || 0;
                    const penaltyAmount = parseFloat(this.getAttribute('data-penalty-amount')) || 0;
                    const dailySalary = parseFloat(this.getAttribute('data-daily-salary')) || 0;
                    const fourthSaturdayOff = this.getAttribute('data-fourth-saturday-off') === 'yes';
                    const fourthSaturdayDate = this.getAttribute('data-fourth-saturday-date');
                    
                    penaltyUsername.textContent = username + ' - ' + new Date(month + '-01').toLocaleDateString('en-US', {month: 'long', year: 'numeric'});
                    
                    // Create HTML for penalty details
                    let detailsHtml = '';
                    
                    // Add summary section with 4th Saturday status included
                    detailsHtml += '<div class="penalty-summary">';
                    detailsHtml += '<h4>4th Saturday Missing Penalty Summary</h4>';
                    
                    // Add 4th Saturday Status in a visually appealing way
                    let saturdayDate = '';
                    if (fourthSaturdayDate) {
                        try {
                            saturdayDate = ' (' + new Date(fourthSaturdayDate).toLocaleDateString('en-US', {day: '2-digit', month: 'short'}) + ')';
                        } catch (e) {
                            saturdayDate = '';
                        }
                    }
                    
                    detailsHtml += `
                        <div style="display: flex; margin-bottom: 15px; background-color: ${fourthSaturdayOff ? '#ECFDF5' : '#FEF2F2'}; padding: 15px; border-radius: 8px; border-left: 5px solid ${fourthSaturdayOff ? '#10B981' : '#EF4444'};">
                            <div style="flex: 1;">
                                <h5 style="margin: 0 0 8px 0; color: ${fourthSaturdayOff ? '#047857' : '#B91C1C'};">4th Saturday Status${saturdayDate}</h5>
                                <div style="font-size: 16px; font-weight: 600; color: ${fourthSaturdayOff ? '#10B981' : '#EF4444'};">
                                    ${fourthSaturdayOff ? 
                                        '<i class="bi bi-check-circle-fill"></i> Marked as Off' : 
                                        '<i class="bi bi-x-circle-fill"></i> Not Marked as Off'}
                                </div>
                            </div>
                        </div>
                    `;
                    
                    detailsHtml += `
                        <div class="late-punch-explanation">
                            <p><strong>Policy:</strong> If an employee is absent on the 4th Saturday without approval, a penalty of 3 days' salary will be applied. This penalty is only for past 4th Saturdays, not future ones.</p>
                        </div>
                        
                        <div class="total-deduction">
                            <h4>4th Saturday Missing Days: ${missingDays} days</h4>
                            <p>Penalty: 3 days' salary = ₹${(dailySalary * 3).toFixed(2)}</p>
                            <p>Daily Salary: ₹${dailySalary.toFixed(2)}</p>
                        </div>
                    `;
                    
                    detailsHtml += '</div>'; // close penalty-summary
                    
                    // Display the details
                    penaltyDetails.innerHTML = detailsHtml;
                    penaltyModal.style.display = 'block';
                });
            });
            
            // Close penalty modal when clicking the close button
            closePenaltyModalBtn.addEventListener('click', function() {
                penaltyModal.style.display = 'none';
            });
            
            // Update the salary modal to include penalty deduction
            document.querySelectorAll('.salary-info').forEach(item => {
                item.addEventListener('click', function() {
                    // ... existing code ...
                    
                    const penaltyAmount = parseFloat(this.getAttribute('data-penalty-amount')) || 0;
                    const missingDays = parseInt(this.getAttribute('data-missing-days')) || 0;
                    
                    // ... existing code ...
                    
                    // In the deductions card, add penalty deduction
                    detailsHtml += `
                        <div class="salary-card deductions">
                            <h4>Deductions</h4>
                            
                            <!-- ... existing deduction items ... -->
                            
                            <div class="deduction-item ${penaltyAmount > 0 ? 'has-deduction' : ''}">
                                <div class="deduction-label">4th Saturday Missing Penalty:</div>
                                <div class="deduction-amount">-₹${penaltyAmount.toFixed(2)}</div>
                                <div class="deduction-details">${missingDays} days with missing punch-in records (3-day penalty)</div>
                            </div>
                            
                            <!-- ... existing total deduction ... -->
                        </div>
                    `;
                    
                    // ... existing code ...
                });
            });
            
            // Update the close modals click event to include the penalty modal
            window.addEventListener('click', function(event) {
                // ... existing code ...
                if (event.target === penaltyModal) {
                    penaltyModal.style.display = 'none';
                }
            });
            
            // ... existing code ...
            
            // Overtime Modal functionality
            const overtimeModal = document.getElementById('overtimeModal');
            const overtimeUsername = document.getElementById('overtimeUsername');
            const overtimeDetails = document.getElementById('overtimeDetails');
            const closeOvertimeModalBtn = overtimeModal.querySelector('.close-modal');
            
            // Open overtime modal when clicking on overtime info icon
            document.querySelectorAll('.overtime-info').forEach(item => {
                item.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const username = this.getAttribute('data-username');
                    const month = this.getAttribute('data-month');
                    const totalOvertimeRaw = parseFloat(this.getAttribute('data-total-overtime')) || 0;
                    // Get pre-calculated hours and minutes from data attributes 
                    const overtimeHours = parseInt(this.getAttribute('data-overtime-hours')) || 0;
                    const overtimeMinutes = parseInt(this.getAttribute('data-overtime-minutes')) || 0;
                    
                    overtimeUsername.textContent = username + ' - ' + new Date(month + '-01').toLocaleDateString('en-US', {month: 'long', year: 'numeric'});
                    
                    // Show loading spinner
                    overtimeDetails.innerHTML = '<div class="loading-spinner"></div>';
                    overtimeModal.style.display = 'block';
                    
                                // Show loading spinner
            overtimeDetails.innerHTML = '<div class="loading-spinner"></div>';
            
            // Create direct query to get attendance data with overtime details
            const directQuery = `
                SELECT 
                    DATE(a.date) as date,
                    a.status,
                    a.punch_in,
                    a.punch_out,
                    a.overtime_hours,
                    TIME_FORMAT(s.start_time, '%h:%i %p') as shift_start,
                    TIME_FORMAT(s.end_time, '%h:%i %p') as shift_end,
                    TIME_FORMAT(TIMEDIFF(a.punch_out, a.punch_in), '%H:%i') as worked_hours,
                    TIME_FORMAT(s.start_time, '%H:%i:%s') as raw_shift_start,
                    TIME_FORMAT(s.end_time, '%H:%i:%s') as raw_shift_end,
                    TIME_FORMAT(a.punch_in, '%H:%i:%s') as punch_in_time,
                    TIME_FORMAT(a.punch_out, '%H:%i:%s') as punch_out_time,
                    TIMESTAMPDIFF(SECOND, s.end_time, TIME(a.punch_out)) as seconds_after_shift,
                    TIMESTAMPDIFF(MINUTE, s.end_time, TIME(a.punch_out)) as minutes_after_shift,
                    TIMESTAMPDIFF(HOUR, s.end_time, TIME(a.punch_out)) as hours_after_shift,
                    TIMESTAMPDIFF(MINUTE, s.end_time, TIME(a.punch_out)) % 60 as remaining_minutes
                FROM attendance a
                LEFT JOIN user_shifts us ON a.user_id = us.user_id AND (us.effective_to IS NULL OR us.effective_to >= a.date)
                LEFT JOIN shifts s ON us.shift_id = s.id
                WHERE a.user_id = ${userId}
                AND DATE(a.date) BETWEEN '${month}-01' AND LAST_DAY('${month}-01')
                AND TIMESTAMPDIFF(MINUTE, s.end_time, TIME(a.punch_out)) >= 90
                ORDER BY a.date ASC
            `;
            
            // Fetch overtime details using a direct query
            fetch('api/run_query.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ query: directQuery })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Overtime data:', data);
                
                // Create HTML for overtime details
                let detailsHtml = '';
                
                // Format total overtime hours for display
                let overtimeDisplay = '';
                
                // Check if there's any qualifying overtime (≥ 1 hour 30 minutes after shift)
                const hasQualifyingOvertime = data.some(record => 
                    record && record.minutes_after_shift && parseInt(record.minutes_after_shift) >= 90);
                
                if (!hasQualifyingOvertime) {
                    overtimeDisplay = 'No qualifying overtime';
                } else if (overtimeHours > 0 && overtimeMinutes > 0) {
                    overtimeDisplay = `${overtimeHours} hours ${overtimeMinutes} minutes`;
                } else if (overtimeHours > 0) {
                    overtimeDisplay = `${overtimeHours} hours`;
                } else if (overtimeMinutes > 0) {
                    overtimeDisplay = `${overtimeMinutes} minutes`;
                } else {
                    overtimeDisplay = '0 hours';
                }
                
                // Add summary section
                detailsHtml += `
                    <div class="overtime-summary" style="margin-bottom: 20px; padding: 15px; background-color: #f0f7ff; border-radius: 8px; border-left: 4px solid #3b82f6;">
                        <h4 style="margin-top: 0; color: #3b82f6;">Total Overtime: ${overtimeDisplay}</h4>
                        <p style="margin-bottom: 0;">Showing detailed breakdown of overtime hours for ${username}.</p>
                        <div style="margin-top: 10px; padding: 10px; background-color: rgba(255,255,255,0.7); border-radius: 6px;">
                            <p style="margin: 0; font-size: 14px;"><strong>How overtime is calculated:</strong></p>
                            <ul style="margin-top: 5px; margin-bottom: 0; padding-left: 20px; font-size: 13px;">
                                <li>Overtime is only counted when an employee works <strong>1 hour and 30 minutes or more</strong> after their shift end time</li>
                                <li>Example: If shift ends at 6:00 PM and employee punches out at 7:30 PM (exactly 1.5 hours), overtime is 1 hour 30 minutes</li>
                                <li>Example: If shift ends at 6:00 PM and employee punches out at 8:00 PM, overtime is 2 hours</li>
                                <li>If an employee works less than 1 hour 30 minutes after shift end, no overtime is counted</li>
                                <li>Minutes are rounded down to the nearest 30-minute increment:
                                    <ul>
                                        <li>2 hours 41 minutes → 2 hours 30 minutes</li>
                                        <li>1 hour 42 minutes → 1 hour 30 minutes</li>
                                        <li>3 hours 29 minutes → 3 hours 0 minutes</li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                `;
                
                if (data && data.length > 0) {
                    // Filter to only show days with overtime (≥ 1 hour 30 minutes after shift end)
                    const validOvertimeDays = data.filter(record => 
                        record && record.minutes_after_shift && parseInt(record.minutes_after_shift) >= 90);
                    
                    if (validOvertimeDays.length === 0) {
                        detailsHtml += `
                            <div class="alert alert-info" style="background-color: #e2f0fd; color: #0c63e4; padding: 15px; border-radius: 8px; margin-top: 20px;">
                                <h4 style="margin-top: 0;"><i class="bi bi-info-circle"></i> No Qualifying Overtime</h4>
                                <p style="margin-bottom: 0;">No days found where the employee worked 1 hour and 30 minutes or more after their shift end time.</p>
                            </div>
                        `;
                    } else {
                        // Create table with overtime details
                                                detailsHtml += '<table class="modal-table">';
                        detailsHtml += '<thead><tr><th>Date</th><th>Day</th><th>Shift Time</th><th>Punch In</th><th>Punch Out</th><th>Worked Hours</th><th>Overtime</th></tr></thead>';
                        detailsHtml += '<tbody>';
                        
                        validOvertimeDays.forEach(record => {
                        try {
                            const date = new Date(record.date);
                            const formattedDate = date.toLocaleDateString('en-US', {day: '2-digit', month: 'short', year: 'numeric'});
                            const dayName = date.toLocaleDateString('en-US', {weekday: 'short'});
                            
                            // Format shift times
                            const shiftTime = record.shift_start && record.shift_end ? 
                                `${record.shift_start} - ${record.shift_end}` : 'N/A';
                            
                            // Format worked hours
                            let workedHours = 'N/A';
                            if (record.worked_hours) {
                                workedHours = record.worked_hours;
                            }
                            
                            // Calculate how overtime is determined
                            let overtimeExplanation = '';
                            if (record.minutes_after_shift > 0) {
                                // Calculate hours and minutes correctly from total minutes
                                const totalMinutes = parseInt(record.minutes_after_shift) || 0;
                                const hours_after_shift = Math.floor(totalMinutes / 60);
                                const remaining_minutes = totalMinutes % 60;
                                
                                // Format the overtime explanation
                                overtimeExplanation = `
                                    <div style="font-size: 12px; margin-top: 5px; border-top: 1px dashed #ccc; padding-top: 5px;">
                                        <div style="color: #666;"><strong>Shift ended at:</strong> ${record.shift_end}</div>
                                        <div style="color: #0d6efd;"><strong>Time after shift:</strong> ${hours_after_shift}h ${remaining_minutes}m</div>
                                        <div style="color: #0d6efd;"><strong>Total minutes:</strong> ${record.minutes_after_shift}</div>
                                    </div>
                                `;
                            }
                            
                            // Calculate punch times for display
                            let punchInTime = '-';
                            let punchOutTime = '-'; 
                            
                            if (record.punch_in && record.punch_out) {
                                // Handle MySQL datetime format properly by making sure it's properly formatted
                                // Avoid the "Invalid Date" problem by using regex to check format
                                const isValidDateTime = (dateStr) => /^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/.test(dateStr);
                                
                                try {
                                    // First try to use the punch_in_time field which is often more reliable
                                    if (record.punch_in_time) {
                                        const timeParts = record.punch_in_time.split(':');
                                        if (timeParts.length >= 2) {
                                            const hours = parseInt(timeParts[0]);
                                            const minutes = timeParts[1];
                                            const ampm = hours >= 12 ? 'PM' : 'AM';
                                            const hours12 = hours % 12 || 12;
                                            punchInTime = `${hours12}:${minutes} ${ampm}`;
                                        }
                                    } else if (isValidDateTime(record.punch_in)) {
                                        const punchIn = new Date(record.punch_in);
                                        
                                        if (!isNaN(punchIn)) {
                                            punchInTime = punchIn.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                                        }
                                    } else {
                                        // Try to extract time directly if date parsing fails
                                        const extractTime = (datetimeStr) => {
                                            const parts = datetimeStr.split(' ');
                                            if (parts.length >= 2) {
                                                const timeParts = parts[1].split(':');
                                                if (timeParts.length >= 2) {
                                                    const hour = parseInt(timeParts[0]);
                                                    const minute = timeParts[1];
                                                    const ampm = hour >= 12 ? 'PM' : 'AM';
                                                    const hour12 = hour % 12 || 12;
                                                    return `${hour12}:${minute} ${ampm}`;
                                                }
                                            }
                                            return null;
                                        };
                                        
                                        const extractedTime = extractTime(record.punch_in);
                                        if (extractedTime) {
                                            punchInTime = extractedTime;
                                        }
                                    }
                                    
                                    // Now do the same for punch out time
                                    if (record.punch_out_time) {
                                        const timeParts = record.punch_out_time.split(':');
                                        if (timeParts.length >= 2) {
                                            const hours = parseInt(timeParts[0]);
                                            const minutes = timeParts[1];
                                            const ampm = hours >= 12 ? 'PM' : 'AM';
                                            const hours12 = hours % 12 || 12;
                                            punchOutTime = `${hours12}:${minutes} ${ampm}`;
                                        }
                                    } else if (isValidDateTime(record.punch_out)) {
                                        const punchOut = new Date(record.punch_out);
                                        
                                        if (!isNaN(punchOut)) {
                                            punchOutTime = punchOut.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                                        }
                                    } else {
                                        const extractTime = (datetimeStr) => {
                                            const parts = datetimeStr.split(' ');
                                            if (parts.length >= 2) {
                                                const timeParts = parts[1].split(':');
                                                if (timeParts.length >= 2) {
                                                    const hour = parseInt(timeParts[0]);
                                                    const minute = timeParts[1];
                                                    const ampm = hour >= 12 ? 'PM' : 'AM';
                                                    const hour12 = hour % 12 || 12;
                                                    return `${hour12}:${minute} ${ampm}`;
                                                }
                                            }
                                            return null;
                                        };
                                        
                                        const extractedTime = extractTime(record.punch_out);
                                        if (extractedTime) {
                                            punchOutTime = extractedTime;
                                        }
                                    }
                                } catch (e) {
                                    console.error('Error parsing punch times:', e);
                                }
                            }
                            
                            const overtimeHoursDecimal = parseFloat(record.overtime_hours) || 0;
                            
                            // Calculate overtime based on time worked after shift end (≥ 1 hour 30 minutes)
                            let dailyOvertimeHours = 0;
                            let dailyOvertimeMinutes = 0;
                            
                            // If the employee worked 1 hour 30 minutes or more after their shift end time
                            if (record.minutes_after_shift >= 90) {
                                // Calculate hours and minutes correctly from total minutes
                                const totalMinutes = parseInt(record.minutes_after_shift) || 0;
                                dailyOvertimeHours = Math.floor(totalMinutes / 60);
                                dailyOvertimeMinutes = totalMinutes % 60;
                            } else {
                                // Check if the stored overtime_hours value exists (legacy data)
                                if (overtimeHoursDecimal > 0) {
                                    // Handle legacy overtime data - could be in seconds or hours
                                    if (overtimeHoursDecimal > 1000) {
                                        // Convert from seconds to hours and minutes
                                        dailyOvertimeHours = Math.floor(overtimeHoursDecimal / 3600);
                                        dailyOvertimeMinutes = Math.floor((overtimeHoursDecimal % 3600) / 60);
                                    } else {
                                        // Assume it's already in hours (decimal)
                                        dailyOvertimeHours = Math.floor(overtimeHoursDecimal);
                                        dailyOvertimeMinutes = Math.round((overtimeHoursDecimal - dailyOvertimeHours) * 60);
                                    }
                                }
                            }
                            
                            // Apply rounding rule for minutes - round down to nearest 30 minute increment
                            // 0-29 minutes rounds to 0, 30-59 minutes rounds to 30
                            if (dailyOvertimeMinutes < 30) {
                                dailyOvertimeMinutes = 0;
                            } else {
                                dailyOvertimeMinutes = 30;
                            }
                            
                            // Format overtime display
                            let dailyOvertimeDisplay = '';
                            if (dailyOvertimeHours > 0 && dailyOvertimeMinutes > 0) {
                                dailyOvertimeDisplay = `${dailyOvertimeHours} hrs ${dailyOvertimeMinutes} min`;
                            } else if (dailyOvertimeHours > 0) {
                                dailyOvertimeDisplay = `${dailyOvertimeHours} hrs`;
                            } else if (dailyOvertimeMinutes > 0) {
                                dailyOvertimeDisplay = `${dailyOvertimeMinutes} min`;
                            } else {
                                dailyOvertimeDisplay = '0';
                            }
                            
                            // Add the original time (before rounding) in a tooltip or small text
                            const originalMinutes = parseInt(record.minutes_after_shift) || 0;
                            const originalHours = Math.floor(originalMinutes / 60);
                            const originalMins = originalMinutes % 60;
                            const originalTimeDisplay = `${originalHours}h ${originalMins}m (${originalMinutes} min)`;
                            
                            
                            detailsHtml += `<tr>
                                <td>${formattedDate}</td>
                                <td>${dayName}</td>
                                <td>${shiftTime}</td>
                                <td><span style="color: #0d6efd;">${punchInTime}</span></td>
                                <td><span style="color: #0d6efd;">${punchOutTime}</span></td>
                                <td>${workedHours}</td>
                                <td>
                                    <strong style="color: #3b82f6;">${dailyOvertimeDisplay}</strong>
                                    <div style="font-size: 11px; color: #666; margin-top: 3px;">Original: ${originalTimeDisplay}</div>
                                    ${overtimeExplanation ? `<br>${overtimeExplanation}` : ''}
                                </td>
                            </tr>`;
                        } catch (e) {
                            console.error('Error processing overtime record:', e);
                        }
                    });
                    
                    detailsHtml += '</tbody></table>';
                    }
                } else {
                    detailsHtml += `
                        <div class="no-data" style="text-align: center; padding: 30px; background-color: #f8f9fa; border-radius: 8px; color: #6c757d;">
                            <i class="bi bi-clock-history" style="font-size: 32px; display: block; margin-bottom: 15px;"></i>
                            <p style="margin: 0; font-size: 16px;">No overtime records found for this month.</p>
                            <p style="margin-top: 10px; font-size: 14px;">Overtime is calculated when an employee works beyond their scheduled shift hours.</p>
                        </div>
                    `;
                }
                
                // Display the details
                overtimeDetails.innerHTML = detailsHtml;
            })
                        .catch(error => {
                            console.error('Error fetching overtime data:', error);
                            overtimeDetails.innerHTML = '<div class="no-data">Error loading overtime data. Please try again.</div>';
                        });
                });
            });
            
            // Close overtime modal when clicking the close button
            closeOvertimeModalBtn.addEventListener('click', function() {
                overtimeModal.style.display = 'none';
            });
            
            // Overtime Amount Modal functionality
            const overtimeAmountModal = document.getElementById('overtimeAmountModal');
            const overtimeAmountUsername = document.getElementById('overtimeAmountUsername');
            const overtimeAmountDetails = document.getElementById('overtimeAmountDetails');
            const closeOvertimeAmountModalBtn = overtimeAmountModal.querySelector('.close-modal');
            
            // Open overtime amount modal when clicking on overtime amount info icon
            document.querySelectorAll('.overtime-amount-info').forEach(item => {
                item.addEventListener('click', function() {
                    const username = this.getAttribute('data-username');
                    const baseSalary = parseFloat(this.getAttribute('data-base-salary')) || 0;
                    const workingDays = parseInt(this.getAttribute('data-working-days')) || 0;
                    const daySalary = parseFloat(this.getAttribute('data-day-salary')) || 0;
                    const overtimeHours = parseFloat(this.getAttribute('data-overtime-hours')) || 0;
                    const overtimeAmount = parseFloat(this.getAttribute('data-overtime-amount')) || 0;
                    
                    overtimeAmountUsername.textContent = username;
                    
                    // Create HTML for overtime amount calculation details
                    let detailsHtml = '';
                    
                    // Add calculation details in a visually appealing way
                    detailsHtml += `
                        <div class="salary-summary">
                            <div class="salary-card base-salary">
                                <h4>Base Information</h4>
                                <div class="details">Base Salary: ₹${baseSalary.toFixed(2)}</div>
                                <div class="details">Working Days: ${workingDays} days</div>
                                <div class="details">Daily Salary: ₹${daySalary.toFixed(2)}</div>
                            </div>
                            
                            <div class="salary-card" style="background-color: #f0f9ff; border-left: 4px solid #3b82f6;">
                                <h4>Overtime Calculation</h4>
                                <div class="details">Total Overtime: ${overtimeHours} hours</div>
                                <div class="calculation" style="margin-top: 10px; font-size: 16px;">
                                    <strong>Hourly Rate:</strong> ₹${daySalary.toFixed(2)} ÷ 8 = ₹${(daySalary / 8).toFixed(2)}/hour
                                </div>
                                <div class="calculation" style="margin-top: 5px; font-size: 16px;">
                                    <strong>Overtime Amount:</strong> ${overtimeHours} hours × ₹${(daySalary / 8).toFixed(2)} = ₹${overtimeAmount.toFixed(2)}
                                </div>
                            </div>
                            
                            <div class="salary-card final-salary">
                                <h4>Total Overtime Amount</h4>
                                <div class="amount">₹${overtimeAmount.toFixed(2)}</div>
                            </div>
                        </div>
                    `;
                    
                    // Display the details
                    overtimeAmountDetails.innerHTML = detailsHtml;
                    overtimeAmountModal.style.display = 'block';
                });
            });
            
            // Close overtime amount modal when clicking the close button
            closeOvertimeAmountModalBtn.addEventListener('click', function() {
                overtimeAmountModal.style.display = 'none';
            });
            
            // Total Salary Modal functionality
            const totalSalaryModal = document.getElementById('totalSalaryModal');
            const totalSalaryUsername = document.getElementById('totalSalaryUsername');
            const totalSalaryDetails = document.getElementById('totalSalaryDetails');
            const closeTotalSalaryModalBtn = totalSalaryModal.querySelector('.close-modal');
            
            // Open total salary modal when clicking on total salary info icon
            document.querySelectorAll('.total-salary-info').forEach(item => {
                item.addEventListener('click', function() {
                    const username = this.getAttribute('data-username');
                    const monthlySalary = parseFloat(this.getAttribute('data-monthly-salary')) || 0;
                    const overtimeAmount = parseFloat(this.getAttribute('data-overtime-amount')) || 0;
                    const totalSalary = parseFloat(this.getAttribute('data-total-salary')) || 0;
                    
                    totalSalaryUsername.textContent = username;
                    
                    // Create HTML for total salary breakdown
                    let detailsHtml = '';
                    
                    // Add breakdown in a visually appealing way
                    detailsHtml += `
                        <div class="salary-summary">
                            <div class="salary-card" style="background-color: #f0f9ff; border-left: 4px solid #3b82f6;">
                                <h4>Monthly Salary (After Deductions)</h4>
                                <div class="amount">₹${monthlySalary.toFixed(2)}</div>
                                <div class="details">Salary after all attendance and leave deductions</div>
                            </div>
                            
                            <div class="salary-card" style="background-color: #f0fff4; border-left: 4px solid #10b981;">
                                <h4>Overtime Amount</h4>
                                <div class="amount">₹${overtimeAmount.toFixed(2)}</div>
                                <div class="details">Additional payment for overtime hours</div>
                            </div>
                            
                            <div class="salary-card final-salary" style="background-color: #fdf2f8; border-left: 4px solid #ec4899;">
                                <h4>Total Salary</h4>
                                <div class="amount" style="font-size: 32px; font-weight: 700;">₹${totalSalary.toFixed(2)}</div>
                                <div class="calculation" style="margin-top: 10px; font-size: 16px;">
                                    <strong>Formula:</strong> Monthly Salary + Overtime Amount
                                </div>
                                <div class="calculation" style="margin-top: 5px; font-size: 16px;">
                                    <strong>Calculation:</strong> ₹${monthlySalary.toFixed(2)} + ₹${overtimeAmount.toFixed(2)} = ₹${totalSalary.toFixed(2)}
                                </div>
                                <div class="details" style="margin-top: 10px; color: #047857;">
                                    <i class="bi bi-info-circle"></i> Note: Any extra days worked beyond scheduled working days are not included in this total and will be carried forward to next month.
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Display the details
                    totalSalaryDetails.innerHTML = detailsHtml;
                    totalSalaryModal.style.display = 'block';
                });
            });
            
            // Close total salary modal when clicking the close button
            closeTotalSalaryModalBtn.addEventListener('click', function() {
                totalSalaryModal.style.display = 'none';
            });
            
            // Remaining Salary Modal functionality
            const remainingSalaryModal = document.getElementById('remainingSalaryModal');
            const remainingSalaryUsername = document.getElementById('remainingSalaryUsername');
            const remainingSalaryDetails = document.getElementById('remainingSalaryDetails');
            const closeRemainingSalaryModalBtn = remainingSalaryModal.querySelector('.close-modal');
            
            // Open remaining salary modal when clicking on remaining salary info icon
            document.querySelectorAll('.remaining-salary-info').forEach(item => {
                item.addEventListener('click', function() {
                    const username = this.getAttribute('data-username');
                    const workingDays = parseInt(this.getAttribute('data-working-days')) || 0;
                    const presentDays = parseInt(this.getAttribute('data-present-days')) || 0;
                    const extraDays = parseInt(this.getAttribute('data-extra-days')) || 0;
                    const dailySalary = parseFloat(this.getAttribute('data-daily-salary')) || 0;
                    const remainingSalary = parseFloat(this.getAttribute('data-remaining-salary')) || 0;
                    
                    remainingSalaryUsername.textContent = username;
                    
                    // Create HTML for remaining salary details
                    let detailsHtml = '';
                    
                    // Add explanation in a visually appealing way
                    detailsHtml += `
                        <div class="salary-summary">
                            <div class="salary-card" style="background-color: #f0f9ff; border-left: 4px solid #3b82f6;">
                                <h4>Work Days Information</h4>
                                <div class="details">Scheduled Working Days: ${workingDays} days</div>
                                <div class="details">Actual Present Days: ${presentDays} days</div>
                                <div class="details">Extra Days Worked: ${extraDays} days</div>
                            </div>
                            
                            <div class="salary-card" style="background-color: #fff7ed; border-left: 4px solid #f97316;">
                                <h4>Calculation</h4>
                                <div class="details">Daily Salary Rate: ₹${dailySalary.toFixed(2)}</div>
                                <div class="calculation" style="margin-top: 10px; font-size: 16px;">
                                    <strong>Formula:</strong> Extra Days × Daily Salary
                                </div>
                                <div class="calculation" style="margin-top: 5px; font-size: 16px;">
                                    <strong>Calculation:</strong> ${extraDays} days × ₹${dailySalary.toFixed(2)} = ₹${remainingSalary.toFixed(2)}
                                </div>
                            </div>
                            
                            <div class="salary-card final-salary" style="background-color: #ecfdf5; border-left: 4px solid #10b981;">
                                <h4>Remaining Salary</h4>
                                <div class="amount">₹${remainingSalary.toFixed(2)}</div>
                                <div class="details" style="margin-top: 10px; color: #047857;">
                                    <i class="bi bi-info-circle"></i> This amount will be carried forward to next month's salary
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Display the details
                    remainingSalaryDetails.innerHTML = detailsHtml;
                    remainingSalaryModal.style.display = 'block';
                });
            });
            
            // Close remaining salary modal when clicking the close button
            closeRemainingSalaryModalBtn.addEventListener('click', function() {
                remainingSalaryModal.style.display = 'none';
            });
            
            // Update the window click event to include all modals
            window.addEventListener('click', function(event) {
                // ... existing code ...
                if (event.target === overtimeModal) {
                    overtimeModal.style.display = 'none';
                }
                if (event.target === overtimeAmountModal) {
                    overtimeAmountModal.style.display = 'none';
                }
                if (event.target === totalSalaryModal) {
                    totalSalaryModal.style.display = 'none';
                }
                if (event.target === remainingSalaryModal) {
                    remainingSalaryModal.style.display = 'none';
                }
            });
            
            // ... existing code ...
        });
    </script>
</body>
</html>