<?php
require_once 'config.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            CASE 
                WHEN a.punch_in IS NOT NULL AND a.punch_out IS NULL THEN 'active'
                ELSE 'inactive'
            END as status
        FROM users u
        LEFT JOIN attendance a ON u.id = a.user_id AND a.date = ?
        WHERE u.role = 'employee'
    ");
    $stmt->execute([$today]);
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($statuses);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error']);
}
