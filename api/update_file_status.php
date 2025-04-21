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
    
    // Get the substage_id for this file
    $getSubstageQuery = "SELECT substage_id FROM substage_files WHERE id = :file_id";
    $substageStmt = $pdo->prepare($getSubstageQuery);
    $substageStmt->execute(['file_id' => $fileId]);
    $substageId = $substageStmt->fetchColumn();
    
    if (!$substageId) {
        throw new Exception('File not found or not associated with a substage');
    }
    
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
    
    $substageUpdated = false;
    $newSubstageStatus = null;
    
    // Check the count of files with different statuses in this substage
    $statusCountQuery = "SELECT 
                            COUNT(*) as total_files,
                            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                            SUM(CASE WHEN status = 'sent_for_approval' THEN 1 ELSE 0 END) as sent_for_approval_count
                        FROM substage_files 
                        WHERE substage_id = :substage_id 
                        AND deleted_at IS NULL";
    
    $statusStmt = $pdo->prepare($statusCountQuery);
    $statusStmt->execute(['substage_id' => $substageId]);
    $fileStats = $statusStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current substage status
    $getCurrentStatusQuery = "SELECT status FROM project_substages WHERE id = :substage_id";
    $currentStatusStmt = $pdo->prepare($getCurrentStatusQuery);
    $currentStatusStmt->execute(['substage_id' => $substageId]);
    $currentStatus = $currentStatusStmt->fetchColumn();
    
    // If at least one file is approved and rest are rejected (no pending files)
    if ($fileStats['total_files'] > 0 && 
        $fileStats['approved_count'] > 0 && 
        $fileStats['pending_count'] == 0 && 
        $fileStats['sent_for_approval_count'] == 0) {
        
        // Set status to completed if it's not already completed
        if ($currentStatus !== 'completed') {
            $newSubstageStatus = 'completed';
            $substageUpdated = true;
        }
    } 
    // If no file is approved or some files are still pending
    elseif ($fileStats['total_files'] > 0 && 
            ($fileStats['approved_count'] == 0 || 
             $fileStats['pending_count'] > 0 || 
             $fileStats['sent_for_approval_count'] > 0)) {
        
        // Set status to in_progress if it's not already in_progress
        if ($currentStatus !== 'in_progress') {
            $newSubstageStatus = 'in_progress';
            $substageUpdated = true;
        }
    }
    
    // Update substage status if needed
    if ($substageUpdated && $newSubstageStatus) {
        $updateSubstageQuery = "UPDATE project_substages 
                               SET status = :status, 
                                   updated_by = :user_id,
                                   updated_at = NOW() 
                               WHERE id = :substage_id";
        
        $updateSubstageStmt = $pdo->prepare($updateSubstageQuery);
        $updateSubstageStmt->execute([
            'status' => $newSubstageStatus,
            'user_id' => $userId,
            'substage_id' => $substageId
        ]);
        
        // Log activity
        $actionDescription = ($newSubstageStatus === 'completed') 
            ? "Substage automatically marked as completed due to all files being approved"
            : "Substage automatically marked as in progress due to file rejection";
        
        $logQuery = "INSERT INTO project_activity_log 
                    (project_id, stage_id, substage_id, activity_type, description, performed_by, performed_at) 
                    SELECT 
                        ps.project_id, 
                        pss.stage_id, 
                        pss.id, 
                        'substage_status_update', 
                        :description, 
                        :user_id, 
                        NOW() 
                    FROM 
                        project_substages pss
                        JOIN project_stages ps ON pss.stage_id = ps.id
                    WHERE 
                        pss.id = :substage_id";
        
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->execute([
            'description' => $actionDescription,
            'user_id' => $userId,
            'substage_id' => $substageId
        ]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'File status updated successfully',
        'substage_updated' => $substageUpdated,
        'new_substage_status' => $newSubstageStatus,
        'substage_id' => $substageId
    ]);
    
} catch (Exception $e) {
    // Roll back transaction on error
    $pdo->rollBack();
    
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 