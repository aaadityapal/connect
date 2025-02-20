<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all errors to a file
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

session_start();
require_once '../../config/db_connect.php';

// Log incoming request data
$input = file_get_contents('php://input');
error_log("Received data: " . $input);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['file_id']) || !isset($data['action'])) {
        throw new Exception('Missing required parameters');
    }

    $fileId = (int)$data['file_id'];
    $action = strtolower($data['action']);
    
    // Validate action
    if (!in_array($action, ['approve', 'reject'])) {
        throw new Exception('Invalid action');
    }

    // Update the file status
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $query = "UPDATE substage_files 
              SET status = ?, 
                  updated_at = NOW(), 
                  updated_by = ? 
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sii', $status, $_SESSION['user_id'], $fileId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update file status');
    }

    // Get the substage_id for this file
    $substageQuery = "SELECT substage_id FROM substage_files WHERE id = ?";
    $stmt = $conn->prepare($substageQuery);
    $stmt->bind_param('i', $fileId);
    $stmt->execute();
    $result = $stmt->get_result();
    $substageData = $result->fetch_assoc();
    $substageId = $substageData['substage_id'];

    // Check if all files in this substage are approved
    $checkFilesQuery = "SELECT 
                           COUNT(*) as total_files,
                           SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_files
                       FROM substage_files 
                       WHERE substage_id = ?";
    
    $stmt = $conn->prepare($checkFilesQuery);
    $stmt->bind_param('i', $substageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $fileStats = $result->fetch_assoc();

    // Important: Directly update the substage status if all files are approved
    if ($fileStats['total_files'] > 0 && $fileStats['total_files'] == $fileStats['approved_files']) {
        // Update the main substage status
        $updateMainStatusQuery = "UPDATE project_substages 
                                SET status = 'completed',
                                    updated_at = NOW(),
                                    updated_by = ?
                                WHERE id = ?";
        
        $stmt = $conn->prepare($updateMainStatusQuery);
        $stmt->bind_param('ii', $_SESSION['user_id'], $substageId);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update substage status');
        }

        // Also update the stage_status in the stages table
        $updateStageStatusQuery = "UPDATE project_stages ps
                                 SET ps.status = CASE 
                                     WHEN (SELECT COUNT(*) 
                                          FROM project_substages 
                                          WHERE stage_id = ps.id 
                                          AND status != 'completed') = 0 
                                     THEN 'completed'
                                     ELSE ps.status
                                 END
                                 WHERE id = (SELECT stage_id 
                                           FROM project_substages 
                                           WHERE id = ?)";
        
        $stmt = $conn->prepare($updateStageStatusQuery);
        $stmt->bind_param('i', $substageId);
        $stmt->execute();
    }

    $conn->commit();

    // Get the current status for the response
    $getCurrentStatusQuery = "SELECT status FROM project_substages WHERE id = ?";
    $stmt = $conn->prepare($getCurrentStatusQuery);
    $stmt->bind_param('i', $substageId);
    $stmt->execute();
    $currentStatus = $stmt->get_result()->fetch_assoc()['status'];

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'file_status' => $status,
        'substage_status' => $currentStatus,
        'all_files_approved' => ($fileStats['total_files'] == $fileStats['approved_files']),
        'substage_id' => $substageId
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("Error in update_file_status.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}