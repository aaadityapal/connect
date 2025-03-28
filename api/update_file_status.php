<?php
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

// Get the JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['file_id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$fileId = $data['file_id'];
$status = $data['status'];
$userId = $_SESSION['user_id'];

// Validate status
$allowedStatuses = ['pending', 'sent_for_approval', 'approved', 'rejected'];
if (!in_array($status, $allowedStatuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Update file status
    $updateQuery = "UPDATE substage_files 
                   SET status = :status, 
                       updated_by = :user_id,
                       updated_at = NOW()
                   WHERE id = :file_id";
    
    $stmt = $pdo->prepare($updateQuery);
    $stmt->execute([
        'status' => $status,
        'user_id' => $userId,
        'file_id' => $fileId
    ]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('File not found or you do not have permission to update it');
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'File status updated successfully']);
    
} catch (Exception $e) {
    // Roll back transaction on error
    $pdo->rollBack();
    
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 