<?php
require_once 'config/db_connect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            lr.id,
            lr.user_id,
            lt.name as leave_type,
            lt.color_code,
            lr.start_date,
            lr.end_date,
            lr.reason,
            lr.status,
            lr.created_at,
            u.username,
            u.profile_picture,
            COALESCE(u.designation, 'Not Assigned') as designation
        FROM leave_request lr
        JOIN users u ON lr.user_id = u.id
        JOIN leave_types lt ON lr.leave_type = lt.id
        WHERE lr.status = 'pending'
        ORDER BY lr.created_at DESC
    ");
    $stmt->execute();
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'count' => count($leaves),
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
    error_log("Error fetching pending leaves: " . $e->getMessage());
} 