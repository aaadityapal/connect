<?php
require_once 'config/db_connect.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $query = "SELECT 
                ft.id,
                ft.project_id,
                ft.type,
                ft.stage_id,
                ft.substage_id,
                ft.status,
                ft.created_at,
                p.title as project_title,
                s.title as stage_title,
                ss.title as substage_title,
                u.name as forwarded_by_name
              FROM forwarded_tasks ft
              JOIN projects p ON ft.project_id = p.id
              LEFT JOIN stages s ON ft.stage_id = s.id
              LEFT JOIN substages ss ON ft.substage_id = ss.id
              JOIN users u ON ft.forwarded_by = u.id
              WHERE ft.forwarded_to = ?
              ORDER BY ft.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'tasks' => $tasks
    ]);
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching forwarded tasks'
    ]);
} 