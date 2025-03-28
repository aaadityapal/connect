<?php
require_once 'config/db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get date from query parameter
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

try {
    // Get present count
    $present_stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM attendance
        WHERE date = ?
        AND punch_in IS NOT NULL
    ");
    $present_stmt->execute([$date]);
    $present_count = $present_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get leave count
    $leave_stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM leave_request lr
        JOIN leave_types lt ON lr.leave_type = lt.id
        WHERE lr.status = 'approved'
        AND ? BETWEEN lr.start_date AND lr.end_date
    ");
    $leave_stmt->execute([$date]);
    $leave_count = $leave_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'present_count' => $present_count,
        'leave_count' => $leave_count,
        'date' => $date
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
} 