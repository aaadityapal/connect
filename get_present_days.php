<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// Validate params
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

if ($userId <= 0 || $month < 1 || $month > 12 || $year < 2000) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    // build month range
    $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
    $firstDayOfMonth = "$year-$monthStr-01";
    $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));

    // Fetch the user's applicable weekly offs from user_shifts for this month (if any)
    $weeklyOffs = [];
    try {
        $shiftStmt = $pdo->prepare(
            "SELECT us.weekly_offs, us.effective_from, us.effective_to, s.start_time, s.end_time
             FROM user_shifts us
             LEFT JOIN shifts s ON us.shift_id = s.id
             WHERE us.user_id = ?
             AND (
                 (us.effective_from IS NULL AND us.effective_to IS NULL) OR
                 (us.effective_from <= ? AND (us.effective_to IS NULL OR us.effective_to >= ?))
             )
             ORDER BY us.effective_from DESC
             LIMIT 1"
        );
        $shiftStmt->execute([$userId, $lastDayOfMonth, $firstDayOfMonth]);
        $userShift = $shiftStmt->fetch(PDO::FETCH_ASSOC);
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
            // Normalize day names (e.g., trim and ucwords)
            $weeklyOffs = array_map(function($d){ return trim($d); }, $weeklyOffs);
        }
    } catch (PDOException $e) {
        error_log("Error fetching user shifts for present days: " . $e->getMessage());
        $weeklyOffs = [];
    }

    $halfShiftSeconds = 4.5 * 3600; // default to 4.5 hours
    if (isset($userShift) && !empty($userShift['start_time']) && !empty($userShift['end_time'])) {
        $start = strtotime($userShift['start_time']);
        $end = strtotime($userShift['end_time']);
        $shiftDuration = $end - $start;
        if ($shiftDuration < 0) { $shiftDuration += 24 * 3600; }
        $halfShiftSeconds = $shiftDuration / 2;
    }

    // Fetch attendance rows where punch_in and punch_out present
    $stmt = $pdo->prepare(
        "SELECT id, user_id, date, status, punch_in, punch_out, working_hours, overtime_hours, punch_in_photo, punch_out_photo
         FROM attendance
         WHERE user_id = ?
         AND DATE(date) BETWEEN ? AND ?
         AND punch_in IS NOT NULL AND punch_in != ''
         AND punch_out IS NOT NULL AND punch_out != ''
         ORDER BY date ASC"
    );

    $stmt->execute([$userId, $firstDayOfMonth, $lastDayOfMonth]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $records = [];
    foreach ($rows as $r) {
        $d = new DateTime($r['date']);
        $dayName = $d->format('l');
        $isWeeklyOff = in_array($dayName, $weeklyOffs);
        
        $isHalfDay = ($r['status'] === 'half_day');
        $displayWorkingHours = $r['working_hours'];

        if (!empty($r['punch_in']) && !empty($r['punch_out'])) {
            $inTime = strtotime($r['punch_in']);
            $outTime = strtotime($r['punch_out']);
            if ($inTime !== false && $outTime !== false) {
                $whSecs = $outTime - $inTime;
                if ($whSecs < 0) { $whSecs += 24 * 3600; }
                
                // Override the display working hours with the correctly calculated value
                $whHours = floor($whSecs / 3600);
                $whMins = floor(($whSecs % 3600) / 60);
                $whRemainderSecs = $whSecs % 60;
                $displayWorkingHours = sprintf('%02d:%02d:%02d', $whHours, $whMins, $whRemainderSecs);

                // Dynamically mark as half day if worked less than half shift
                if ($whSecs < $halfShiftSeconds) {
                    $isHalfDay = true;
                }
            }
        }

        $records[] = [
            'id' => $r['id'],
            'date' => $d->format('Y-m-d'),
            'displayDate' => $d->format('d-M-Y'),
            'day' => $dayName,
            'status' => $isHalfDay ? 'half_day' : $r['status'],
            'punch_in' => $r['punch_in'],
            'punch_out' => $r['punch_out'],
            'working_hours' => $displayWorkingHours,
            'overtime_hours' => $r['overtime_hours'],
            'punch_in_photo' => !empty($r['punch_in_photo']) ? $r['punch_in_photo'] : null,
            'punch_out_photo' => !empty($r['punch_out_photo']) ? $r['punch_out_photo'] : null,
            'is_weekly_off' => $isWeeklyOff
        ];
    }

    // Fetch approved leaves for the month
    $leaveStmt = $pdo->prepare("
        SELECT 
            lr.start_date,
            lr.end_date,
            lt.name as leave_type,
            lr.duration
        FROM leave_request lr
        LEFT JOIN leave_types lt ON lr.leave_type = lt.id
        WHERE lr.user_id = ?
        AND lr.status = 'approved'
        AND (
            (MONTH(lr.start_date) = ? AND YEAR(lr.start_date) = ?) OR
            (MONTH(lr.end_date) = ? AND YEAR(lr.end_date) = ?) OR
            (lr.start_date <= ? AND lr.end_date >= ?)
        )
    ");
    $leaveStmt->execute([
        $userId,
        $month, $year,
        $month, $year,
        $lastDayOfMonth, $firstDayOfMonth
    ]);
    $leaves = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

    // Build a map of leaves by date (handles multiple leaves per day)
    $leaveMap = [];
    foreach ($leaves as $lv) {
        $start = new DateTime($lv['start_date']);
        $end = new DateTime($lv['end_date']);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end->modify('+1 day'));

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            // Only add if it's within current month
            if (date('m', strtotime($dateStr)) == $month && date('Y', strtotime($dateStr)) == $year) {
                if (!isset($leaveMap[$dateStr])) {
                    $leaveMap[$dateStr] = [];
                }
                $leaveMap[$dateStr][] = [
                    'type' => $lv['leave_type'],
                    'duration' => floatval($lv['duration'] ?: 1)
                ];
            }
        }
    }

    $monthName = date('F Y', strtotime($firstDayOfMonth));

    echo json_encode([
        'status' => 'success',
        'monthYear' => $monthName,
        'records' => $records,
        'leaves' => $leaveMap,
        'weekly_offs' => $weeklyOffs,
        'count' => count($records)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in get_present_days.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get_present_days.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred']);
    exit;
}
