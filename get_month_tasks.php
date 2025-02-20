<?php
session_start();
require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);

try {
    $stmt = $pdo->prepare("
        SELECT 
            t.*, 
            u.username as assigned_to_name
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.due_date BETWEEN ? AND ?
        ORDER BY t.due_date, t.due_time
    ");
    
    $stmt->execute([
        $data['start_date'],
        $data['end_date']
    ]);
    
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($tasks);
} catch (PDOException $e) {
    error_log("Error fetching tasks: " . $e->getMessage());
    echo json_encode([]);
} 