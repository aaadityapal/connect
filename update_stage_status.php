<?php
session_start();
require_once 'config/db_connect.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['stage_id']) || !isset($data['status'])) {
        throw new Exception('Missing required parameters');
    }

    $query = "UPDATE project_stages 
              SET status = :status, 
                  updated_at = NOW(), 
                  updated_by = :user_id 
              WHERE id = :stage_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'status' => $data['status'],
        'user_id' => $_SESSION['user_id'],
        'stage_id' => $data['stage_id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Stage status updated successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 