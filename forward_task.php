<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if it's a POST request with JSON content
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['CONTENT_TYPE']) || 
    strpos($_SERVER['CONTENT_TYPE'], 'application/json') === false) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method or content type']);
    exit;
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['type']) || !isset($data['id']) || 
    !isset($data['userId']) || !isset($data['status']) || !isset($data['task_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    $type = $data['type'];
    $entityId = $data['id'];
    $status = $data['status'];
    $newUserId = $data['userId'];
    $message = $data['message'] ?? '';
    $currentUserId = $_SESSION['user_id'];
    $currentDateTime = date('Y-m-d H:i:s');
    $taskId = $data['task_id'];

    // Get the new user's name
    $userQuery = "SELECT username FROM users WHERE id = ?";
    $stmt = $conn->prepare($userQuery);
    $stmt->bind_param('i', $newUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $newUsername = $userData['username'];

    // Update assignment and status in the appropriate table
    if ($type === 'substage') {
        // First, get the stage_id for this substage
        $stageQuery = "SELECT stage_id FROM task_substages WHERE id = ?";
        $stmt = $conn->prepare($stageQuery);
        $stmt->bind_param('i', $entityId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stageId = $result->fetch_assoc()['stage_id'];

        // Update both stage and substage
        $updateStageQuery = "UPDATE task_stages 
                           SET assignee_id = ?, 
                               status = 'pending',
                               updated_at = ?
                           WHERE id = ?";
        $stmt = $conn->prepare($updateStageQuery);
        $stmt->bind_param('isi', $newUserId, $currentDateTime, $stageId);
        $stmt->execute();

        $updateSubstageQuery = "UPDATE task_substages 
                              SET assignee_id = ?, 
                                  status = 'pending',
                                  updated_at = ?
                              WHERE id = ?";
        $stmt = $conn->prepare($updateSubstageQuery);
        $stmt->bind_param('isi', $newUserId, $currentDateTime, $entityId);
        $stmt->execute();
    } else {
        // Update stage and all its substages
        $updateStageQuery = "UPDATE task_stages 
                           SET assignee_id = ?, 
                               status = 'pending',
                               updated_at = ?
                           WHERE id = ?";
        $stmt = $conn->prepare($updateStageQuery);
        $stmt->bind_param('isi', $newUserId, $currentDateTime, $entityId);
        $stmt->execute();

        // Update all substages of this stage
        $updateSubstagesQuery = "UPDATE task_substages 
                               SET assignee_id = ?, 
                                   status = 'pending',
                                   updated_at = ?
                               WHERE stage_id = ?";
        $stmt = $conn->prepare($updateSubstagesQuery);
        $stmt->bind_param('isi', $newUserId, $currentDateTime, $entityId);
        $stmt->execute();
    }

    // Record in task_status_history with username
    $historyQuery = "INSERT INTO task_status_history 
                    (entity_type, entity_id, old_status, new_status, 
                     changed_by, changed_at, task_id) 
                    VALUES (?, ?, ?, CONCAT('Forwarded to ', ?), ?, STR_TO_DATE(?, '%Y-%m-%d %H:%i:%s'), ?)";
    
    $stmt = $conn->prepare($historyQuery);
    $stmt->bind_param('sisisis', $type, $entityId, $status, 
                     $newUsername, $currentUserId, $currentDateTime, $taskId);
    $stmt->execute();

    // Add pending status record
    $pendingHistoryQuery = "INSERT INTO task_status_history 
                          (entity_type, entity_id, old_status, new_status, 
                           changed_by, changed_at, task_id) 
                          VALUES (?, ?, CONCAT('Forwarded to ', ?), 'pending', ?, STR_TO_DATE(?, '%Y-%m-%d %H:%i:%s'), ?)";
    
    $stmt = $conn->prepare($pendingHistoryQuery);
    $stmt->bind_param('sissis', $type, $entityId, $newUsername, 
                     $currentUserId, $currentDateTime, $taskId);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    // Send success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => ucfirst($type) . ' forwarded successfully',
        'data' => [
            'new_status' => 'pending',
            'assigned_to' => $newUsername,
            'updated_at' => $currentDateTime
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log the error
    error_log("Forward task error: " . $e->getMessage());
    
    // Send error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error forwarding ' . $type . ': ' . $e->getMessage()
    ]);
}

// Close database connection
$conn->close();
?> 