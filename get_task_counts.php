<?php
session_start();
require_once 'config.php';

try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
            COUNT(*) as total
        FROM tasks
    ");
    
    $stmt->execute();
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($counts);
} catch (PDOException $e) {
    error_log("Error getting task counts: " . $e->getMessage());
    echo json_encode([
        'pending' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'total' => 0
    ]);
} 