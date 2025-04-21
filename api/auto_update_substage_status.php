<?php
/**
 * Auto Update Substage Status
 * 
 * This file checks if all files in a substage are approved and updates
 * the substage status to 'completed' if that's the case.
 */

session_start();
require_once '../config/db_connect.php';
header('Content-Type: application/json');

// Function to verify if all files in a substage are approved
function areAllFilesApproved($pdo, $substageId) {
    // Check if there are any files not approved
    $query = "SELECT COUNT(*) FROM substage_files 
              WHERE substage_id = :substage_id 
              AND status != 'approved' 
              AND deleted_at IS NULL";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['substage_id' => $substageId]);
    $nonApprovedCount = $stmt->fetchColumn();
    
    // Also verify there's at least one file
    $query = "SELECT COUNT(*) FROM substage_files 
              WHERE substage_id = :substage_id 
              AND deleted_at IS NULL";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(['substage_id' => $substageId]);
    $totalFiles = $stmt->fetchColumn();
    
    // Return true if there are files and all are approved
    return ($totalFiles > 0 && $nonApprovedCount == 0);
}

// Function to update substage status
function updateSubstageStatus($pdo, $substageId, $status, $userId) {
    try {
        $query = "UPDATE project_substages 
                 SET status = :status, 
                     updated_by = :user_id,
                     updated_at = NOW() 
                 WHERE id = :substage_id";
        
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([
            'status' => $status,
            'user_id' => $userId,
            'substage_id' => $substageId
        ]);
        
        if ($result) {
            // Log activity
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
                'description' => "Substage automatically marked as completed due to all files being approved",
                'user_id' => $userId,
                'substage_id' => $substageId
            ]);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Error updating substage status: " . $e->getMessage());
        return false;
    }
}

try {
    // Main code execution starts here
    
    // Get input data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['file_id']) || !isset($data['substage_id'])) {
        throw new Exception('Missing required parameters');
    }
    
    $fileId = $data['file_id'];
    $substageId = $data['substage_id'];
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        throw new Exception('User not authenticated');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Check if all files in the substage are approved
        if (areAllFilesApproved($pdo, $substageId)) {
            // Update the substage status to completed
            $result = updateSubstageStatus($pdo, $substageId, 'completed', $userId);
            
            if (!$result) {
                throw new Exception('Failed to update substage status');
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Substage status checked and updated if needed']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 