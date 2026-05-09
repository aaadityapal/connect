<?php
session_start();
require_once 'config/db_connect.php';

/**
 * Calculate leave deductions based on leave type and company rules
 * Synchronized with dashboard (fetch_monthly_analytics_data.php)
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
    // Get month boundaries
    $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
    $firstDayOfMonth = "$year-$monthStr-01";
    $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));

    // Joining date only affects absent penalty after joining
    $periodStart = $firstDayOfMonth;
    $joinStmt = $pdo->prepare("SELECT joining_date FROM users WHERE id = ?");
    $joinStmt->execute([$user_id]);
    $joinRow = $joinStmt->fetch(PDO::FETCH_ASSOC);
    if (!empty($joinRow['joining_date'])) {
        $joinDate = date('Y-m-d', strtotime($joinRow['joining_date']));
        if ($joinDate > $periodStart) {
            $periodStart = $joinDate;
        }
    }

    // Get salary record
    $salaryStmt = $pdo->prepare("
        SELECT base_salary FROM employee_salary_records 
        WHERE user_id = ? AND month = ? AND year = ? AND deleted_at IS NULL
        ORDER BY created_at DESC LIMIT 1
    ");
    $salaryStmt->execute([$user_id, $month, $year]);
    $salaryRecord = $salaryStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$salaryRecord) {
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

    // Get Weekly Offs
    $shiftStmt = $pdo->prepare("
        SELECT us.weekly_offs, us.effective_from, us.effective_to, s.start_time, s.end_time
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
    $shiftStmt->execute([$user_id, $lastDayOfMonth, $firstDayOfMonth]);
    $userShift = $shiftStmt->fetch(PDO::FETCH_ASSOC);
    
    $weeklyOffs = [];
    if ($userShift && !empty($userShift['weekly_offs'])) {
        $raw = trim($userShift['weekly_offs']);
        if (strpos($raw, '[') === 0) { $weeklyOffs = json_decode($raw, true) ?: []; }
        elseif (strpos($raw, ',') !== false) { $weeklyOffs = array_map('trim', explode(',', $raw)); }
        else { $weeklyOffs = [$raw]; }
    }

    // Get Half Shift Threshold
    $halfShiftSecs = 4.5 * 3600;
    if ($userShift && !empty($userShift['start_time']) && !empty($userShift['end_time'])) {
        $sTs = strtotime($userShift['start_time']);
        $eTs = strtotime($userShift['end_time']);
        $dur = $eTs - $sTs;
        if ($dur < 0) $dur += 86400;
        $halfShiftSecs = $dur / 2;
    }

    // Fetch office holidays
    $holidayStmt = $pdo->prepare("SELECT DATE(holiday_date) as holiday_date FROM office_holidays WHERE DATE(holiday_date) BETWEEN ? AND ?");
    $holidayStmt->execute([$firstDayOfMonth, $lastDayOfMonth]);
    $officeHolidays = array_flip($holidayStmt->fetchAll(PDO::FETCH_COLUMN));

    // Calculate Working Days
    $workingDaysCount = 0;
    $currDate = new DateTime($firstDayOfMonth);
    $endDate = new DateTime($lastDayOfMonth);
    while ($currDate <= $endDate) {
        $dayN = $currDate->format('l');
        $dStr = $currDate->format('Y-m-d');
        if (!in_array($dayN, $weeklyOffs) && !isset($officeHolidays[$dStr])) {
            $workingDaysCount++;
        }
        $currDate->modify('+1 day');
    }
    $oneDaySalary = $workingDaysCount > 0 ? $baseSalary / $workingDaysCount : 0;

    // Active working days (from join date)
    $activeWorkingDays = 0;
    $currDate = new DateTime($periodStart);
    while ($currDate <= $endDate) {
        $dayN = $currDate->format('l');
        $dStr = $currDate->format('Y-m-d');
        if (!in_array($dayN, $weeklyOffs) && !isset($officeHolidays[$dStr])) {
            $activeWorkingDays++;
        }
        $currDate->modify('+1 day');
    }

    // Calculate Present Days (Sync with dashboard)
    $attendanceStmt = $pdo->prepare("
        SELECT DATE(date) as punch_date, status, punch_in, punch_out,
               MOD(TIME_TO_SEC(punch_out) - TIME_TO_SEC(punch_in) + 86400, 86400) as wh_sec
        FROM attendance
        WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
        AND punch_in IS NOT NULL AND punch_in != ''
    ");
    $attendanceStmt->execute([$user_id, $month, $year]);
    $punches = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
    $punchMap = [];
    foreach ($punches as $p) { $punchMap[$p['punch_date']] = $p; }

    $presentDays = 0;
    $presentDaysActive = 0;
    $currDate = new DateTime($firstDayOfMonth);
    while ($currDate <= $endDate) {
        $dStr = $currDate->format('Y-m-d');
        $dayN = $currDate->format('l');
        if (!in_array($dayN, $weeklyOffs) && !isset($officeHolidays[$dStr])) {
            if (isset($punchMap[$dStr])) {
                $p = $punchMap[$dStr];
                $isHD = ($p['status'] === 'half_day');
                if (!$isHD && !empty($p['punch_out'])) {
                    if ($p['wh_sec'] < $halfShiftSecs) $isHD = true;
                }
                $presentDays += ($isHD ? 0.5 : 1.0);
                if ($dStr >= $periodStart) {
                    $presentDaysActive += ($isHD ? 0.5 : 1.0);
                }
            }
        }
        $currDate->modify('+1 day');
    }

    // Calculate Leave Taken (Month-aware)
    $leaveTaken = 0;
    $leaveTakenStmt = $pdo->prepare("
        SELECT SUM(
            CASE
                WHEN lr.start_date = lr.end_date AND lr.duration > 0 THEN lr.duration
                WHEN LOWER(lt.name) LIKE '%half%' THEN 0.5
                ELSE DATEDIFF(LEAST(lr.end_date, ?), GREATEST(lr.start_date, ?)) + 1
            END
        ) as total_leave_days
        FROM leave_request lr
        LEFT JOIN leave_types lt ON lr.leave_type = lt.id
        WHERE lr.user_id = ? AND lr.status = 'approved'
        AND (
            (MONTH(lr.start_date) = ? AND YEAR(lr.start_date) = ?) OR
            (MONTH(lr.end_date) = ? AND YEAR(lr.end_date) = ?) OR
            (lr.start_date < ? AND lr.end_date > ?)
        )
    ");
    $leaveTakenStmt->execute([$lastDayOfMonth, $periodStart, $user_id, $month, $year, $month, $year, $periodStart, $lastDayOfMonth]);
    $leaveTakenResult = $leaveTakenStmt->fetch(PDO::FETCH_ASSOC);
    $leaveTaken = max(0, floatval($leaveTakenResult['total_leave_days'] ?? 0));

    // Fetch Yearly Usage for restricted leaves
    $yearStartDate = "$year-01-01";
    $yearlyLeaveStmt = $pdo->prepare("
        SELECT lt.name as leave_type, SUM(
            CASE
                WHEN lr.start_date = lr.end_date AND lr.duration > 0 THEN lr.duration
                WHEN LOWER(lt.name) LIKE '%half%' THEN 0.5
                ELSE DATEDIFF(lr.end_date, lr.start_date) + 1
            END
        ) as total_days
        FROM leave_request lr
        LEFT JOIN leave_types lt ON lr.leave_type = lt.id
        WHERE lr.user_id = ? AND lr.status = 'approved' AND lr.start_date >= ? AND lr.start_date <= ?
        GROUP BY lt.name
    ");
    $yearlyLeaveStmt->execute([$user_id, $yearStartDate, $lastDayOfMonth]);
    $yearlyLeaves = $yearlyLeaveStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $leaveUsageYearly = ['sick_leave' => 0, 'paternity_leave' => 0, 'maternity_leave' => 0];
    foreach ($yearlyLeaves as $yl) {
        $ltName = strtolower(str_replace(' ', '_', $yl['leave_type'] ?? ''));
        if (strpos($ltName, 'sick') !== false) $leaveUsageYearly['sick_leave'] = floatval($yl['total_days']);
        if (strpos($ltName, 'paternity') !== false) $leaveUsageYearly['paternity_leave'] = floatval($yl['total_days']);
        if (strpos($ltName, 'maternity') !== false) $leaveUsageYearly['maternity_leave'] = floatval($yl['total_days']);
    }

    // Fetch All Approved Leaves for the Modal List
    $listStmt = $pdo->prepare("
        SELECT lr.id, lr.start_date, lr.end_date, lr.duration, lt.name as leave_type,
               DATEDIFF(lr.end_date, lr.start_date) + 1 as calculated_days
        FROM leave_request lr
        LEFT JOIN leave_types lt ON lr.leave_type = lt.id
        WHERE lr.user_id = ? AND lr.status = 'approved'
        AND ( (MONTH(lr.start_date) = ? AND YEAR(lr.start_date) = ?) OR (MONTH(lr.end_date) = ? AND YEAR(lr.end_date) = ?) OR (lr.start_date <= ? AND lr.end_date >= ?) )
        ORDER BY lr.start_date ASC
    ");
    $listStmt->execute([$user_id, $month, $year, $month, $year, $lastDayOfMonth, $periodStart]);
    $leavesList = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    $finalLeaveDeductions = [];
    $totalDeductionAmount = 0;

    $dailyLeaveMap = [];
    foreach ($leavesList as $lv) {
        $lvStart = $lv['start_date'];
        $lvEnd = $lv['end_date'];
        if ($lvStart < $periodStart) {
            $lvStart = $periodStart;
        }
        if ($lvEnd > $lastDayOfMonth) {
            $lvEnd = $lastDayOfMonth;
        }
        if ($lvStart > $lvEnd) {
            continue;
        }
        $lt = strtolower(str_replace(' ', '_', $lv['leave_type'] ?? 'other'));
        $isHD = stripos($lv['leave_type'] ?? '', 'half') !== false;
        $numDays = (isset($lv['duration']) && floatval($lv['duration']) > 0 && $lv['start_date'] === $lv['end_date']) 
            ? floatval($lv['duration']) : ($isHD ? 0.5 : (intval((strtotime($lvEnd) - strtotime($lvStart)) / 86400) + 1));
        
        $deduction = 0;
        $reason = 'Paid Leave';

        switch ($lt) {
            case 'compensate_leave': case 'compensate leave': case 'casual_leave': case 'casual leave': case 'short_leave': case 'short leave':
                $deduction = 0; $reason = 'Paid Leave (No deduction)'; break;
            case 'sick_leave': case 'sick leave':
                if ($leaveUsageYearly['sick_leave'] > 6) { $deduction = $numDays * $oneDaySalary; $reason = 'Beyond yearly limit (6 days)'; }
                else { $deduction = 0; $reason = 'Within yearly limit (6 days)'; }
                break;
            case 'half_day': case 'half day': case 'half_day_leave': case 'half day leave':
                $deduction = 0.5 * $oneDaySalary; $reason = 'Half day deduction'; break;
            case 'unpaid_leave': case 'unpaid leave':
                $deduction = $numDays * $oneDaySalary; $reason = 'Unpaid Leave'; break;
            case 'paternity_leave': case 'paternity leave':
                if ($leaveUsageYearly['paternity_leave'] > 7) { $deduction = $numDays * $oneDaySalary; $reason = 'Beyond yearly limit (7 days)'; }
                else { $deduction = 0; $reason = 'Within yearly limit (7 days)'; }
                break;
            case 'maternity_leave': case 'maternity leave':
                if ($leaveUsageYearly['maternity_leave'] > 60) { $deduction = $numDays * $oneDaySalary; $reason = 'Beyond yearly limit (60 days)'; }
                else { $deduction = 0; $reason = 'Within yearly limit (60 days)'; }
                break;
            default:
                $deduction = 0; break;
        }

        $totalDeductionAmount += $deduction;
        $finalLeaveDeductions[] = [
            'leave_type' => $lv['leave_type'],
            'num_days' => $numDays,
            'deduction' => round($deduction, 2),
            'deduction_type' => $reason
        ];

        $start = new DateTime($lvStart);
        $end = new DateTime($lvEnd);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
        foreach ($period as $date) {
            $d = $date->format('Y-m-d');
            if (!isset($dailyLeaveMap[$d])) $dailyLeaveMap[$d] = 0;
            $dailyLeaveMap[$d] += $numDays >= 1 ? 1.0 : $numDays;
        }
    }

    // Calculate Absent Days per date (no punch and no leave)
    $officeHolidaysLocal = isset($officeHolidays) && is_array($officeHolidays) ? $officeHolidays : [];
    $absentDays = 0;
    $absentCursor = new DateTime($periodStart);
    while ($absentCursor <= $endDate) {
        $dStr = $absentCursor->format('Y-m-d');
        $dayN = $absentCursor->format('l');
        $isWO = in_array($dayN, $weeklyOffs);
        $isH  = isset($officeHolidaysLocal[$dStr]);
        if (!$isWO && !$isH) {
            if (!isset($punchMap[$dStr])) {
                $leaveAmount = isset($dailyLeaveMap[$dStr]) ? min(1.0, floatval($dailyLeaveMap[$dStr])) : 0.0;
                $absentDays += max(0, 1.0 - $leaveAmount);
            }
        }
        $absentCursor->modify('+1 day');
    }

    // Add 1.5x penalty for unauthorized absences after joining
    if ($absentDays > 0) {
        $absentDeduction = $absentDays * $oneDaySalary * 1.5;
        $totalDeductionAmount += $absentDeduction;
        $finalLeaveDeductions[] = [
            'leave_type' => 'Absent (Unauthorized)',
            'num_days' => round($absentDays, 2),
            'deduction' => round($absentDeduction, 2),
            'deduction_type' => 'Absent after joining (1.5x)'
        ];
    }

    echo json_encode([
        'status' => 'success',
        'base_salary' => $baseSalary,
        'working_days' => $workingDaysCount,
        'one_day_salary' => round($oneDaySalary, 2),
        'deductions' => [
            'total_deduction' => round($totalDeductionAmount, 2),
            'leave_deductions' => $finalLeaveDeductions
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
