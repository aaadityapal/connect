<?php
// Start session and include necessary files
session_start();
require_once '../../config/db_connect.php';

// Set header to return JSON response
header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Check if it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Get POST data
$postData = json_decode(file_get_contents('php://input'), true);

if (!isset($postData['file_id']) || !isset($postData['reason'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit();
}

$fileId = intval($postData['file_id']);
$reason = trim($postData['reason']);
$userId = $_SESSION['user_id'];
$currentDateTime = date('Y-m-d H:i:s');

try {
    // Start transaction
    $conn->begin_transaction();

    // Update substage_files table with all relevant columns
    $updateFileQuery = "UPDATE substage_files 
                       SET status = 'rejected',
                           updated_at = ?,
                           last_modified_at = ?,
                           last_modified_by = ?,
                           updated_by = ?,
                           rejection_reason = ?
                       WHERE id = ? 
                       AND deleted_at IS NULL";
    
    $stmt = $conn->prepare($updateFileQuery);
    $stmt->bind_param("ssiisi", 
        $currentDateTime,    // updated_at
        $currentDateTime,    // last_modified_at
        $userId,            // last_modified_by
        $userId,            // updated_by
        $reason,            // rejection_reason
        $fileId             // id
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update file status");
    }

    // Get substage information
    $getSubstageQuery = "SELECT sf.substage_id, sf.file_name, ps.project_id 
                        FROM substage_files sf
                        JOIN project_substages ps ON sf.substage_id = ps.id
                        WHERE sf.id = ? AND sf.deleted_at IS NULL";
    $stmt = $conn->prepare($getSubstageQuery);
    $stmt->bind_param("i", $fileId);
    $stmt->execute();
    $result = $stmt->get_result();
    $fileData = $result->fetch_assoc();

    if (!$fileData) {
        throw new Exception("File not found or already deleted");
    }

    // Update substage status
    $updateSubstageQuery = "UPDATE project_substages 
                           SET status = 'in_progress',
                               updated_at = ?,
                               last_modified_at = ?,
                               last_modified_by = ?
                           WHERE id = ?";
    
    $stmt = $conn->prepare($updateSubstageQuery);
    $stmt->bind_param("ssii", 
        $currentDateTime,
        $currentDateTime,
        $userId,
        $fileData['substage_id']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update substage status");
    }

    // Create notification
    $notificationQuery = "INSERT INTO notifications 
                         (user_id, type, message, related_id, project_id, created_at) 
                         VALUES (?, 'file_rejected', ?, ?, ?, ?)";
    
    $message = "File '{$fileData['file_name']}' has been rejected. Reason: {$reason}";
    
    $stmt = $conn->prepare($notificationQuery);
    $stmt->bind_param("isiii", 
        $userId,
        $message,
        $fileId,
        $fileData['project_id'],
        $currentDateTime
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create notification");
    }

    // Create file history record
    $historyQuery = "INSERT INTO file_history 
                    (file_id, action, action_by, reason, created_at) 
                    VALUES (?, 'rejected', ?, ?, ?)";
    
    $stmt = $conn->prepare($historyQuery);
    $stmt->bind_param("iiss", 
        $fileId,
        $userId,
        $reason,
        $currentDateTime
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create file history record");
    }

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'File rejected successfully',
        'data' => [
            'file_id' => $fileId,
            'status' => 'rejected',
            'rejected_at' => $currentDateTime,
            'substage_id' => $fileData['substage_id'],
            'file_name' => $fileData['file_name']
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Error in reject_file.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to reject file: ' . $e->getMessage()
    ]);
}

// Close database connection
$conn->close(); 