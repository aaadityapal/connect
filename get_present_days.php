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
            "SELECT us.weekly_offs, us.effective_from, us.effective_to
             FROM user_shifts us
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

    // Fetch attendance rows where punch_in and punch_out present
    $stmt = $pdo->prepare(
        "SELECT id, user_id, date, punch_in, punch_out, working_hours, overtime_hours, punch_in_photo, punch_out_photo
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
        $records[] = [
            'id' => $r['id'],
            'date' => $d->format('Y-m-d'),
            'displayDate' => $d->format('d-M-Y'),
            'day' => $dayName,
            'punch_in' => $r['punch_in'],
            'punch_out' => $r['punch_out'],
            'working_hours' => $r['working_hours'],
            'overtime_hours' => $r['overtime_hours'],
            'punch_in_photo' => !empty($r['punch_in_photo']) ? $r['punch_in_photo'] : null,
            'punch_out_photo' => !empty($r['punch_out_photo']) ? $r['punch_out_photo'] : null,
            'is_weekly_off' => $isWeeklyOff
        ];
    }

    $monthName = date('F Y', strtotime($firstDayOfMonth));

    echo json_encode([
        'status' => 'success',
        'monthYear' => $monthName,
        'records' => $records,
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
