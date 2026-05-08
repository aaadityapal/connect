<?php
session_start();
require_once 'config/db_connect.php';

/**
 * Calculate leave deductions based on leave type and company rules
 * 
 * Rules:
 * - Compensate Leave: No deduction (covered by compensate)
 * - Casual Leave: No deduction
 * - Sick Leave: First 6 leaves per year no deduction, beyond that deduct daily salary
 * - Half Day: Deduct 0.5 day salary
 * - Short Leave: No deduction
 * - Unpaid Leave: Deduct full day salary for each day
 * - Paternity: 7 days per year no deduction, beyond that deduct daily salary
 * - Maternity: 60 days per year no deduction, beyond that deduct daily salary
 */

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$year = isset($_GET['year']) ? intval($_GET['year']) : 0;

if (!$user_id || !$month || !$year) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit;
}

try {
    // Get base salary and working days
    $salaryStmt = $pdo->prepare("
        SELECT base_salary FROM employee_salary_records 
        WHERE user_id = ? AND month = ? AND year = ? AND deleted_at IS NULL
        ORDER BY created_at DESC LIMIT 1
    ");
    $salaryStmt->execute([$user_id, $month, $year]);
    $salaryRecord = $salaryStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$salaryRecord) {
        // Try to get the most recent salary record
        $fallbackStmt = $pdo->prepare("
            SELECT base_salary FROM employee_salary_records 
            WHERE user_id = ? AND deleted_at IS NULL
            ORDER BY year DESC, month DESC LIMIT 1
        ");
        $fallbackStmt->execute([$user_id]);
        $fallbackRecord = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
        $baseSalary = $fallbackRecord ? $fallbackRecord['base_salary'] : 0;
    } else {
        $baseSalary = $salaryRecord['base_salary'];
    }
    
    // Calculate working days (same logic as fetch_monthly_analytics_data.php)
    $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
    $firstDayOfMonth = "$year-$monthStr-01";
    $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
    
    // Get user's shift for weekly offs
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
    $shiftStmt->execute([$user_id, $lastDayOfMonth, $firstDayOfMonth]);
    $userShift = $shiftStmt->fetch(PDO::FETCH_ASSOC);
    
    // Parse weekly offs
    $weeklyOffs = [];
    if ($userShift && !empty($userShift['weekly_offs'])) {
        $weeklyOffsRaw = trim($userShift['weekly_offs']);
        if (strpos($weeklyOffsRaw, '[') === 0) {
            $decoded = json_decode($weeklyOffsRaw, true);
            if (is_array($decoded)) $weeklyOffs = $decoded;
        } elseif (strpos($weeklyOffsRaw, ',') !== false) {
            $weeklyOffs = array_map('trim', explode(',', $weeklyOffsRaw));
        } else {
            $weeklyOffs = [$weeklyOffsRaw];
        }
    }
    
    // Fetch office holidays
    $holidayStmt = $pdo->prepare("
        SELECT DATE(holiday_date) as holiday_date
        FROM office_holidays
        WHERE DATE(holiday_date) BETWEEN ? AND ?
    ");
    $holidayStmt->execute([$firstDayOfMonth, $lastDayOfMonth]);
    $holidays = $holidayStmt->fetchAll(PDO::FETCH_COLUMN);
    $officeHolidays = array_flip($holidays);
    
    // Calculate working days
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
    
    // Calculate one day salary
    $oneDaySalary = $workingDaysCount > 0 ? $baseSalary / $workingDaysCount : 0;
    
    // Fetch all approved leaves for the month
    $leaveStmt = $pdo->prepare("
        SELECT 
            lr.id,
            lr.start_date,
            lr.end_date,
            lr.duration,
            lt.name as leave_type,
            DATEDIFF(lr.end_date, lr.start_date) + 1 as calculated_days
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
    
    $leaveStmt->execute([
        $user_id,
        $month, $year,
        $month, $year,
        $lastDayOfMonth, $firstDayOfMonth
    ]);
    
    $leaves = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch total leave days (month-aware)
    $leaveTaken = 0;
    try {
        $leaveTakenStmt = $pdo->prepare("
            SELECT SUM(
                CASE
                    WHEN lr.start_date = lr.end_date AND lr.duration > 0 THEN lr.duration
                    WHEN LOWER(lt.name) LIKE '%half%' THEN 0.5
                    ELSE DATEDIFF(
                        LEAST(lr.end_date, :last_day),
                        GREATEST(lr.start_date, :first_day)
                    ) + 1
                END
            ) as total_leave_days
            FROM leave_request lr
            LEFT JOIN leave_types lt ON lr.leave_type = lt.id
            WHERE lr.user_id = :user_id
            AND lr.status = 'approved'
            AND (
                (MONTH(lr.start_date) = :month AND YEAR(lr.start_date) = :year) OR
                (MONTH(lr.end_date) = :month AND YEAR(lr.end_date) = :year) OR
                (lr.start_date < :first_day AND lr.end_date > :last_day)
            )
        ");
        $leaveTakenStmt->execute([
            'last_day' => $lastDayOfMonth,
            'first_day' => $firstDayOfMonth,
            'user_id' => $user_id,
            'month' => $month,
            'year' => $year
        ]);
        $leaveTakenResult = $leaveTakenStmt->fetch(PDO::FETCH_ASSOC);
        $leaveTaken = max(0, floatval($leaveTakenResult['total_leave_days'] ?? 0));
    } catch (PDOException $e) {
        $leaveTaken = 0;
    }

    // Fetch present days (shift-aware)
    $presentDays = 0;
    try {
        // Re-fetch shift details with start/end time
        $fullShiftStmt = $pdo->prepare("
            SELECT s.start_time, s.end_time
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
        $fullShiftStmt->execute([$user_id, $lastDayOfMonth, $firstDayOfMonth]);
        $userFullShift = $fullShiftStmt->fetch(PDO::FETCH_ASSOC);
        
        $halfShiftSecs = 4.5 * 3600;
        if ($userFullShift && !empty($userFullShift['start_time']) && !empty($userFullShift['end_time'])) {
            $sTime = strtotime($userFullShift['start_time']);
            $eTime = strtotime($userFullShift['end_time']);
            $sDur = $eTime - $sTime;
            if ($sDur < 0) $sDur += 24 * 3600;
            $halfShiftSecs = $sDur / 2;
        }

        $presentStmt = $pdo->prepare("
            SELECT SUM(
                CASE 
                    WHEN status = 'half_day' THEN 0.5 
                    WHEN MOD(TIME_TO_SEC(punch_out) - TIME_TO_SEC(punch_in) + 86400, 86400) < ? THEN 0.5 
                    ELSE 1 
                END
            ) as present_days
            FROM attendance
            WHERE user_id = ?
            AND MONTH(date) = ?
            AND YEAR(date) = ?
            AND punch_in IS NOT NULL AND punch_in != ''
            AND punch_out IS NOT NULL AND punch_out != ''
        ");
        $presentStmt->execute([$halfShiftSecs, $user_id, $month, $year]);
        $presentResult = $presentStmt->fetch(PDO::FETCH_ASSOC);
        $presentDays = floatval($presentResult['present_days'] ?? 0);
    } catch (PDOException $e) {
        $presentDays = 0;
    }
    
    // Initialize deduction tracking
    $deductions = [
        'total_deduction' => 0,
        'leave_deductions' => [],
        'leave_summary' => []
    ];
    
    // Calculate leave deductions directly based on outer logic
    $presentDaysCapped = floatval(min($presentDays, $workingDaysCount));
    
    $leaveCreditsToAdd = 0;
    $sumUnpaidLeaveDays = 0;
    
    foreach ($leaves as $leave) {
        $lt = strtolower(str_replace(' ', '_', $leave['leave_type'] ?? 'other'));
        $isHalfDay = stripos($leave['leave_type'] ?? '', 'half') !== false;
        $numDays = (isset($leave['duration']) && floatval($leave['duration']) > 0 && $leave['start_date'] === $leave['end_date']) 
            ? floatval($leave['duration']) 
            : ($isHalfDay ? 0.5 : intval($leave['calculated_days']));
            
        $deductionDays = 0;
        $deductionType = '';
        
        switch ($lt) {
            case 'casual_leave':
            case 'casual leave':
                $leaveCreditsToAdd += $numDays;
                $deductionDays = 0;
                $deductionType = 'No deduction - Casual leave';
                break;
            case 'half_day':
            case 'half day':
            case 'half_day_leave':
            case 'half day leave':
                $leaveCreditsToAdd += 0.5 * $numDays;
                $deductionDays = $numDays - (0.5 * $numDays);
                $deductionType = 'Deduction - Half day leave';
                break;
            case 'compensate_leave':
            case 'compensate leave':
                $leaveCreditsToAdd += $numDays;
                $deductionDays = 0;
                $deductionType = 'No deduction - Compensate leave';
                break;
            case 'short_leave':
            case 'short leave':
                // Short leave is assumed to overlap with present days
                $deductionDays = 0;
                $deductionType = 'No deduction - Short leave';
                break;
            default:
                // Other leaves (Sick, Unpaid, Paternity, Maternity) are not credited
                // so they are fully deducted in the new logic.
                $deductionDays = $numDays;
                $deductionType = "Deduction - " . ($leave['leave_type'] ?? 'Leave') . " (Unpaid)";
                break;
        }
        
        $sumUnpaidLeaveDays += $deductionDays;
        $deductionAmount = round($deductionDays * $oneDaySalary, 2);
        
        $deductions['leave_deductions'][] = [
            'leave_id' => $leave['id'],
            'leave_type' => $leave['leave_type'] ?? 'Unknown',
            'start_date' => $leave['start_date'],
            'end_date' => $leave['end_date'],
            'num_days' => $numDays,
            'deduction' => $deductionAmount,
            'deduction_type' => $deductionType
        ];
        
        // Add to summary
        $summary_key = $leave['leave_type'] ?? 'Unknown';
        if (!isset($deductions['leave_summary'][$summary_key])) {
            $deductions['leave_summary'][$summary_key] = [
                'total_days' => 0,
                'total_deduction' => 0
            ];
        }
        $deductions['leave_summary'][$summary_key]['total_days'] += $numDays;
        $deductions['leave_summary'][$summary_key]['total_deduction'] += $deductionAmount;
    }
    
    $paidDaysCredits = $presentDaysCapped + $leaveCreditsToAdd;
    $totalUnpaidDays = max(0, $workingDaysCount - $paidDaysCredits);
    $totalLeaveDeduction = $totalUnpaidDays * $oneDaySalary;
    
    // Calculate absent days by subtracting the unpaid leave days from total unpaid days
    // Ensure it doesn't go below 0
    $absentDays = max(0, $totalUnpaidDays - $sumUnpaidLeaveDays);
    
    if ($absentDays > 0) {
        $absentDeduction = round($absentDays * $oneDaySalary, 2);
        
        $deductions['leave_deductions'][] = [
            'leave_id' => 0,
            'leave_type' => 'Absent (No Punch, No Leave)',
            'start_date' => '-',
            'end_date' => '-',
            'num_days' => round($absentDays, 2),
            'deduction' => $absentDeduction,
            'deduction_type' => 'Automatic deduction for missing days'
        ];
    }
    
    $deductions['total_deduction'] = round($totalLeaveDeduction, 2);
    
    echo json_encode([
        'status' => 'success',
        'base_salary' => $baseSalary,
        'working_days' => $workingDaysCount,
        'one_day_salary' => round($oneDaySalary, 2),
        'month' => $month,
        'year' => $year,
        'deductions' => $deductions
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in calculate_leave_deductions.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in calculate_leave_deductions.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred']);
    exit;
}
?>
