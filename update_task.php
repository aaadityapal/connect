<?php
session_start();
require_once 'config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $_SESSION['user_id'];
    
    // Validate input
    if (!isset($data['task_id'], $data['status'], $data['progress'])) {
        throw new Exception('Missing required fields');
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Get current status before update
    $getCurrentStatus = "SELECT status FROM tasks WHERE id = ?";
    $stmt = $conn->prepare($getCurrentStatus);
    $stmt->bind_param('i', $data['task_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentStatus = $result->fetch_assoc()['status'];
    
    // Update task
    $query = "
        UPDATE tasks 
        SET status = ?, progress = ?, last_updated = NOW()
        WHERE id = ? AND assigned_to = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('siii', 
        $data['status'],
        $data['progress'],
        $data['task_id'],
        $user_id
    );
    $stmt->execute();
    
    // Log status change if status has changed
    if ($currentStatus !== $data['status']) {
        $logQuery = "
            INSERT INTO task_status_history 
            (entity_type, entity_id, old_status, new_status, changed_by, changed_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $conn->prepare($logQuery);
        $entityType = 'task'; // or 'stage' or 'substage' depending on your needs
        $stmt->bind_param('sissi',
            $entityType,
            $data['task_id'],
            $currentStatus,
            $data['status'],
            $user_id
        );
        $stmt->execute();
    }
    
    // Add comment if provided
    if (!empty($data['comments'])) {
        $query = "
            INSERT INTO task_comments (task_id, user_id, comment, created_at)
            VALUES (?, ?, ?, NOW())
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iis',
            $data['task_id'],
            $user_id,
            $data['comments']
        );
        $stmt->execute();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Task updated successfully'
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update task: ' . $e->getMessage()
    ]);
}

// Function to get status history
function getStatusHistory($conn, $entityId, $entityType = 'task') {
    $query = "
        SELECT 
            tsh.*,
            u.name as changed_by_name,
            u.role as changed_by_role
        FROM task_status_history tsh
        LEFT JOIN users u ON tsh.changed_by = u.id
        WHERE tsh.entity_type = ? 
        AND tsh.entity_id = ?
        ORDER BY tsh.changed_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $entityType, $entityId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'old_status' => $row['old_status'],
            'new_status' => $row['new_status'],
            'changed_by' => $row['changed_by_name'],
            'changed_by_role' => $row['changed_by_role'],
            'changed_at' => $row['changed_at']
        ];
    }
    
    return $history;
}
?> 