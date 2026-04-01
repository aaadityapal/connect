<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../config/db_connect.php';

$userId = $_GET['user_id'] ?? null;
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

try {
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $startDate = sprintf("%04d-%02d-01", $year, $month);
    $endDate = sprintf("%04d-%02d-%02d", $year, $month, $daysInMonth);
    
    // 1. Fetch Attendance (Work Reports)
    $stmtAtt = $pdo->prepare("SELECT date, work_report FROM attendance WHERE user_id = ? AND date BETWEEN ? AND ?");
    $stmtAtt->execute([$userId, $startDate, $endDate]);
    $attendanceData = [];
    while($row = $stmtAtt->fetch(PDO::FETCH_ASSOC)){
        // Only keep valid string reports if present
        $attendanceData[$row['date']] = $row['work_report'];
    }
    
    // 2. Fetch Holidays
    $stmtHol = $pdo->prepare("SELECT holiday_date AS date, title FROM holidays WHERE holiday_date BETWEEN ? AND ?");
    $stmtHol->execute([$startDate, $endDate]);
    $holidayData = [];
    while($row = $stmtHol->fetch(PDO::FETCH_ASSOC)){
        $holidayData[$row['date']] = $row['title'] ?? 'Holiday';
    }
    
    // 3. Fetch Leaves
    $stmtLeave = $pdo->prepare("
        SELECT lr.start_date, lr.end_date, lt.name AS leave_name, lr.duration_type, lr.day_type, lr.time_from, lr.time_to 
        FROM leave_request lr
        LEFT JOIN leave_types lt ON lr.leave_type = lt.id 
        WHERE lr.user_id = ? 
        AND lr.start_date <= ? 
        AND lr.end_date >= ? 
        AND LOWER(lr.status) = 'approved'
    ");
    $stmtLeave->execute([$userId, $endDate, $startDate]);
    $leaves = $stmtLeave->fetchAll(PDO::FETCH_ASSOC);
    
    $leaveData = [];
    foreach($leaves as $leave) {
        $lStart = strtotime($leave['start_date']);
        $lEnd = strtotime($leave['end_date']);
        
        $modifier = "";
        $ltNameLower = strtolower($leave['leave_name'] ?? '');
        $durTypeLower = strtolower($leave['duration_type'] ?? '');
        $dayTypeLower = strtolower($leave['day_type'] ?? '');
        
        // Handle Half Days
        if (strpos($ltNameLower, 'half') !== false || $durTypeLower === 'half_day' || strpos($durTypeLower, 'half') !== false) {
            $half = "Half Day";
            if ($durTypeLower === 'first_half' || strpos($dayTypeLower, 'first') !== false) {
                $half = "First Half";
            } else if ($durTypeLower === 'second_half' || strpos($dayTypeLower, 'second') !== false) {
                $half = "Second Half";
            }
            $modifier = " - {$half}";
        } 
        // Handle Short Leaves
        else if (strpos($ltNameLower, 'short') !== false || $durTypeLower === 'short') {
            if (!empty($leave['time_from']) && !empty($leave['time_to'])) {
                $fromFormat = date("h:i A", strtotime($leave['time_from']));
                $toFormat = date("h:i A", strtotime($leave['time_to']));
                $modifier = " ({$fromFormat} to {$toFormat})";
            }
        }
        
        $leaveText = ($leave['leave_name'] ?? 'On Leave') . $modifier;
        
        for($current = $lStart; $current <= $lEnd; $current += 86400) {
            $dDate = date('Y-m-d', $current);
            $leaveData[$dDate] = $leaveText;
        }
    }
    
    // 4. Fetch User Shifts (Week Offs)
    $stmtShifts = $pdo->prepare("
        SELECT weekly_offs, effective_from, effective_to 
        FROM user_shifts 
        WHERE user_id = ? 
        AND effective_from <= ? 
        AND (effective_to >= ? OR effective_to IS NULL)
    ");
    $stmtShifts->execute([$userId, $endDate, $startDate]);
    $shifts = $stmtShifts->fetchAll(PDO::FETCH_ASSOC);

    $weekOffData = [];
    if (count($shifts) > 0) {
        foreach ($shifts as $shift) {
            $weeklyOffsArr = !empty($shift['weekly_offs']) ? array_map('trim', explode(',', $shift['weekly_offs'])) : [];
            $fromTime = strtotime($shift['effective_from']);
            $toTime = empty($shift['effective_to']) ? strtotime('9999-12-31') : strtotime($shift['effective_to']);
            
            $monthStart = strtotime($startDate);
            $monthEnd = strtotime($endDate);
            
            $calcStart = max($fromTime, $monthStart);
            $calcEnd = min($toTime, $monthEnd);
            
            for ($current = $calcStart; $current <= $calcEnd; $current += 86400) {
                $dDate = date('Y-m-d', $current);
                $dayName = date('l', $current); // 'Monday', 'Tuesday', etc.
                if (in_array($dayName, $weeklyOffsArr)) {
                    $weekOffData[$dDate] = true;
                }
            }
        }
    } else {
        // Fallback to Saturday/Sunday if no shifts configured
        $calcStart = strtotime($startDate);
        $calcEnd = strtotime($endDate);
        for ($current = $calcStart; $current <= $calcEnd; $current += 86400) {
            $dayName = date('l', $current);
            if ($dayName === 'Saturday' || $dayName === 'Sunday') {
                $weekOffData[date('Y-m-d', $current)] = true;
            }
        }
    }
    
    $reports = [];
    $todayStr = date('Y-m-d');
    
    for ($i = 1; $i <= $daysInMonth; $i++) {
        $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $i);
        $displayDate = date('d M Y', strtotime($dateStr));
        $displayDay = date('l', strtotime($dateStr));
        
        $reportText = '';
        
        if (isset($attendanceData[$dateStr]) && trim($attendanceData[$dateStr] ?? '') !== '') {
             $reportContent = htmlspecialchars($attendanceData[$dateStr]);
             $reportText = '<div style="display: flex; align-items: flex-start; gap: 8px;"><i data-lucide="check-circle-2" style="width: 16px; height: 16px; color: #10b981; flex-shrink: 0; margin-top: 2px;"></i><span style="color: #3f3f46;">' . $reportContent . '</span></div>';
        } 
        else if (isset($leaveData[$dateStr])) {
             $reportText = '<div style="display: flex; align-items: center; gap: 6px; color: #eab308; font-weight: 500;"><i data-lucide="plane" style="width: 16px; height: 16px;"></i> ' . htmlspecialchars($leaveData[$dateStr]) . '</div>';
        }
        else if (isset($holidayData[$dateStr])) {
             $reportText = '<div style="display: flex; align-items: center; gap: 6px; color: #10b981; font-weight: 500;"><i data-lucide="party-popper" style="width: 16px; height: 16px;"></i> ' . htmlspecialchars($holidayData[$dateStr]) . '</div>';
        } 
        else if (isset($weekOffData[$dateStr])) { 
             $reportText = '<div style="display: flex; align-items: center; gap: 6px; color: #a3a3a3; font-style: italic;"><i data-lucide="coffee" style="width: 16px; height: 16px;"></i> Week Off</div>';
        }
        else if ($dateStr > $todayStr) {
             $reportText = '<div style="display: flex; align-items: center; gap: 6px; color: #9ca3af; font-weight: 500;"><i data-lucide="clock" style="width: 16px; height: 16px;"></i> Upcoming</div>';
        }
        else if (isset($attendanceData[$dateStr])) { // User punched in, but empty work report
             $reportText = '<div style="display: flex; align-items: center; gap: 6px; color: #f97316; font-weight: 500;"><i data-lucide="alert-circle" style="width: 16px; height: 16px;"></i> No Report Submitted</div>';
        }
        else {
             $reportText = '<div style="display: flex; align-items: center; gap: 6px; color: #ef4444; font-weight: 500;"><i data-lucide="x-circle" style="width: 16px; height: 16px;"></i> Absent / Missed Punch</div>';
        }
        
        $reports[] = [
            'date' => $displayDate,
            'day' => $displayDay,
            'report' => $reportText
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $reports]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch reports: ' . $e->getMessage()]);
}
?>
