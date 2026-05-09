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
function calculateWorkingDays($pdo, $userId, $month, $year, $startDate = null) {
    try {
        // Ensure month and year are integers
        $month = intval($month);
        $year = intval($year);
        
        // Create proper date strings with padding
        $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
        $firstDayOfMonth = "$year-$monthStr-01";
        $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
        $effectiveStart = $firstDayOfMonth;
        if (!empty($startDate)) {
            $startDate = date('Y-m-d', strtotime($startDate));
            if ($startDate > $effectiveStart) {
                $effectiveStart = $startDate;
            }
        }
        if (strtotime($effectiveStart) > strtotime($lastDayOfMonth)) {
            return 0;
        }
        
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
        global $officeHolidays;
        if (!isset($officeHolidays)) {
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
        }
        
        // Count working days using DateTime loop
        $workingDaysCount = 0;
        $currentDate = new DateTime($effectiveStart);
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
            u.role as role,
            u.designation,
            u.department,
            u.status,
            u.joining_date,
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
    
    // Define month boundaries for reuse
    $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
    $firstDayOfMonth = "$year-$monthStr-01";
    $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));

    foreach ($employees as $employee) {
        // Fetch salary record
        $salaryStmt = $pdo->prepare("
            SELECT base_salary, base_salary_effective_from, tds_percentage, tds_effective_from
            FROM employee_salary_records 
            WHERE user_id = ? AND month = ? AND year = ? AND deleted_at IS NULL
            ORDER BY created_at DESC LIMIT 1
        ");
        $salaryStmt->execute([$employee['id'], $month, $year]);
        $salaryRecord = $salaryStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($salaryRecord) {
            $baseSalary = $salaryRecord['base_salary'];
            $baseSalaryEffectiveFrom = $salaryRecord['base_salary_effective_from'];
            $tdsPercentage = floatval($salaryRecord['tds_percentage']);
            $tdsEffectiveFrom = $salaryRecord['tds_effective_from'];
        } else {
            // Fallback: Get the most recent salary record for this user (any month/year)
            $fallbackStmt = $pdo->prepare("
                SELECT base_salary, base_salary_effective_from, tds_percentage, tds_effective_from
                FROM employee_salary_records 
                WHERE user_id = ? AND deleted_at IS NULL
                ORDER BY year DESC, month DESC LIMIT 1
            ");
            $fallbackStmt->execute([$employee['id']]);
            $fallbackRecord = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
            $baseSalary = $fallbackRecord ? $fallbackRecord['base_salary'] : 50000;
            $baseSalaryEffectiveFrom = $fallbackRecord ? $fallbackRecord['base_salary_effective_from'] : null;
            $tdsPercentage = $fallbackRecord ? floatval($fallbackRecord['tds_percentage']) : 0.00;
            $tdsEffectiveFrom = $fallbackRecord ? $fallbackRecord['tds_effective_from'] : null;
        }

        // base_salary stored in DB IS the Gross Salary (what user types in edit modal)
        // Payable Salary = Gross - TDS deduction = base_salary * (1 - tds%/100)
        $grossSalary = $baseSalary;

        $periodStart = $firstDayOfMonth;
        if (!empty($employee['joining_date'])) {
            $joinDate = date('Y-m-d', strtotime($employee['joining_date']));
            if ($joinDate > $periodStart) {
                $periodStart = $joinDate;
            }
        }
        $hasActiveDays = (strtotime($periodStart) <= strtotime($lastDayOfMonth));

        // Calculate working days for the full month
        $workingDays = calculateWorkingDays($pdo, $employee['id'], $month, $year);
        
        // Fetch user's shift start time and end time early to calculate dynamic half day threshold
        $shiftStmt = $pdo->prepare("
            SELECT s.start_time, s.end_time
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
        
        $halfShiftSeconds = 4.5 * 3600; // default 4.5 hours
        if ($userShift && !empty($userShift['start_time']) && !empty($userShift['end_time'])) {
            $start = strtotime($userShift['start_time']);
            $end = strtotime($userShift['end_time']);
            $shiftDuration = $end - $start;
            if ($shiftDuration < 0) { $shiftDuration += 24 * 3600; }
            $halfShiftSeconds = $shiftDuration / 2;
        }

        // Fetch Weekly Offs for this employee to use in present days calculation
        $userShiftStmt = $pdo->prepare("SELECT weekly_offs FROM user_shifts WHERE user_id = ? AND ( (effective_from IS NULL AND effective_to IS NULL) OR (effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)) ) ORDER BY effective_from DESC LIMIT 1");
        $userShiftStmt->execute([$employee['id'], $lastDayOfMonth, $firstDayOfMonth]);
        $uShift = $userShiftStmt->fetch(PDO::FETCH_ASSOC);
        $uWeeklyOffs = [];
        if ($uShift && !empty($uShift['weekly_offs'])) {
            $raw = trim($uShift['weekly_offs']);
            if (strpos($raw, '[') === 0) { $uWeeklyOffs = json_decode($raw, true) ?: []; }
            elseif (strpos($raw, ',') !== false) { $uWeeklyOffs = array_map('trim', explode(',', $raw)); }
            else { $uWeeklyOffs = [$raw]; }
        }

        // Fetch present days following the user formula:
        // present days = total month day - week off days - office holidays - no punch in except week off
        // Which is: Working Days - (Working Days with no punch_in)
        
        $attendanceStmt = $pdo->prepare("
            SELECT DATE(date) as punch_date, status, punch_in, punch_out,
                   MOD(TIME_TO_SEC(punch_out) - TIME_TO_SEC(punch_in) + 86400, 86400) as wh_sec
            FROM attendance
            WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
            AND punch_in IS NOT NULL AND punch_in != ''
        ");
        $attendanceStmt->execute([$employee['id'], $month, $year]);
        $punches = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
        $punchMap = [];
        foreach ($punches as $p) { $punchMap[$p['punch_date']] = $p; }

        $presentDays = 0;
        $presentDaysActive = 0;
        $curr = new DateTime($firstDayOfMonth);
        $end  = new DateTime($lastDayOfMonth);
        while ($curr <= $end) {
            $dStr = $curr->format('Y-m-d');
            $dayN = $curr->format('l');
            $isWO = in_array($dayN, $uWeeklyOffs);
            $isH  = isset($officeHolidays[$dStr]);
            
            if (!$isWO && !$isH) {
                if (isset($punchMap[$dStr])) {
                    $p = $punchMap[$dStr];
                    $isHD = ($p['status'] === 'half_day');
                    if (!$isHD && !empty($p['punch_out'])) {
                        if ($p['wh_sec'] < $halfShiftSeconds) $isHD = true;
                    }
                    $presentDays += ($isHD ? 0.5 : 1.0);
                    if ($dStr >= $periodStart) {
                        $presentDaysActive += ($isHD ? 0.5 : 1.0);
                    }
                }
            }
            $curr->modify('+1 day');
        }
        
        // Fetch late days from attendance table
        // Late is counted when punch_in time is more than 15 minutes after shift start time
        
        // Fetch ONLY MORNING short leave dates for this month to exclude from late day calculations
        // Evening short leaves should NOT reduce late punch-in counts
        $shortLeaveDates = [];
        try {
            $shortLeavesStmt = $pdo->prepare("
                SELECT DISTINCT DATE(lr.start_date) as leave_date, 
                       lr.time_from, lr.time_to,
                       s.start_time, s.end_time
                FROM leave_request lr
                INNER JOIN user_shifts us ON us.user_id = lr.user_id
                INNER JOIN shifts s ON s.id = us.shift_id
                WHERE lr.user_id = ?
                AND lr.status = 'approved'
                AND MONTH(lr.start_date) = ?
                AND YEAR(lr.start_date) = ?
                AND lr.leave_type IN (
                    SELECT id FROM leave_types 
                    WHERE LOWER(name) LIKE '%short%' OR LOWER(name) LIKE '%half%'
                )
                AND (us.effective_from IS NULL OR us.effective_from <= lr.start_date)
                AND (us.effective_to IS NULL OR us.effective_to >= lr.start_date)
            ");
            $shortLeavesStmt->execute([$employee['id'], $month, $year]);
            $shortLeaves = $shortLeavesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Filter to only MORNING short leaves
            // Morning short leave: time_from is between shift_start and shift_start + 1.5 hours
            foreach ($shortLeaves as $shortLeave) {
                $timeFrom = $shortLeave['time_from'];
                $shiftStart = $shortLeave['start_time'];
                
                // Create DateTime objects with reference date to properly compare times
                $referenceDate = '2000-01-01';
                $timeFromDT = new DateTime($referenceDate . ' ' . $timeFrom);
                $shiftStartDT = new DateTime($referenceDate . ' ' . $shiftStart);
                $shiftStart1_5HoursDT = new DateTime($referenceDate . ' ' . $shiftStart);
                $shiftStart1_5HoursDT->add(new DateInterval('PT1H30M'));
                
                // Check if this is a MORNING short leave
                // (time_from is between shift_start and shift_start + 1.5 hours)
                if ($timeFromDT >= $shiftStartDT && $timeFromDT <= $shiftStart1_5HoursDT) {
                    // This is a MORNING short leave - add it to the list
                    $leaveDate = $shortLeave['leave_date'];
                    if ($hasActiveDays && $leaveDate >= $periodStart) {
                        $shortLeaveDates[$leaveDate] = true;
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Error fetching short leaves for user " . $employee['id'] . ": " . $e->getMessage());
            $shortLeaveDates = [];
        }
        
        // Late days are now calculated inside the leave deduction block below
        // to ensure they are aware of half-day leaves.
        $lateDays = 0;
        $oneHourLateDays = 0;
        
        // Fetch leave taken from leave_request table
        // Count only approved leaves for the selected month and year
        $leaveTaken = 0;
        if ($hasActiveDays) {
            try {
            // Calculate month boundaries
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $firstDayOfMonth = "$year-$monthStr-01";
            $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
            $effectiveStart = $hasActiveDays ? $periodStart : $lastDayOfMonth;
            
            $leaveStmt = $pdo->prepare("
                SELECT SUM(
                    CASE
                        WHEN lr.start_date = lr.end_date AND lr.duration > 0 THEN lr.duration
                        WHEN LOWER(lt.name) LIKE '%half%' THEN 0.5
                        ELSE DATEDIFF(
                            LEAST(lr.end_date, ?),
                            GREATEST(lr.start_date, ?)
                        ) + 1
                    END
                ) as total_leave_days
                FROM leave_request lr
                LEFT JOIN leave_types lt ON lr.leave_type = lt.id
                WHERE lr.user_id = ?
                AND lr.status = 'approved'
                AND (
                    (MONTH(lr.start_date) = ? AND YEAR(lr.start_date) = ?) OR
                    (MONTH(lr.end_date) = ? AND YEAR(lr.end_date) = ?) OR
                    (lr.start_date < ? AND lr.end_date > ?)
                )
            ");
            
            $leaveStmt->execute([
                $lastDayOfMonth,
                $effectiveStart,
                $employee['id'],
                $month, $year,
                $month, $year,
                $effectiveStart, $lastDayOfMonth
            ]);
            $leaveResult = $leaveStmt->fetch(PDO::FETCH_ASSOC);
            $leaveTaken = $leaveResult['total_leave_days'] ?? 0;
            // Use floatval to preserve 0.5 half-day values
            $leaveTaken = max(0, floatval($leaveTaken));
            } catch (PDOException $e) {
                error_log("Error fetching leave data for user " . $employee['id'] . ": " . $e->getMessage());
                $leaveTaken = 0;
            }
        }
        
        // Calculate leave deductions directly
        $leaveDeduction = 0;
        $leaves = [];
        $dailyLeaveMap = [];
        if ($hasActiveDays) {
            try {
            // Calculate working days
            $workingDaysCount = $workingDays;
            $oneDaySalary = $workingDaysCount > 0 ? $grossSalary / $workingDaysCount : 0;
            
            // Fetch month boundaries
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $firstDayOfMonth = "$year-$monthStr-01";
            $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
            $effectiveStart = $hasActiveDays ? $periodStart : $lastDayOfMonth;
            
            // Fetch all approved leaves for the month
            $leaveDeductionStmt = $pdo->prepare("
                SELECT 
                    lr.id,
                    lr.start_date,
                    lr.end_date,
                    lt.name as leave_type,
                    lr.duration,
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
            
            $leaveDeductionStmt->execute([
                $employee['id'],
                $month, $year,
                $month, $year,
                $lastDayOfMonth, $effectiveStart
            ]);
            
            $leaves = $leaveDeductionStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Build a map of approved leave durations by date
            $dailyLeaveMap = [];
            foreach ($leaves as $lv) {
                $start = new DateTime($lv['start_date']);
                $end = new DateTime($lv['end_date']);
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
                foreach ($period as $date) {
                    $d = $date->format('Y-m-d');
                    if ($d < $effectiveStart) {
                        continue;
                    }
                    if (!isset($dailyLeaveMap[$d])) $dailyLeaveMap[$d] = 0;
                    $dailyLeaveMap[$d] += (isset($lv['duration']) && floatval($lv['duration']) > 0 && $lv['start_date'] === $lv['end_date']) 
                        ? floatval($lv['duration']) 
                        : (stripos($lv['leave_type'] ?? '', 'half') !== false ? 0.5 : intval($lv['calculated_days']));
                }
            }
            
            // Fetch all attendance records to calculate lates accurately
            $attendanceStmt = $pdo->prepare("
                SELECT date, punch_in FROM attendance
                WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
                AND punch_in IS NOT NULL AND punch_in != ''
            ");
            $attendanceStmt->execute([$employee['id'], $month, $year]);
            $attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $lateDays = 0;
            $oneHourLateDays = 0;
            
            if ($userShift && !empty($userShift['start_time']) && !empty($userShift['end_time'])) {
                $sTime = strtotime($userShift['start_time']);
                $eTime = strtotime($userShift['end_time']);
                $duration = $eTime - $sTime;
                if ($duration < 0) $duration += 24 * 3600;
                $halfShiftSecs = $duration / 2;
                
                foreach ($attendanceRecords as $att) {
                    $attDate = $att['date'];
                    if ($attDate < $effectiveStart) {
                        continue;
                    }
                    if (isset($shortLeaveDates[$attDate])) continue; // Skip if short leave approved
                    
                    $punchTime = strtotime($att['punch_in']);
                    $baseStart = $sTime;
                    
                    // If user has a 0.5 day leave and punched in late in the day, assume second half shift
                    if (isset($dailyLeaveMap[$attDate]) && $dailyLeaveMap[$attDate] >= 0.5) {
                        $afternoonThreshold = $sTime + $halfShiftSecs - 3600; // 1 hour before half-shift starts
                        if ($punchTime > $afternoonThreshold) {
                            $baseStart = $sTime + $halfShiftSecs;
                        }
                    }
                    
                    $diff = $punchTime - $baseStart;
                    
                    // 15 minute grace period (959 seconds to match HH:15:59 logic)
                    if ($diff > 959) {
                        if ($diff >= 3600) {
                            $oneHourLateDays++;
                        } else {
                            $lateDays++;
                        }
                    }
                }
            }
            
            $yearStartDate = "$year-01-01";
            $currentMonthDate = $lastDayOfMonth;
            
            $yearlyLeaveStmt = $pdo->prepare("
                SELECT 
                    lt.name as leave_type,
                    SUM(
                        CASE
                            WHEN lr.start_date = lr.end_date AND lr.duration > 0 THEN lr.duration
                            WHEN LOWER(lt.name) LIKE '%half%' THEN 0.5
                            ELSE DATEDIFF(lr.end_date, lr.start_date) + 1
                        END
                    ) as total_days
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
                
                $isHalfDay = stripos($leave['leave_type'] ?? '', 'half') !== false;
                $numDays = (isset($leave['duration']) && floatval($leave['duration']) > 0 && $leave['start_date'] === $leave['end_date']) 
                    ? floatval($leave['duration']) 
                    : ($isHalfDay ? 0.5 : intval($leave['calculated_days']));
                
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
            
            } catch (PDOException $e) {
                error_log("Error calculating leave deductions for user " . $employee['id'] . ": " . $e->getMessage());
                $leaveDeduction = 0;
            } catch (Exception $e) {
                error_log("Error in leave deduction calculation for user " . $employee['id'] . ": " . $e->getMessage());
                $leaveDeduction = 0;
            }
        }
        
        // Calculate late deductions
        // Daily salary calculation
        $dailySalary = $workingDays > 0 ? $grossSalary / $workingDays : 0;
        
        // Regular late deduction: Every 3 late days = 0.5 day deduction
        $lateDaysDeductionDays = floor($lateDays / 3) * 0.5;
        $lateDeductionAmount = $lateDaysDeductionDays * $dailySalary;
        
        // One hour late deduction: Each 1+ hour late = 0.5 day deduction
        $oneHourLateDaysDeductionDays = $oneHourLateDays * 0.5;
        $oneHourLateDeductionAmount = $oneHourLateDaysDeductionDays * $dailySalary;
        
        // Calculate 4th Saturday missing deduction
        // If user has not punched in on the 4th Saturday of the month, deduct 2 days salary
        // BUT only if the 4th Saturday has already passed
        // AND there is NO approved leave on that date
        $fourthSaturdayDeduction = 0;
        try {
            // Calculate month boundaries
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $firstDayOfMonth = "$year-$monthStr-01";
            $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
            
            // Find all Saturdays in the month
            $saturdays = [];
            $currentDate = new DateTime($firstDayOfMonth);
            $endDate = new DateTime($lastDayOfMonth);
            
            while ($currentDate <= $endDate) {
                if ($currentDate->format('N') == 6) { // 6 = Saturday
                    $saturdays[] = $currentDate->format('Y-m-d');
                }
                $currentDate->modify('+1 day');
            }
            
            // Get the 4th Saturday if it exists
            if (count($saturdays) >= 4) {
                $fourthSaturday = $saturdays[3]; // Index 3 is the 4th Saturday
                $today = date('Y-m-d');
                
                // Only apply deduction if the 4th Saturday has already passed
                if ($hasActiveDays && $fourthSaturday <= $today && $fourthSaturday >= $periodStart) {
                    // Check if user has an approved leave on the 4th Saturday
                    $leaveCheckStmt = $pdo->prepare("
                        SELECT id
                        FROM leave_request
                        WHERE user_id = ?
                        AND status = 'approved'
                        AND (
                            (DATE(start_date) <= ? AND DATE(end_date) >= ?)
                        )
                        LIMIT 1
                    ");
                    $leaveCheckStmt->execute([$employee['id'], $fourthSaturday, $fourthSaturday]);
                    $leaveRecord = $leaveCheckStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // If user has an approved leave on 4th Saturday, no deduction
                    if (!$leaveRecord) {
                        // Check if user has punched in on the 4th Saturday
                        $fourthSaturdayCheckStmt = $pdo->prepare("
                            SELECT punch_in
                            FROM attendance
                            WHERE user_id = ?
                            AND DATE(date) = ?
                            AND punch_in IS NOT NULL
                            AND punch_in != ''
                            LIMIT 1
                        ");
                        $fourthSaturdayCheckStmt->execute([$employee['id'], $fourthSaturday]);
                        $punchRecord = $fourthSaturdayCheckStmt->fetch(PDO::FETCH_ASSOC);
                        
                        // If no punch in found on 4th Saturday, deduct 2 days salary
                        if (!$punchRecord) {
                            $fourthSaturdayDeduction = 2 * $dailySalary;
                        }
                    }
                }
            }
            
            $fourthSaturdayDeduction = round($fourthSaturdayDeduction, 2);
            
        } catch (Exception $e) {
            error_log("Error calculating 4th Saturday deduction for user " . $employee['id'] . ": " . $e->getMessage());
            $fourthSaturdayDeduction = 0;
        }

        // Calculate overtime hours — dynamically recalculated using the same formula
        // as the overtime page (api_overtime.php) to guarantee consistency.
        // Rule   : punch_out must exceed shift end_time by >= 90 mins.
        // Formula: OT = floor(diffMins / 30) * 0.5  (rounds DOWN to nearest 30-min chunk).
        // Only approved overtime_requests records are counted (INNER JOIN).
        $overtimeHours  = 0;
        $overtimeAmount = 0;
        if ($hasActiveDays) {
        try {
            $monthStr        = str_pad($month, 2, '0', STR_PAD_LEFT);
            $firstDayOfMonth = "$year-$monthStr-01";
            $lastDayOfMonth  = date('Y-m-t', strtotime($firstDayOfMonth));
            $effectiveStart  = $hasActiveDays ? $periodStart : $lastDayOfMonth;

            // Fetch user's shift start_time / end_time to calculate shift duration
            $shiftHoursStmt = $pdo->prepare("
                SELECT s.start_time, s.end_time
                FROM user_shifts us
                JOIN shifts s ON us.shift_id = s.id
                WHERE us.user_id = ?
                  AND (
                      (us.effective_from IS NULL AND us.effective_to IS NULL) OR
                      (us.effective_from <= ? AND (us.effective_to IS NULL OR us.effective_to >= ?))
                  )
                ORDER BY us.effective_from DESC
                LIMIT 1
            ");
            $shiftHoursStmt->execute([$employee['id'], $lastDayOfMonth, $firstDayOfMonth]);
            $shiftHoursResult = $shiftHoursStmt->fetch(PDO::FETCH_ASSOC);

            $shiftHours   = 8;    // Default shift duration
            $shiftEndTime = null; // Fallback shift end time string

            if ($shiftHoursResult && $shiftHoursResult['start_time'] && $shiftHoursResult['end_time']) {
                $stDT         = new DateTime($shiftHoursResult['start_time']);
                $etDT         = new DateTime($shiftHoursResult['end_time']);
                $shiftEndTime = $shiftHoursResult['end_time'];
                if ($etDT < $stDT) { $etDT->modify('+1 day'); }
                $iv         = $stDT->diff($etDT);
                $shiftHours = floatval($iv->h) + (floatval($iv->i) / 60);
            }

            if ($shiftEndTime) {
                // Join attendance with approved overtime_requests so only approved OT days are counted.
                // Use the per-row shift end_time from the shifts table; fall back to $shiftEndTime.
                $otStmt = $pdo->prepare("
                    SELECT a.punch_out,
                           COALESCE(s.end_time, ?) AS end_time
                    FROM attendance a
                    INNER JOIN overtime_requests oreq
                           ON oreq.attendance_id = a.id
                          AND oreq.user_id       = a.user_id
                          AND oreq.status        = 'approved'
                    LEFT JOIN user_shifts us
                           ON us.user_id = a.user_id
                          AND a.date >= us.effective_from
                          AND (us.effective_to IS NULL OR a.date <= us.effective_to)
                    LEFT JOIN shifts s ON us.shift_id = s.id
                    WHERE a.user_id = ?
                      AND DATE(a.date) BETWEEN ? AND ?
                      AND a.punch_out IS NOT NULL
                      AND a.punch_out != ''
                ");
                $otStmt->execute([$shiftEndTime, $employee['id'], $effectiveStart, $lastDayOfMonth]);
                $otRows = $otStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($otRows as $otRow) {
                    $shiftEndTs = strtotime($otRow['end_time']);
                    $punchOutTs = strtotime($otRow['punch_out']);
                    if ($punchOutTs > $shiftEndTs) {
                        $diffMins = floor(($punchOutTs - $shiftEndTs) / 60);
                        // Must be at least 90 min past shift end
                        if ($diffMins >= 90) {
                            // Round DOWN to nearest 30-min chunk (identical to api_overtime.php)
                            $overtimeHours += floor($diffMins / 30) * 0.5;
                        }
                    }
                }

                $overtimeHours = round($overtimeHours, 2);

                if ($overtimeHours > 0 && $workingDays > 0 && $shiftHours > 0) {
                    $oneDaySalary   = $grossSalary / $workingDays;
                    $oneHourSalary  = $oneDaySalary / $shiftHours;
                    $overtimeAmount = round($overtimeHours * $oneHourSalary, 2);
                }
            }
        } catch (PDOException $e) {
            error_log("Error calculating overtime for user " . $employee['id'] . ": " . $e->getMessage());
            $overtimeHours  = 0;
            $overtimeAmount = 0;
        } catch (Exception $e) {
            error_log("Error calculating shift hours for user " . $employee['id'] . ": " . $e->getMessage());
            $overtimeHours  = 0;
            $overtimeAmount = 0;
        }
        }
        
        // Calculate salary calculated days
        // Start from present days - but cap present days to working days first
        $presentDaysCapped    = floatval(min($presentDays, $workingDays));
        $salaryCalculatedDays = $presentDaysCapped;

        // Add/Subtract leave credits from approved leaves fetched earlier ($leaves from leave deduction block)
        // Only add leaves that actually add to salary (casual) 
        // Deduct leaves that reduce salary (half-day)
        $casualLeaveCount = 0;
        $halfDayCount = 0;
        $compensateCount = 0;
        $leaveCreditsToAdd = 0;
        
        if (!empty($leaves) && is_array($leaves)) {
            foreach ($leaves as $lv) {
                $lt = strtolower(str_replace(' ', '_', $lv['leave_type'] ?? 'other'));
                
                $isHalfDay = stripos($lv['leave_type'] ?? '', 'half') !== false;
                $numDays = (isset($lv['duration']) && floatval($lv['duration']) > 0 && $lv['start_date'] === $lv['end_date']) 
                    ? floatval($lv['duration']) 
                    : ($isHalfDay ? 0.5 : intval($lv['calculated_days']));

                $deductionDays = 0;

                switch ($lt) {
                    case 'casual_leave':
                    case 'casual leave':
                        // Casual leave counts as full day(s) - ADD to salary
                        $leaveCreditsToAdd += $numDays;
                        $casualLeaveCount += $numDays;
                        break;
                    case 'half_day':
                    case 'half day':
                    case 'half_day_leave':
                    case 'half day leave':
                        // Half day leave is an approved leave for half a day - ADD 0.5 to salary days
                        // (Employee already got 0.5 from Present Days for the hours they worked)
                        $leaveCreditsToAdd += 0.5 * $numDays;
                        $halfDayCount += $numDays;
                        $deductionDays = $numDays - (0.5 * $numDays);
                        break;
                    case 'compensate_leave':
                    case 'compensate leave':
                        // Compensate leave counts as full day(s) - ADD to salary/present days
                        $leaveCreditsToAdd += $numDays;
                        $compensateCount += $numDays;
                        break;
                    case 'short_leave':
                    case 'short leave':
                        $deductionDays = 0;
                        break;
                    default:
                        // other leave types (sick, unpaid etc.) generally do not add salary days here
                        $deductionDays = $numDays;
                        break;
                }
            }
        }
        
        $officeHolidaysLocal = isset($officeHolidays) && is_array($officeHolidays) ? $officeHolidays : [];
        $absentDays = 0;
        if ($hasActiveDays) {
            $absentCursor = new DateTime($periodStart);
            $absentEnd = new DateTime($lastDayOfMonth);
            while ($absentCursor <= $absentEnd) {
                $dStr = $absentCursor->format('Y-m-d');
                $dayN = $absentCursor->format('l');
                $isWO = in_array($dayN, $uWeeklyOffs);
                $isH  = isset($officeHolidaysLocal[$dStr]);
                if (!$isWO && !$isH) {
                    if (!isset($punchMap[$dStr])) {
                        $leaveAmount = isset($dailyLeaveMap[$dStr]) ? min(1.0, floatval($dailyLeaveMap[$dStr])) : 0.0;
                        $absentDays += max(0, 1.0 - $leaveAmount);
                    }
                }
                $absentCursor->modify('+1 day');
            }
        }

        // Add casual/compensate/half-day credits and apply 1.5x penalty for unauthorized absences.
        $salaryCalculatedDays = floatval(min(max($salaryCalculatedDays + $leaveCreditsToAdd - (0.5 * $absentDays), 0), $workingDays));

        // Subtract deductions due to late arrivals
        // Regular late deduction days (every 3 late days => 0.5 day)
        $regularLateDeductionDays = floor($lateDays / 3) * 0.5;
        // 1+ hour late deduction days (each 1+ hour late = 0.5 day)
        $oneHourLateDeductionDays = $oneHourLateDays * 0.5;

        $salaryCalculatedDays -= $regularLateDeductionDays;
        $salaryCalculatedDays -= $oneHourLateDeductionDays;

        // Fetch and subtract penalty days from salary_penalties table - FIXED BUG: Proper NULL checking
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

        // Ensure salary calculated days are within reasonable bounds [0, workingDays]
        if ($salaryCalculatedDays < 0) {
            $salaryCalculatedDays = 0;
        }
        if ($salaryCalculatedDays > $workingDays) {
            // Cap to working days (can't be more than total working days)
            $salaryCalculatedDays = floatval($workingDays);
        }

        // Round to 2 decimal places
        $salaryCalculatedDays = round($salaryCalculatedDays, 2);

        // TDS deduction amount (deducted from gross to get payable salary)
        $tdsAmount = round($baseSalary * ($tdsPercentage / 100), 2);

        // Add 1.5x deduction for unauthorized absences after joining
        if ($absentDays > 0) {
            $leaveDeduction += ($absentDays * $oneDaySalary * 1.5);
        }
        $leaveDeduction = round($leaveDeduction, 2);

        // Present days display: punches only
        $displayPresentDays = $presentDays;

        $employeeData[] = [
            'id' => $employee['id'],
            'employee_id' => $employee['employee_id'] ?? $employee['id'],
            'name' => $employee['name'],
            'role' => $employee['role'],
            'base_salary' => $baseSalary,
            'base_salary_effective_from' => $baseSalaryEffectiveFrom,
            'tds_percentage' => $tdsPercentage,
            'tds_effective_from' => $tdsEffectiveFrom,
            'tds_amount' => $tdsAmount,
            'gross_salary' => $grossSalary,
            'working_days' => $workingDays,
            'present_days' => round($displayPresentDays, 2),
            'late_days' => $lateDays,
            'one_hour_late' => $oneHourLateDays,
            'leave_taken' => $leaveTaken,
            'leave_deduction' => round($leaveDeduction, 2),
            'late_deduction' => round($lateDeductionAmount, 2),
            'one_hour_late_deduction' => round($oneHourLateDeductionAmount, 2),
            'fourth_saturday_deduction' => $fourthSaturdayDeduction,
            'penalty_days' => $penaltyDays,
            'salary_calculated_days' => $salaryCalculatedDays,
            'overtime_hours' => $overtimeHours,
            'overtime_amount' => $overtimeAmount,
            // detailed counts used for salary calculation breakdown
            'casual_leave_days' => $casualLeaveCount,
            'half_day_leave_days' => $halfDayCount,
            'compensate_leave_days' => $compensateCount
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
