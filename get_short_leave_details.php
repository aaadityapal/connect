<?php
require_once 'config/db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT 
            lr.id,
            lr.user_id,
            lr.start_date,
            lr.end_date,
            lr.reason,
            u.username,
            u.profile_picture,
            u.designation,
            lt.name as leave_type,
            lt.color_code
        FROM leave_request lr
        JOIN users u ON lr.user_id = u.id
        JOIN leave_types lt ON lr.leave_type = lt.id
        WHERE lr.status = 'approved'
        AND lt.name = 'Short Leave'
        AND ? BETWEEN lr.start_date AND lr.end_date
        AND u.status = 'active'
        ORDER BY lr.created_at DESC
    ");
    $stmt->execute([$today]);
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total active users count
    $total_query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
    $total_stmt = $pdo->prepare($total_query);
    $total_stmt->execute();
    $total_result = $total_stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'count' => count($leaves),
        'total_users' => $total_result['total'],
        'leaves' => $leaves
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'count' => 0,
        'leaves' => []
    ]);
} 