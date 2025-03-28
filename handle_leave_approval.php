<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Ensure request is POST and has JSON content
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['leave_id']) || !isset($data['action']) || !isset($data['reason'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

try {
    // First, check if the leave request exists and is still pending
    $checkStmt = $pdo->prepare("
        SELECT status 
        FROM leave_request 
        WHERE id = ? AND status = 'pending'
    ");
    $checkStmt->execute([$data['leave_id']]);
    
    if (!$checkStmt->fetch()) {
        throw new Exception('Leave request not found or already processed');
    }

    // Prepare the update statement
    $updateStmt = $pdo->prepare("
        UPDATE leave_request 
        SET 
            status = ?,
            manager_approval = ?,
            manager_action_reason = ?,
            manager_action_by = ?,
            manager_action_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");

    // Set status based on action
    $status = $data['action'] === 'accept' ? 'approved' : 'rejected';
    $managerApproval = $data['action'] === 'accept' ? 'accepted' : 'rejected';

    // Execute update
    $success = $updateStmt->execute([
        $status,
        $managerApproval,
        $data['reason'],
        $_SESSION['user_id'],
        $data['leave_id']
    ]);

    if (!$success) {
        throw new Exception('Failed to update leave request');
    }

    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'Leave request ' . ($status === 'approved' ? 'approved' : 'rejected') . ' successfully',
        'status' => $status
    ]);

} catch (Exception $e) {
    // Log the error
    error_log("Leave approval error: " . $e->getMessage());

    // Send error response
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process leave request: ' . $e->getMessage()
    ]);
}
?> 