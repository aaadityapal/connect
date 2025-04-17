<?php
/**
 * API endpoint to update file status to sent_for_approval 
 * when sent to a Senior Manager for approval
 */

session_start();
require_once 'config/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

// Handle different operations based on the data provided
if (isset($data['file_id']) && isset($data['manager_id'])) {
    // This is a request to send a file for approval
    $fileId = intval($data['file_id']);
    $managerId = intval($data['manager_id']);
    $userId = $_SESSION['user_id'];

    try {
        // Check connection before proceeding
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        
        // Check if required columns exist, and create them if they don't
        $checkColumnsQuery = "SHOW COLUMNS FROM substage_files LIKE 'sent_to'";
        $columnResult = $conn->query($checkColumnsQuery);
        
        if ($columnResult->num_rows === 0) {
            // Column doesn't exist, create it
            $alterTableQuery = "ALTER TABLE substage_files 
                               ADD COLUMN sent_to INT DEFAULT NULL,
                               ADD COLUMN sent_by INT DEFAULT NULL,
                               ADD COLUMN sent_at DATETIME DEFAULT NULL";
            
            if (!$conn->query($alterTableQuery)) {
                throw new Exception('Failed to update database structure: ' . $conn->error);
            }
        }
        
        // Begin transaction
        $conn->begin_transaction();
        
        // First verify the file exists
        $checkFileQuery = "SELECT id FROM substage_files WHERE id = ?";
        $checkFileStmt = $conn->prepare($checkFileQuery);
        $checkFileStmt->bind_param("i", $fileId);
        $checkFileStmt->execute();
        $checkFileResult = $checkFileStmt->get_result();
        
        if ($checkFileResult->num_rows === 0) {
            throw new Exception('File not found');
        }
        
        // Update file status - use a simpler query first to troubleshoot
        $updateQuery = "UPDATE substage_files SET status = 'sent_for_approval' WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        
        if (!$updateStmt) {
            throw new Exception('Prepare statement failed: ' . $conn->error);
        }
        
        $updateStmt->bind_param("i", $fileId);
        $updateStmt->execute();
        
        // Now try to update the other fields
        $updateDetailQuery = "UPDATE substage_files 
                             SET sent_to = ?, 
                                 sent_by = ?, 
                                 sent_at = NOW() 
                             WHERE id = ?";
        
        $updateDetailStmt = $conn->prepare($updateDetailQuery);
        
        if (!$updateDetailStmt) {
            throw new Exception('Prepare detail statement failed: ' . $conn->error);
        }
        
        $updateDetailStmt->bind_param("iii", $managerId, $userId, $fileId);
        $updateDetailStmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'File sent for approval successfully',
            'file_id' => $fileId,
            'status' => 'sent_for_approval'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn && $conn->ping()) {
            try {
                $conn->rollback();
            } catch (Exception $rollbackError) {
                // Ignore rollback errors
            }
        }
        
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
        exit();
    }
} else if (isset($data['fileId']) && isset($data['status'])) {
    // This is the existing code for updating status
    $fileId = $data['fileId'];
    $status = $data['status'];
    $comment = $data['comment'] ?? '';

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update file status
        $query = "UPDATE substage_files 
                  SET status = ?, review_comment = ?, reviewed_at = NOW() 
                  WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $status, $comment, $fileId);
        $stmt->execute();

        // The rest of the logic for substage completion
        // ... (abbreviated for brevity)

        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Error updating status: ' . $e->getMessage()
        ]);
    }
} else {
    // Invalid request
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request parameters'
    ]);
    exit();
} 