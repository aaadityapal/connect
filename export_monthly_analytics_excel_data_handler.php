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

try {
    // Fetch all employee analytics data for the month
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
            'status' => 'success',
            'data' => [],
            'message' => 'No employees found'
        ]);
        exit;
    }

    // Calculate working days function
    function calculateWorkingDays($pdo, $userId, $month, $year) {
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
    }

    // Process employee data
    $employeeData = [];
    
    foreach ($employees as $employee) {
        // Fetch salary record
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
        
        // Fetch present days
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
        
        // Calculate late days and other metrics (simplified for export)
        $lateDays = 0;
        $oneHourLateDays = 0;
        $leaveTaken = 0;
        $leaveDeduction = 0;
        $dailySalary = $workingDays > 0 ? $baseSalary / $workingDays : 0;
        
        // Fetch late days
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
        
        if ($userShift && !empty($userShift['start_time'])) {
            $shiftStartTime = $userShift['start_time'];
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
            
            $lateDays = max(0, $lateDays - $oneHourLateDays);
        }
        
        // Calculate leave taken
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
        $leaveTaken = max(0, intval($leaveResult['total_leave_days'] ?? 0));
        
        // Calculate deductions
        $lateDeductionAmount = (floor($lateDays / 3) * 0.5) * $dailySalary;
        $oneHourLateDeductionAmount = ($oneHourLateDays * 0.5) * $dailySalary;
        
        // Overtime calculation
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
        
        // Calculate salary calculated days
        $presentDaysCapped = floatval(min($presentDays, $workingDays));
        $salaryCalculatedDays = $presentDaysCapped;
        
        $regularLateDeductionDays = floor($lateDays / 3) * 0.5;
        $oneHourLateDeductionDays = $oneHourLateDays * 0.5;
        
        $salaryCalculatedDays -= $regularLateDeductionDays;
        $salaryCalculatedDays -= $oneHourLateDeductionDays;
        
        if ($salaryCalculatedDays < 0) {
            $salaryCalculatedDays = 0;
        }
        if ($salaryCalculatedDays > $workingDays) {
            $salaryCalculatedDays = floatval($workingDays);
        }
        
        $salaryCalculatedDays = round($salaryCalculatedDays, 2);
        
        // Calculate net salary and final salary
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
            'fourth_saturday_deduction' => 0,
            'salary_calculated_days' => $salaryCalculatedDays,
            'net_salary' => $netSalary,
            'overtime_hours' => round($overtimeHours, 2),
            'overtime_amount' => $overtimeAmount,
            'final_salary' => $finalSalary
        ];
    }

    // Calculate totals for summary
    $totalWithoutOvertime = 0;
    $totalWithOvertime = 0;
    
    foreach ($employeeData as $emp) {
        $totalWithoutOvertime += $emp['net_salary'];
        $totalWithOvertime += $emp['final_salary'];
    }
    
    // Return success response with all data and summary
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
