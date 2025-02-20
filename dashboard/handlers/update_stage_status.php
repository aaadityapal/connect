<?php
session_start();
require_once '../../config/db_connect.php';

// Set header to return JSON response
header('Content-Type: application/json');

// Check if request is POST and has required data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['stage_id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$stageId = filter_var($data['stage_id'], FILTER_SANITIZE_NUMBER_INT);
$newStatus = filter_var($data['status'], FILTER_SANITIZE_STRING);
$performedBy = $_SESSION['user_id'] ?? null;

if (!$performedBy) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get current stage status and project_id
    $stageQuery = "SELECT status, project_id FROM project_stages WHERE id = ? AND deleted_at IS NULL";
    $stmt = $conn->prepare($stageQuery);
    $stmt->bind_param('i', $stageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stageData = $result->fetch_assoc();

    if (!$stageData) {
        throw new Exception('Stage not found');
    }

    $oldStatus = $stageData['status'];
    $projectId = $stageData['project_id'];

    // Update stage status
    $updateQuery = "UPDATE project_stages SET 
                    status = ?, 
                    updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('si', $newStatus, $stageId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('No changes made to stage status');
    }

    // Log the activity
    $description = "Stage status changed from '{$oldStatus}' to '{$newStatus}'";
    $activityType = 'status_update';
    
    $logQuery = "INSERT INTO project_activity_log 
                (project_id, stage_id, activity_type, description, performed_by, performed_at) 
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
    
    $stmt = $conn->prepare($logQuery);
    $stmt->bind_param('iissi', 
        $projectId,
        $stageId,
        $activityType,
        $description,
        $performedBy
    );
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'data' => [
            'new_status' => $newStatus,
            'stage_id' => $stageId
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Error updating stage status: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update status: ' . $e->getMessage()
    ]);
}

// Close database connection
$conn->close(); 