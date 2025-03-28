<?php
require_once 'config/db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    
    // Get first and last day of month
    $firstDay = date('Y-m-d', strtotime("$year-$month-01"));
    $lastDay = date('Y-m-t', strtotime($firstDay));
    
    // Fetch attendance data with count
    $attendanceStmt = $pdo->prepare("
        SELECT 
            DATE(date) as date, 
            COUNT(DISTINCT user_id) as present_count
        FROM attendance
        WHERE date BETWEEN ? AND ?
        AND punch_in IS NOT NULL
        GROUP BY DATE(date)
    ");
    $attendanceStmt->execute([$firstDay, $lastDay]);
    $attendance = $attendanceStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Fetch leave data with count
    $leaveStmt = $pdo->prepare("
        SELECT 
            DATE(start_date) as date,
            COUNT(DISTINCT user_id) as leave_count
        FROM leave_request
        WHERE status = 'approved'
        AND (
            (start_date BETWEEN ? AND ?) OR
            (end_date BETWEEN ? AND ?) OR
            (start_date <= ? AND end_date >= ?)
        )
        GROUP BY DATE(start_date)
    ");
    $leaveStmt->execute([$firstDay, $lastDay, $firstDay, $lastDay, $firstDay, $lastDay]);
    $leaves = $leaveStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Fetch holidays
    $holidayStmt = $pdo->prepare("
        SELECT date, name
        FROM holidays
        WHERE date BETWEEN ? AND ?
    ");
    $holidayStmt->execute([$firstDay, $lastDay]);
    $holidays = $holidayStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    echo json_encode([
        'success' => true,
        'attendance' => $attendance,
        'leaves' => $leaves,
        'holidays' => $holidays
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
} 