<?php
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $month = isset($_GET['month']) ? $_GET['month'] : date('m');
    $year = isset($_GET['year']) ? $_GET['year'] : date('Y');

    // Fetch holidays for the entire month
    $holidayStmt = $pdo->prepare("
        SELECT holiday_date, holiday_name 
        FROM office_holidays 
        WHERE DATE_FORMAT(holiday_date, '%Y-%m') = ?
    ");
    $holidayStmt->execute(["$year-$month"]);
    $holidays = $holidayStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch attendance data for the specific date
    $attendanceStmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN punch_in IS NOT NULL THEN user_id END) as present_count,
            COUNT(DISTINCT CASE WHEN status = 'on_leave' THEN user_id END) as leave_count
        FROM attendance 
        WHERE DATE(date) = ?
    ");
    $attendanceStmt->execute([$date]);
    $attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'holidays' => $holidays,
            'attendance' => $attendance
        ]
    ]);

} catch (Exception $e) {
    error_log("Calendar data error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch calendar data'
    ]);
}
?> 