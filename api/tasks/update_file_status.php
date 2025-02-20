<?php
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['file_id']) || !isset($data['status'])) {
        throw new Exception('Missing required parameters');
    }

    $fileId = intval($data['file_id']);
    $newStatus = strtoupper($data['status']);
    $userId = $_SESSION['user_id'] ?? 0; // Ensure you have user session management
    
    // First get the current record
    $query = "SELECT * FROM task_status_history WHERE id = :file_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['file_id' => $fileId]);
    $currentRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentRecord) {
        throw new Exception('File record not found');
    }

    // Insert new status history record
    $query = "INSERT INTO task_status_history 
              (entity_type, entity_id, old_status, new_status, changed_by, task_id, file_path) 
              VALUES 
              (:entity_type, :entity_id, :old_status, :new_status, :changed_by, :task_id, :file_path)";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'entity_type' => $currentRecord['entity_type'],
        'entity_id' => $currentRecord['entity_id'],
        'old_status' => $currentRecord['new_status'], // Current status becomes old status
        'new_status' => $newStatus,
        'changed_by' => $userId,
        'task_id' => $currentRecord['task_id'],
        'file_path' => $currentRecord['file_path']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 