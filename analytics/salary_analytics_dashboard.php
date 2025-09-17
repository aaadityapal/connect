<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/salary_analytics_errors.log');

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Check if user has HR role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    // Redirect to an access denied page or dashboard
    header("Location: ../access_denied.php?message=You must have HR role to access this page");
    exit;
}

// Include database connection
try {
    require_once '../config/db_connect.php';
    if (!isset($pdo)) {
        throw new Exception("Database connection not established");
    }
} catch (Exception $e) {
    die("<div style='padding: 20px; margin: 20px; border: 1px solid #dc2626; background: #fee2e2; color: #dc2626; border-radius: 8px;'>
         <h3>Database Connection Error</h3>
         <p>Unable to connect to the database: " . htmlspecialchars($e->getMessage()) . "</p>
         <p>Please check your database configuration and ensure the database server is running.</p>
         <p><a href='debug_production_issues.php' style='color: #2563eb;'>Run diagnostic check</a></p>
         </div>");
}

// Get current month for default filtering
$current_month = date('Y-m');
$selected_filter_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : $current_month;

// Calculate total users
$query = "SELECT COUNT(*) as total_users FROM users WHERE status = 'active' AND deleted_at IS NULL";
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_users = $result['total_users'] ?? 0;
} catch (PDOException $e) {
    error_log("Error fetching total users: " . $e->getMessage());
    $total_users = 0;
}

// Calculate monthly outstanding (pending salary payments)
// This would typically be calculated based on pending payroll entries
try {
    // First check if salary_payments table exists
    $table_check = "SHOW TABLES LIKE 'salary_payments'";
    $table_result = $pdo->query($table_check);
    
    if ($table_result->rowCount() > 0) {
        $outstanding_query = "SELECT COUNT(*) as outstanding_count 
                             FROM users u 
                             WHERE u.status = 'active' 
                             AND u.deleted_at IS NULL 
                             AND u.id NOT IN (
                                 SELECT DISTINCT user_id 
                                 FROM salary_payments sp 
                                 WHERE DATE_FORMAT(sp.payment_date, '%Y-%m') = ?
                             )";
        $stmt = $pdo->prepare($outstanding_query);
        $stmt->execute([$current_month]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $monthly_outstanding = $result['outstanding_count'] ?? 0;
    } else {
        // Table doesn't exist, use total users as outstanding
        $monthly_outstanding = $total_users;
    }
} catch (PDOException $e) {
    error_log("Error fetching monthly outstanding: " . $e->getMessage());
    $monthly_outstanding = $total_users; // Safe fallback
}

// Initialize overtime variables (overtime functionality removed)
$total_overtime = 0;
$pending_overtime = 0;

// Calculate total payable amount for current month
// This includes base salaries only (overtime removed)
$payable_query = "SELECT 
                  SUM(CASE 
                      WHEN si.salary_after_increment IS NOT NULL 
                      THEN si.salary_after_increment 
                      ELSE u.base_salary 
                  END) as total_base_salary
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
                  WHERE u.status = 'active' AND u.deleted_at IS NULL";

try {
    // Get base salary total
    $stmt = $pdo->prepare($payable_query);
    $stmt->execute();
    $base_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_base_salary = $base_result['total_base_salary'] ?? 0;
    
    $total_payable = $total_base_salary; // No overtime included
} catch (PDOException $e) {
    error_log("Error fetching payable amount: " . $e->getMessage());
    $total_payable = 0;
}

// Fetch active users for the table with month filter
$users_table_query = "SELECT 
    u.id,
    u.username,
    u.position,
    u.email,
    u.employee_id,
    u.phone_number,
    u.designation,
    u.department,
    u.role,
    u.unique_id,
    u.reporting_manager,
    u.created_at,
    u.updated_at,
    u.deleted_at,
    u.status,
    u.last_login,
    u.profile_image,
    u.address,
    u.emergency_contact,
    u.joining_date,
    u.modified_at,
    u.city,
    u.state,
    u.country,
    u.postal_code,
    u.emergency_contact_name,
    u.emergency_contact_phone,
    u.phone,
    u.dob,
    u.profile_picture,
    u.bio,
    u.gender,
    u.marital_status,
    u.nationality,
    u.languages,
    u.social_media,
    u.skills,
    u.interests,
    u.blood_group,
    u.education,
    u.work_experience,
    u.bank_details,
    u.shift_id,
    u.base_salary,
    CASE 
        WHEN si.salary_after_increment IS NOT NULL 
        THEN si.salary_after_increment 
        ELSE u.base_salary 
    END as current_salary,
    -- Shift information
    s.shift_name,
    s.start_time as shift_start_time,
    us.weekly_offs,
    -- Short leave preferences
    COALESCE(slp.use_for_one_hour_late, 0) as use_short_leave_for_one_hour,
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
LEFT JOIN user_shifts us ON u.id = us.user_id AND 
    (us.effective_to IS NULL OR us.effective_to >= LAST_DAY(?))
LEFT JOIN shifts s ON us.shift_id = s.id
LEFT JOIN short_leave_preferences slp ON u.id = slp.user_id AND slp.filter_month = ?
LEFT JOIN (
    SELECT 
        a.user_id,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
        COUNT(CASE WHEN a.status = 'present' 
                   AND TIMESTAMPDIFF(MINUTE, 
                       CONCAT(DATE(a.date), ' ', COALESCE(s.start_time, '09:00:00')), 
                       CONCAT(DATE(a.date), ' ', TIME(a.punch_in))
                   ) > 15 THEN 1 END) as late_days
    FROM attendance a
    LEFT JOIN users u_att ON a.user_id = u_att.id
    LEFT JOIN user_shifts us_att ON u_att.id = us_att.user_id AND 
        (us_att.effective_to IS NULL OR us_att.effective_to >= LAST_DAY(?))
    LEFT JOIN shifts s ON us_att.shift_id = s.id
    WHERE DATE_FORMAT(a.date, '%Y-%m') = ?
    GROUP BY a.user_id
) att ON u.id = att.user_id
WHERE u.status = 'active' AND u.deleted_at IS NULL 
ORDER BY u.username ASC";

try {
    $stmt = $pdo->prepare($users_table_query);
    $stmt->execute([$selected_filter_month, $selected_filter_month, $selected_filter_month, $selected_filter_month]);
    $active_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate working days for each user based on their weekly offs
    foreach ($active_users as &$user) {
        $working_days = 0;
        $weekly_offs = !empty($user['weekly_offs']) ? explode(',', $user['weekly_offs']) : [];
        
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
        
        // Calculate late punch-ins based on user's shift start time
        $shift_start = $user['shift_start_time'] ?? '09:00:00'; // Use actual shift start time or default
        
        // Validate shift start time format
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $shift_start)) {
            $shift_start = '09:00:00'; // Fallback to default if invalid format
            error_log("Invalid shift start time for user {$user['id']}, using default 09:00:00");
        }
        
        // Note: Late days are already calculated correctly in the main query using shift start time
        // No need to recalculate here unless we want to double-check
        
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
            // Table might not exist, create it
            try {
                $create_table_sql = "CREATE TABLE IF NOT EXISTS excess_days_carryover (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    month VARCHAR(7) NOT NULL,
                    excess_days_carried_forward INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_month (user_id, month)
                )";
                $pdo->exec($create_table_sql);
                $carried_forward_days = 0;
            } catch (PDOException $create_error) {
                error_log("Error creating excess_days_carryover table: " . $create_error->getMessage());
                $carried_forward_days = 0;
            }
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
        $current_salary = $user['incremented_salary'] ?? $user['current_salary'] ?? $user['base_salary'] ?? 0;
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
        $daily_salary = $working_days > 0 ? (($user['incremented_salary'] ?? $user['current_salary'] ?? $user['base_salary'] ?? 0) / $working_days) : 0;
        $leave_deduction_amount = $leave_deduction_days * $daily_salary;
        
        $user['leave_deduction_days'] = $leave_deduction_days;
        $user['leave_deduction_amount'] = $leave_deduction_amount;
        
        // Calculate 1-hour late punch-ins (more than 1 hour late)
        // Also exclude records that are 15 minutes or less late (grace period)
        // AND exclude excess days from 1-hour late penalties
        $one_hour_late_query = "SELECT 
                                COUNT(*) as one_hour_late_days,
                                GROUP_CONCAT(DISTINCT CONCAT(DATE(date), ' (', TIME(punch_in), ')') SEPARATOR ', ') as one_hour_late_dates,
                                GROUP_CONCAT(DISTINCT DATE(date) ORDER BY date) as one_hour_late_dates_only
                                FROM (
                                    SELECT date, punch_in,
                                           ROW_NUMBER() OVER (ORDER BY date) as day_rank
                                    FROM attendance 
                                    WHERE user_id = ? 
                                    AND DATE_FORMAT(date, '%Y-%m') = ? 
                                    AND status = 'present' 
                                    AND punch_in IS NOT NULL
                                    AND TIMESTAMPDIFF(MINUTE, 
                                        CONCAT(DATE(date), ' ', ?), 
                                        CONCAT(DATE(date), ' ', TIME(punch_in))
                                    ) > 60
                                ) ranked_attendance
                                WHERE day_rank <= ?
                                ORDER BY date";
        $one_hour_late_stmt = $pdo->prepare($one_hour_late_query);
        $one_hour_late_stmt->execute([$user['id'], $selected_filter_month, $shift_start, $effective_working_days_for_late]);
        $one_hour_late_result = $one_hour_late_stmt->fetch(PDO::FETCH_ASSOC);
        
        $one_hour_late_days = $one_hour_late_result['one_hour_late_days'] ?? 0;
        $one_hour_late_dates = $one_hour_late_result['one_hour_late_dates'] ?? '';
        
        // For now, get checkbox state from database
        $use_short_leave_for_one_hour = (bool) $user['use_short_leave_for_one_hour'];
        
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
        $current_salary_for_calculation = $user['incremented_salary'] ?? $user['current_salary'] ?? $user['base_salary'] ?? 0;
        $daily_salary_for_one_hour = $working_days > 0 ? ($current_salary_for_calculation / $working_days) : 0;
        $one_hour_late_deduction_amount = $one_hour_late_deduction * $daily_salary_for_one_hour;
        
        // Debug log for troubleshooting - only log if amount seems incorrect
        if ($one_hour_late_deduction_amount > ($current_salary_for_calculation * 0.1)) { // If deduction > 10% of salary, log it
            error_log("UNUSUAL 1-HOUR LATE DEDUCTION - User ID: {$user['id']}, Username: {$user['username']}, Salary: {$current_salary_for_calculation}, Working Days: {$working_days}, Daily Salary: {$daily_salary_for_one_hour}, One Hour Late Days: {$one_hour_late_days}, Deduction Days: {$one_hour_late_deduction}, Amount: {$one_hour_late_deduction_amount}");
        }
        
        // Update user data with new calculations
        $user['one_hour_late_days'] = $one_hour_late_days;
        $user['one_hour_late_dates'] = $one_hour_late_dates;
        $user['one_hour_late_deduction'] = $one_hour_late_deduction;
        $user['one_hour_late_deduction_amount'] = $one_hour_late_deduction_amount;
        $user['use_short_leave_for_one_hour'] = $use_short_leave_for_one_hour;
        
        // Update late deduction with adjusted values
        $daily_salary_for_late = $working_days > 0 ? (($user['incremented_salary'] ?? $user['current_salary'] ?? $user['base_salary'] ?? 0) / $working_days) : 0;
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
                $daily_salary_for_penalty = $working_days > 0 ? (($user['incremented_salary'] ?? $user['current_salary'] ?? $user['base_salary'] ?? 0) / $working_days) : 0;
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
        // Example: If user worked 23.5 out of 24.0 days, salary = (base_salary / 24.0) * 23.5
        // Debug calculation: Base: ₹12,000, Working Days: 24, Present Days: 23.5 → (12000/24)*23.5 = ₹11,750
        $daily_rate = $adjusted_working_days > 0 ? ($base_salary / $adjusted_working_days) : 0;
        $proportional_base_salary = $daily_rate * $adjusted_present_days;
        
        // Round to nearest rupee for cleaner display
        $proportional_base_salary = round($proportional_base_salary);
        
        // Debug log for verification
        if ($user['id'] == 29) { // Log for specific user if needed
            error_log("PROPORTIONAL SALARY CALC - User: {$user['username']}, Base: {$base_salary}, Working Days: {$adjusted_working_days}, Present Days: {$adjusted_present_days}, Proportional: {$proportional_base_salary}");
        }
        
        // Ensure proportional salary doesn't exceed base salary
        $proportional_base_salary = min($proportional_base_salary, $base_salary);
        
        // Get the old salary (previous incremented salary or base salary)
        $old_salary_query = "SELECT salary_after_increment FROM salary_increments 
                            WHERE user_id = ? 
                            AND effective_from < ?
                            ORDER BY effective_from DESC 
                            LIMIT 1";
        $old_salary_stmt = $pdo->prepare($old_salary_query);
        $old_salary_stmt->execute([$user['id'], date('Y-m-01', strtotime($selected_filter_month))]);
        $old_salary_result = $old_salary_stmt->fetch(PDO::FETCH_ASSOC);
        $user['old_salary'] = $old_salary_result['salary_after_increment'] ?? $user['base_salary'] ?? 0;
        
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
        
        // Store excess days carryover for next month
        if ($excess_days > 0) {
            $next_month = date('Y-m', strtotime($selected_filter_month . ' +1 month'));
            $carryover_insert_sql = "INSERT INTO excess_days_carryover (user_id, month, excess_days_carried_forward) 
                                   VALUES (?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE 
                                   excess_days_carried_forward = VALUES(excess_days_carried_forward)";
            try {
                $carryover_stmt = $pdo->prepare($carryover_insert_sql);
                $carryover_stmt->execute([$user['id'], $next_month, $excess_days]);
            } catch (PDOException $e) {
                error_log("Error storing excess days carryover: " . $e->getMessage());
            }
        }
        
        // Calculate excess days (when present days exceed working days)
        $excess_days = max(0, $user['present_days'] - $working_days);
        $user['excess_days'] = $excess_days;
        
        // Note: Excess days don't add to salary - they're just tracked for information
    }
    
} catch (PDOException $e) {
    error_log("Error fetching active users: " . $e->getMessage());
    $active_users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Analytics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Temporarily hide incremented salary input */
        .hide-incremented-salary input,
        .hide-incremented-salary button {
            display: none !important;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #fafbfc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1a1a1a;
            line-height: 1.6;
        }
        
        .page-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 2rem 0 1.5rem;
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0;
            text-align: center;
        }
        
        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            padding: 0;
        }
        
        .main-content.expanded {
            margin-left: 70px;
        }
        
        @media (max-width: 768px) {
            .main-content { 
                margin-left: 0; 
                padding-top: 60px; 
            }
        }
        
        .stats-container {
            padding: 0 2rem 1.5rem;
        }
        
        .stats-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.25rem;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: #d1d5db;
        }
        
        .stats-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }
        
        .stats-icon.total-users {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stats-icon.outstanding {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .stats-icon.overtime {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        .stats-icon.payable {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        
        .stats-title {
            font-size: 0.75rem;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 0.375rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stats-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.375rem;
            line-height: 1.2;
        }
        
        .stats-subtitle {
            font-size: 0.75rem;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .stats-trend {
            font-size: 0.6875rem;
            font-weight: 600;
            padding: 0.1875rem 0.375rem;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 0.1875rem;
        }
        
        .stats-trend.positive {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .stats-trend.neutral {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .sidebar-brand {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a1a1a;
            text-decoration: none;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-item {
            padding: 0.5rem 1.5rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #6b7280;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .nav-link:hover {
            background-color: #f3f4f6;
            color: #1a1a1a;
        }
        
        .nav-link.active {
            background-color: #eff6ff;
            color: #2563eb;
        }
        
        .nav-icon {
            width: 20px;
            height: 20px;
            margin-right: 0.75rem;
            font-size: 1rem;
        }
        
        .nav-text {
            white-space: nowrap;
        }
        
        .sidebar.collapsed .nav-text,
        .sidebar.collapsed .sidebar-brand {
            display: none;
        }
        
        .sidebar-toggle {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .sidebar-toggle:hover {
            background-color: #f3f4f6;
        }
        
        .filter-section {
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .filter-form {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .filter-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            background-color: #ffffff;
            transition: all 0.3s ease;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .filter-btn {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .filter-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .filter-btn:active {
            transform: translateY(0);
        }
        
        .users-table-section {
            padding: 0 2rem 2rem;
        }
        
        .table-container {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .table-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
        }
        
        .table-subtitle {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        
        .users-table th {
            background-color: #f8f9fa;
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        
        .users-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            color: #1a1a1a;
        }
        
        .users-table tbody tr:hover {
            background-color: #f9fafb;
        }
        
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .user-name {
            font-weight: 500;
            color: #1a1a1a;
        }
        
        .user-id {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .salary-amount {
            font-weight: 600;
            color: #059669;
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .users-table-section {
                padding: 0 1rem 1.5rem;
            }
            
            .table-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Toggle -->
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="../hr_dashboard.php" class="sidebar-brand">
                <i class="fas fa-users-cog"></i> HR System
            </a>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="../hr_dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt nav-icon"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="../salary.php" class="nav-link">
                    <i class="fas fa-money-bill-wave nav-icon"></i>
                    <span class="nav-text">Salary Management</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="salary_analytics_dashboard.php" class="nav-link active">
                    <i class="fas fa-chart-bar nav-icon"></i>
                    <span class="nav-text">Salary Analytics</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="../employee.php" class="nav-link">
                    <i class="fas fa-users nav-icon"></i>
                    <span class="nav-text">Employees</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="../attendance.php" class="nav-link">
                    <i class="fas fa-clock nav-icon"></i>
                    <span class="nav-text">Attendance</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="../leave.php" class="nav-link">
                    <i class="fas fa-calendar-times nav-icon"></i>
                    <span class="nav-text">Leave Management</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div class="container-fluid px-4">
                <h1 class="page-title">Salary Analytics Dashboard</h1>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="container-fluid px-4">
                <div class="row g-4">
                    <!-- Total Users Card -->
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="stats-icon total-users">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stats-title">Total Active Users</div>
                            <div class="stats-value"><?php echo number_format($total_users); ?></div>
                            <div class="stats-subtitle">
                                <span class="stats-trend positive">
                                    <i class="fas fa-arrow-up"></i>
                                    Active employees
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Outstanding Card -->
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="stats-icon outstanding">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stats-title">Monthly Outstanding</div>
                            <div class="stats-value"><?php echo number_format($monthly_outstanding); ?></div>
                            <div class="stats-subtitle">
                                <span class="stats-trend neutral">
                                    <i class="fas fa-clock"></i>
                                    Pending payments
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Total Overtime Card (Disabled) -->
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="stats-icon overtime">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stats-title">Total Overtime</div>
                            <div class="stats-value">Disabled</div>
                            <div class="stats-subtitle">
                                <span class="stats-trend neutral">
                                    <i class="fas fa-info-circle"></i>
                                    Feature removed
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Total Payable Amount Card -->
                    <div class="col-lg-3 col-md-6">
                        <div class="stats-card">
                            <div class="stats-icon payable">
                                <i class="fas fa-rupee-sign"></i>
                            </div>
                            <div class="stats-title">Total Payable Amount</div>
                            <div class="stats-value">₹<?php echo number_format($total_payable); ?></div>
                            <div class="stats-subtitle">
                                <span class="stats-trend positive">
                                    <i class="fas fa-arrow-up"></i>
                                    This month
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Users Table -->
        <div class="users-table-section">
            <div class="container-fluid px-4">
                <div class="table-container">
                    <div class="table-header">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <div>
                                <h2 class="table-title">Active Users</h2>
                                <p class="table-subtitle">Complete list of active employees in the system</p>
                            </div>
                        </div>
                        
                        <!-- Filter Section -->
                        <div class="filter-section">
                            <form method="GET" class="filter-form">
                                <div class="filter-group">
                                    <label for="filter_month" class="filter-label">
                                        <i class="fas fa-calendar-alt" style="margin-right: 0.5rem;"></i>
                                        Filter by Month
                                    </label>
                                    <input type="month" 
                                           id="filter_month" 
                                           name="filter_month" 
                                           value="<?php echo htmlspecialchars($selected_filter_month); ?>"
                                           class="filter-input">
                                </div>
                                <div style="display: flex; gap: 0.75rem;">
                                    <button type="submit" class="filter-btn">
                                        <i class="fas fa-search" style="margin-right: 0.5rem;"></i>
                                        Apply Filter
                                    </button>
                                    <a href="?" class="filter-btn" style="background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); text-decoration: none; display: inline-flex; align-items: center;">
                                        <i class="fas fa-undo" style="margin-right: 0.5rem;"></i>
                                        Reset
                                    </a>
                                    <button type="button" class="filter-btn" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);" onclick="exportToExcel()">
                                        <i class="fas fa-file-excel" style="margin-right: 0.5rem;"></i>
                                        Export to Excel
                                    </button>
                                    <!-- Salary editor toggle button hidden
                                    <button type="button" class="filter-btn" style="background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);" onclick="toggleIncrementedSalary()">
                                        <i class="fas fa-edit" style="margin-right: 0.5rem;"></i>
                                        <span id="toggleIncrementText">Show Salary Editor</span>
                                    </button>
                                    -->
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Old Salary</th>
                                    <th>Base Salary</th>
                                    <!-- Incremented Salary column hidden -->
                                    <th style="display: none;">Incremented Salary</th>
                                    <th>Working Days</th>
                                    <th>Present Days</th>
                                    <th>Carried Forward</th>
                                    <th>Excess Days</th>
                                    <th>Late Punch In</th>
                                    <th>Late Deduction</th>
                                    <th>Leave Taken</th>
                                    <th>Leave Deduction</th>
                                    <th>1 Hour Late Deduction</th>
                                    <th>4th Saturday Penalty</th>
                                    <th>Monthly Salary</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($active_users)): ?>
                                    <?php foreach ($active_users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <?php if (!empty($user['profile_image']) || !empty($user['profile_picture'])): ?>
                                                        <img src="<?php echo htmlspecialchars($user['profile_image'] ?? $user['profile_picture']); ?>" 
                                                             alt="<?php echo htmlspecialchars($user['username']); ?>" 
                                                             class="user-avatar">
                                                    <?php else: ?>
                                                        <div class="user-avatar" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                                                        <div class="user-id"><?php echo htmlspecialchars($user['unique_id'] ?? 'N/A'); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="salary-amount" style="color: #6b7280;">
                                                    ₹<?php echo number_format($user['old_salary'] ?? $user['base_salary'] ?? 0); ?>
                                                </span>
                                                <?php if (($user['old_salary'] ?? 0) != ($user['base_salary'] ?? 0)): ?>
                                                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem;">
                                                        Previous increment
                                                    </div>
                                                <?php else: ?>
                                                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem;">
                                                        Base salary
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="salary-amount">
                                                    ₹<?php echo number_format($user['current_salary'] ?? $user['base_salary'] ?? 0); ?>
                                                </span>
                                                <?php if ($user['adjusted_present_days'] < $user['adjusted_working_days']): ?>
                                                    <div style="font-size: 0.75rem; color: #dc2626; margin-top: 0.125rem;">
                                                        Proportional: ₹<?php echo number_format($user['proportional_base_salary']); ?>
                                                    </div>
                                                    <div style="font-size: 0.7rem; color: #6b7280; margin-top: 0.125rem;">
                                                        <?php echo number_format($user['adjusted_present_days'], 1); ?>/<?php echo number_format($user['adjusted_working_days'], 1); ?> days ratio
                                                    </div>
                                                    <div style="font-size: 0.65rem; color: #6b7280; margin-top: 0.125rem;">
                                                        (₹<?php echo number_format(($user['current_salary'] ?? $user['base_salary']) / $user['adjusted_working_days']); ?> per day)
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Incremented Salary column hidden -->
                                            <td style="display: none;" class="hide-incremented-salary">
                                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                    <input type="number" 
                                                           id="incremented_salary_<?php echo $user['id']; ?>"
                                                           value="<?php echo $user['incremented_salary'] ?? $user['current_salary'] ?? $user['base_salary'] ?? 0; ?>"
                                                           min="0"
                                                           step="100"
                                                           style="width: 120px; padding: 0.375rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem;"
                                                           onchange="updateIncrementedSalary(<?php echo $user['id']; ?>, '<?php echo $selected_filter_month; ?>')">
                                                    <button type="button" 
                                                            onclick="saveIncrementedSalary(<?php echo $user['id']; ?>, '<?php echo $selected_filter_month; ?>')"
                                                            style="padding: 0.25rem 0.5rem; background: #059669; color: white; border: none; border-radius: 4px; font-size: 0.75rem; cursor: pointer;"
                                                            title="Save increment">
                                                        <i class="fas fa-save"></i>
                                                    </button>
                                                </div>
                                                <!-- Display the value as text when inputs are hidden -->
                                                <span class="salary-amount">
                                                    ₹<?php echo number_format($user['incremented_salary'] ?? $user['current_salary'] ?? $user['base_salary'] ?? 0); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="color: #3b82f6; font-weight: 500;">
                                                    <?php echo number_format($user['adjusted_working_days'], 1); ?> days
                                                </span>
                                                <?php if ($user['adjusted_working_days'] != $user['working_days']): ?>
                                                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem;">
                                                        Original: <?php echo $user['working_days']; ?> days
                                                    </div>
                                                    <div style="font-size: 0.7rem; color: #f59e0b; margin-top: 0.125rem;">
                                                        Adjusted for half days
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span style="color: #059669; font-weight: 500;">
                                                    <?php echo number_format($user['adjusted_present_days'], 1); ?> days
                                                </span>
                                                <?php if ($user['adjusted_present_days'] != $user['present_days']): ?>
                                                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem;">
                                                        Attendance: <?php echo $user['present_days']; ?> days
                                                    </div>
                                                    <div style="font-size: 0.7rem; color: #3b82f6; margin-top: 0.125rem;">
                                                        +<?php echo number_format(($user['adjusted_present_days'] - $user['present_days']), 1); ?> half day credit
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['carried_forward_days'] > 0): ?>
                                                    <span style="color: #3b82f6; font-weight: 500;">
                                                        <?php echo $user['carried_forward_days']; ?> days
                                                    </span>
                                                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem;">
                                                        From previous month
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: #6b7280; font-weight: 500;">
                                                        0 days
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['excess_days'] > 0): ?>
                                                    <span style="color: #f59e0b; font-weight: 500;">
                                                        <?php echo $user['excess_days']; ?> days
                                                    </span>
                                                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem;">
                                                        Carries to next month
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: #6b7280; font-weight: 500;">
                                                        0 days
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span style="color: #f59e0b; font-weight: 500;">
                                                    <?php echo $user['late_days']; ?> days
                                                </span>
                                            </td>
                                            <td>
                                                <div>
                                                    <span style="color: #dc2626; font-weight: 600;">
                                                        ₹<?php echo number_format($user['late_deduction_amount'], 0); ?>
                                                    </span>
                                                    <?php if ($user['late_deduction_days'] > 0): ?>
                                                        <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem;">
                                                            <?php echo $user['late_deduction_days']; ?> days deducted
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($user['short_leave_days'] > 0): ?>
                                                        <div style="font-size: 0.75rem; color: #059669; margin-top: 0.125rem;">
                                                            <?php echo min($user['short_leave_days'], 2); ?> days covered by short leave
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <span style="color: #8b5cf6; font-weight: 500;">
                                                        <?php echo $user['total_leave_days']; ?> days
                                                    </span>
                                                    <?php if ($user['leave_types_taken'] !== 'None'): ?>
                                                        <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem;">
                                                            <?php echo htmlspecialchars($user['leave_types_taken']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <span style="color: #ef4444; font-weight: 600;">
                                                        ₹<?php echo number_format($user['leave_deduction_amount'], 0); ?>
                                                    </span>
                                                    <?php if ($user['leave_deduction_days'] > 0): ?>
                                                        <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem;">
                                                            <?php echo $user['leave_deduction_days']; ?> days deducted
                                                        </div>
                                                    <?php else: ?>
                                                        <div style="font-size: 0.75rem; color: #059669; margin-top: 0.125rem;">
                                                            No deduction
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($user['leave_deduction_amount'] > ($user['current_salary'] * 0.02)): // Show debug if > 2% of salary ?>
                                                        <div style="font-size: 0.7rem; margin-top: 0.25rem;">
                                                            <a href="debug_leave_deduction.php?user_id=<?php echo $user['id']; ?>&filter_month=<?php echo $selected_filter_month; ?>" 
                                                               style="color: #dc2626; text-decoration: underline;" 
                                                               target="_blank">Debug Leave Calc</a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <span style="color: #dc2626; font-weight: 600;">
                                                        ₹<?php echo number_format($user['one_hour_late_deduction_amount'], 0); ?>
                                                    </span>
                                                    <?php if ($user['one_hour_late_days'] > 0): ?>
                                                        <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem;">
                                                            <?php echo $user['one_hour_late_days']; ?> occurrences × 0.5 days each
                                                        </div>
                                                        <div style="font-size: 0.75rem; color: #dc2626; margin-top: 0.125rem;">
                                                            Total: <?php echo $user['one_hour_late_deduction']; ?> days deducted
                                                        </div>
                                                        <?php if (!empty($user['one_hour_late_dates'])): ?>
                                                            <div style="font-size: 0.7rem; color: #6b7280; margin-top: 0.25rem; max-width: 200px; overflow: hidden; text-overflow: ellipsis;" 
                                                                 title="<?php echo htmlspecialchars($user['one_hour_late_dates']); ?>">
                                                                Dates: <?php echo htmlspecialchars(substr($user['one_hour_late_dates'], 0, 50)) . (strlen($user['one_hour_late_dates']) > 50 ? '...' : ''); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ($user['one_hour_late_deduction_amount'] > ($user['current_salary'] * 0.05)): // Show debug if > 5% of salary ?>
                                                            <div style="font-size: 0.7rem; margin-top: 0.25rem;">
                                                                <a href="debug_one_hour_late.php?user_id=<?php echo $user['id']; ?>&filter_month=<?php echo $selected_filter_month; ?>" 
                                                                   style="color: #dc2626; text-decoration: underline;" 
                                                                   target="_blank">Debug Calculation</a>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div style="font-size: 0.75rem; color: #059669; margin-top: 0.125rem;">
                                                            No 1-hour late occurrences
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($user['one_hour_late_days'] > 0): ?>
                                                        <div style="margin-top: 0.5rem;">
                                                            <label style="display: flex; align-items: center; font-size: 0.75rem; color: #374151; cursor: pointer;">
                                                                <input type="checkbox" 
                                                                       id="short_leave_checkbox_<?php echo $user['id']; ?>"
                                                                       <?php echo $user['use_short_leave_for_one_hour'] ? 'checked' : ''; ?>
                                                                       onchange="toggleShortLeaveUsage(<?php echo $user['id']; ?>, '<?php echo $selected_filter_month; ?>')"
                                                                       style="margin-right: 0.375rem; transform: scale(0.9);">
                                                                Use short leave for 1-hour late
                                                            </label>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <span style="color: #7c2d12; font-weight: 600;">
                                                        ₹<?php echo number_format($user['fourth_saturday_penalty_amount'], 0); ?>
                                                    </span>
                                                    <?php if ($user['fourth_saturday_date']): ?>
                                                        <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem;">
                                                            4th Sat: <?php echo date('M d', strtotime($user['fourth_saturday_date'])); ?>
                                                        </div>
                                                        <?php if ($user['fourth_saturday_penalty'] > 0): ?>
                                                            <div style="font-size: 0.75rem; color: #dc2626; margin-top: 0.125rem;">
                                                                ❌ Not punched in - 3 days penalty
                                                            </div>
                                                        <?php else: ?>
                                                            <div style="font-size: 0.75rem; color: #059669; margin-top: 0.125rem;">
                                                                ✓ Punched in - No penalty
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem;">
                                                            No 4th Saturday this month
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <span style="color: #059669; font-weight: 700; font-size: 1rem;">
                                                        ₹<?php echo number_format($user['monthly_salary_after_deductions'], 0); ?>
                                                    </span>
                                                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.125rem;">
                                                        Total Deductions: ₹<?php echo number_format($user['total_deductions'], 0); ?>
                                                    </div>
                                                    <?php if ($user['total_deductions'] > 0): ?>
                                                        <div style="font-size: 0.7rem; color: #dc2626; margin-top: 0.125rem;">
                                                            <?php echo number_format((($user['total_deductions'] / ($user['current_salary'] ?? $user['base_salary'] ?? 1)) * 100), 1); ?>% deducted
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="status-badge status-active">
                                                    <i class="fas fa-circle" style="font-size: 0.5rem; margin-right: 0.25rem;"></i>
                                                    Active
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="17" style="text-align: center; padding: 2rem; color: #6b7280;">
                                            <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                            No active users found for the selected month
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar functionality
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');

        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                // Mobile behavior
                sidebar.classList.toggle('mobile-open');
            } else {
                // Desktop behavior
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
            }
        });

        // Close mobile sidebar when clicking outside
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !sidebarToggle.contains(event.target) &&
                sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
            }
        });
        
        // Function to toggle short leave usage for 1-hour late deductions
        function toggleShortLeaveUsage(userId, filterMonth) {
            const checkbox = document.getElementById(`short_leave_checkbox_${userId}`);
            const isChecked = checkbox.checked;
            
            // Send AJAX request to update the calculation
            fetch('update_short_leave_usage.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    filter_month: filterMonth,
                    use_for_one_hour: isChecked
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload the page to reflect updated calculations
                    window.location.reload();
                } else {
                    alert('Error updating short leave usage');
                    // Revert checkbox state
                    checkbox.checked = !isChecked;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating short leave usage');
                // Revert checkbox state
                checkbox.checked = !isChecked;
            });
        }
        
        // Make function globally available
        window.toggleShortLeaveUsage = toggleShortLeaveUsage;
        
        // Function to update incremented salary calculation in real-time
        function updateIncrementedSalary(userId, filterMonth) {
            const input = document.getElementById(`incremented_salary_${userId}`);
            const newSalary = parseFloat(input.value) || 0;
            
            // Optional: Add visual feedback that changes are pending
            input.style.borderColor = '#f59e0b';
            input.style.backgroundColor = '#fef3c7';
        }
        
        // Function to save incremented salary to database
        function saveIncrementedSalary(userId, filterMonth) {
            const input = document.getElementById(`incremented_salary_${userId}`);
            const newSalary = parseFloat(input.value) || 0;
            
            if (newSalary < 0) {
                alert('Salary cannot be negative');
                return;
            }
            
            console.log('Saving salary:', { userId, filterMonth, newSalary }); // Debug log
            
            // Send AJAX request to save the incremented salary
            fetch('save_incremented_salary.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    filter_month: filterMonth,
                    incremented_salary: newSalary
                })
            })
            .then(response => {
                console.log('Response status:', response.status); // Debug log
                return response.text(); // Get text first to see what's returned
            })
            .then(text => {
                console.log('Response text:', text); // Debug log
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        // Visual feedback for successful save
                        input.style.borderColor = '#059669';
                        input.style.backgroundColor = '#d1fae5';
                        
                        // Reset to normal after 2 seconds
                        setTimeout(() => {
                            input.style.borderColor = '#d1d5db';
                            input.style.backgroundColor = '#ffffff';
                        }, 2000);
                        
                        // Reload the page to reflect updated calculations
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        console.error('Save failed:', data); // Debug log
                        alert('Error saving incremented salary: ' + (data.message || 'Unknown error'));
                        if (data.debug_info) {
                            console.error('Debug info:', data.debug_info);
                        }
                        input.style.borderColor = '#dc2626';
                        input.style.backgroundColor = '#fee2e2';
                    }
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Raw response:', text);
                    alert('Error: Invalid response from server. Check console for details.');
                    input.style.borderColor = '#dc2626';
                    input.style.backgroundColor = '#fee2e2';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Error saving incremented salary: ' + error.message);
                input.style.borderColor = '#dc2626';
                input.style.backgroundColor = '#fee2e2';
            });
        }
        
        // Export to Excel function
        function exportToExcel() {
            const filterMonth = document.getElementById('filter_month').value;
            const exportUrl = 'export_salary_excel.php?filter_month=' + encodeURIComponent(filterMonth);
            
            // Show loading indication
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 0.5rem;"></i>Exporting...';
            button.disabled = true;
            
            // Create a temporary link to trigger download
            const link = document.createElement('a');
            link.href = exportUrl;
            link.download = 'salary_analytics_' + filterMonth + '.xls';
            
            // Add error handling
            link.onerror = function() {
                console.error('Export failed');
                alert('Export failed. Please try again.');
                button.innerHTML = originalText;
                button.disabled = false;
            };
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Reset button after a short delay
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 3000);
        }
        
        // Function to toggle incremented salary inputs
        function toggleIncrementedSalary() {
            const cells = document.querySelectorAll('.hide-incremented-salary');
            const toggleText = document.getElementById('toggleIncrementText');
            
            // Check if inputs are currently hidden
            const isHidden = cells[0]?.classList.contains('hide-incremented-salary');
            
            cells.forEach(cell => {
                if (isHidden) {
                    // Show inputs
                    cell.classList.remove('hide-incremented-salary');
                    toggleText.textContent = 'Hide Salary Editor';
                } else {
                    // Hide inputs
                    cell.classList.add('hide-incremented-salary');
                    toggleText.textContent = 'Show Salary Editor';
                }
            });
        }
        
        // Make functions globally available
        window.updateIncrementedSalary = updateIncrementedSalary;
        window.saveIncrementedSalary = saveIncrementedSalary;
        window.exportToExcel = exportToExcel;
        window.toggleIncrementedSalary = toggleIncrementedSalary;
    </script>
</body>
</html>