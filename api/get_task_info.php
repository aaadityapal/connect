<?php
require_once '../config/db_connect.php';

function getTaskAndSubstageInfo($taskId, $substageId) {
    global $conn;
    
    try {
        // Get task information
        $taskStmt = $conn->prepare("
            SELECT id, title, status_id, task_type 
            FROM tasks 
            WHERE id = ?
        ");
        $taskStmt->bind_param("i", $taskId);
        $taskStmt->execute();
        $taskResult = $taskStmt->get_result();
        
        if ($taskResult->num_rows === 0) {
            throw new Exception('Task not found');
        }
        
        // Get substage information
        $substageStmt = $conn->prepare("
            SELECT id, stage_id, status, assignee_id 
            FROM task_substages 
            WHERE id = ?
        ");
        $substageStmt->bind_param("i", $substageId);
        $substageStmt->execute();
        $substageResult = $substageStmt->get_result();
        
        if ($substageResult->num_rows === 0) {
            throw new Exception('Substage not found');
        }
        
        $taskData = $taskResult->fetch_assoc();
        $substageData = $substageResult->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'task' => $taskData,
            'substage' => $substageData
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

if (isset($_GET['task_id']) && isset($_GET['substage_id'])) {
    getTaskAndSubstageInfo($_GET['task_id'], $_GET['substage_id']);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Missing task_id or substage_id'
    ]);
}
?> 