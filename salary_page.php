<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'config/db_connect.php';

$pageTitle = "Salary Management";

// Get the selected month from URL parameter or use current month
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Fetch employees data from the database with working days calculation and salary data
try {
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
    WHERE u.status = 'active' 
    ORDER BY u.username");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate working days for each employee
    foreach ($employees as &$employee) {
        $weekly_offs = !empty($employee['weekly_offs']) ? explode(',', $employee['weekly_offs']) : ['Saturday', 'Sunday'];
        
        // Get the selected month's start and end dates
        $month_start = date('Y-m-01', strtotime($selected_month));
        $month_end = date('Y-m-t', strtotime($selected_month));
        
        // Calculate the number of days in the month
        $days_in_month = date('t', strtotime($selected_month));
        
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
                        $year_start = date('Y-01-01', strtotime($selected_month));
                        $year_end = date('Y-12-31', strtotime($selected_month));
                        $sick_leave_stmt->execute([$year_end, $year_start, $employee['id'], $selected_month]);
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
                        $year_start = date('Y-01-01', strtotime($selected_month));
                        $year_end = date('Y-12-31', strtotime($selected_month));
                        $maternity_leave_stmt->execute([$year_end, $year_start, $employee['id'], $selected_month]);
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
                        $year_start = date('Y-01-01', strtotime($selected_month));
                        $year_end = date('Y-12-31', strtotime($selected_month));
                        $paternity_leave_stmt->execute([$year_end, $year_start, $employee['id'], $selected_month]);
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
                
                // Calculate unpaid leave days for salary days calculation
                $unpaid_leave_days = 0;
                foreach ($leave_requests as $leave) {
                    if (stripos($leave['leave_type_name'], 'Unpaid') !== false) {
                        if ($leave['duration_type'] == 'half_day') {
                            $unpaid_leave_days += 0.5;
                        } else {
                            $start = new DateTime(max($leave['start_date'], $month_start));
                            $end = new DateTime(min($leave['end_date'], $month_end));
                            $interval = $start->diff($end);
                            $unpaid_leave_days += $interval->days + 1;
                        }
                    }
                }
                $employee['unpaid_leave_days'] = $unpaid_leave_days;
                
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
                
                // Calculate salary days: working days - (unpaid leave days) - (adjusted late days deduction) - (adjusted 1+ hour late count * 0.5)
                // Casual and compensate leaves are not deducted as they are paid leaves
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
                
                // Calculate salary days from working days as per new requirement
                // Working days - deductions (unpaid leave + late deductions)
                // Casual and compensate leaves are not deducted as they are paid leaves
                $salary_days = $employee['working_days'] - $unpaid_leave_days - $adjusted_late_days_deduction - $adjusted_one_hour_late_deduction;
                $employee['salary_days_calculated_before_deduction'] = max(0, $salary_days);
                
                // Check for missing punch out on 4th Saturday
                // Find the 4th Saturday of the selected month
                $year = date('Y', strtotime($selected_month));
                $month = date('m', strtotime($selected_month));
                
                // Get all Saturdays in the month
                $saturdays = [];
                $days_in_month = date('t', strtotime($selected_month));
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $date = sprintf('%s-%02d-%02d', $year, $month, $day);
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
                
                // Calculate salary days after penalty
                $salary_days_after_penalty = max(0, $salary_days - $employee['penalty']);
                
                // Net salary is now (salary days after penalty * per day salary)
                $net_salary = $salary_days_after_penalty * $per_day_salary;
                
                $employee['net_salary'] = round($net_salary, 2);
                $employee['net_salary_before_penalty'] = round($salary_days * $per_day_salary, 2);
                $employee['salary_days_calculated'] = max(0, $salary_days);
                $employee['salary_days_after_penalty'] = $salary_days_after_penalty;
                
                // Fetch existing penalty value for this employee and month
                try {
                    $penalty_query = "SELECT penalty_amount FROM penalty_reasons WHERE user_id = ? AND penalty_date = ?";
                    $penalty_stmt = $pdo->prepare($penalty_query);
                    $penalty_stmt->execute([$employee['id'], $month_start]);
                    $penalty_result = $penalty_stmt->fetch(PDO::FETCH_ASSOC);
                    $employee['penalty'] = $penalty_result['penalty_amount'] ?? 0;
                } catch (PDOException $e) {
                    error_log("Error fetching penalty data for user " . $employee['id'] . ": " . $e->getMessage());
                    $employee['penalty'] = 0;
                }
                
                // Calculate excess days (when present days > working days)
                $employee['excess_days'] = max(0, $employee['present_days'] - $employee['working_days']);
                $employee['excess_day_salary'] = $employee['excess_days'] * $per_day_salary;
                
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
                    
            // Initialize base salary if not set
            if (!isset($employee['base_salary']) || $employee['base_salary'] === null) {
                $employee['base_salary'] = 0;
            }
        } catch (PDOException $e) {
            error_log("Error fetching late days for user " . $employee['id'] . ": " . $e->getMessage());
            $employee['late_days'] = 0;
            $employee['shift_start_time'] = '09:00:00';
            $employee['grace_time'] = '09:15:00';
        }
    }
    unset($employee); // Break the reference
} catch (PDOException $e) {
    error_log("Error fetching employees: " . $e->getMessage());
    $employees = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Architecture Studio Manager' ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Dashboard CSS -->
    <link rel="stylesheet" href="includes/dashboard/dashboard_styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        body {
            background-color: #f5f5f5;
        }
        .navbar {
            background: #2c3e50;
            padding: 1rem 2rem;
            color: white;
        }
        .navbar h1 {
            font-size: 1.5rem;
        }
        .dashboard-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .card-body {
            padding: 1rem;
        }
        .table-responsive {
            margin-top: 2rem;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table thead th {
            background: #2c3e50;
            color: white;
            padding: 1rem;
            text-align: left;
        }
        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid #ddd;
        }
        .pagination {
            margin-top: 2rem;
        }
        .pagination li {
            margin-right: 0.5rem;
        }
        .pagination li a {
            padding: 0.5rem 1rem;
            background: #2c3e50;
            color: white;
            border-radius: 5px;
        }
        .pagination li a:hover {
            background: #34495e;
        }
        .pagination li.disabled a {
            background: #ddd;
            color: #999;
            cursor: not-allowed;
        }
        .pagination li.active a {
            background: #34495e;
        }
        .modal-content {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .modal-header {
            background: #2c3e50;
            color: white;
            padding: 1rem;
        }
        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
        }
        .modal-body {
            padding: 1rem;
        }
        .modal-footer {
            background: #f5f5f5;
            padding: 1rem;
        }
        .modal-footer button {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="dashboard-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="section-title mb-0">Salary Management</h2>
                        <div class="d-flex align-items-center">
                            <div class="form-group mr-3 mb-0">
                                <label for="monthSelect" class="mr-2">Select Month:</label>
                                <select class="form-control" id="monthSelect" style="width: auto; display: inline-block;">
                                    <?php
                                    // Generate options for the last 12 months
                                    for ($i = 0; $i < 12; $i++) {
                                        $month = date('Y-m', strtotime("-$i months"));
                                        $monthName = date('F Y', strtotime("-$i months"));
                                        $selected = ($month == $selected_month) ? 'selected' : '';
                                        echo "<option value=\"$month\" $selected>$monthName</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <button class="btn btn-primary" id="exportCsvBtn">
                                <i class="fas fa-download mr-2"></i>Export CSV
                            </button>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Employees</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= count($employees) ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Total Salary</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?= number_format(array_sum(array_column($employees, 'net_salary')), 2) ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-rupee-sign fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Overtime</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">₹0</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                Deductions</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?= number_format(array_sum(array_column($employees, 'leave_deduction_amount')) + array_sum(array_column($employees, 'late_deduction_amount')) + array_sum(array_column($employees, 'one_hour_late_deduction_amount')), 2) ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-minus-circle fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Base Salary</th>
                                    <th>Working Days</th>
                                    <th>Present Days</th>
                                    <th>Leave Taken</th>
                                    <th>Leave Deduction</th>
                                    <th>Short Leave</th>
                                    <th>Late Days</th>
                                    <th>Late Deduction</th>
                                    <th>1+ Hour Late</th>
                                    <th>1+ Hour Late Deduction</th>
                                    <th>4th Punch Out Missing</th>
                                    <th>Salary Days Calculated</th>
                                    <th>Salary Days After Penalty</th>
                                    <th>Penalty</th>
                                    <th>Net Salary</th>
                                    <th>Excess Day Salary</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($employees)): ?>
                                    <?php foreach ($employees as $employee): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($employee['unique_id']) ?></td>
                                        <td><?= htmlspecialchars($employee['username']) ?></td>
                                        <td><?= htmlspecialchars($employee['role']) ?></td>
                                        <td>₹<?= number_format($employee['base_salary'] ?? 0, 2) ?></td>
                                        <td><?= htmlspecialchars($employee['working_days']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($employee['present_days']) ?>
                                            <button class="btn btn-sm btn-info ml-2 present-days-details-btn" 
                                                    data-user-id="<?= $employee['id'] ?>" 
                                                    data-month="<?= $selected_month ?>" 
                                                    title="View Present Days Details">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($employee['leave_taken']) ?> days (<?= htmlspecialchars($employee['leave_count']) ?>)
                                            <button class="btn btn-sm btn-info ml-2 leave-details-btn" 
                                                    data-user-id="<?= $employee['id'] ?>" 
                                                    data-month="<?= $selected_month ?>" 
                                                    title="View Leave Details">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                        </td>
                                        <td>₹<?= number_format($employee['leave_deduction_amount'] ?? 0, 2) ?></td>
                                        <td>
                                            <?= htmlspecialchars($employee['short_leave_days']) ?> days
                                            <button class="btn btn-sm btn-info ml-2 short-leave-details-btn" 
                                                    data-user-id="<?= $employee['id'] ?>" 
                                                    data-month="<?= $selected_month ?>" 
                                                    title="View Short Leave Details">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($employee['late_days']) ?>
                                            <button class="btn btn-sm btn-info ml-2 late-details-btn" 
                                                    data-user-id="<?= $employee['id'] ?>" 
                                                    data-month="<?= $selected_month ?>" 
                                                    title="View Late Details">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                        <td>₹<?= number_format($employee['late_deduction_amount'] ?? 0, 2) ?></td>
                                        <td>
                                            <?= htmlspecialchars($employee['one_hour_late_count']) ?>
                                            <button class="btn btn-sm btn-info ml-2 one-hour-late-details-btn" 
                                                    data-user-id="<?= $employee['id'] ?>" 
                                                    data-month="<?= $selected_month ?>" 
                                                    title="View 1+ Hour Late Details">
                                                <i class="fas fa-info-circle"></i>
                                            </button>
                                        </td>
                                        <td>₹<?= number_format($employee['one_hour_late_deduction_amount'] ?? 0, 2) ?></td>
                                        <td>
                                            <?php if ($employee['missing_fourth_saturday_punch_out']): ?>
                                                <span class="text-danger">Yes (3 days deduction)</span>
                                                <small class="d-block text-muted"><?= date('d M', strtotime($employee['fourth_saturday_date'])) ?></small>
                                            <?php else: ?>
                                                <span class="text-success">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($employee['missing_fourth_saturday_punch_out']): ?>
                                                <?= number_format($employee['salary_days_calculated_before_deduction'], 1) ?> - 3 = <?= number_format($employee['salary_days_calculated'], 1) ?> days
                                            <?php else: ?>
                                                <?= number_format($employee['salary_days_calculated'] ?? 0, 1) ?> days
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= number_format($employee['salary_days_calculated'] - $employee['penalty'], 1) ?> days
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <button class="btn btn-sm btn-outline-danger penalty-decrease" 
                                                        data-user-id="<?= $employee['id'] ?>" 
                                                        data-penalty-value="<?= $employee['penalty'] ?? 0 ?>">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <span class="mx-2 penalty-value" id="penalty-<?= $employee['id'] ?>"><?= number_format($employee['penalty'] ?? 0, 1) ?></span>
                                                <button class="btn btn-sm btn-outline-success penalty-increase" 
                                                        data-user-id="<?= $employee['id'] ?>" 
                                                        data-penalty-value="<?= $employee['penalty'] ?? 0 ?>">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td id="net-salary-<?= $employee['id'] ?>" 
                                            data-original-salary="<?= $employee['net_salary_before_penalty'] ?? $employee['net_salary'] ?? 0 ?>" 
                                            data-per-day-salary="<?= $per_day_salary ?? 0 ?>">₹<?= number_format($employee['net_salary'] ?? 0, 2) ?></td>
                                        <td>₹<?= number_format($employee['excess_day_salary'] ?? 0, 2) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info salary-details-btn mr-1" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-primary edit-salary-btn" 
                                                    data-user-id="<?= $employee['id'] ?>" 
                                                    data-base-salary="<?= $employee['base_salary'] ?? 0 ?>" 
                                                    title="Edit Salary">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-success paid-btn ml-1" 
                                                    data-user-id="<?= $employee['id'] ?>"
                                                    title="Mark as Paid">
                                                <i class="fas fa-check"></i> Paid
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="18" class="text-center">No employees found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div>
                            Showing 1 to 5 of 124 entries
                        </div>
                        <nav>
                            <ul class="pagination mb-0">
                                <li class="page-item disabled">
                                    <a class="page-link" href="#" tabindex="-1">Previous</a>
                                </li>
                                <li class="page-item active">
                                    <a class="page-link" href="#">1</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="#">2</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="#">3</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="#">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Salary Detail Modal -->
    <div class="modal fade" id="salaryDetailModal" tabindex="-1" aria-labelledby="salaryDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="salaryDetailModalLabel">Salary Details - Loading...</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Employee information will be loaded here -->
                        </div>
                        <div class="col-md-6">
                            <!-- Salary summary will be loaded here -->
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <!-- Deduction breakdown will be loaded here -->
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-12">
                            <!-- Earnings will be loaded here -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary print-salary-slip">Print Salary Slip</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Details Modal -->
    <div class="modal fade" id="leaveDetailsModal" tabindex="-1" aria-labelledby="leaveDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leaveDetailsModalLabel">Leave Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Leave details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Late Details Modal -->
    <div class="modal fade" id="lateDetailsModal" tabindex="-1" aria-labelledby="lateDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="lateDetailsModalLabel">Late Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Late details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 1+ Hour Late Details Modal -->
    <div class="modal fade" id="oneHourLateDetailsModal" tabindex="-1" aria-labelledby="oneHourLateDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="oneHourLateDetailsModalLabel">1+ Hour Late Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- 1+ hour late details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Present Days Details Modal -->
    <div class="modal fade" id="presentDaysDetailsModal" tabindex="-1" aria-labelledby="presentDaysDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="presentDaysDetailsModalLabel">Present Days Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Present days details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Short Leave Details Modal -->
    <div class="modal fade" id="shortLeaveDetailsModal" tabindex="-1" aria-labelledby="shortLeaveDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shortLeaveDetailsModalLabel">Short Leave Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Short leave details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Salary Modal -->
    <div class="modal fade" id="editSalaryModal" tabindex="-1" aria-labelledby="editSalaryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSalaryModalLabel">Edit Salary</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="form-group">
                            <label for="baseSalary">Base Salary (₹)</label>
                            <input type="number" class="form-control" id="baseSalary" value="0">
                        </div>
                        <div class="form-group">
                            <label for="incrementPercentage">Increment Percentage (%)</label>
                            <input type="number" class="form-control" id="incrementPercentage" value="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="effectiveDate">Effective From</label>
                            <input type="date" class="form-control" id="effectiveDate" value="">
                        </div>
                        <input type="hidden" id="userId" value="">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveSalaryBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        $(document).ready(function(){
            $('[data-toggle="tooltip"]').tooltip();
            
            // Export CSV button click handler
            $('#exportCsvBtn').click(function() {
                var selectedMonth = $('#monthSelect').val();
                window.location.href = 'export_salary_csv.php?month=' + selectedMonth;
            });
            
            // Edit button click handler
            $('.edit-salary-btn').click(function() {
                // Get employee data from the button data attributes
                var userId = $(this).data('user-id');
                var baseSalary = $(this).data('base-salary');
                
                // Set the user ID and base salary in the modal form
                $('#userId').val(userId);
                $('#baseSalary').val(baseSalary);
                
                // Clear other fields
                $('#incrementPercentage').val('0');
                $('#effectiveDate').val('');
                
                $('#editSalaryModal').modal('show');
            });
            
            // Save salary data handler
            $('#saveSalaryBtn').click(function() {
                var userId = $('#userId').val();
                var baseSalary = $('#baseSalary').val();
                var incrementPercentage = $('#incrementPercentage').val();
                var effectiveDate = $('#effectiveDate').val();
                
                // Validate required fields
                if (!userId || userId <= 0 || isNaN(userId)) {
                    alert('Valid User ID is required');
                    return;
                }
                
                if (!baseSalary || baseSalary < 0 || isNaN(baseSalary)) {
                    alert('Valid Base Salary is required');
                    return;
                }
                
                // Show loading state
                var saveBtn = $(this);
                var originalText = saveBtn.text();
                saveBtn.prop('disabled', true).text('Saving...');
                
                // Send data to server
                $.post('save_salary_data.php', {
                    user_id: userId,
                    base_salary: baseSalary,
                    increment_percentage: incrementPercentage || 0,
                    effective_from: effectiveDate || null
                }, function(response) {
                    if (response.success) {
                        alert('Salary data saved successfully');
                        $('#editSalaryModal').modal('hide');
                        // Reload the page to show updated data
                        location.reload();
                    } else {
                        alert('Error: ' + (response.error || 'Unknown error'));
                    }
                }, 'json').fail(function() {
                    alert('Error saving salary data');
                }).always(function() {
                    // Restore button state
                    saveBtn.prop('disabled', false).text(originalText);
                });
            });
            
            // Month selection change handler
            $('#monthSelect').change(function() {
                var selectedMonth = $(this).val();
                window.location.href = '?month=' + selectedMonth;
            });
            
            // Leave details button click handler
            $('.leave-details-btn').click(function() {
                var userId = $(this).data('user-id');
                var month = $(this).data('month');
                
                // Show loading message
                $('#leaveDetailsModal .modal-body').html('<p>Loading leave details...</p>');
                $('#leaveDetailsModal').modal('show');
                
                // Fetch leave details via AJAX
                $.get('get_leave_late_details.php', {
                    user_id: userId,
                    month: month,
                    type: 'leave'
                }, function(data) {
                    if (data.error) {
                        $('#leaveDetailsModal .modal-body').html('<p class="text-danger">Error: ' + data.error + '</p>');
                        return;
                    }
                    
                    // Build leave details table
                    var html = '<h6>Leave Details for ' + month + '</h6>';
                    
                    if (data.details.length === 0) {
                        html += '<p>No leaves taken in this month.</p>';
                    } else {
                        html += '<table class="table table-bordered">';
                        html += '<thead><tr><th>Date</th><th>Leave Type</th><th>Days</th><th>Reason</th></tr></thead>';
                        html += '<tbody>';
                        
                        $.each(data.details, function(index, leave) {
                            var dateRange = leave.start_date;
                            if (leave.start_date !== leave.end_date) {
                                dateRange += ' to ' + leave.end_date;
                            }
                            
                            html += '<tr>';
                            html += '<td>' + dateRange + '</td>';
                            html += '<td>' + leave.leave_type + '</td>';
                            html += '<td>' + leave.days_count + '</td>';
                            html += '<td>' + (leave.reason || 'N/A') + '</td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                    }
                    
                    $('#leaveDetailsModal .modal-body').html(html);
                }).fail(function() {
                    $('#leaveDetailsModal .modal-body').html('<p class="text-danger">Error loading leave details.</p>');
                });
            });
            
            // Late details button click handler
            $('.late-details-btn').click(function() {
                var userId = $(this).data('user-id');
                var month = $(this).data('month');
                
                // Show loading message
                $('#lateDetailsModal .modal-body').html('<p>Loading late details...</p>');
                $('#lateDetailsModal').modal('show');
                
                // Fetch late details via AJAX
                $.get('get_leave_late_details.php', {
                    user_id: userId,
                    month: month,
                    type: 'late'
                }, function(data) {
                    if (data.error) {
                        $('#lateDetailsModal .modal-body').html('<p class="text-danger">Error: ' + data.error + '</p>');
                        return;
                    }
                    
                    // Build late details table
                    var html = '<h6>Late Details for ' + month + '</h6>';
                    html += '<p>Shift Start Time: ' + data.shift_start + ', Grace Time: ' + data.grace_time + '</p>';
                    
                    if (data.details.length === 0) {
                        html += '<p>No late arrivals in this month.</p>';
                    } else {
                        html += '<table class="table table-bordered">';
                        html += '<thead><tr><th>Date</th><th>Punch In Time</th><th>Minutes Late</th></tr></thead>';
                        html += '<tbody>';
                        
                        $.each(data.details, function(index, late) {
                            html += '<tr>';
                            html += '<td>' + late.date + '</td>';
                            html += '<td>' + late.punch_in + '</td>';
                            html += '<td>' + late.minutes_late + ' minutes</td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                    }
                    
                    $('#lateDetailsModal .modal-body').html(html);
                }).fail(function() {
                    $('#lateDetailsModal .modal-body').html('<p class="text-danger">Error loading late details.</p>');
                });
            });
            
            // 1+ Hour late details button click handler
            $('.one-hour-late-details-btn').click(function() {
                var userId = $(this).data('user-id');
                var month = $(this).data('month');
                
                // Show loading message
                $('#oneHourLateDetailsModal .modal-body').html('<p>Loading 1+ hour late details...</p>');
                $('#oneHourLateDetailsModal').modal('show');
                
                // Fetch 1+ hour late details via AJAX
                $.get('get_leave_late_details.php', {
                    user_id: userId,
                    month: month,
                    type: 'one_hour_late'
                }, function(data) {
                    if (data.error) {
                        $('#oneHourLateDetailsModal .modal-body').html('<p class="text-danger">Error: ' + data.error + '</p>');
                        return;
                    }
                    
                    // Build 1+ hour late details table
                    var html = '<h6>1+ Hour Late Details for ' + month + '</h6>';
                    html += '<p>Shift Start Time: ' + data.shift_start + ', 1 Hour Late Time: ' + data.one_hour_late_time + '</p>';
                    
                    if (data.details.length === 0) {
                        html += '<p>No 1+ hour late arrivals in this month.</p>';
                    } else {
                        html += '<table class="table table-bordered">';
                        html += '<thead><tr><th>Date</th><th>Punch In Time</th><th>Minutes Late</th></tr></thead>';
                        html += '<tbody>';
                        
                        $.each(data.details, function(index, late) {
                            html += '<tr>';
                            html += '<td>' + late.date + '</td>';
                            html += '<td>' + late.punch_in + '</td>';
                            html += '<td>' + late.minutes_late + ' minutes</td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                    }
                    
                    $('#oneHourLateDetailsModal .modal-body').html(html);
                }).fail(function() {
                    $('#oneHourLateDetailsModal .modal-body').html('<p class="text-danger">Error loading 1+ hour late details.</p>');
                });
            });
            
            // Salary details button click handler
            $('.salary-details-btn').click(function() {
                var userId = $(this).closest('tr').find('.edit-salary-btn').data('user-id');
                var selectedMonth = $('#monthSelect').val();
                
                // Store user ID in the modal for later use
                $('#salaryDetailModal').data('user-id', userId);
                
                // Show loading message
                $('#salaryDetailModal .modal-body').html('<div class="row"><div class="col-md-6"><p>Loading salary details...</p></div><div class="col-md-6"></div></div><div class="row mt-4"><div class="col-12"></div></div><div class="row mt-4"><div class="col-12"></div></div>');
                $('#salaryDetailModal .modal-title').text('Salary Details - Loading...');
                $('#salaryDetailModal').modal('show');
                
                // Fetch salary details via AJAX
                $.get('get_salary_details.php', {
                    user_id: userId,
                    month: selectedMonth
                }, function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        // Update modal title
                        $('#salaryDetailModal .modal-title').text('Salary Details - ' + data.username);
                        
                        // Update employee information
                        var employeeInfoHtml = '<h6>Employee Information</h6>';
                        employeeInfoHtml += '<table class="table table-sm">';
                        employeeInfoHtml += '<tr><td><strong>Employee ID:</strong></td><td>' + data.unique_id + '</td></tr>';
                        employeeInfoHtml += '<tr><td><strong>Name:</strong></td><td>' + data.username + '</td></tr>';
                        employeeInfoHtml += '<tr><td><strong>Role:</strong></td><td>' + data.role + '</td></tr>';
                        employeeInfoHtml += '<tr><td><strong>Status:</strong></td><td><span class="badge badge-success">Active</span></td></tr>';
                        employeeInfoHtml += '</table>';
                        $('#salaryDetailModal .modal-body .col-md-6:first').html(employeeInfoHtml);
                        
                        // Update salary summary
                        var salarySummaryHtml = '<h6>Salary Summary</h6>';
                        salarySummaryHtml += '<table class="table table-sm">';
                        salarySummaryHtml += '<tr><td><strong>Base Salary:</strong></td><td>₹' + parseFloat(data.base_salary || 0).toFixed(2) + '</td></tr>';
                        salarySummaryHtml += '<tr><td><strong>Working Days:</strong></td><td>' + data.working_days + '</td></tr>';
                        salarySummaryHtml += '<tr><td><strong>Present Days:</strong></td><td>' + data.present_days + '</td></tr>';
                        salarySummaryHtml += '<tr><td><strong>Leave Taken:</strong></td><td>' + parseFloat(data.leave_taken || 0).toFixed(1) + ' days</td></tr>';
                        salarySummaryHtml += '<tr><td><strong>Late Days:</strong></td><td>' + data.late_days + '</td></tr>';
                        salarySummaryHtml += '<tr><td><strong>Net Salary:</strong></td><td>₹' + parseFloat(data.net_salary || 0).toFixed(2) + '</td></tr>';
                        salarySummaryHtml += '</table>';
                        $('#salaryDetailModal .modal-body .col-md-6:last').html(salarySummaryHtml);
                        
                        // Update deduction breakdown
                        var deductionHtml = '<h6>Deduction Breakdown</h6>';
                        deductionHtml += '<table class="table table-bordered">';
                        deductionHtml += '<thead class="thead-light"><tr><th>Description</th><th>Days</th><th>Amount</th></tr></thead>';
                        deductionHtml += '<tbody>';
                        deductionHtml += '<tr><td>Late Punch In</td><td>' + (data.late_days || 0) + ' days</td><td>₹' + parseFloat(data.late_deduction_amount || 0).toFixed(2) + '</td></tr>';
                        deductionHtml += '<tr><td>1+ Hour Late</td><td>' + (data.one_hour_late_count || 0) + ' days</td><td>₹' + parseFloat(data.one_hour_late_deduction_amount || 0).toFixed(2) + '</td></tr>';
                        deductionHtml += '<tr><td>Leave Deduction</td><td>' + parseFloat(data.leave_taken || 0).toFixed(1) + ' days</td><td>₹' + parseFloat(data.leave_deduction_amount || 0).toFixed(2) + '</td></tr>';
                        deductionHtml += '<tr><td>Penalty</td><td>' + parseFloat(data.penalty || 0).toFixed(1) + ' days</td><td>₹' + (parseFloat(data.penalty || 0) * (parseFloat(data.base_salary || 0) / parseFloat(data.working_days || 1))).toFixed(2) + '</td></tr>';
                        deductionHtml += '<tr><td><strong>Total Deductions</strong></td><td><strong>' + (parseFloat(data.late_days || 0) + parseFloat(data.one_hour_late_count || 0) + parseFloat(data.leave_taken || 0) + parseFloat(data.penalty || 0)).toFixed(1) + ' days</strong></td><td><strong>₹' + (parseFloat(data.late_deduction_amount || 0) + parseFloat(data.one_hour_late_deduction_amount || 0) + parseFloat(data.leave_deduction_amount || 0) + (parseFloat(data.penalty || 0) * (parseFloat(data.base_salary || 0) / parseFloat(data.working_days || 1)))).toFixed(2) + '</strong></td></tr>';
                        deductionHtml += '</tbody></table>';
                        $('#salaryDetailModal .modal-body .col-12:first').html(deductionHtml);
                        
                        // Update earnings
                        var earningsHtml = '<h6>Earnings</h6>';
                        earningsHtml += '<table class="table table-bordered">';
                        earningsHtml += '<thead class="thead-light"><tr><th>Description</th><th>Days</th><th>Amount</th></tr></thead>';
                        earningsHtml += '<tbody>';
                        earningsHtml += '<tr><td>Base Salary</td><td>' + parseFloat(data.salary_days_calculated || 0).toFixed(1) + ' days</td><td>₹' + parseFloat(data.net_salary || 0).toFixed(2) + '</td></tr>';
                        earningsHtml += '<tr><td>Excess Day Salary</td><td>' + parseFloat(data.excess_days || 0).toFixed(1) + ' days</td><td>₹' + parseFloat(data.excess_day_salary || 0).toFixed(2) + '</td></tr>';
                        earningsHtml += '<tr><td><strong>Total Earnings</strong></td><td><strong>' + (parseFloat(data.salary_days_calculated || 0) + parseFloat(data.excess_days || 0)).toFixed(1) + ' days</strong></td><td><strong>₹' + (parseFloat(data.net_salary || 0) + parseFloat(data.excess_day_salary || 0)).toFixed(2) + '</strong></td></tr>';
                        earningsHtml += '</tbody></table>';
                        $('#salaryDetailModal .modal-body .col-12:last').html(earningsHtml);
                    } else {
                        $('#salaryDetailModal .modal-body').html('<p class="text-danger">Error: ' + (response.error || 'Unknown error') + '</p>');
                    }
                }).fail(function() {
                    $('#salaryDetailModal .modal-body').html('<p class="text-danger">Error loading salary details.</p>');
                });
            });
            
            // Present days details button click handler
            $('.present-days-details-btn').click(function() {
                var userId = $(this).data('user-id');
                var month = $(this).data('month');
                
                // Show loading message
                $('#presentDaysDetailsModal .modal-body').html('<p>Loading present days details...</p>');
                $('#presentDaysDetailsModal').modal('show');
                
                // Fetch present days details via AJAX
                $.get('get_leave_late_details.php', {
                    user_id: userId,
                    month: month,
                    type: 'present_days'
                }, function(data) {
                    if (data.error) {
                        $('#presentDaysDetailsModal .modal-body').html('<p class="text-danger">Error: ' + data.error + '</p>');
                        return;
                    }
                    
                    // Build present days details table
                    var html = '<h6>Present Days Details for ' + month + '</h6>';
                    
                    if (data.details.length === 0) {
                        html += '<p>No present days recorded in this month.</p>';
                    } else {
                        html += '<table class="table table-bordered">';
                        html += '<thead><tr><th>Date</th><th>Punch In Time</th><th>Punch Out Time</th><th>Status</th></tr></thead>';
                        html += '<tbody>';
                        
                        $.each(data.details, function(index, record) {
                            html += '<tr>';
                            html += '<td>' + record.date + '</td>';
                            html += '<td>' + (record.punch_in || 'N/A') + '</td>';
                            html += '<td>' + (record.punch_out || 'N/A') + '</td>';
                            html += '<td>' + record.status + '</td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                    }
                    
                    $('#presentDaysDetailsModal .modal-body').html(html);
                }).fail(function() {
                    $('#presentDaysDetailsModal .modal-body').html('<p class="text-danger">Error loading present days details.</p>');
                });
            });
            
            // Short leave details button click handler
            $('.short-leave-details-btn').click(function() {
                var userId = $(this).data('user-id');
                var month = $(this).data('month');
                
                // Show loading message
                $('#shortLeaveDetailsModal .modal-body').html('<p>Loading short leave details...</p>');
                $('#shortLeaveDetailsModal').modal('show');
                
                // Fetch short leave details via AJAX
                $.get('get_leave_late_details.php', {
                    user_id: userId,
                    month: month,
                    type: 'short_leave'
                }, function(data) {
                    if (data.error) {
                        $('#shortLeaveDetailsModal .modal-body').html('<p class="text-danger">Error: ' + data.error + '</p>');
                        return;
                    }
                    
                    // Build short leave details table
                    var html = '<h6>Short Leave Details for ' + month + '</h6>';
                    
                    if (data.details.length === 0) {
                        html += '<p>No short leaves taken in this month.</p>';
                    } else {
                        html += '<table class="table table-bordered">';
                        html += '<thead><tr><th>Date</th><th>Leave Type</th><th>Days</th><th>Reason</th></tr></thead>';
                        html += '<tbody>';
                        
                        $.each(data.details, function(index, leave) {
                            var dateRange = leave.start_date;
                            if (leave.start_date !== leave.end_date) {
                                dateRange += ' to ' + leave.end_date;
                            }
                            
                            html += '<tr>';
                            html += '<td>' + dateRange + '</td>';
                            html += '<td>' + leave.leave_type + '</td>';
                            html += '<td>' + leave.days_count + '</td>';
                            html += '<td>' + (leave.reason || 'N/A') + '</td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                    }
                    
                    $('#shortLeaveDetailsModal .modal-body').html(html);
                }).fail(function() {
                    $('#shortLeaveDetailsModal .modal-body').html('<p class="text-danger">Error loading short leave details.</p>');
                });
            });
            
            // Print salary slip button click handler
            $(document).on('click', '.print-salary-slip', function() {
                // Get the user ID and month from the currently open modal
                var userId = $('#salaryDetailModal').data('user-id');
                var selectedMonth = $('#monthSelect').val();
                
                if (userId && selectedMonth) {
                    // Open the salary slip in a new window/tab
                    window.open('salary_slip.php?user_id=' + userId + '&month=' + selectedMonth, '_blank');
                } else {
                    alert('Unable to generate salary slip. Missing user ID or month.');
                }
            });
            
            // Penalty increment button click handler
            $(document).on('click', '.penalty-increase', function() {
                var userId = $(this).data('user-id');
                var currentPenalty = parseFloat($(this).data('penalty-value')) || 0;
                
                // Prompt for reason before increasing penalty
                var reason = prompt("Please enter the reason for increasing the penalty:");
                if (reason === null || reason.trim() === "") {
                    alert("Reason is required to increase the penalty.");
                    return;
                }
                
                var newPenalty = currentPenalty + 0.5;
                
                // Update the displayed value
                $('#penalty-' + userId).text(newPenalty.toFixed(1));
                
                // Update the data attributes on both buttons
                $('.penalty-increase[data-user-id="' + userId + '"]').data('penalty-value', newPenalty);
                $('.penalty-decrease[data-user-id="' + userId + '"]').data('penalty-value', newPenalty);
                
                // Update the net salary display
                var netSalaryElement = $('#net-salary-' + userId);
                var currentNetSalary = parseFloat(netSalaryElement.data('original-salary')) || 0;
                if (currentNetSalary === 0) {
                    // Store the original salary if not already stored
                    currentNetSalary = parseFloat(netSalaryElement.text().replace(/[^\d.-]/g, '')) || 0;
                    netSalaryElement.data('original-salary', currentNetSalary);
                }
                
                // If this is the first adjustment after page load, we might need to account for existing penalties
                // Check if the displayed salary matches what we expect after penalty deduction
                var displayedSalary = parseFloat(netSalaryElement.text().replace(/[^\d.-]/g, '')) || 0;
                var perDaySalary = parseFloat(netSalaryElement.data('per-day-salary')) || 0;
                
                // If per day salary is not available, try to calculate it from original salary and working days
                if (perDaySalary <= 0) {
                    var workingDays = parseInt(netSalaryElement.closest('tr').find('td:eq(4)').text()) || 0;
                    if (workingDays > 0) {
                        perDaySalary = currentNetSalary / workingDays;
                    } else {
                        perDaySalary = 0; // Cannot calculate without working days
                    }
                }
                
                var expectedSalaryWithCurrentPenalty = currentNetSalary - (currentPenalty * perDaySalary);
                if (Math.abs(displayedSalary - expectedSalaryWithCurrentPenalty) > 1) {
                    // There's a mismatch, likely due to existing penalties on page load
                    // Adjust the original salary to account for this
                    currentNetSalary = displayedSalary + (currentPenalty * perDaySalary);
                    netSalaryElement.data('original-salary', currentNetSalary);
                }
                
                // Calculate new net salary (salary days after penalty * per day salary)
                // Get working days from the table
                var workingDays = parseInt(netSalaryElement.closest('tr').find('td:eq(4)').text()) || 0;
                // Get salary days calculated from the table (14th column)
                var salaryDaysCalculated = parseFloat(netSalaryElement.closest('tr').find('td:eq(14)').text().match(/[\d.]+/g)?.pop() || 0);
                // Calculate salary days after penalty
                var salaryDaysAfterPenalty = Math.max(0, salaryDaysCalculated - newPenalty);
                // Calculate new net salary
                var newNetSalary = salaryDaysAfterPenalty * perDaySalary;
                
                // Update the net salary display
                netSalaryElement.text('₹' + newNetSalary.toFixed(2));
                
                // Save penalty reason to database
                var selectedMonth = $('#monthSelect').val();
                $.post('save_penalty_reason.php', {
                    user_id: userId,
                    penalty_date: selectedMonth + '-01', // First day of selected month
                    penalty_amount: newPenalty,
                    reason: reason
                }, function(response) {
                    if (!response.success) {
                        alert('Error saving penalty reason: ' + (response.error || 'Unknown error'));
                    }
                }, 'json').fail(function() {
                    alert('Error saving penalty reason');
                });
            });
            
            // Penalty decrement button click handler
            $(document).on('click', '.penalty-decrease', function() {
                var userId = $(this).data('user-id');
                var currentPenalty = parseFloat($(this).data('penalty-value')) || 0;
                var newPenalty = Math.max(0, currentPenalty - 0.5); // Don't go below 0
                
                // Update the displayed value
                $('#penalty-' + userId).text(newPenalty.toFixed(1));
                
                // Update the data attributes on both buttons
                $('.penalty-increase[data-user-id="' + userId + '"]').data('penalty-value', newPenalty);
                $('.penalty-decrease[data-user-id="' + userId + '"]').data('penalty-value', newPenalty);
                
                // Update the net salary display
                var netSalaryElement = $('#net-salary-' + userId);
                var currentNetSalary = parseFloat(netSalaryElement.data('original-salary')) || 0;
                if (currentNetSalary === 0) {
                    // Store the original salary if not already stored
                    currentNetSalary = parseFloat(netSalaryElement.text().replace(/[^\d.-]/g, '')) || 0;
                    netSalaryElement.data('original-salary', currentNetSalary);
                }
                
                // If this is the first adjustment after page load, we might need to account for existing penalties
                // Check if the displayed salary matches what we expect after penalty deduction
                var displayedSalary = parseFloat(netSalaryElement.text().replace(/[^\d.-]/g, '')) || 0;
                var perDaySalary = parseFloat(netSalaryElement.data('per-day-salary')) || 0;
                
                // If per day salary is not available, try to calculate it from original salary and working days
                if (perDaySalary <= 0) {
                    var workingDays = parseInt(netSalaryElement.closest('tr').find('td:eq(4)').text()) || 0;
                    if (workingDays > 0) {
                        perDaySalary = currentNetSalary / workingDays;
                    } else {
                        perDaySalary = 0; // Cannot calculate without working days
                    }
                }
                
                var expectedSalaryWithCurrentPenalty = currentNetSalary - (currentPenalty * perDaySalary);
                if (Math.abs(displayedSalary - expectedSalaryWithCurrentPenalty) > 1) {
                    // There's a mismatch, likely due to existing penalties on page load
                    // Adjust the original salary to account for this
                    currentNetSalary = displayedSalary + (currentPenalty * perDaySalary);
                    netSalaryElement.data('original-salary', currentNetSalary);
                }
                
                // Calculate new net salary (salary days after penalty * per day salary)
                // Get working days from the table
                var workingDays = parseInt(netSalaryElement.closest('tr').find('td:eq(4)').text()) || 0;
                // Get salary days calculated from the table (14th column)
                var salaryDaysCalculated = parseFloat(netSalaryElement.closest('tr').find('td:eq(14)').text().match(/[\d.]+/g)?.pop() || 0);
                // Calculate salary days after penalty
                var salaryDaysAfterPenalty = Math.max(0, salaryDaysCalculated - newPenalty);
                // Calculate new net salary
                var newNetSalary = salaryDaysAfterPenalty * perDaySalary;
                
                // Update the net salary display
                netSalaryElement.text('₹' + newNetSalary.toFixed(2));
                
                // If penalty is reduced to 0, we might want to remove the reason record
                if (newPenalty === 0) {
                    var selectedMonth = $('#monthSelect').val();
                    $.post('remove_penalty_reason.php', {
                        user_id: userId,
                        penalty_date: selectedMonth + '-01'
                    }, function(response) {
                        if (!response.success) {
                            console.log('Error removing penalty reason: ' + (response.error || 'Unknown error'));
                        }
                    }, 'json').fail(function() {
                        console.log('Error removing penalty reason');
                    });
                }
            });
            
            // Paid button click handler
            $(document).on('click', '.paid-btn', function() {
                var userId = $(this).data('user-id');
                var selectedMonth = $('#monthSelect').val();
                
                // Confirm before saving
                if (!confirm('Are you sure you want to mark this salary as paid and save all details?')) {
                    return;
                }
                
                // Collect all employee data
                var salaryData = [];
                $('tr').each(function() {
                    var rowUserId = $(this).find('.paid-btn').data('user-id');
                    if (rowUserId) {
                        // Get the penalty value for this user
                        var penaltyValue = parseFloat($('#penalty-' + rowUserId).text()) || 0;
                        
                        // Get the net salary value for this user
                        var netSalaryElement = $('#net-salary-' + rowUserId);
                        var netSalaryValue = parseFloat(netSalaryElement.text().replace(/[^\d.-]/g, '')) || 0;
                        
                        var employeeData = {
                            user_id: rowUserId,
                            month: selectedMonth + '-01',
                            employee_id: $(this).find('td:eq(0)').text(),
                            employee_name: $(this).find('td:eq(1)').text(),
                            role: $(this).find('td:eq(2)').text(),
                            base_salary: parseFloat($(this).find('td:eq(3)').text().replace(/[^\d.-]/g, '')) || 0,
                            working_days: parseInt($(this).find('td:eq(4)').text()) || 0,
                            present_days: parseInt($(this).find('td:eq(5)').text()) || 0,
                            leave_taken: parseFloat($(this).find('td:eq(6)').text()) || 0,
                            leave_deduction: parseFloat($(this).find('td:eq(7)').text().replace(/[^\d.-]/g, '')) || 0,
                            short_leave: parseFloat($(this).find('td:eq(8)').text()) || 0,
                            late_days: parseInt($(this).find('td:eq(9)').text()) || 0,
                            late_deduction: parseFloat($(this).find('td:eq(10)').text().replace(/[^\d.-]/g, '')) || 0,
                            one_hour_late: parseInt($(this).find('td:eq(11)').text()) || 0,
                            one_hour_late_deduction: parseFloat($(this).find('td:eq(12)').text().replace(/[^\d.-]/g, '')) || 0,
                            fourth_saturday_missing: $(this).find('td:eq(13)').text().includes('Yes') ? 1 : 0,
                            salary_days_calculated: parseFloat($(this).find('td:eq(14)').text().match(/[\d.]+/g)?.pop() || 0),
                            penalty: penaltyValue,
                            net_salary: netSalaryValue,
                            excess_day_salary: parseFloat($(this).find('td:eq(17)').text().replace(/[^\d.-]/g, '')) || 0
                        };
                        salaryData.push(employeeData);
                    }
                });
                
                // Send data to server
                var saveBtn = $(this);
                var originalText = saveBtn.html();
                saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
                
                $.post('save_salary_payment.php', {
                    data: JSON.stringify(salaryData)
                }, function(response) {
                    if (response.success) {
                        alert('Salary details saved successfully!');
                        // Optionally, you can change the button to show it's been paid
                        saveBtn.removeClass('btn-success').addClass('btn-secondary').html('<i class="fas fa-check"></i> Paid');
                        saveBtn.prop('disabled', true);
                    } else {
                        alert('Error saving salary details: ' + (response.error || 'Unknown error'));
                        saveBtn.prop('disabled', false).html(originalText);
                    }
                }, 'json').fail(function() {
                    alert('Error saving salary details');
                    saveBtn.prop('disabled', false).html(originalText);
                });
            });
        });
    </script>

</body>
</html>