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
        $month = intval($month);
        $year = intval($year);
        
        $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
        $firstDayOfMonth = "$year-$monthStr-01";
        $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
        
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
            error_log("Error fetching office holidays: " . $e->getMessage());
            $officeHolidays = [];
        }
        
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
        error_log("Error calculating working days for user $userId, month $month, year $year: " . $e->getMessage());
        return 26;
    }
}

try {
    // Fetch employee data
    $query = "
        SELECT 
            u.id,
            u.unique_id as employee_id,
            u.username as name,
            u.role as role,
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
            'status' => 'error',
            'message' => 'No employees found'
        ]);
        exit;
    }

    // Process employee data (same logic as fetch_monthly_analytics_data.php)
    $employeeData = [];
    $totalSalaryWithoutOvertime = 0;
    $totalSalaryWithOvertime = 0;
    $totalOvertimeAmount = 0;
    
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
            $fallbackStmt = $pdo->prepare("
                SELECT base_salary FROM employee_salary_records 
                WHERE user_id = ? AND deleted_at IS NULL
                ORDER BY year DESC, month DESC LIMIT 1
            ");
            $fallbackStmt->execute([$employee['id']]);
            $fallbackRecord = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
            $baseSalary = $fallbackRecord ? $fallbackRecord['base_salary'] : 50000;
        }
        
        $workingDays = calculateWorkingDays($pdo, $employee['id'], $month, $year);
        
        // Present days
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
        
        // Get shift start time
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
        
        // Fetch short leave dates
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
            
            foreach ($shortLeaves as $shortLeave) {
                $timeFrom = $shortLeave['time_from'];
                $shiftStart = $shortLeave['start_time'];
                
                $referenceDate = '2000-01-01';
                $timeFromDT = new DateTime($referenceDate . ' ' . $timeFrom);
                $shiftStartDT = new DateTime($referenceDate . ' ' . $shiftStart);
                $shiftStart1_5HoursDT = new DateTime($referenceDate . ' ' . $shiftStart);
                $shiftStart1_5HoursDT->add(new DateInterval('PT1H30M'));
                
                if ($timeFromDT >= $shiftStartDT && $timeFromDT <= $shiftStart1_5HoursDT) {
                    $leaveDate = $shortLeave['leave_date'];
                    $shortLeaveDates[$leaveDate] = true;
                }
            }
        } catch (PDOException $e) {
            error_log("Error fetching short leaves: " . $e->getMessage());
            $shortLeaveDates = [];
        }
        
        // Late days calculation
        $lateDays = 0;
        if ($userShift && !empty($userShift['start_time'])) {
            $shiftStartTime = $userShift['start_time'];
            $graceTime = date('H:i:s', strtotime($shiftStartTime . ' +15 minutes'));
            
            $lateDaysStmt = $pdo->prepare("
                SELECT DATE(date) as late_date
                FROM attendance
                WHERE user_id = ?
                AND MONTH(date) = ?
                AND YEAR(date) = ?
                AND punch_in IS NOT NULL
                AND punch_in != ''
                AND TIME(punch_in) > ?
            ");
            $lateDaysStmt->execute([$employee['id'], $month, $year, $graceTime]);
            $lateDaysResults = $lateDaysStmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($lateDaysResults as $lateDate) {
                if (!isset($shortLeaveDates[$lateDate])) {
                    $lateDays++;
                }
            }
        }
        
        // 1+ hour late days
        $oneHourLateDays = 0;
        if ($userShift && !empty($userShift['start_time'])) {
            $shiftStartTime = $userShift['start_time'];
            $oneHourLateTime = date('H:i:s', strtotime($shiftStartTime . ' +1 hour'));
            
            $oneHourLateStmt = $pdo->prepare("
                SELECT DATE(date) as late_date
                FROM attendance
                WHERE user_id = ?
                AND MONTH(date) = ?
                AND YEAR(date) = ?
                AND punch_in IS NOT NULL
                AND punch_in != ''
                AND TIME(punch_in) >= ?
            ");
            $oneHourLateStmt->execute([$employee['id'], $month, $year, $oneHourLateTime]);
            $oneHourLateResults = $oneHourLateStmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($oneHourLateResults as $lateDate) {
                if (!isset($shortLeaveDates[$lateDate])) {
                    $oneHourLateDays++;
                }
            }
            
            $lateDays = max(0, $lateDays - $oneHourLateDays);
        }
        
        // Leave taken
        $leaveTaken = 0;
        try {
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
            $leaveTaken = max(0, intval($leaveTaken));
        } catch (PDOException $e) {
            error_log("Error fetching leave data: " . $e->getMessage());
            $leaveTaken = 0;
        }
        
        // Leave deductions (same logic as fetch_monthly_analytics_data.php)
        $leaveDeduction = 0;
        $leaves = [];
        try {
            $workingDaysCount = $workingDays;
            $oneDaySalary = $workingDaysCount > 0 ? $baseSalary / $workingDaysCount : 0;
            
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $firstDayOfMonth = "$year-$monthStr-01";
            $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
            
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
            
        } catch (PDOException $e) {
            error_log("Error calculating leave deductions: " . $e->getMessage());
            $leaveDeduction = 0;
        } catch (Exception $e) {
            error_log("Error in leave deduction calculation: " . $e->getMessage());
            $leaveDeduction = 0;
        }
        
        // Late deductions
        $dailySalary = $workingDays > 0 ? $baseSalary / $workingDays : 0;
        
        $lateDaysDeductionDays = floor($lateDays / 3) * 0.5;
        $lateDeductionAmount = $lateDaysDeductionDays * $dailySalary;
        
        $oneHourLateDaysDeductionDays = $oneHourLateDays * 0.5;
        $oneHourLateDeductionAmount = $oneHourLateDaysDeductionDays * $dailySalary;
        
        // 4th Saturday deduction
        $fourthSaturdayDeduction = 0;
        try {
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $firstDayOfMonth = "$year-$monthStr-01";
            $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
            
            $saturdays = [];
            $currentDate = new DateTime($firstDayOfMonth);
            $endDate = new DateTime($lastDayOfMonth);
            
            while ($currentDate <= $endDate) {
                if ($currentDate->format('N') == 6) {
                    $saturdays[] = $currentDate->format('Y-m-d');
                }
                $currentDate->modify('+1 day');
            }
            
            if (count($saturdays) >= 4) {
                $fourthSaturday = $saturdays[3];
                $today = date('Y-m-d');
                
                if ($fourthSaturday <= $today) {
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
                    
                    if (!$leaveRecord) {
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
                        
                        if (!$punchRecord) {
                            $fourthSaturdayDeduction = 2 * $dailySalary;
                        }
                    }
                }
            }
            
            $fourthSaturdayDeduction = round($fourthSaturdayDeduction, 2);
            
        } catch (Exception $e) {
            error_log("Error calculating 4th Saturday deduction: " . $e->getMessage());
            $fourthSaturdayDeduction = 0;
        }

        // Overtime calculation
        $overtimeHours = 0;
        $overtimeAmount = 0;
        try {
            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $firstDayOfMonth = "$year-$monthStr-01";
            $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
            
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
        } catch (PDOException $e) {
            error_log("Error calculating overtime: " . $e->getMessage());
            $overtimeHours = 0;
            $overtimeAmount = 0;
        } catch (Exception $e) {
            error_log("Error calculating shift hours: " . $e->getMessage());
            $overtimeHours = 0;
            $overtimeAmount = 0;
        }
        
        // Salary calculated days
        $presentDaysCapped = floatval(min($presentDays, $workingDays));
        $salaryCalculatedDays = $presentDaysCapped;

        $casualLeaveCount = 0;
        $halfDayCount = 0;
        $compensateCount = 0;
        $leaveCreditsToAdd = 0;
        
        if (!empty($leaves) && is_array($leaves)) {
            foreach ($leaves as $lv) {
                $lt = strtolower(str_replace(' ', '_', $lv['leave_type'] ?? 'other'));
                $numDays = intval($lv['num_days']);

                switch ($lt) {
                    case 'casual_leave':
                    case 'casual leave':
                        $leaveCreditsToAdd += $numDays;
                        $casualLeaveCount += $numDays;
                        break;
                    case 'half_day':
                    case 'half day':
                    case 'half_day_leave':
                    case 'half day leave':
                        $leaveCreditsToAdd -= 0.5 * $numDays;
                        $halfDayCount += $numDays;
                        break;
                    case 'compensate_leave':
                    case 'compensate leave':
                        $compensateCount += $numDays;
                        break;
                    default:
                        break;
                }
            }
        }
        
        $salaryCalculatedDays = floatval(min(max($salaryCalculatedDays + $leaveCreditsToAdd, 0), $workingDays));

        $regularLateDeductionDays = floor($lateDays / 3) * 0.5;
        $oneHourLateDeductionDays = $oneHourLateDays * 0.5;

        $salaryCalculatedDays -= $regularLateDeductionDays;
        $salaryCalculatedDays -= $oneHourLateDeductionDays;

        // Fetch penalty days - FIXED BUG: Proper NULL checking
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
            
            if ($penaltyRecord !== false && isset($penaltyRecord['penalty_days']) && $penaltyRecord['penalty_days'] !== null) {
                $penaltyDays = floatval($penaltyRecord['penalty_days']);
                $salaryCalculatedDays -= $penaltyDays;
            }
        } catch (PDOException $e) {
            error_log("Error fetching penalty days: " . $e->getMessage());
            $penaltyDays = 0;
        }

        $fourthSaturdayMissingDays = ($fourthSaturdayDeduction > 0) ? 2 : 0;
        $salaryCalculatedDays -= $fourthSaturdayMissingDays;

        if ($salaryCalculatedDays < 0) {
            $salaryCalculatedDays = 0;
        }
        if ($salaryCalculatedDays > $workingDays) {
            $salaryCalculatedDays = floatval($workingDays);
        }

        $salaryCalculatedDays = round($salaryCalculatedDays, 2);

        // Calculate net salary and final salary
        $netSalary = ($salaryCalculatedDays * $dailySalary);
        $finalSalary = $netSalary + $overtimeAmount;

        $employeeData[] = [
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
            'late_deduction' => round($lateDeductionAmount, 2),
            'one_hour_late_deduction' => round($oneHourLateDeductionAmount, 2),
            'fourth_saturday_deduction' => $fourthSaturdayDeduction,
            'penalty_days' => $penaltyDays,
            'salary_calculated_days' => $salaryCalculatedDays,
            'net_salary' => round($netSalary, 2),
            'overtime_hours' => $overtimeHours,
            'overtime_amount' => $overtimeAmount,
            'final_salary' => round($finalSalary, 2)
        ];

        $totalSalaryWithoutOvertime += round($netSalary, 2);
        $totalSalaryWithOvertime += round($finalSalary, 2);
        $totalOvertimeAmount += $overtimeAmount;
    }

    // Return JSON response
    echo json_encode([
        'status' => 'success',
        'data' => $employeeData,
        'month' => $month,
        'year' => $year,
        'summary' => [
            'total_salary_without_overtime' => round($totalSalaryWithoutOvertime, 2),
            'total_salary_with_overtime' => round($totalSalaryWithOvertime, 2),
            'total_overtime_amount' => round($totalOvertimeAmount, 2),
            'employee_count' => count($employeeData)
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in generate_monthly_payroll_report.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred'
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in generate_monthly_payroll_report.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred'
    ]);
    exit;
}
?>
