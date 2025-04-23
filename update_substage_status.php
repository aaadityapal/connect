<?php
/**
 * Update Substage Status API Endpoint
 * This file handles updating the status of a substage
 */

// Prevent any PHP warnings or notices from being output
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON content type header first thing
header('Content-Type: application/json');

// Buffer output to catch any unexpected output
ob_start();

try {
    // Include database connection - fixing the path to use the correct file
    require_once 'config/db_connect.php';
    
    // Check if user is logged in
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }
    
    // Get the current user ID
    $userId = $_SESSION['user_id'];
    
    // Get user role
    $userRole = $_SESSION['role'] ?? '';
    $isAdmin = in_array($userRole, ['admin', 'HR', 'Senior Manager (Studio)']);
    
    // Check if request is POST and has JSON content
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
        !isset($_SERVER['CONTENT_TYPE']) || 
        strpos($_SERVER['CONTENT_TYPE'], 'application/json') === false) {
        throw new Exception('Invalid request method or content type');
    }
    
    // Get POST data
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data received');
    }
    
    // Check if required fields are present
    if (!isset($data['substage_id']) || !isset($data['status'])) {
        throw new Exception('Missing required fields');
    }
    
    $substageId = (int)$data['substage_id'];
    $newStatus = $data['status'];
    
    // Validate status value
    // Make sure 'not_started' is included in the valid statuses
    $validStatuses = ['not_started', 'in_progress', 'completed', 'in_review', 'on_hold', 'cancelled'];
    $adminOnlyStatuses = ['in_review', 'on_hold'];
    
    // Only admins can set in_review or on_hold statuses
    if (in_array($newStatus, $adminOnlyStatuses) && !$isAdmin) {
        throw new Exception('You do not have permission to set this status');
    }
    
    // Add admin-only statuses to valid statuses if admin
    if ($isAdmin) {
        $validStatuses = array_merge($validStatuses, $adminOnlyStatuses);
    }
    
    if (!in_array($newStatus, $validStatuses)) {
        throw new Exception('Invalid status value');
    }
    
    // Test DB connection
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Database connection failed');
    }
    
    // Check if user is authorized to update this substage
    $checkQuery = "
        SELECT ps.*, ps.id as substage_id, ps.stage_id, s.project_id,
               s.assigned_to as stage_assigned_to, 
               p.assigned_to as project_assigned_to 
        FROM project_substages ps
        JOIN project_stages s ON ps.stage_id = s.id
        JOIN projects p ON s.project_id = p.id
        WHERE ps.id = ?
    ";
    
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([$substageId]);
    $substage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$substage) {
        throw new Exception('Substage not found');
    }
    
    // Check if trying to change from admin-only status to another status
    if (!$isAdmin && in_array($substage['status'], $adminOnlyStatuses)) {
        throw new Exception('This substage is currently in an admin-controlled status and cannot be modified');
    }
    
    // Verify user has permission to update the substage
    $hasPermission = (
        $isAdmin || 
        $userId == $substage['assigned_to'] || 
        $userId == $substage['stage_assigned_to'] || 
        $userId == $substage['project_assigned_to']
    );
    
    if (!$hasPermission) {
        throw new Exception('You do not have permission to update this substage');
    }
    
    // Update the status
    $updateQuery = "UPDATE project_substages SET status = ?, updated_at = NOW() WHERE id = ?";
    $updateStmt = $pdo->prepare($updateQuery);
    $result = $updateStmt->execute([$newStatus, $substageId]);
    
    if (!$result) {
        throw new Exception('Failed to update status');
    }
    
    // Record the status change in the project_status_history table
    
    // Check if project_id is available
    if (!isset($substage['project_id']) || $substage['project_id'] === null) {
        // Try to get project_id directly from the database as a fallback
        $projectIdQuery = "
            SELECT p.id as project_id
            FROM project_stages s
            JOIN projects p ON s.project_id = p.id
            WHERE s.id = ?
        ";
        $projectStmt = $pdo->prepare($projectIdQuery);
        $projectStmt->execute([$substage['stage_id']]);
        $projectData = $projectStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$projectData || !isset($projectData['project_id'])) {
            throw new Exception('Failed to determine project ID for history record');
        }
        
        $substage['project_id'] = $projectData['project_id'];
        
        error_log("Retrieved project_id as fallback: " . $substage['project_id']);
    }

    // Debug - Log values before insert
    error_log("Debug - Substage data: " . json_encode([
        'project_id' => $substage['project_id'],
        'stage_id' => $substage['stage_id'],
        'substage_id' => $substageId,
        'old_status' => $substage['status'],
        'new_status' => $newStatus
    ]));

    $historyStmt = $pdo->prepare("INSERT INTO project_status_history 
                                  (project_id, stage_id, substage_id, old_status, new_status, changed_by, remarks) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $remarks = "Substage status updated from {$substage['status']} to {$newStatus}";
    $historyResult = $historyStmt->execute([
        $substage['project_id'],
        $substage['stage_id'],
        $substageId,
        $substage['status'],
        $newStatus,
        $userId,
        $remarks
    ]);
    
    if (!$historyResult) {
        throw new Exception('Failed to record status history');
    }
    
    // Check if all substages of this stage are completed and update stage status if needed
    if ($newStatus === 'completed') {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed 
                                     FROM project_substages WHERE stage_id = ?");
        $checkStmt->execute([$substage['stage_id']]);
        $counts = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // If all substages are completed, update the stage status
        if ($counts['total'] > 0 && $counts['total'] == $counts['completed']) {
            // First get current stage status
            $stageStmt = $pdo->prepare("SELECT status FROM project_stages WHERE id = ?");
            $stageStmt->execute([$substage['stage_id']]);
            $stage = $stageStmt->fetch(PDO::FETCH_ASSOC);
            $oldStageStatus = $stage['status'];
            
            // Update stage status to completed
            $stageUpdate = $pdo->prepare("UPDATE project_stages SET status = 'completed' WHERE id = ?");
            $stageUpdate->execute([$substage['stage_id']]);
            
            // Record stage status change in history
            $stageHistory = $pdo->prepare("INSERT INTO project_status_history 
                                          (project_id, stage_id, substage_id, old_status, new_status, changed_by, remarks) 
                                          VALUES (?, ?, NULL, ?, ?, ?, ?)");
            
            $stageRemarks = "Stage automatically marked as completed because all substages are completed";
            $stageHistory->execute([
                $substage['project_id'],
                $substage['stage_id'],
                $oldStageStatus,
                'completed',
                $userId,
                $stageRemarks
            ]);
        }
    }
    
    // Clear any buffered output
    ob_clean();
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Substage status updated successfully',
        'old_status' => $substage['status'],
        'new_status' => $newStatus
    ]);

} catch (Exception $e) {
    // Clear any buffered output
    ob_clean();
    
    // Log the error
    error_log("Error in update_substage_status.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// End the script to prevent any additional output
exit;
?>