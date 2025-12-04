<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// Check if user_id, month and year are provided
if (!isset($_GET['user_id']) || !isset($_GET['month']) || !isset($_GET['year'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'user_id, month and year are required']);
    exit;
}

$userId = intval($_GET['user_id']);
$month = intval($_GET['month']);
$year = intval($_GET['year']);

// Validate inputs
if ($userId <= 0 || $month < 1 || $month > 12 || $year < 2000 || $year > date('Y') + 5) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Create month boundaries
    $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
    $firstDayOfMonth = "$year-$monthStr-01";
    $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));

    // Fetch user details
    $userStmt = $pdo->prepare("
        SELECT u.id, u.username as name, u.position as role
        FROM users u
        WHERE u.id = ? AND u.deleted_at IS NULL AND u.status = 'active'
    ");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        exit;
    }

    // Fetch salary record for the month
    $salaryStmt = $pdo->prepare("
        SELECT base_salary FROM employee_salary_records 
        WHERE user_id = ? AND month = ? AND year = ? AND deleted_at IS NULL
        ORDER BY created_at DESC LIMIT 1
    ");
    $salaryStmt->execute([$userId, $month, $year]);
    $salaryRecord = $salaryStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($salaryRecord) {
        $baseSalary = $salaryRecord['base_salary'];
    } else {
        // Fallback: Get the most recent salary record
        $fallbackStmt = $pdo->prepare("
            SELECT base_salary FROM employee_salary_records 
            WHERE user_id = ? AND deleted_at IS NULL
            ORDER BY year DESC, month DESC LIMIT 1
        ");
        $fallbackStmt->execute([$userId]);
        $fallbackRecord = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
        $baseSalary = $fallbackRecord ? $fallbackRecord['base_salary'] : 50000;
    }

    // Calculate working days
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
    
    $workingDays = 26; // Default
    if ($userShift) {
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
        
        $workingDays = $workingDaysCount;
    }

    // Fetch shift hours
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
    $shiftHoursStmt->execute([$userId, $lastDayOfMonth, $firstDayOfMonth]);
    $shiftHoursResult = $shiftHoursStmt->fetch(PDO::FETCH_ASSOC);
    
    $shiftHours = 8; // Default
    if ($shiftHoursResult && $shiftHoursResult['start_time'] && $shiftHoursResult['end_time']) {
        $startTime = new DateTime($shiftHoursResult['start_time']);
        $endTime = new DateTime($shiftHoursResult['end_time']);
        
        if ($endTime < $startTime) {
            $endTime->modify('+1 day');
        }
        
        $interval = $startTime->diff($endTime);
        $shiftHours = floatval($interval->h) + (floatval($interval->i) / 60);
    }

    // Calculate per hour salary
    $oneDaySalary = $baseSalary / $workingDays;
    $oneHourSalary = $oneDaySalary / $shiftHours;

    // Fetch all approved overtime records for the month
    $overtimeStmt = $pdo->prepare("
        SELECT 
            id,
            DATE(date) as overtime_date,
            overtime_hours,
            work_report,
            overtime_description,
            submitted_at,
            actioned_at
        FROM overtime_requests
        WHERE user_id = ?
        AND DATE(date) BETWEEN ? AND ?
        AND status = 'approved'
        ORDER BY DATE(date) ASC
    ");
    $overtimeStmt->execute([$userId, $firstDayOfMonth, $lastDayOfMonth]);
    $overtimeRecords = $overtimeStmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total overtime hours and amount
    $totalOvertimeHours = 0;
    $totalOvertimeAmount = 0;
    $overtimeDetails = [];

    foreach ($overtimeRecords as $record) {
        $hours = floatval($record['overtime_hours']);
        $amount = round($hours * $oneHourSalary, 2);
        
        $totalOvertimeHours += $hours;
        $totalOvertimeAmount += $amount;

        $overtimeDetails[] = [
            'id' => $record['id'],
            'date' => $record['overtime_date'],
            'hours' => $hours,
            'amount' => $amount,
            'work_report' => $record['work_report'],
            'description' => $record['overtime_description'],
            'submitted_at' => $record['submitted_at'],
            'actioned_at' => $record['actioned_at']
        ];
    }

    // Return response
    echo json_encode([
        'status' => 'success',
        'user' => $user,
        'salary_info' => [
            'base_salary' => $baseSalary,
            'working_days' => $workingDays,
            'shift_hours' => $shiftHours,
            'per_hour_salary' => round($oneHourSalary, 2),
            'per_day_salary' => round($oneDaySalary, 2)
        ],
        'overtime_summary' => [
            'total_hours' => round($totalOvertimeHours, 2),
            'total_amount' => round($totalOvertimeAmount, 2)
        ],
        'overtime_records' => $overtimeDetails,
        'month' => $month,
        'year' => $year
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in fetch_user_overtime_detailed_breakdown.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred'
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in fetch_user_overtime_detailed_breakdown.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred'
    ]);
    exit;
}
?>
