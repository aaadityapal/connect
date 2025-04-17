<?php
/**
 * API endpoint to update a task's status
 */

session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);
$taskId = isset($data['task_id']) ? intval($data['task_id']) : 0;
$status = isset($data['status']) ? $data['status'] : '';

// Validate required parameters
if (!$taskId || empty($status)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Validate status value
$validStatuses = ['pending', 'in_progress', 'completed', 'not_started'];
if (!in_array($status, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit();
}

// Get user ID from session
$userId = $_SESSION['user_id'];

try {
    // Verify task exists and user has permission
    $checkTaskQuery = "SELECT t.id, t.assigned_to, s.assigned_to AS stage_assigned_to, 
                        ss.assigned_to AS substage_assigned_to, p.assigned_to AS project_assigned_to
                      FROM tasks t
                      LEFT JOIN project_stages s ON t.stage_id = s.id
                      LEFT JOIN project_substages ss ON t.substage_id = ss.id
                      LEFT JOIN projects p ON s.project_id = p.id
                      WHERE t.id = ? AND t.deleted_at IS NULL";
    
    $checkTaskStmt = $conn->prepare($checkTaskQuery);
    $checkTaskStmt->bind_param("i", $taskId);
    $checkTaskStmt->execute();
    $checkTaskResult = $checkTaskStmt->get_result();
    
    if ($checkTaskResult->num_rows === 0) {
        throw new Exception('Task not found');
    }
    
    $taskData = $checkTaskResult->fetch_assoc();
    
    // Check if user has permission to update this task
    $hasPermission = 
        $userId == $taskData['assigned_to'] || 
        $userId == $taskData['stage_assigned_to'] || 
        $userId == $taskData['substage_assigned_to'] || 
        $userId == $taskData['project_assigned_to'];
    
    if (!$hasPermission) {
        throw new Exception('You do not have permission to update this task');
    }
    
    // Update task status
    $updateTaskQuery = "UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?";
    $updateTaskStmt = $conn->prepare($updateTaskQuery);
    $updateTaskStmt->bind_param("si", $status, $taskId);
    $updateTaskStmt->execute();
    
    if ($updateTaskStmt->affected_rows === 0) {
        throw new Exception('Failed to update task status');
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Task status updated successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}