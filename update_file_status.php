<?php
require_once 'config/db_connect.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
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

    // Get substage_id for this file
    $query = "SELECT substage_id FROM substage_files WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $fileId);
    $stmt->execute();
    $result = $stmt->get_result();
    $substage_id = $result->fetch_assoc()['substage_id'];

    // Check if all files in this substage are approved
    $query = "SELECT COUNT(*) as total, 
              SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
              FROM substage_files 
              WHERE substage_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $substage_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $counts = $result->fetch_assoc();

    // If all files are approved, update substage status to completed
    if ($counts['total'] > 0 && $counts['total'] == $counts['approved']) {
        $query = "UPDATE project_substages 
                  SET status = 'completed', completed_at = NOW() 
                  WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $substage_id);
        $stmt->execute();

        // Check if all substages in the parent stage are completed
        $query = "SELECT ps.stage_id, 
                  COUNT(ps.id) as total_substages,
                  SUM(CASE WHEN ps.status = 'completed' THEN 1 ELSE 0 END) as completed_substages
                  FROM project_substages ps
                  WHERE ps.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $substage_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stage_data = $result->fetch_assoc();

        // If all substages are completed, update stage status
        if ($stage_data['total_substages'] == $stage_data['completed_substages']) {
            $query = "UPDATE project_stages 
                      SET status = 'completed', completed_at = NOW() 
                      WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $stage_data['stage_id']);
            $stmt->execute();
        }
    }

    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'substage_completed' => ($counts['total'] == $counts['approved'])
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error updating status: ' . $e->getMessage()
    ]);
} 