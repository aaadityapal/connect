<?php
// Save construction site task to database
header('Content-Type: application/json');
session_start(); // Start session to access $_SESSION

try {
    // Include database connection
    require_once '../config/db_connect.php';
    
    if (!isset($pdo)) {
        throw new Exception('Database connection not available');
    }
    
    // Get JSON data from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        $data = $_POST; // Fallback to POST data
    }
    
    // Validate required fields
    if (empty($data['title']) || empty($data['start_date']) || empty($data['end_date'])) {
        throw new Exception('Missing required fields: title, start_date, end_date');
    }
    
    // Validate project_id
    if (empty($data['project_id'])) {
        throw new Exception('Project ID is required');
    }
    
    // Get user ID from session
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        throw new Exception('User not authenticated');
    }
    
    $createdBy = $userId;
    $updatedBy = $userId;
    
    // Get assigned user ID if assignee username is provided
    $assignedUserId = null;
    if (!empty($data['assign_to'])) {
        $userQuery = "SELECT id FROM users WHERE username = ? AND status = 'active' AND deleted_at IS NULL LIMIT 1";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute([$data['assign_to']]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        $assignedUserId = $user['id'] ?? null;
    }
    
    // Handle images
    $images = null;
    if (!empty($data['images'])) {
        if (is_array($data['images'])) {
            $images = json_encode($data['images']);
        } else {
            $images = $data['images'];
        }
    }
    
    if (!empty($data['id'])) {
        // Update existing task
        $query = "UPDATE construction_site_tasks 
                  SET title = ?, 
                      description = ?, 
                      start_date = ?, 
                      end_date = ?, 
                      status = ?, 
                      assign_to = ?,
                      assigned_user_id = ?,
                      images = ?,
                      updated_by = ?,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE id = ? AND deleted_at IS NULL";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['start_date'],
            $data['end_date'],
            $data['status'] ?? 'planned',
            $data['assign_to'] ?? null,
            $assignedUserId,
            $images,
            $updatedBy,
            $data['id']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Task updated successfully',
            'task_id' => $data['id']
        ]);
    } else {
        // Create new task
        $query = "INSERT INTO construction_site_tasks 
                  (project_id, title, description, start_date, end_date, status, assign_to, assigned_user_id, images, created_by, updated_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $data['project_id'] ?? null,
            $data['title'],
            $data['description'] ?? null,
            $data['start_date'],
            $data['end_date'],
            $data['status'] ?? 'planned',
            $data['assign_to'] ?? null,
            $assignedUserId,
            $images,
            $createdBy,
            $updatedBy
        ]);
        
        $taskId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Task created successfully',
            'task_id' => $taskId
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
