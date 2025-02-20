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

if (!isset($data['substage_id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$substageId = filter_var($data['substage_id'], FILTER_SANITIZE_NUMBER_INT);
$newStatus = filter_var($data['status'], FILTER_SANITIZE_STRING);
$performedBy = $_SESSION['user_id'] ?? null;

if (!$performedBy) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get current substage status and project_id
    $substageQuery = "SELECT ps.status, ps.project_id, ps.id as stage_id 
                     FROM project_substages pss 
                     JOIN project_stages ps ON ps.id = pss.stage_id 
                     WHERE pss.id = ? AND pss.deleted_at IS NULL";
    $stmt = $conn->prepare($substageQuery);
    $stmt->bind_param('i', $substageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $substageData = $result->fetch_assoc();

    if (!$substageData) {
        throw new Exception('Substage not found');
    }

    $oldStatus = $substageData['status'];
    $projectId = $substageData['project_id'];
    $stageId = $substageData['stage_id'];

    // Update substage status
    $updateQuery = "UPDATE project_substages SET 
                    status = ?, 
                    updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('si', $newStatus, $substageId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('No changes made to substage status');
    }

    // Log the activity
    $description = "Substage status changed from '{$oldStatus}' to '{$newStatus}'";
    $activityType = 'substage_status_update';
    
    $logQuery = "INSERT INTO project_activity_log 
                (project_id, stage_id, substage_id, activity_type, description, performed_by, performed_at) 
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
    
    $stmt = $conn->prepare($logQuery);
    $stmt->bind_param('iiissi', 
        $projectId,
        $stageId,
        $substageId,
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
            'substage_id' => $substageId
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Error updating substage status: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update status: ' . $e->getMessage()
    ]);
}

// Close database connection
$conn->close(); 