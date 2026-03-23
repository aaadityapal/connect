<?php
/**
 * Shared helper function to log user activity.
 */
function logUserActivity($pdo, $userId, $actionType, $entityType, $description, $entityId = null, $metadata = null) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO global_activity_logs
                (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read)
             VALUES
                (:user_id, :action_type, :entity_type, :entity_id, :description, :metadata, NOW(), 0)"
        );

        $stmt->execute([
            'user_id'     => $userId,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'description' => $description,
            'metadata'    => $metadata ? json_encode($metadata) : null,
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
        return false;
    }
}
