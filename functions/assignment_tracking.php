<?php
/**
 * Functions for tracking assignment status changes
 */

/**
 * Log an assignment status change
 * 
 * @param string $entityType Type of entity ('project', 'stage', 'substage')
 * @param int $entityId ID of the entity
 * @param string $previousStatus Previous assignment status
 * @param string $newStatus New assignment status
 * @param int $assignedTo User ID assigned to the task
 * @param int $assignedBy User ID who made the assignment
 * @param int $projectId Project ID
 * @param int|null $stageId Stage ID (for stage and substage entities)
 * @param int|null $substageId Substage ID (for substage entities)
 * @param string|null $comments Optional comments about the change
 * @return bool True if logged successfully, false otherwise
 */
function logAssignmentStatusChange($conn, $entityType, $entityId, $previousStatus, $newStatus, 
                                  $assignedTo, $assignedBy, $projectId, $stageId = null, 
                                  $substageId = null, $comments = null) {
    try {
        // Log any status change (removed condition that only logged unassigned to assigned)
        if ($previousStatus !== $newStatus) {
            // Additional debug information
            error_log("ASSIGNMENT TRACKING: Logging status change for {$entityType} #{$entityId}");
            error_log("ASSIGNMENT TRACKING: Previous status: '{$previousStatus}', New status: '{$newStatus}'");
            error_log("ASSIGNMENT TRACKING: Assigned to: {$assignedTo}, Assigned by: {$assignedBy}");
            if ($comments) {
                error_log("ASSIGNMENT TRACKING: Comments: {$comments}");
            }
            
            $sql = "INSERT INTO assignment_status_logs 
                    (entity_type, entity_id, previous_status, new_status, assigned_to, 
                     assigned_by, project_id, stage_id, substage_id, comments) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("ASSIGNMENT TRACKING: SQL prepare error: " . $conn->error);
                return false;
            }
            
            // Convert possible null values to appropriate values for binding
            $assignedTo = $assignedTo ?: null;
            $assignedBy = $assignedBy ?: null;
            
            $stmt->bind_param('sissiiiiis', 
                $entityType, 
                $entityId, 
                $previousStatus, 
                $newStatus, 
                $assignedTo, 
                $assignedBy, 
                $projectId, 
                $stageId, 
                $substageId, 
                $comments
            );
            
            $result = $stmt->execute();
            if ($result) {
                error_log("ASSIGNMENT TRACKING: Successfully logged {$entityType} #{$entityId} from {$previousStatus} to {$newStatus}");
                
                // Create notification if status changes to 'assigned'
                if ($newStatus === 'assigned' && $assignedTo) {
                    createAssignmentNotification(
                        $conn, 
                        $entityType, 
                        $entityId, 
                        $newStatus, 
                        $assignedTo, 
                        $assignedBy, 
                        $projectId, 
                        $stageId, 
                        $substageId
                    );
                }
                
                return true;
            } else {
                error_log("ASSIGNMENT TRACKING: Failed to log change: " . $stmt->error);
                return false;
            }
        } else {
            error_log("ASSIGNMENT TRACKING: No status change for {$entityType} #{$entityId} - both {$previousStatus}");
        }
        
        return true; // No need to log if status hasn't changed
    } catch (Exception $e) {
        error_log("ASSIGNMENT TRACKING: Error logging status change: " . $e->getMessage());
        error_log("ASSIGNMENT TRACKING: Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Get previous assignment status for an entity
 * 
 * @param mysqli $conn Database connection
 * @param string $entityType Type of entity ('project', 'stage', 'substage') 
 * @param int $entityId ID of the entity
 * @return string|null Previous assignment status or null if not found
 */
function getPreviousAssignmentStatus($conn, $entityType, $entityId) {
    try {
        // If entity ID is empty or null, this is a new entity
        if (empty($entityId)) {
            error_log("ASSIGNMENT TRACKING: No entity ID provided for {$entityType}, assuming new entity with 'unassigned' status");
            return 'unassigned';
        }
        
        $tableName = '';
        $idColumn = 'id';
        
        switch ($entityType) {
            case 'project':
                $tableName = 'projects';
                break;
            case 'stage':
                $tableName = 'project_stages';
                break;
            case 'substage':
                $tableName = 'project_substages';
                break;
            default:
                error_log("ASSIGNMENT TRACKING: Invalid entity type '{$entityType}'");
                return null;
        }
        
        $sql = "SELECT assignment_status, assigned_to FROM {$tableName} WHERE {$idColumn} = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("ASSIGNMENT TRACKING: SQL prepare error in getPreviousAssignmentStatus: " . $conn->error);
            return 'unassigned'; // Fallback to unassigned as a default
        }
        
        $stmt->bind_param('i', $entityId);
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("ASSIGNMENT TRACKING: SQL execute error in getPreviousAssignmentStatus: " . $stmt->error);
            return 'unassigned';
        }
        
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $status = $row['assignment_status'];
            $assignee = $row['assigned_to'];
            error_log("ASSIGNMENT TRACKING: Found previous status for {$entityType} #{$entityId}: '{$status}', assigned to: {$assignee}");
            return $status;
        }
        
        error_log("ASSIGNMENT TRACKING: No previous status found for {$entityType} #{$entityId}, defaulting to 'unassigned'");
        return 'unassigned'; // If entity exists but has no status, default to unassigned
    } catch (Exception $e) {
        error_log("ASSIGNMENT TRACKING: Error getting previous status: " . $e->getMessage());
        error_log("ASSIGNMENT TRACKING: Stack trace: " . $e->getTraceAsString());
        return 'unassigned'; // Default to unassigned on error
    }
}

/**
 * Create a notification for an assignment change
 * 
 * @param mysqli $conn Database connection
 * @param string $entityType Type of entity ('project', 'stage', 'substage')
 * @param int $entityId ID of the entity
 * @param string $newStatus New assignment status
 * @param int $assignedTo User ID assigned to the task
 * @param int $assignedBy User ID who made the assignment
 * @param int $projectId Project ID
 * @param int|null $stageId Stage ID (for stage and substage entities)
 * @param int|null $substageId Substage ID (for substage entities)
 * @return bool True if notification created successfully, false otherwise
 */
function createAssignmentNotification($conn, $entityType, $entityId, $newStatus, $assignedTo, $assignedBy, 
                                     $projectId, $stageId = null, $substageId = null) {
    // Only create notification if status is 'assigned' and a user is assigned
    if ($newStatus !== 'assigned' || empty($assignedTo)) {
        return false;
    }
    
    try {
        // Get entity details to create a meaningful notification
        $entityDetails = getEntityDetails($conn, $entityType, $entityId, $projectId, $stageId);
        if (!$entityDetails) {
            error_log("NOTIFICATION: Failed to get entity details for {$entityType} #{$entityId}");
            return false;
        }
        
        // Create notification title and message based on entity type
        $title = '';
        $message = '';
        
        switch ($entityType) {
            case 'project':
                $title = "New Project Assignment";
                $message = "You have been assigned to project: {$entityDetails['title']}";
                break;
                
            case 'stage':
                $title = "New Project Stage Assignment";
                $message = "You have been assigned to a stage in project: {$entityDetails['project_title']}";
                break;
                
            case 'substage':
                $title = "New Project Task Assignment";
                $message = "You have been assigned to a task in project: {$entityDetails['project_title']}";
                break;
                
            default:
                error_log("NOTIFICATION: Invalid entity type '{$entityType}'");
                return false;
        }
        
        // Get assigner's name if available
        $assignerName = '';
        if ($assignedBy) {
            $assignerQuery = "SELECT username FROM users WHERE id = ?";
            $stmt = $conn->prepare($assignerQuery);
            if ($stmt) {
                $stmt->bind_param('i', $assignedBy);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $assignerName = $row['username'];
                }
                $stmt->close();
            }
        }
        
        // Add assigner's name to message if available
        if ($assignerName) {
            $message .= " by {$assignerName}";
        }
        
        // Prepare detailed content based on entity type
        $detailedContent = '';
        
        if ($entityType === 'project') {
            $detailedContent = "
                <div class='notification-detail'>
                    <p><strong>Project:</strong> {$entityDetails['title']}</p>
                    <p><strong>Description:</strong> {$entityDetails['description']}</p>
                    <p><strong>Start Date:</strong> {$entityDetails['start_date']}</p>
                    <p><strong>Due Date:</strong> {$entityDetails['end_date']}</p>
                </div>
                <div class='notification-actions'>
                    <a href='view-project.php?id={$projectId}' class='action-btn'>View Project</a>
                </div>
            ";
        } else if ($entityType === 'stage') {
            $detailedContent = "
                <div class='notification-detail'>
                    <p><strong>Project:</strong> {$entityDetails['project_title']}</p>
                    <p><strong>Stage:</strong> {$entityDetails['stage_number']}</p>
                    <p><strong>Start Date:</strong> {$entityDetails['start_date']}</p>
                    <p><strong>Due Date:</strong> {$entityDetails['end_date']}</p>
                </div>
                <div class='notification-actions'>
                    <a href='view-project.php?id={$projectId}#stage-{$stageId}' class='action-btn'>View Stage</a>
                </div>
            ";
        } else if ($entityType === 'substage') {
            $detailedContent = "
                <div class='notification-detail'>
                    <p><strong>Project:</strong> {$entityDetails['project_title']}</p>
                    <p><strong>Stage:</strong> {$entityDetails['stage_number']}</p>
                    <p><strong>Task:</strong> {$entityDetails['title']}</p>
                    <p><strong>Start Date:</strong> {$entityDetails['start_date']}</p>
                    <p><strong>Due Date:</strong> {$entityDetails['end_date']}</p>
                </div>
                <div class='notification-actions'>
                    <a href='view-project.php?id={$projectId}#substage-{$substageId}' class='action-btn'>View Task</a>
                </div>
            ";
        }
        
        // Insert notification into the database
        $insertSql = "INSERT INTO notifications 
                      (user_id, title, message, detailed_content, type, source_type, source_id, 
                       related_id, created_at, is_read, expiration_date) 
                      VALUES (?, ?, ?, ?, 'assignment', ?, ?, ?, NOW(), 0, DATE_ADD(NOW(), INTERVAL 30 DAY))";
                       
        $stmt = $conn->prepare($insertSql);
        if (!$stmt) {
            error_log("NOTIFICATION: SQL prepare error: " . $conn->error);
            return false;
        }
        
        // The related_id is the project ID
        $relatedId = $projectId;
        
        $stmt->bind_param('issssii', 
            $assignedTo, 
            $title, 
            $message, 
            $detailedContent,
            $entityType,
            $entityId,
            $relatedId
        );
        
        $result = $stmt->execute();
        if ($result) {
            error_log("NOTIFICATION: Created assignment notification for user #{$assignedTo}, {$entityType} #{$entityId}");
            return true;
        } else {
            error_log("NOTIFICATION: Failed to create notification: " . $stmt->error);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("NOTIFICATION: Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get entity details for notification creation
 * 
 * @param mysqli $conn Database connection
 * @param string $entityType Type of entity ('project', 'stage', 'substage')
 * @param int $entityId ID of the entity
 * @param int $projectId Project ID
 * @param int|null $stageId Stage ID (for substage entities)
 * @return array|null Entity details or null if not found
 */
function getEntityDetails($conn, $entityType, $entityId, $projectId, $stageId = null) {
    try {
        switch ($entityType) {
            case 'project':
                $sql = "SELECT title, description, start_date, end_date FROM projects WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $entityId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    return $row;
                }
                break;
                
            case 'stage':
                $sql = "SELECT ps.stage_number, ps.start_date, ps.end_date, p.title as project_title 
                       FROM project_stages ps 
                       JOIN projects p ON ps.project_id = p.id 
                       WHERE ps.id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $entityId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    return $row;
                }
                break;
                
            case 'substage':
                $sql = "SELECT pss.title, pss.start_date, pss.end_date, 
                       ps.stage_number, p.title as project_title 
                       FROM project_substages pss 
                       JOIN project_stages ps ON pss.stage_id = ps.id 
                       JOIN projects p ON ps.project_id = p.id 
                       WHERE pss.id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('i', $entityId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    return $row;
                }
                break;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("NOTIFICATION: Error getting entity details: " . $e->getMessage());
        return null;
    }
} 