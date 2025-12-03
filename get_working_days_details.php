<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// Get parameters
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

if ($userId <= 0 || $month < 1 || $month > 12 || $year < 2000) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    // Create proper date strings with padding
    $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
    $firstDayOfMonth = "$year-$monthStr-01";
    $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));
    
    // Get total days in month
    $totalDays = intval(date('t', strtotime($firstDayOfMonth)));
    
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
    
    // Parse weekly_offs
    $weeklyOffs = [];
    if ($userShift && !empty($userShift['weekly_offs'])) {
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
    
    // Fetch office holidays for this month
    $holidayStmt = $pdo->prepare("
        SELECT DATE(holiday_date) as holiday_date, holiday_name
        FROM office_holidays
        WHERE DATE(holiday_date) BETWEEN ? AND ?
        ORDER BY holiday_date ASC
    ");
    $holidayStmt->execute([$firstDayOfMonth, $lastDayOfMonth]);
    $holidays = $holidayStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format holidays for display
    $holidaysList = [];
    $holidaysArray = [];
    $holidayDetailedDates = [];
    foreach ($holidays as $holiday) {
        $date = new DateTime($holiday['holiday_date']);
        $holidaysList[] = $date->format('d-M') . ' (' . $holiday['holiday_name'] . ')';
        $holidaysArray[] = $holiday['holiday_date'];
        $holidayDetailedDates[] = [
            'date' => $date->format('d-M'),
            'fullDate' => $date->format('d M Y'),
            'day' => $date->format('l'),
            'name' => $holiday['holiday_name']
        ];
    }
    
    // Calculate working days
    $workingDaysCount = 0;
    $weeklyOffsCount = 0;
    $officeHolidaysCount = 0;
    $officeHolidaysArray = array_flip($holidaysArray);
    
    // Collect weekly off dates
    $weeklyOffDates = [];
    
    $currentDate = new DateTime($firstDayOfMonth);
    $endDate = new DateTime($lastDayOfMonth);
    
    while ($currentDate <= $endDate) {
        $dayOfWeek = $currentDate->format('l');
        $currentDateStr = $currentDate->format('Y-m-d');
        
        $isWeeklyOff = in_array($dayOfWeek, $weeklyOffs);
        $isHoliday = isset($officeHolidaysArray[$currentDateStr]);
        
        if ($isWeeklyOff) {
            $weeklyOffsCount++;
            // Collect weekly off dates
            $weeklyOffDates[] = [
                'date' => $currentDate->format('d-M'),
                'fullDate' => $currentDate->format('d M Y'),
                'day' => $dayOfWeek
            ];
        } elseif ($isHoliday) {
            $officeHolidaysCount++;
        } else {
            $workingDaysCount++;
        }
        
        $currentDate->modify('+1 day');
    }
    
    // Get month name
    $monthName = date('F Y', strtotime($firstDayOfMonth));
    
    // Calculate weekly offs breakdown
    $weeklyOffsBreakdown = '';
    if (!empty($weeklyOffs)) {
        // Count occurrences of each weekly off in the month
        $weeklyOffCounts = [];
        $currentDate = new DateTime($firstDayOfMonth);
        $endDate = new DateTime($lastDayOfMonth);
        
        while ($currentDate <= $endDate) {
            $dayOfWeek = $currentDate->format('l');
            if (in_array($dayOfWeek, $weeklyOffs)) {
                if (!isset($weeklyOffCounts[$dayOfWeek])) {
                    $weeklyOffCounts[$dayOfWeek] = 0;
                }
                $weeklyOffCounts[$dayOfWeek]++;
            }
            $currentDate->modify('+1 day');
        }
        
        // Format the breakdown
        $breakdown = [];
        foreach ($weeklyOffCounts as $day => $count) {
            $breakdown[] = "$count $day" . ($count > 1 ? 's' : '');
        }
        $weeklyOffsBreakdown = implode(', ', $breakdown);
    }
    
    echo json_encode([
        'status' => 'success',
        'totalDays' => $totalDays,
        'weeklyOffsCount' => $weeklyOffsCount,
        'weeklyOffs' => $weeklyOffs,
        'weeklyOffsBreakdown' => $weeklyOffsBreakdown,
        'weeklyOffDates' => $weeklyOffDates,
        'holidaysCount' => $officeHolidaysCount,
        'holidays' => $holidaysList,
        'holidayDetailedDates' => $holidayDetailedDates,
        'workingDays' => $workingDaysCount,
        'monthYear' => $monthName,
        'calculation' => "$totalDays - $weeklyOffsCount - $officeHolidaysCount = $workingDaysCount"
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in get_working_days_details.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred'
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get_working_days_details.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred'
    ]);
    exit;
}
?>
