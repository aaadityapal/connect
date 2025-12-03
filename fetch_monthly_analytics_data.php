<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// Check if month and year are provided
if (!isset($_GET['month']) || !isset($_GET['year'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Month and year are required']);
    exit;
}

$month = intval($_GET['month']);
$year = intval($_GET['year']);

// Validate month and year
if ($month < 1 || $month > 12 || $year < 2000 || $year > date('Y') + 5) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid month or year']);
    exit;
}

/**
 * Calculate working days for a user in a given month/year
 * Subtracts weekly off days and office holidays from total days in the month
 */
function calculateWorkingDays($pdo, $userId, $month, $year) {
    try {
        // Ensure month and year are integers
        $month = intval($month);
        $year = intval($year);
        
        // Create proper date strings with padding
        $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
        $firstDayOfMonth = "$year-$monthStr-01";
        $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
        
        // Get user's shift information for the month
        $shiftStmt = $pdo->prepare("
            SELECT us.weekly_offs, us.effective_from, us.effective_to
            FROM user_shifts us
            WHERE us.user_id = ?
            AND (
                (us.effective_from IS NULL AND us.effective_to IS NULL) OR
                (us.effective_from <= ? AND (us.effective_to IS NULL OR us.effective_to >= ?))
            )
            ORDER BY us.effective_from DESC
            LIMIT 1
        ");
        
        $shiftStmt->execute([$userId, $lastDayOfMonth, $firstDayOfMonth]);
        $userShift = $shiftStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$userShift) {
            // If no shift found, assume 26 working days (all days minus typical weekends)
            return 26;
        }
        
        // Parse weekly_offs (stored as comma-separated values, JSON array, or single day name)
        $weeklyOffs = [];
        if (!empty($userShift['weekly_offs'])) {
            $weeklyOffsRaw = trim($userShift['weekly_offs']);
            
            // Handle JSON array format
            if (strpos($weeklyOffsRaw, '[') === 0) {
                $decoded = json_decode($weeklyOffsRaw, true);
                if (is_array($decoded)) {
                    $weeklyOffs = $decoded;
                }
            } 
            // Handle comma-separated format
            elseif (strpos($weeklyOffsRaw, ',') !== false) {
                $weeklyOffs = array_map('trim', explode(',', $weeklyOffsRaw));
            } 
            // Handle single day name
            else {
                $weeklyOffs = [$weeklyOffsRaw];
            }
        }
        
        // Fetch office holidays for this month
        try {
            $holidayStmt = $pdo->prepare("
                SELECT DATE(holiday_date) as holiday_date
                FROM office_holidays
                WHERE DATE(holiday_date) BETWEEN ? AND ?
            ");
            $holidayStmt->execute([$firstDayOfMonth, $lastDayOfMonth]);
            $holidays = $holidayStmt->fetchAll(PDO::FETCH_COLUMN);
            $officeHolidays = array_flip($holidays); // Convert to associative array for faster lookup
        } catch (PDOException $e) {
            error_log("Error fetching office holidays: " . $e->getMessage());
            $officeHolidays = [];
        }
        
        // Count working days using DateTime loop
        $workingDaysCount = 0;
        $currentDate = new DateTime($firstDayOfMonth);
        $endDate = new DateTime($lastDayOfMonth);
        
        while ($currentDate <= $endDate) {
            $dayOfWeek = $currentDate->format('l'); // Get day name (e.g., "Monday", "Tuesday")
            $currentDateStr = $currentDate->format('Y-m-d');
            
            // Check if this day is a weekly off or an office holiday
            $isWeeklyOff = in_array($dayOfWeek, $weeklyOffs);
            $isHoliday = isset($officeHolidays[$currentDateStr]);
            
            // If not a weekly off and not a holiday, count as working day
            if (!$isWeeklyOff && !$isHoliday) {
                $workingDaysCount++;
            }
            
            // Move to the next day
            $currentDate->modify('+1 day');
        }
        
        return $workingDaysCount;
        
    } catch (Exception $e) {
        error_log("Error calculating working days for user $userId, month $month, year $year: " . $e->getMessage());
        return 26; // Default fallback
    }
}

try {
    // Fetch employee data with salary and attendance information
    $query = "
        SELECT 
            u.id,
            u.unique_id as employee_id,
            u.username as name,
            u.position as role,
            u.designation,
            u.department,
            u.status,
            u.created_at,
            u.updated_at
        FROM users u
        WHERE u.deleted_at IS NULL
        AND u.status = 'active'
        AND u.designation != 'admin'
        ORDER BY u.username ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($employees)) {
        echo json_encode([
            'status' => 'success',
            'data' => [],
            'message' => 'No employees found'
        ]);
        exit;
    }

    // Process employee data
    $employeeData = [];
    
    foreach ($employees as $employee) {
        // Fetch salary record for the specific month/year if it exists
        $salaryStmt = $pdo->prepare("
            SELECT base_salary FROM employee_salary_records 
            WHERE user_id = ? AND month = ? AND year = ? AND deleted_at IS NULL
            ORDER BY created_at DESC LIMIT 1
        ");
        $salaryStmt->execute([$employee['id'], $month, $year]);
        $salaryRecord = $salaryStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($salaryRecord) {
            $baseSalary = $salaryRecord['base_salary'];
        } else {
            // Fallback: Get the most recent salary record for this user (any month/year)
            $fallbackStmt = $pdo->prepare("
                SELECT base_salary FROM employee_salary_records 
                WHERE user_id = ? AND deleted_at IS NULL
                ORDER BY year DESC, month DESC LIMIT 1
            ");
            $fallbackStmt->execute([$employee['id']]);
            $fallbackRecord = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
            $baseSalary = $fallbackRecord ? $fallbackRecord['base_salary'] : 50000;
        }
        
        // Calculate working days based on user_shifts and shifts with weekly offs
        $workingDays = calculateWorkingDays($pdo, $employee['id'], $month, $year);
        
        // Fetch present days from attendance table
        // Present days are counted when both punch_in and punch_out are not NULL/empty
        $presentDaysStmt = $pdo->prepare("
            SELECT COUNT(*) as present_days
            FROM attendance
            WHERE user_id = ?
            AND MONTH(date) = ?
            AND YEAR(date) = ?
            AND punch_in IS NOT NULL
            AND punch_in != ''
            AND punch_out IS NOT NULL
            AND punch_out != ''
        ");
        $presentDaysStmt->execute([$employee['id'], $month, $year]);
        $presentDaysResult = $presentDaysStmt->fetch(PDO::FETCH_ASSOC);
        $presentDays = $presentDaysResult['present_days'] ?? 0;
        
        // Fetch late days from attendance table
        // Late is counted when punch_in time is more than 15 minutes after shift start time
        // First, get the user's shift start time
        $shiftStmt = $pdo->prepare("
            SELECT s.start_time
            FROM user_shifts us
            LEFT JOIN shifts s ON us.shift_id = s.id
            WHERE us.user_id = ?
            AND (
                (us.effective_from IS NULL AND us.effective_to IS NULL) OR
                (us.effective_from <= CURDATE() AND (us.effective_to IS NULL OR us.effective_to >= CURDATE()))
            )
            ORDER BY us.effective_from DESC
            LIMIT 1
        ");
        $shiftStmt->execute([$employee['id']]);
        $userShift = $shiftStmt->fetch(PDO::FETCH_ASSOC);
        
        $lateDays = 0;
        if ($userShift && !empty($userShift['start_time'])) {
            $shiftStartTime = $userShift['start_time'];
            // Add 15 minutes grace period
            $graceTime = date('H:i:s', strtotime($shiftStartTime . ' +15 minutes'));
            
            // Count late days: punch_in > grace_time (more than 15 minutes late)
            $lateDaysStmt = $pdo->prepare("
                SELECT COUNT(*) as late_days_count
                FROM attendance
                WHERE user_id = ?
                AND MONTH(date) = ?
                AND YEAR(date) = ?
                AND punch_in IS NOT NULL
                AND punch_in != ''
                AND TIME(punch_in) > ?
            ");
            $lateDaysStmt->execute([$employee['id'], $month, $year, $graceTime]);
            $lateDaysResult = $lateDaysStmt->fetch(PDO::FETCH_ASSOC);
            $lateDays = $lateDaysResult['late_days_count'] ?? 0;
        }
        
        // Fetch 1+ hour late days from attendance table
        // 1+ hour late is when punch_in is 1 hour or more after shift start time
        $oneHourLateDays = 0;
        if ($userShift && !empty($userShift['start_time'])) {
            $shiftStartTime = $userShift['start_time'];
            // Calculate 1 hour after shift start time
            $oneHourLateTime = date('H:i:s', strtotime($shiftStartTime . ' +1 hour'));
            
            // Count 1+ hour late days: punch_in >= 1 hour after shift start
            $oneHourLateStmt = $pdo->prepare("
                SELECT COUNT(*) as one_hour_late_count
                FROM attendance
                WHERE user_id = ?
                AND MONTH(date) = ?
                AND YEAR(date) = ?
                AND punch_in IS NOT NULL
                AND punch_in != ''
                AND TIME(punch_in) >= ?
            ");
            $oneHourLateStmt->execute([$employee['id'], $month, $year, $oneHourLateTime]);
            $oneHourLateResult = $oneHourLateStmt->fetch(PDO::FETCH_ASSOC);
            $oneHourLateDays = $oneHourLateResult['one_hour_late_count'] ?? 0;
            
            // Adjust regular late days: exclude 1+ hour late from late days count
            // 1+ hour late should not be counted in regular late days
            $lateDays = max(0, $lateDays - $oneHourLateDays);
        }
        
        // Fetch leave taken from leave_request table
        // Count only approved leaves for the selected month and year
        $leaveTaken = 0;
        try {
            // Calculate month boundaries
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $firstDayOfMonth = "$year-$monthStr-01";
            $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
            
            $leaveStmt = $pdo->prepare("
                SELECT SUM(
                    DATEDIFF(
                        LEAST(end_date, ?),
                        GREATEST(start_date, ?)
                    ) + 1
                ) as total_leave_days
                FROM leave_request
                WHERE user_id = ?
                AND status = 'approved'
                AND (
                    (MONTH(start_date) = ? AND YEAR(start_date) = ?) OR
                    (MONTH(end_date) = ? AND YEAR(end_date) = ?) OR
                    (start_date < ? AND end_date > ?)
                )
            ");
            
            $leaveStmt->execute([
                $lastDayOfMonth,
                $firstDayOfMonth,
                $employee['id'],
                $month, $year,
                $month, $year,
                $firstDayOfMonth, $lastDayOfMonth
            ]);
            $leaveResult = $leaveStmt->fetch(PDO::FETCH_ASSOC);
            $leaveTaken = $leaveResult['total_leave_days'] ?? 0;
            // Ensure it's a positive integer
            $leaveTaken = max(0, intval($leaveTaken));
        } catch (PDOException $e) {
            error_log("Error fetching leave data for user " . $employee['id'] . ": " . $e->getMessage());
            $leaveTaken = 0;
        }
        
        // Calculate leave deductions directly
        $leaveDeduction = 0;
        try {
            // Calculate working days
            $workingDaysCount = $workingDays;
            $oneDaySalary = $workingDaysCount > 0 ? $baseSalary / $workingDaysCount : 0;
            
            // Fetch month boundaries
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $firstDayOfMonth = "$year-$monthStr-01";
            $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
            
            // Fetch all approved leaves for the month
            $leaveDeductionStmt = $pdo->prepare("
                SELECT 
                    lr.id,
                    lr.start_date,
                    lr.end_date,
                    lt.name as leave_type,
                    DATEDIFF(lr.end_date, lr.start_date) + 1 as num_days
                FROM leave_request lr
                LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                WHERE lr.user_id = ?
                AND lr.status = 'approved'
                AND (
                    (MONTH(lr.start_date) = ? AND YEAR(lr.start_date) = ?) OR
                    (MONTH(lr.end_date) = ? AND YEAR(lr.end_date) = ?) OR
                    (lr.start_date <= ? AND lr.end_date >= ?)
                )
                ORDER BY lr.start_date ASC
            ");
            
            $leaveDeductionStmt->execute([
                $employee['id'],
                $month, $year,
                $month, $year,
                $lastDayOfMonth, $firstDayOfMonth
            ]);
            
            $leaves = $leaveDeductionStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $yearStartDate = "$year-01-01";
            $currentMonthDate = $lastDayOfMonth;
            
            $yearlyLeaveStmt = $pdo->prepare("
                SELECT 
                    lt.name as leave_type,
                    SUM(DATEDIFF(lr.end_date, lr.start_date) + 1) as total_days
                FROM leave_request lr
                LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                WHERE lr.user_id = ?
                AND lr.status = 'approved'
                AND lr.start_date >= ?
                AND lr.start_date <= ?
                GROUP BY lt.name
            ");
            
            $yearlyLeaveStmt->execute([$employee['id'], $yearStartDate, $currentMonthDate]);
            $yearlyLeaves = $yearlyLeaveStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $leaveUsageYearly = [
                'sick_leave' => 0,
                'paternity_leave' => 0,
                'maternity_leave' => 0
            ];
            
            foreach ($yearlyLeaves as $yl) {
                $leaveType = strtolower(str_replace(' ', '_', $yl['leave_type'] ?? 'other'));
                if ($leaveType === 'sick_leave' || $leaveType === 'sick leave') {
                    $leaveUsageYearly['sick_leave'] = intval($yl['total_days']);
                } elseif ($leaveType === 'paternity_leave' || $leaveType === 'paternity leave') {
                    $leaveUsageYearly['paternity_leave'] = intval($yl['total_days']);
                } elseif ($leaveType === 'maternity_leave' || $leaveType === 'maternity leave') {
                    $leaveUsageYearly['maternity_leave'] = intval($yl['total_days']);
                }
            }
            
            // Calculate deductions for each leave
            foreach ($leaves as $leave) {
                $leaveType = strtolower(str_replace(' ', '_', $leave['leave_type'] ?? 'other'));
                $numDays = intval($leave['num_days']);
                $deduction = 0;
                
                switch ($leaveType) {
                    case 'compensate_leave':
                    case 'compensate leave':
                        $deduction = 0;
                        break;
                    case 'casual_leave':
                    case 'casual leave':
                        $deduction = 0;
                        break;
                    case 'sick_leave':
                    case 'sick leave':
                        $totalSickLeavesYearly = $leaveUsageYearly['sick_leave'];
                        if ($totalSickLeavesYearly > 6) {
                            $excessDays = $totalSickLeavesYearly - 6;
                            $deduction = $excessDays * $oneDaySalary;
                        }
                        break;
                    case 'half_day':
                    case 'half day':
                        $deduction = $oneDaySalary * 0.5;
                        break;
                    case 'short_leave':
                    case 'short leave':
                        $deduction = 0;
                        break;
                    case 'unpaid_leave':
                    case 'unpaid leave':
                        $deduction = $numDays * $oneDaySalary;
                        break;
                    case 'paternity_leave':
                    case 'paternity leave':
                        $totalPaternityLeavesYearly = $leaveUsageYearly['paternity_leave'];
                        if ($totalPaternityLeavesYearly > 7) {
                            $excessDays = $totalPaternityLeavesYearly - 7;
                            $deduction = $excessDays * $oneDaySalary;
                        }
                        break;
                    case 'maternity_leave':
                    case 'maternity leave':
                        $totalMaternityLeavesYearly = $leaveUsageYearly['maternity_leave'];
                        if ($totalMaternityLeavesYearly > 60) {
                            $excessDays = $totalMaternityLeavesYearly - 60;
                            $deduction = $excessDays * $oneDaySalary;
                        }
                        break;
                    default:
                        $deduction = 0;
                }
                
                $leaveDeduction += $deduction;
            }
            
            $leaveDeduction = round($leaveDeduction, 2);
            
        } catch (PDOException $e) {
            error_log("Error calculating leave deductions for user " . $employee['id'] . ": " . $e->getMessage());
            $leaveDeduction = 0;
        } catch (Exception $e) {
            error_log("Error in leave deduction calculation for user " . $employee['id'] . ": " . $e->getMessage());
            $leaveDeduction = 0;
        }
        
        $employeeData[] = [
            'id' => $employee['id'], // Add user ID
            'employee_id' => $employee['employee_id'] ?? $employee['id'],
            'name' => $employee['name'],
            'role' => $employee['role'],
            'base_salary' => $baseSalary,
            'working_days' => $workingDays,
            'present_days' => $presentDays,
            'late_days' => $lateDays,
            'one_hour_late' => $oneHourLateDays,
            'leave_taken' => $leaveTaken,
            'leave_deduction' => round($leaveDeduction, 2),
            'one_hour_late_deduction' => 0,
            'fourth_saturday_deduction' => 0,
            'salary_calculated_days' => $workingDays
        ];
    }

    // Return success response
    echo json_encode([
        'status' => 'success',
        'data' => $employeeData,
        'month' => $month,
        'year' => $year,
        'count' => count($employeeData)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in fetch_monthly_analytics_data.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred'
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in fetch_monthly_analytics_data.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred'
    ]);
    exit;
}
?>
