<?php
/**
 * Assignment Notification Functions
 * Handles creating and managing notifications for project, stage, and substage assignments
 */

/**
 * Create a notification for a new assignment
 * 
 * @param string $entityType The type of entity (project, stage, substage)
 * @param int $entityId The ID of the entity
 * @param int $assignedTo The user ID of the person assigned
 * @param int $assignedBy The user ID of the person who made the assignment
 * @param string $title The title of the entity
 * @return bool Whether the notification was created successfully
 */
function createAssignmentNotification($entityType, $entityId, $assignedTo, $assignedBy, $title) {
    global $conn;
    
    // Don't create notification if no one is assigned
    if (empty($assignedTo)) {
        return false;
    }
    
    // Create the notification message
    $message = "You have been assigned to " . $entityType . ": " . $title;
    
    // Insert the notification
    $sql = "INSERT INTO notifications 
            (user_id, title, message, source_type, source_id, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssii", 
        $assignedTo, 
        $title, 
        $message, 
        $entityType, 
        $entityId, 
        $assignedBy
    );
    
    return $stmt->execute();
}

/**
 * Create notifications for project assignment
 * 
 * @param int $projectId The project ID
 * @param int $assignedTo The user ID of the person assigned
 * @param int $assignedBy The user ID of the person who made the assignment
 * @param string $projectTitle The title of the project
 * @return bool Whether the notification was created successfully
 */
function createProjectAssignmentNotification($projectId, $assignedTo, $assignedBy, $projectTitle) {
    return createAssignmentNotification('project', $projectId, $assignedTo, $assignedBy, $projectTitle);
}

/**
 * Create notifications for stage assignment
 * 
 * @param int $stageId The stage ID
 * @param int $assignedTo The user ID of the person assigned
 * @param int $assignedBy The user ID of the person who made the assignment
 * @param string $stageTitle The title of the stage
 * @return bool Whether the notification was created successfully
 */
function createStageAssignmentNotification($stageId, $assignedTo, $assignedBy, $stageTitle) {
    return createAssignmentNotification('stage', $stageId, $assignedTo, $assignedBy, $stageTitle);
}

/**
 * Create notifications for substage assignment
 * 
 * @param int $substageId The substage ID
 * @param int $assignedTo The user ID of the person assigned
 * @param int $assignedBy The user ID of the person who made the assignment
 * @param string $substageTitle The title of the substage
 * @return bool Whether the notification was created successfully
 */
function createSubstageAssignmentNotification($substageId, $assignedTo, $assignedBy, $substageTitle) {
    return createAssignmentNotification('substage', $substageId, $assignedTo, $assignedBy, $substageTitle);
}

/**
 * Get assignment notifications for a user
 * 
 * @param int $userId The user ID
 * @param int $limit The maximum number of notifications to return
 * @return array The notifications
 */
function getAssignmentNotifications($userId, $limit = 10) {
    global $conn;
    
    $sql = "SELECT n.*, 
            CASE 
                WHEN n.source_type = 'project' THEN p.title
                WHEN n.source_type = 'stage' THEN CONCAT('Stage ', ps.stage_number)
                WHEN n.source_type = 'substage' THEN pss.title
                ELSE n.title
            END as entity_title,
            CASE 
                WHEN n.source_type = 'project' THEN p.id
                WHEN n.source_type = 'stage' THEN ps.id
                WHEN n.source_type = 'substage' THEN pss.id
                ELSE n.source_id
            END as entity_id
            FROM notifications n
            LEFT JOIN projects p ON n.source_type = 'project' AND n.source_id = p.id
            LEFT JOIN project_stages ps ON n.source_type = 'stage' AND n.source_id = ps.id
            LEFT JOIN project_substages pss ON n.source_type = 'substage' AND n.source_id = pss.id
            WHERE n.user_id = ? AND n.source_type IN ('project', 'stage', 'substage')
            ORDER BY n.created_at DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

/**
 * Mark an assignment notification as read
 * 
 * @param int $userId The ID of the user
 * @param int $notificationId The ID of the notification to mark as read
 * @return bool True if successful, false otherwise
 */
function markAssignmentNotificationAsRead($userId, $notificationId) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET read_status = 1, 
                read_at = CURRENT_TIMESTAMP 
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->bind_param("ii", $notificationId, $userId);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all assignment notifications as read for a user
 * 
 * @param int $userId The user ID
 * @return bool Whether the notifications were marked as read successfully
 */
function markAllAssignmentNotificationsAsRead($userId) {
    global $conn;
    
    $sql = "UPDATE notifications SET read_status = 1, read_at = NOW() 
            WHERE user_id = ? AND source_type IN ('project', 'stage', 'substage') AND read_status = 0";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    
    return $stmt->execute();
} 