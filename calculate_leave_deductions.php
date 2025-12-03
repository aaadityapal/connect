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
    
    $leaveStmt->execute([
        $user_id,
        $month, $year,
        $month, $year,
        $lastDayOfMonth, $firstDayOfMonth
    ]);
    
    $leaves = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize deduction tracking
    $deductions = [
        'total_deduction' => 0,
        'leave_deductions' => [],
        'leave_summary' => []
    ];
    
    // Track leave usage per type for the year
    $leaveUsageYearly = [
        'sick_leave' => 0,
        'paternity_leave' => 0,
        'maternity_leave' => 0
    ];
    
    // Get yearly leave usage (count from Jan to current month)
    $yearStartDate = "$year-01-01";
    $currentMonthDate = date('Y-m-t', strtotime($firstDayOfMonth));
    
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
    
    $yearlyLeaveStmt->execute([$user_id, $yearStartDate, $currentMonthDate]);
    $yearlyLeaves = $yearlyLeaveStmt->fetchAll(PDO::FETCH_ASSOC);
    
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
        $deductionType = '';
        
        switch ($leaveType) {
            case 'compensate_leave':
            case 'compensate leave':
                $deduction = 0;
                $deductionType = 'No deduction - Compensate leave';
                break;
                
            case 'casual_leave':
            case 'casual leave':
                $deduction = 0;
                $deductionType = 'No deduction - Casual leave';
                break;
                
            case 'sick_leave':
            case 'sick leave':
                // First 6 days per year no deduction, beyond that deduct daily salary
                $sickLeaveDaysInMonth = $numDays;
                $totalSickLeavesYearly = $leaveUsageYearly['sick_leave'];
                $allowedSickDays = 6;
                
                if ($totalSickLeavesYearly <= $allowedSickDays) {
                    $deduction = 0;
                    $deductionType = "No deduction - Within 6 days limit ($totalSickLeavesYearly/6 days used)";
                } else {
                    // Calculate how much exceeds the 6-day limit
                    $excessDays = $totalSickLeavesYearly - $allowedSickDays;
                    $deduction = $excessDays * $oneDaySalary;
                    $deductionType = "Deduction for excess sick days ($excessDays days over 6-day limit)";
                }
                break;
                
            case 'half_day':
            case 'half day':
                $deduction = $oneDaySalary * 0.5;
                $deductionType = 'Deduction - Half day leave';
                break;
                
            case 'short_leave':
            case 'short leave':
                $deduction = 0;
                $deductionType = 'No deduction - Short leave';
                break;
                
            case 'unpaid_leave':
            case 'unpaid leave':
                $deduction = $numDays * $oneDaySalary;
                $deductionType = "Deduction - Unpaid leave ($numDays days)";
                break;
                
            case 'paternity_leave':
            case 'paternity leave':
                // First 7 days per year no deduction, beyond that deduct daily salary
                $totalPaternityLeavesYearly = $leaveUsageYearly['paternity_leave'];
                $allowedPaternityDays = 7;
                
                if ($totalPaternityLeavesYearly <= $allowedPaternityDays) {
                    $deduction = 0;
                    $deductionType = "No deduction - Within 7 days limit ($totalPaternityLeavesYearly/7 days used)";
                } else {
                    $excessDays = $totalPaternityLeavesYearly - $allowedPaternityDays;
                    $deduction = $excessDays * $oneDaySalary;
                    $deductionType = "Deduction for excess paternity days ($excessDays days over 7-day limit)";
                }
                break;
                
            case 'maternity_leave':
            case 'maternity leave':
                // First 60 days per year no deduction, beyond that deduct daily salary
                $totalMaternityLeavesYearly = $leaveUsageYearly['maternity_leave'];
                $allowedMaternityDays = 60;
                
                if ($totalMaternityLeavesYearly <= $allowedMaternityDays) {
                    $deduction = 0;
                    $deductionType = "No deduction - Within 60 days limit ($totalMaternityLeavesYearly/60 days used)";
                } else {
                    $excessDays = $totalMaternityLeavesYearly - $allowedMaternityDays;
                    $deduction = $excessDays * $oneDaySalary;
                    $deductionType = "Deduction for excess maternity days ($excessDays days over 60-day limit)";
                }
                break;
                
            default:
                $deduction = 0;
                $deductionType = 'No deduction - Unknown leave type';
        }
        
        // Round deduction to 2 decimal places
        $deduction = round($deduction, 2);
        
        $deductions['total_deduction'] += $deduction;
        
        $deductions['leave_deductions'][] = [
            'leave_id' => $leave['id'],
            'leave_type' => $leave['leave_type'] ?? 'Unknown',
            'start_date' => $leave['start_date'],
            'end_date' => $leave['end_date'],
            'num_days' => $numDays,
            'deduction' => $deduction,
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
        $deductions['leave_summary'][$summary_key]['total_deduction'] += $deduction;
    }
    
    // Round total deduction
    $deductions['total_deduction'] = round($deductions['total_deduction'], 2);
    
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
