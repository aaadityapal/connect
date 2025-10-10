<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'config/db_connect.php';

// Get parameters
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Validate required fields
if (!$user_id || !$month) {
    die("User ID and month are required");
}

try {
    // Fetch employee data from the database with working days calculation and salary data
    $stmt = $pdo->prepare("SELECT 
        u.id, 
        u.unique_id, 
        u.username, 
        u.role,
        us.weekly_offs,
        s.shift_name, 
        s.start_time, 
        s.end_time,
        fs.base_salary,
        fs.increment_percentage,
        fs.effective_from
    FROM users u
    LEFT JOIN user_shifts us ON u.id = us.user_id AND 
        (us.effective_to IS NULL OR us.effective_to >= CURDATE())
    LEFT JOIN shifts s ON us.shift_id = s.id
    LEFT JOIN final_salary fs ON u.id = fs.user_id
    WHERE u.id = ? AND u.status = 'active'");
    $stmt->execute([$user_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        die("Employee not found");
    }
    
    // Calculate working days for the employee
    $weekly_offs = !empty($employee['weekly_offs']) ? explode(',', $employee['weekly_offs']) : ['Saturday', 'Sunday'];
    
    // Get the selected month's start and end dates
    $month_start = date('Y-m-01', strtotime($month));
    $month_end = date('Y-m-t', strtotime($month));
    
    // Calculate the number of days in the month
    $days_in_month = date('t', strtotime($month));
    
    // Count working days
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
    
    // Fetch office holidays for the selected month
    try {
        $holidays_query = "SELECT DATE(holiday_date) as holiday_date FROM office_holidays 
                          WHERE DATE(holiday_date) BETWEEN ? AND ?";
        $holidays_stmt = $pdo->prepare($holidays_query);
        $holidays_stmt->execute([$month_start, $month_end]);
        $holidays_result = $holidays_stmt->fetchAll(PDO::FETCH_COLUMN);
        $office_holidays = array_flip($holidays_result); // Convert to associative array for faster lookup
    } catch (PDOException $e) {
        error_log("Error fetching office holidays: " . $e->getMessage());
        $office_holidays = [];
    }
    
    // Recalculate working days excluding office holidays
    $working_days_count = 0;
    
    // Loop through each day of the month again
    $current_date = new DateTime($month_start);
    $end_date = new DateTime($month_end);
    
    while ($current_date <= $end_date) {
        $day_of_week = $current_date->format('l'); // Get day name (Monday, Tuesday, etc.)
        $current_date_str = $current_date->format('Y-m-d');
        
        // If the day is not a weekly off and not an office holiday, increment the working days counter
        if (!in_array($day_of_week, $weekly_offs) && !isset($office_holidays[$current_date_str])) {
            $working_days_count++;
        }
        
        // Move to the next day
        $current_date->modify('+1 day');
    }
    
    $employee['working_days'] = $working_days_count;
    
    // Calculate leave taken for this employee in the selected month
    try {
        $leave_query = "SELECT 
                           SUM(CASE 
                               WHEN lr.duration_type = 'half_day' THEN 0.5 
                               ELSE 
                                  LEAST(DATEDIFF(
                                      LEAST(lr.end_date, ?), 
                                      GREATEST(lr.start_date, ?)
                                  ) + 1, 
                                  DATEDIFF(?, ?) + 1)
                           END) as total_days,
                           COUNT(*) as leave_count
                    FROM leave_request lr
                    WHERE lr.user_id = ? 
                    AND lr.status = 'approved'
                    AND (
                        (lr.start_date BETWEEN ? AND ?) 
                        OR 
                        (lr.end_date BETWEEN ? AND ?)
                        OR 
                        (lr.start_date <= ? AND lr.end_date >= ?)
                    )";
        $leave_stmt = $pdo->prepare($leave_query);
        $leave_stmt->execute([
            $month_end, // For LEAST(lr.end_date, ?)
            $month_start, // For GREATEST(lr.start_date, ?)
            $month_end, // For DATEDIFF(?, ?)
            $month_start, // For DATEDIFF(?, ?)
            $employee['id'], 
            $month_start, $month_end, 
            $month_start, $month_end,
            $month_start, $month_end
        ]);
        $leave_result = $leave_stmt->fetch(PDO::FETCH_ASSOC);
        $employee['leave_taken'] = $leave_result['total_days'] ?? 0;
        $employee['leave_count'] = $leave_result['leave_count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error fetching leave data for user " . $employee['id'] . ": " . $e->getMessage());
        $employee['leave_taken'] = 0;
        $employee['leave_count'] = 0;
    }
    
    // Calculate late days for this employee in the selected month
    try {
        // Get the shift start time for the user
        $shift_start = !empty($employee['start_time']) ? $employee['start_time'] : '09:00:00'; // Default to 9 AM if no shift assigned
        
        // Add 15 minutes grace period to shift start time
        $grace_time = date('H:i:s', strtotime($shift_start . ' +15 minutes'));
        
        // Count late punch-ins (only between grace period and 1 hour late)
        $late_query = "SELECT COUNT(*) as late_days 
                       FROM attendance 
                       WHERE user_id = ? 
                       AND DATE(date) BETWEEN ? AND ? 
                       AND status = 'present' 
                       AND TIME(punch_in) > ?
                       AND TIME(punch_in) <= ?";  // Not more than 1 hour late
        
        // Calculate 1 hour after shift start for half-day calculation
        $one_hour_late = date('H:i:s', strtotime($shift_start . ' +1 hour'));
        
        $late_stmt = $pdo->prepare($late_query);
        $late_stmt->execute([$employee['id'], $month_start, $month_end, $grace_time, $one_hour_late]);
        $late_result = $late_stmt->fetch(PDO::FETCH_ASSOC);
        
        $employee['late_days'] = $late_result['late_days'] ?? 0;
        
        // Store shift start time for detailed late calculation
        $employee['shift_start_time'] = $shift_start;
        $employee['grace_time'] = $grace_time;
                
        // Calculate 1+ hour late punches
        try {
            // Calculate 1 hour after shift start for 1+ hour late calculation
            $one_hour_late = date('H:i:s', strtotime($shift_start . ' +1 hour'));
                    
            // Count punch-ins more than 1 hour late, excluding days with half-day leave
            $one_hour_late_query = "SELECT COUNT(*) as one_hour_late_count 
                                   FROM attendance a
                                   WHERE a.user_id = ? 
                                   AND DATE(a.date) BETWEEN ? AND ? 
                                   AND a.status = 'present' 
                                   AND TIME(a.punch_in) > ?
                                   AND DATE(a.date) NOT IN (
                                       SELECT DATE(lr.start_date)
                                       FROM leave_request lr
                                       LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                                       WHERE lr.user_id = ?
                                       AND lr.status = 'approved'
                                       AND lt.name LIKE '%Half Day%'
                                       AND DATE(lr.start_date) BETWEEN ? AND ?
                                   )";
                    
            $one_hour_late_stmt = $pdo->prepare($one_hour_late_query);
            $one_hour_late_stmt->execute([$employee['id'], $month_start, $month_end, $one_hour_late, $employee['id'], $month_start, $month_end]);
            $one_hour_late_result = $one_hour_late_stmt->fetch(PDO::FETCH_ASSOC);
                    
            $employee['one_hour_late_count'] = $one_hour_late_result['one_hour_late_count'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error fetching 1+ hour late count for user " . $employee['id'] . ": " . $e->getMessage());
            $employee['one_hour_late_count'] = 0;
        }
                
        // Calculate present days
        try {
            $present_days_query = "SELECT COUNT(*) as present_days 
                                  FROM attendance 
                                  WHERE user_id = ? 
                                  AND DATE(date) BETWEEN ? AND ? 
                                  AND status = 'present'";
                    
            $present_days_stmt = $pdo->prepare($present_days_query);
            $present_days_stmt->execute([$employee['id'], $month_start, $month_end]);
            $present_days_result = $present_days_stmt->fetch(PDO::FETCH_ASSOC);
                    
            $employee['present_days'] = $present_days_result['present_days'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error fetching present days for user " . $employee['id'] . ": " . $e->getMessage());
            $employee['present_days'] = 0;
        }
                
        // Calculate late deduction with short leave adjustment
        try {
            // Calculate daily salary
            $daily_salary = 0;
            if ($employee['working_days'] > 0) {
                $daily_salary = ($employee['base_salary'] ?? 0) / $employee['working_days'];
            }
                    
            // Calculate half day salary
            $half_day_salary = $daily_salary / 2;
                    
            // Get short leave count for the month
            try {
                $short_leave_query = "SELECT SUM(CASE 
                                        WHEN lr.duration_type = 'half_day' THEN 0.5 
                                        ELSE 
                                           LEAST(DATEDIFF(
                                               LEAST(lr.end_date, ?), 
                                               GREATEST(lr.start_date, ?)
                                           ) + 1, 
                                           DATEDIFF(?, ?) + 1)
                                    END) as short_leave_days
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
                    $employee['id'], 
                    $month_start, $month_end, 
                    $month_start, $month_end,
                    $month_start, $month_end
                ]);
                $short_leave_result = $short_leave_stmt->fetch(PDO::FETCH_ASSOC);
                $short_leave_days = $short_leave_result['short_leave_days'] ?? 0;
            } catch (PDOException $e) {
                error_log("Error fetching short leave data for user " . $employee['id'] . ": " . $e->getMessage());
                $short_leave_days = 0;
            }
                    
            // Apply short leave reduction logic
            // Max 2 short leaves can be used for reduction
            $effective_short_leaves = min($short_leave_days, 2);
                    
            // First, reduce 1+ hour late count (if any)
            $reduced_one_hour_late_count = max(0, $employee['one_hour_late_count'] - $effective_short_leaves);
            $used_short_leaves_for_one_hour = $employee['one_hour_late_count'] - $reduced_one_hour_late_count;
                    
            // Remaining short leaves (if any) reduce late days
            $remaining_short_leaves = $effective_short_leaves - $used_short_leaves_for_one_hour;
            $reduced_late_days = max(0, $employee['late_days'] - $remaining_short_leaves);
                    
            // Calculate 1+ hour late deduction (half day salary for each 1+ hour late)
            $employee['one_hour_late_deduction_amount'] = round($reduced_one_hour_late_count * $half_day_salary, 2);
                    
            // Calculate late deduction based on reduced late days
            // 3 late days = 0.5 day deduction
            // Additional 3 late days = additional 0.5 day deduction, etc.
            $late_deduction_days = 0;
            if ($reduced_late_days >= 3) {
                // Initial half-day for first 3 late days
                $late_deduction_days = 0.5;
                        
                // Additional half-day for every 3 more late days
                $additional_late_days = $reduced_late_days - 3;
                if ($additional_late_days > 0) {
                    $additional_half_days = floor($additional_late_days / 3);
                    $late_deduction_days += ($additional_half_days * 0.5);
                }
            }
                    
            $employee['late_deduction_amount'] = round($late_deduction_days * $daily_salary, 2);
                    
            // Store adjusted counts for reference
            $employee['adjusted_late_days'] = $reduced_late_days;
            $employee['adjusted_one_hour_late_count'] = $reduced_one_hour_late_count;
            
            // Store adjusted deduction amounts for reference in salary days calculation
            $employee['adjusted_late_deduction_amount'] = $employee['late_deduction_amount'];
            
            // Store short leave days for display
            $employee['short_leave_days'] = $short_leave_days;
            
            // Calculate leave deduction based on leave types and rules
            $leave_deduction_amount = 0;
            
            // Fetch all leave requests for this employee in the selected month
            try {
                $leave_query = "SELECT 
                                   lr.*, 
                                   lt.name as leave_type_name,
                                   lt.max_days as leave_type_max_days
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
                            ORDER BY lr.start_date";
                
                $leave_stmt = $pdo->prepare($leave_query);
                $leave_stmt->execute([
                    $employee['id'], 
                    $month_start, $month_end, 
                    $month_start, $month_end,
                    $month_start, $month_end
                ]);
                
                $leave_requests = $leave_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Process each leave request according to the rules
                foreach ($leave_requests as $leave) {
                    // Calculate days for this leave request
                    $leave_days = 0;
                    if ($leave['duration_type'] == 'half_day') {
                        $leave_days = 0.5;
                    } else {
                        // Calculate full days between start and end dates within the month
                        $start = new DateTime(max($leave['start_date'], $month_start));
                        $end = new DateTime(min($leave['end_date'], $month_end));
                        $interval = $start->diff($end);
                        $leave_days = $interval->days + 1;
                    }
                    
                    // Apply deduction rules based on leave type
                    $leave_type = $leave['leave_type_name'];
                    
                    if (stripos($leave_type, 'Casual') !== false) {
                        // Casual Leave: No deduction for first 2 leaves per month
                        // This is checked at the end after counting all casual leaves
                    } elseif (stripos($leave_type, 'Compensate') !== false) {
                        // Compensate Leave: No deduction
                    } elseif (stripos($leave_type, 'Unpaid') !== false) {
                        // Unpaid Leave: Deduct full salary for each day
                        $leave_deduction_amount += $leave_days * $daily_salary;
                    } elseif (stripos($leave_type, 'Half Day') !== false) {
                        // Half Day Leave: Deduct half day salary for each leave
                        $leave_deduction_amount += $leave_days * $half_day_salary;
                    } elseif (stripos($leave_type, 'Sick') !== false) {
                        // Sick Leave: No deduction up to 6 days per year
                        // This is checked at the end after counting all sick leaves for the year
                    } elseif (stripos($leave_type, 'Maternity') !== false) {
                        // Maternity Leave: No deduction up to 60 days per year
                        // This is checked at the end after counting all maternity leaves for the year
                    } elseif (stripos($leave_type, 'Paternity') !== false) {
                        // Paternity Leave: No deduction up to 7 days per year
                        // This is checked at the end after counting all paternity leaves for the year
                    } elseif (stripos($leave_type, 'Emergency') !== false) {
                        // Emergency Leave: Always deduct
                        $leave_deduction_amount += $leave_days * $daily_salary;
                    } elseif (stripos($leave_type, 'Short') !== false) {
                        // Short Leave: No deduction (already handled in late deduction logic)
                    }
                }
                
                // Count total casual leaves for the month
                $casual_leave_count = 0;
                foreach ($leave_requests as $leave) {
                    if (stripos($leave['leave_type_name'], 'Casual') !== false) {
                        if ($leave['duration_type'] == 'half_day') {
                            $casual_leave_count += 0.5;
                        } else {
                            $start = new DateTime(max($leave['start_date'], $month_start));
                            $end = new DateTime(min($leave['end_date'], $month_end));
                            $interval = $start->diff($end);
                            $casual_leave_count += $interval->days + 1;
                        }
                    }
                }
                
                // Apply casual leave deduction rule: No deduction for first 2 leaves per month
                if ($casual_leave_count > 2) {
                    $excess_casual_leaves = $casual_leave_count - 2;
                    $leave_deduction_amount += $excess_casual_leaves * $daily_salary;
                }
                
                // Count total sick leaves for the year
                try {
                    $sick_leave_query = "SELECT 
                                           SUM(CASE 
                                               WHEN lr.duration_type = 'half_day' THEN 0.5 
                                               ELSE 
                                                  DATEDIFF(
                                                      LEAST(lr.end_date, ?), 
                                                      GREATEST(lr.start_date, ?)
                                                  ) + 1
                                           END) as total_sick_days
                                    FROM leave_request lr
                                    LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                                    WHERE lr.user_id = ? 
                                    AND lr.status = 'approved'
                                    AND lt.name LIKE '%Sick%'
                                    AND YEAR(lr.start_date) = YEAR(?)";
                    
                    $sick_leave_stmt = $pdo->prepare($sick_leave_query);
                    $year_start = date('Y-01-01', strtotime($month));
                    $year_end = date('Y-12-31', strtotime($month));
                    $sick_leave_stmt->execute([$year_end, $year_start, $employee['id'], $month]);
                    $sick_leave_result = $sick_leave_stmt->fetch(PDO::FETCH_ASSOC);
                    $total_sick_days = $sick_leave_result['total_sick_days'] ?? 0;
                    
                    // Apply sick leave deduction rule: No deduction up to 6 days per year
                    if ($total_sick_days > 6) {
                        $excess_sick_days = $total_sick_days - 6;
                        $leave_deduction_amount += $excess_sick_days * $daily_salary;
                    }
                } catch (PDOException $e) {
                    error_log("Error calculating sick leave deduction for user " . $employee['id'] . ": " . $e->getMessage());
                }
                
                // Count total maternity leaves for the year
                try {
                    $maternity_leave_query = "SELECT 
                                           SUM(CASE 
                                               WHEN lr.duration_type = 'half_day' THEN 0.5 
                                               ELSE 
                                                  DATEDIFF(
                                                      LEAST(lr.end_date, ?), 
                                                      GREATEST(lr.start_date, ?)
                                                  ) + 1
                                           END) as total_maternity_days
                                    FROM leave_request lr
                                    LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                                    WHERE lr.user_id = ? 
                                    AND lr.status = 'approved'
                                    AND lt.name LIKE '%Maternity%'
                                    AND YEAR(lr.start_date) = YEAR(?)";
                    
                    $maternity_leave_stmt = $pdo->prepare($maternity_leave_query);
                    $year_start = date('Y-01-01', strtotime($month));
                    $year_end = date('Y-12-31', strtotime($month));
                    $maternity_leave_stmt->execute([$year_end, $year_start, $employee['id'], $month]);
                    $maternity_leave_result = $maternity_leave_stmt->fetch(PDO::FETCH_ASSOC);
                    $total_maternity_days = $maternity_leave_result['total_maternity_days'] ?? 0;
                    
                    // Apply maternity leave deduction rule: No deduction up to 60 days per year
                    if ($total_maternity_days > 60) {
                        $excess_maternity_days = $total_maternity_days - 60;
                        $leave_deduction_amount += $excess_maternity_days * $daily_salary;
                    }
                } catch (PDOException $e) {
                    error_log("Error calculating maternity leave deduction for user " . $employee['id'] . ": " . $e->getMessage());
                }
                
                // Count total paternity leaves for the year
                try {
                    $paternity_leave_query = "SELECT 
                                           SUM(CASE 
                                               WHEN lr.duration_type = 'half_day' THEN 0.5 
                                               ELSE 
                                                  DATEDIFF(
                                                      LEAST(lr.end_date, ?), 
                                                      GREATEST(lr.start_date, ?)
                                                  ) + 1
                                           END) as total_paternity_days
                                    FROM leave_request lr
                                    LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                                    WHERE lr.user_id = ? 
                                    AND lr.status = 'approved'
                                    AND lt.name LIKE '%Paternity%'
                                    AND YEAR(lr.start_date) = YEAR(?)";
                    
                    $paternity_leave_stmt = $pdo->prepare($paternity_leave_query);
                    $year_start = date('Y-01-01', strtotime($month));
                    $year_end = date('Y-12-31', strtotime($month));
                    $paternity_leave_stmt->execute([$year_end, $year_start, $employee['id'], $month]);
                    $paternity_leave_result = $paternity_leave_stmt->fetch(PDO::FETCH_ASSOC);
                    $total_paternity_days = $paternity_leave_result['total_paternity_days'] ?? 0;
                    
                    // Apply paternity leave deduction rule: No deduction up to 7 days per year
                    if ($total_paternity_days > 7) {
                        $excess_paternity_days = $total_paternity_days - 7;
                        $leave_deduction_amount += $excess_paternity_days * $daily_salary;
                    }
                } catch (PDOException $e) {
                    error_log("Error calculating paternity leave deduction for user " . $employee['id'] . ": " . $e->getMessage());
                }
            } catch (PDOException $e) {
                error_log("Error fetching leave data for user " . $employee['id'] . ": " . $e->getMessage());
            }
            
            // Store leave deduction amount
            $employee['leave_deduction_amount'] = round($leave_deduction_amount, 2);
            
            // Calculate half day leaves for salary days calculation
            $half_day_leaves = 0;
            foreach ($leave_requests as $leave) {
                if (stripos($leave['leave_type_name'], 'Half Day') !== false) {
                    if ($leave['duration_type'] == 'half_day') {
                        $half_day_leaves += 0.5;
                    } else {
                        $start = new DateTime(max($leave['start_date'], $month_start));
                        $end = new DateTime(min($leave['end_date'], $month_end));
                        $interval = $start->diff($end);
                        $half_day_leaves += $interval->days + 1;
                    }
                }
            }
            $employee['half_day_leaves'] = $half_day_leaves;
            
            // Calculate salary days: present days + casual leaves - (half day leaves * 0.5) - (adjusted 1+ hour late count * 0.5) - (adjusted late days deduction)
            // Use adjusted counts after short leave reduction
            
            // Count casual leaves for addition to salary days
            $casual_leaves = 0;
            foreach ($leave_requests as $leave) {
                if (stripos($leave['leave_type_name'], 'Casual') !== false) {
                    if ($leave['duration_type'] == 'half_day') {
                        $casual_leaves += 0.5;
                    } else {
                        $start = new DateTime(max($leave['start_date'], $month_start));
                        $end = new DateTime(min($leave['end_date'], $month_end));
                        $interval = $start->diff($end);
                        $casual_leaves += $interval->days + 1;
                    }
                }
            }
            
            // Calculate deduction for adjusted late days (after short leave reduction)
            // For late days: every 3 late days = 0.5 day deduction
            $adjusted_late_days_deduction = 0;
            if ($reduced_late_days >= 3) {
                $adjusted_late_days_deduction = floor($reduced_late_days / 3) * 0.5;
            }
            
            // Calculate deduction for adjusted 1+ hour late days (after short leave reduction)
            $adjusted_one_hour_late_deduction = $reduced_one_hour_late_count * 0.5;
            
            $salary_days = $employee['present_days'] + $casual_leaves - ($half_day_leaves * 0.5) - $adjusted_one_hour_late_deduction - $adjusted_late_days_deduction;
            $employee['salary_days_calculated_before_deduction'] = max(0, $salary_days);
            
            // Check for missing punch out on 4th Saturday
            // Find the 4th Saturday of the selected month
            $year = date('Y', strtotime($month));
            $month_num = date('m', strtotime($month));
            
            // Get all Saturdays in the month
            $saturdays = [];
            $days_in_month = date('t', strtotime($month));
            for ($day = 1; $day <= $days_in_month; $day++) {
                $date = sprintf('%s-%02d-%02d', $year, $month_num, $day);
                if (date('N', strtotime($date)) == 6) { // 6 = Saturday
                    $saturdays[] = $date;
                }
            }
            
            // Get the 4th Saturday (if it exists)
            $fourth_saturday = isset($saturdays[3]) ? $saturdays[3] : null;
            
            // Check if user has attendance record for the 4th Saturday and if punch_out is missing
            $missing_fourth_saturday_punch_out = false;
            if ($fourth_saturday) {
                $punch_query = "SELECT * FROM attendance 
                               WHERE user_id = ? 
                               AND DATE(date) = ? 
                               AND (punch_out IS NULL OR punch_out = '')";
                $punch_stmt = $pdo->prepare($punch_query);
                $punch_stmt->execute([$employee['id'], $fourth_saturday]);
                $punch_result = $punch_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($punch_result) {
                    $missing_fourth_saturday_punch_out = true;
                }
            }
            
            $employee['missing_fourth_saturday_punch_out'] = $missing_fourth_saturday_punch_out;
            $employee['fourth_saturday_date'] = $fourth_saturday;
            
            // Apply 3 days deduction if 4th Saturday punch out is missing
            if ($missing_fourth_saturday_punch_out) {
                $salary_days = max(0, $salary_days - 3);
            }
            
            // Ensure salary days do not exceed working days
            $salary_days = min($salary_days, $employee['working_days']);
            
            // Calculate net salary: salary days calculated * per day salary
            // Per day salary = base salary / working days
            $per_day_salary = 0;
            if ($employee['working_days'] > 0) {
                $per_day_salary = ($employee['base_salary'] ?? 0) / $employee['working_days'];
            }
            $net_salary = $salary_days * $per_day_salary;
            $employee['net_salary'] = round($net_salary, 2);
            $employee['salary_days_calculated'] = max(0, $salary_days);
            
            // Calculate excess days (when present days > working days)
            $employee['excess_days'] = max(0, $employee['present_days'] - $employee['working_days']);
            $employee['excess_day_salary'] = $employee['excess_days'] * $per_day_salary;
            
            // Initialize penalty value (this would come from the database in a real implementation)
            $employee['penalty'] = 0;
            
            // Store late days deduction for reference
            $employee['late_days_deduction'] = $adjusted_late_days_deduction;
            // Store casual leaves for reference
            $employee['casual_leaves'] = $casual_leaves;
        } catch (PDOException $e) {
            error_log("Error calculating late deduction for user " . $employee['id'] . ": " . $e->getMessage());
            $employee['late_deduction_amount'] = 0;
            $employee['one_hour_late_deduction_amount'] = 0;
            $employee['adjusted_late_days'] = $employee['late_days'];
            $employee['adjusted_one_hour_late_count'] = $employee['one_hour_late_count'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching late days for user " . $employee['id'] . ": " . $e->getMessage());
        $employee['late_days'] = 0;
        $employee['shift_start_time'] = '09:00:00';
        $employee['grace_time'] = '09:15:00';
    }
    
} catch (PDOException $e) {
    error_log("Error fetching employee salary details: " . $e->getMessage());
    die("Database error occurred");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip - <?php echo htmlspecialchars($employee['username']); ?> - <?php echo htmlspecialchars($month); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <style>
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }
        
        .salary-slip {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .slip-title {
            font-size: 20px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .employee-info {
            background-color: #f1f8ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin: 20px 0 10px 0;
        }
        
        .table th {
            background-color: #e9ecef;
        }
        
        .total-row {
            font-weight: bold;
        }
        
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            text-align: center;
            width: 45%;
        }
        
        .stamp {
            border: 1px dashed #999;
            padding: 10px;
            text-align: center;
            margin-top: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="salary-slip">
        <div class="header">
            <div class="company-name">Architecture Studio</div>
            <div class="slip-title">SALARY SLIP</div>
            <div>For the month of <?php echo date('F Y', strtotime($month)); ?></div>
        </div>
        
        <div class="employee-info">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Employee ID:</strong></td>
                            <td><?php echo !empty($employee['unique_id']) ? htmlspecialchars($employee['unique_id']) : ''; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Employee Name:</strong></td>
                            <td><?php echo !empty($employee['username']) ? htmlspecialchars($employee['username']) : ''; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Designation:</strong></td>
                            <td><?php echo !empty($employee['role']) ? htmlspecialchars($employee['role']) : ''; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Department:</strong></td>
                            <td><?php echo ''; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Joining Date:</strong></td>
                            <td><?php echo ''; ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Working Days:</strong></td>
                            <td><?php echo !empty($employee['working_days']) ? $employee['working_days'] : '0'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Present Days:</strong></td>
                            <td><?php echo !empty($employee['present_days']) ? $employee['present_days'] : '0'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Payable Days:</strong></td>
                            <td><?php echo !empty($employee['salary_days_calculated']) ? number_format($employee['salary_days_calculated'], 1) : '0.0'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Bank Name:</strong></td>
                            <td><?php echo ''; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Account Number:</strong></td>
                            <td><?php echo ''; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="section-title">Pay Summary</div>
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Basic Salary:</strong></td>
                        <td class="text-right">₹<?php echo !empty($employee['base_salary']) ? number_format($employee['base_salary'], 2) : '0.00'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Allowances:</strong></td>
                        <td class="text-right">₹0.00</td>
                    </tr>
                    <tr>
                        <td><strong>Gross Salary:</strong></td>
                        <td class="text-right">₹<?php echo !empty($employee['base_salary']) ? number_format($employee['base_salary'], 2) : '0.00'; ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Total Deductions:</strong></td>
                        <td class="text-right">₹<?php echo number_format(
                            $employee['late_deduction_amount'] + 
                            $employee['one_hour_late_deduction_amount'] + 
                            $employee['leave_deduction_amount'] + 
                            ($employee['penalty'] * $per_day_salary), 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Net Payable:</strong></td>
                        <td class="text-right">₹<?php 
                            $netPayable = ($employee['net_salary'] + $employee['excess_day_salary']) - 
                                ($employee['late_deduction_amount'] + 
                                $employee['one_hour_late_deduction_amount'] + 
                                $employee['leave_deduction_amount'] + 
                                ($employee['penalty'] * $per_day_salary));
                            echo number_format($netPayable, 2);
                        ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="section-title">Earnings</div>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Days</th>
                    <th>Rate (₹)</th>
                    <th>Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Basic Salary</td>
                    <td><?php echo number_format($employee['salary_days_calculated'], 1); ?></td>
                    <td><?php echo number_format($per_day_salary, 2); ?></td>
                    <td><?php echo number_format($employee['net_salary'], 2); ?></td>
                </tr>
                <?php if ($employee['excess_days'] > 0): ?>
                <tr>
                    <td>Excess Day Salary</td>
                    <td><?php echo number_format($employee['excess_days'], 1); ?></td>
                    <td><?php echo number_format($per_day_salary, 2); ?></td>
                    <td><?php echo number_format($employee['excess_day_salary'], 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td colspan="3" class="text-right"><strong>Total Earnings:</strong></td>
                    <td><strong><?php echo number_format($employee['net_salary'] + $employee['excess_day_salary'], 2); ?></strong></td>
                </tr>
            </tbody>
        </table>
        
        <div class="section-title">Deductions</div>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Days</th>
                    <th>Rate (₹)</th>
                    <th>Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($employee['late_deduction_amount'] > 0): ?>
                <tr>
                    <td>Late Punch In Deduction</td>
                    <td><?php echo number_format($employee['adjusted_late_days_deduction'], 1); ?></td>
                    <td><?php echo number_format($per_day_salary / 2, 2); ?></td>
                    <td><?php echo number_format($employee['late_deduction_amount'], 2); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if ($employee['one_hour_late_deduction_amount'] > 0): ?>
                <tr>
                    <td>1+ Hour Late Deduction</td>
                    <td><?php echo $employee['adjusted_one_hour_late_count']; ?></td>
                    <td><?php echo number_format($per_day_salary / 2, 2); ?></td>
                    <td><?php echo number_format($employee['one_hour_late_deduction_amount'], 2); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if ($employee['leave_deduction_amount'] > 0): ?>
                <tr>
                    <td>Leave Deduction</td>
                    <td><?php echo number_format($employee['leave_taken'], 1); ?></td>
                    <td><?php echo number_format($per_day_salary, 2); ?></td>
                    <td><?php echo number_format($employee['leave_deduction_amount'], 2); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if ($employee['penalty'] > 0): ?>
                <tr>
                    <td>Penalty</td>
                    <td><?php echo number_format($employee['penalty'], 1); ?></td>
                    <td><?php echo number_format($per_day_salary, 2); ?></td>
                    <td><?php echo number_format($employee['penalty'] * $per_day_salary, 2); ?></td>
                </tr>
                <?php endif; ?>
                
                <tr class="total-row">
                    <td colspan="3" class="text-right"><strong>Total Deductions:</strong></td>
                    <td><strong><?php echo number_format(
                        $employee['late_deduction_amount'] + 
                        $employee['one_hour_late_deduction_amount'] + 
                        $employee['leave_deduction_amount'] + 
                        ($employee['penalty'] * $per_day_salary), 2); ?></strong></td>
                </tr>
            </tbody>
        </table>
        
        <div class="section-title">Leave Details</div>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Days</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Leave Taken</td>
                    <td><?php echo number_format($employee['leave_taken'], 1); ?></td>
                </tr>
                <tr>
                    <td>Late Days</td>
                    <td><?php echo $employee['late_days']; ?></td>
                </tr>
                <tr>
                    <td>1+ Hour Late</td>
                    <td><?php echo $employee['one_hour_late_count']; ?></td>
                </tr>
                <tr>
                    <td>Short Leave</td>
                    <td><?php echo number_format($employee['short_leave_days'], 1); ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="row">
            <div class="col-md-6">
                <div class="section-title">Net Payable Amount</div>
                <table class="table table-bordered">
                    <tr>
                        <td><strong>Gross Earnings:</strong></td>
                        <td class="text-right">₹<?php echo number_format($employee['net_salary'] + $employee['excess_day_salary'], 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total Deductions:</strong></td>
                        <td class="text-right">₹<?php echo number_format(
                            $employee['late_deduction_amount'] + 
                            $employee['one_hour_late_deduction_amount'] + 
                            $employee['leave_deduction_amount'] + 
                            ($employee['penalty'] * $per_day_salary), 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>Net Salary:</strong></td>
                        <td class="text-right"><strong>₹<?php 
                            $netPayable = ($employee['net_salary'] + $employee['excess_day_salary']) - 
                                ($employee['late_deduction_amount'] + 
                                $employee['one_hour_late_deduction_amount'] + 
                                $employee['leave_deduction_amount'] + 
                                ($employee['penalty'] * $per_day_salary));
                            echo number_format($netPayable, 2);
                        ?></strong></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <div class="section-title">Attendance Summary</div>
                <table class="table table-bordered">
                    <tr>
                        <td>Working Days</td>
                        <td><?php echo $employee['working_days']; ?></td>
                    </tr>
                    <tr>
                        <td>Present Days</td>
                        <td><?php echo $employee['present_days']; ?></td>
                    </tr>
                    <tr>
                        <td>Leave Days</td>
                        <td><?php echo number_format($employee['leave_taken'], 1); ?></td>
                    </tr>
                    <tr>
                        <td>Payable Days</td>
                        <td><?php echo number_format($employee['salary_days_calculated'], 1); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="signature-section">
            <div class="signature-box">
                <div>___________________________</div>
                <div>Employee Signature</div>
            </div>
            <div class="signature-box">
                <div>___________________________</div>
                <div>Authorized Signature</div>
                <div class="stamp">Company Stamp</div>
            </div>
        </div>
        
        <div class="text-center no-print mt-4">
            <button class="btn btn-primary" onclick="window.print()">Print Salary Slip</button>
            <a href="salary_page.php" class="btn btn-secondary">Back to Salary Page</a>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>
</html>