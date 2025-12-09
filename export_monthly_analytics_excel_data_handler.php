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
 */
function calculateWorkingDays($pdo, $userId, $month, $year) {
    try {
        $month = intval($month);
        $year = intval($year);
        
        $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
        $firstDayOfMonth = "$year-$monthStr-01";
        $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
        
        // Get user's shift information
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
            return 26;
        }
        
        // Parse weekly_offs
        $weeklyOffs = [];
        if (!empty($userShift['weekly_offs'])) {
            $weeklyOffsRaw = trim($userShift['weekly_offs']);
            
            if (strpos($weeklyOffsRaw, '[') === 0) {
                $decoded = json_decode($weeklyOffsRaw, true);
                if (is_array($decoded)) {
                    $weeklyOffs = $decoded;
                }
            } elseif (strpos($weeklyOffsRaw, ',') !== false) {
                $weeklyOffs = array_map('trim', explode(',', $weeklyOffsRaw));
            } else {
                $weeklyOffs = [$weeklyOffsRaw];
            }
        }
        
        // Fetch office holidays
        try {
            $holidayStmt = $pdo->prepare("
                SELECT DATE(holiday_date) as holiday_date
                FROM office_holidays
                WHERE DATE(holiday_date) BETWEEN ? AND ?
            ");
            $holidayStmt->execute([$firstDayOfMonth, $lastDayOfMonth]);
            $holidays = $holidayStmt->fetchAll(PDO::FETCH_COLUMN);
            $officeHolidays = array_flip($holidays);
        } catch (PDOException $e) {
            $officeHolidays = [];
        }
        
        // Count working days
        $workingDaysCount = 0;
        $currentDate = new DateTime($firstDayOfMonth);
        $endDate = new DateTime($lastDayOfMonth);
        
        while ($currentDate <= $endDate) {
            $dayOfWeek = $currentDate->format('l');
            $currentDateStr = $currentDate->format('Y-m-d');
            
            $isWeeklyOff = in_array($dayOfWeek, $weeklyOffs);
            $isHoliday = isset($officeHolidays[$currentDateStr]);
            
            if (!$isWeeklyOff && !$isHoliday) {
                $workingDaysCount++;
            }
            
            $currentDate->modify('+1 day');
        }
        
        return $workingDaysCount;
        
    } catch (Exception $e) {
        error_log("Error calculating working days: " . $e->getMessage());
        return 26;
    }
}

try {
    // Fetch employees
    $query = "
        SELECT 
            u.id,
            u.unique_id as employee_id,
            u.username as name,
            u.role as role
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

    $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
    $firstDayOfMonth = "$year-$monthStr-01";
    $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
    
    $employeeData = [];

    foreach ($employees as $employee) {
        // Get base salary
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
            $fallbackStmt = $pdo->prepare("
                SELECT base_salary FROM employee_salary_records 
                WHERE user_id = ? AND deleted_at IS NULL
                ORDER BY year DESC, month DESC LIMIT 1
            ");
            $fallbackStmt->execute([$employee['id']]);
            $fallbackRecord = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
            $baseSalary = $fallbackRecord ? $fallbackRecord['base_salary'] : 50000;
        }

        // Calculate working days
        $workingDays = calculateWorkingDays($pdo, $employee['id'], $month, $year);
        $dailySalary = $workingDays > 0 ? $baseSalary / $workingDays : 0;

        // Get present days (both punch_in and punch_out)
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

        // Get late days (>15 min late)
        $lateDays = 0;
        $oneHourLateDays = 0;
        
        $shiftStmt = $pdo->prepare("
            SELECT s.start_time
            FROM user_shifts us
            LEFT JOIN shifts s ON us.shift_id = s.id
            WHERE us.user_id = ?
            AND (
                (us.effective_from IS NULL AND us.effective_to IS NULL) OR
                (us.effective_from <= ? AND (us.effective_to IS NULL OR us.effective_to >= ?))
            )
            ORDER BY us.effective_from DESC
            LIMIT 1
        ");
        $shiftStmt->execute([$employee['id'], $lastDayOfMonth, $firstDayOfMonth]);
        $userShift = $shiftStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userShift && !empty($userShift['start_time'])) {
            $shiftStartTime = $userShift['start_time'];
            
            // Count late days (>15 minutes)
            $graceTime = date('H:i:s', strtotime($shiftStartTime . ' +15 minutes'));
            $lateDaysStmt = $pdo->prepare("
                SELECT COUNT(*) as late_count
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
            $lateDays = $lateDaysResult['late_count'] ?? 0;
            
            // Count 1+ hour late days
            $oneHourLateTime = date('H:i:s', strtotime($shiftStartTime . ' +1 hour'));
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
            
            // Remove 1+ hour late from regular late days
            $lateDays = max(0, $lateDays - $oneHourLateDays);
        }

        // Get leave taken
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
        $leaveTaken = max(0, intval($leaveResult['total_leave_days'] ?? 0));

        // Calculate leave deductions (complex logic)
        $leaveDeduction = 0;
        try {
            $oneDaySalary = $workingDays > 0 ? $baseSalary / $workingDays : 0;
            
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
            
            // Get yearly leave usage
            $yearStartDate = "$year-01-01";
            
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
            
            $yearlyLeaveStmt->execute([$employee['id'], $yearStartDate, $lastDayOfMonth]);
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
                    case 'half_day_leave':
                    case 'half day leave':
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
            
        } catch (Exception $e) {
            error_log("Error calculating leave deductions: " . $e->getMessage());
            $leaveDeduction = 0;
        }

        // Calculate deductions for late arrivals
        $lateDeductionAmount = (floor($lateDays / 3) * 0.5) * $dailySalary;
        $oneHourLateDeductionAmount = ($oneHourLateDays * 0.5) * $dailySalary;

        // Calculate 4th Saturday deduction
        $fourthSaturdayDeduction = 0;
        try {
            $fourthSatStmt = $pdo->prepare("
                SELECT COUNT(*) as fourth_sat_count
                FROM (
                    SELECT DATE(date) as date_only
                    FROM attendance
                    WHERE user_id = ?
                    AND DATE(date) BETWEEN ? AND ?
                    AND (punch_in IS NULL OR punch_in = '')
                ) sub
                WHERE DAYOFWEEK(date_only) = 7
                AND DAY(date_only) > 21
                AND DAY(date_only) <= 27
            ");
            $fourthSatStmt->execute([$employee['id'], $firstDayOfMonth, $lastDayOfMonth]);
            $fourthSatResult = $fourthSatStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($fourthSatResult && $fourthSatResult['fourth_sat_count'] > 0) {
                $fourthSaturdayDeduction = 2 * $dailySalary;
            }
        } catch (Exception $e) {
            $fourthSaturdayDeduction = 0;
        }

        // Get overtime
        $overtimeHours = 0;
        $overtimeAmount = 0;
        try {
            $shiftHoursStmt = $pdo->prepare("
                SELECT s.start_time, s.end_time
                FROM user_shifts us
                JOIN shifts s ON us.shift_id = s.id
                WHERE us.user_id = ?
                ORDER BY us.effective_from DESC
                LIMIT 1
            ");
            $shiftHoursStmt->execute([$employee['id']]);
            $shiftHoursResult = $shiftHoursStmt->fetch(PDO::FETCH_ASSOC);
            
            $shiftHours = 8;
            if ($shiftHoursResult && $shiftHoursResult['start_time'] && $shiftHoursResult['end_time']) {
                $startTime = new DateTime($shiftHoursResult['start_time']);
                $endTime = new DateTime($shiftHoursResult['end_time']);
                
                if ($endTime < $startTime) {
                    $endTime->modify('+1 day');
                }
                
                $interval = $startTime->diff($endTime);
                $shiftHours = floatval($interval->h) + (floatval($interval->i) / 60);
            }
            
            $overtimeStmt = $pdo->prepare("
                SELECT SUM(overtime_hours) as total_overtime_hours
                FROM overtime_requests
                WHERE user_id = ?
                AND DATE(date) BETWEEN ? AND ?
                AND status = 'approved'
            ");
            $overtimeStmt->execute([$employee['id'], $firstDayOfMonth, $lastDayOfMonth]);
            $overtimeResult = $overtimeStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($overtimeResult && $overtimeResult['total_overtime_hours'] !== null) {
                $overtimeHours = floatval($overtimeResult['total_overtime_hours']);
                $oneDaySalary = $baseSalary / $workingDays;
                $oneHourSalary = $oneDaySalary / $shiftHours;
                $overtimeAmount = round($overtimeHours * $oneHourSalary, 2);
            }
        } catch (Exception $e) {
            $overtimeHours = 0;
            $overtimeAmount = 0;
        }

        // NOW CALCULATE SALARY CALCULATED DAYS - THIS IS THE KEY PART
        // Start with present days
        $salaryCalculatedDays = floatval($presentDays);
        
        // Subtract regular late deduction days (every 3 late = 0.5 day)
        $regularLateDeductionDays = floor($lateDays / 3) * 0.5;
        $salaryCalculatedDays -= $regularLateDeductionDays;
        
        // Subtract 1+ hour late deduction days (each = 0.5 day)
        $oneHourLateDeductionDays = $oneHourLateDays * 0.5;
        $salaryCalculatedDays -= $oneHourLateDeductionDays;

        // Get penalty days - FIXED BUG: Proper NULL checking for penalty values
        $penaltyDays = 0;
        try {
            $penaltyStmt = $pdo->prepare("
                SELECT penalty_days FROM salary_penalties
                WHERE user_id = ? AND penalty_month = ?
                LIMIT 1
            ");
            $penaltyMonth = str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . $year;
            $penaltyStmt->execute([$employee['id'], $penaltyMonth]);
            $penaltyRecord = $penaltyStmt->fetch(PDO::FETCH_ASSOC);
            
            // FIX: Check if record exists AND penalty_days is not null (handles 0 values properly)
            if ($penaltyRecord !== false && isset($penaltyRecord['penalty_days']) && $penaltyRecord['penalty_days'] !== null) {
                $penaltyDays = floatval($penaltyRecord['penalty_days']);
                $salaryCalculatedDays -= $penaltyDays;
            }
        } catch (PDOException $e) {
            error_log("Error fetching penalty days for user " . $employee['id'] . ": " . $e->getMessage());
            $penaltyDays = 0;
        }

        // Subtract 4th Saturday missing days (2 days) if penalty applied
        $fourthSaturdayMissingDays = ($fourthSaturdayDeduction > 0) ? 2 : 0;
        $salaryCalculatedDays -= $fourthSaturdayMissingDays;

        // Ensure within bounds
        if ($salaryCalculatedDays < 0) {
            $salaryCalculatedDays = 0;
        }
        if ($salaryCalculatedDays > $workingDays) {
            $salaryCalculatedDays = floatval($workingDays);
        }

        $salaryCalculatedDays = round($salaryCalculatedDays, 2);

        // Calculate net and final salary
        $netSalary = round($salaryCalculatedDays * $dailySalary, 2);
        $finalSalary = round($netSalary + $overtimeAmount, 2);

        $employeeData[] = [
            'employee_id' => $employee['employee_id'] ?? $employee['id'],
            'name' => $employee['name'],
            'role' => $employee['role'],
            'base_salary' => round($baseSalary, 2),
            'working_days' => $workingDays,
            'present_days' => $presentDays,
            'late_days' => $lateDays,
            'one_hour_late' => $oneHourLateDays,
            'leave_taken' => $leaveTaken,
            'leave_deduction' => round($leaveDeduction, 2),
            'late_deduction' => round($lateDeductionAmount, 2),
            'one_hour_late_deduction' => round($oneHourLateDeductionAmount, 2),
            'fourth_saturday_deduction' => round($fourthSaturdayDeduction, 2),
            'penalty_days' => $penaltyDays,
            'salary_calculated_days' => $salaryCalculatedDays,
            'net_salary' => $netSalary,
            'overtime_hours' => round($overtimeHours, 2),
            'overtime_amount' => $overtimeAmount,
            'final_salary' => $finalSalary
        ];
    }

    // Calculate summary
    $totalWithoutOvertime = 0;
    $totalWithOvertime = 0;
    
    foreach ($employeeData as $emp) {
        $totalWithoutOvertime += $emp['net_salary'];
        $totalWithOvertime += $emp['final_salary'];
    }

    echo json_encode([
        'status' => 'success',
        'data' => $employeeData,
        'summary' => [
            'total_salary_without_overtime' => round($totalWithoutOvertime, 2),
            'total_salary_with_overtime' => round($totalWithOvertime, 2),
            'total_overtime_amount' => round($totalWithOvertime - $totalWithoutOvertime, 2),
            'employee_count' => count($employeeData)
        ],
        'month' => $month,
        'year' => $year,
        'count' => count($employeeData),
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in export_monthly_analytics_excel_data_handler.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred'
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in export_monthly_analytics_excel_data_handler.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred'
    ]);
    exit;
}
?>
