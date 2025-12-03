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
    // Build month range
    $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
    $firstDayOfMonth = "$year-$monthStr-01";
    $lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));

    // Get user's shift start time (current/applicable shift)
    $shiftStmt = $pdo->prepare(
        "SELECT s.start_time
         FROM user_shifts us
         LEFT JOIN shifts s ON us.shift_id = s.id
         WHERE us.user_id = ?
         AND (
             (us.effective_from IS NULL AND us.effective_to IS NULL) OR
             (us.effective_from <= CURDATE() AND (us.effective_to IS NULL OR us.effective_to >= CURDATE()))
         )
         ORDER BY us.effective_from DESC
         LIMIT 1"
    );
    $shiftStmt->execute([$userId]);
    $userShift = $shiftStmt->fetch(PDO::FETCH_ASSOC);

    if (!$userShift || empty($userShift['start_time'])) {
        echo json_encode([
            'status' => 'success',
            'monthYear' => date('F Y', strtotime($firstDayOfMonth)),
            'records' => [],
            'count' => 0,
            'message' => 'No shift information found for this user'
        ]);
        exit;
    }

    $shiftStartTime = $userShift['start_time'];
    // 1 hour mark for "very late"
    $oneHourLateTime = date('H:i:s', strtotime($shiftStartTime . ' +1 hour'));

    // Fetch attendance records where punch_in is 1+ hour late
    $stmt = $pdo->prepare(
        "SELECT id, user_id, date, punch_in, punch_out, working_hours, overtime_hours, punch_in_photo
         FROM attendance
         WHERE user_id = ?
         AND DATE(date) BETWEEN ? AND ?
         AND punch_in IS NOT NULL AND punch_in != ''
         AND TIME(punch_in) >= ?
         ORDER BY date ASC"
    );

    $stmt->execute([$userId, $firstDayOfMonth, $lastDayOfMonth, $oneHourLateTime]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $records = [];
    foreach ($rows as $r) {
        $d = new DateTime($r['date']);
        $dayName = $d->format('l');
        $displayDate = $d->format('d-M-Y');

        // Parse punch_in time
        $punchInTime = new DateTime($r['punch_in']);
        $punchInFormatted = $punchInTime->format('H:i:s');

        // Calculate lateness in minutes
        $minutesLate = intval((strtotime($punchInFormatted) - strtotime($shiftStartTime)) / 60);

        $records[] = [
            'id' => $r['id'],
            'date' => $r['date'],
            'displayDate' => $displayDate,
            'day' => $dayName,
            'shift_start_time' => $shiftStartTime,
            'punch_in' => $punchInFormatted,
            'minutes_late' => $minutesLate,
            'punch_in_photo' => !empty($r['punch_in_photo']) ? $r['punch_in_photo'] : null,
        ];
    }

    $monthName = date('F Y', strtotime($firstDayOfMonth));

    echo json_encode([
        'status' => 'success',
        'monthYear' => $monthName,
        'shift_start_time' => $shiftStartTime,
        'records' => $records,
        'count' => count($records)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error in get_one_hour_late_days.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get_one_hour_late_days.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An error occurred']);
    exit;
}
?>
