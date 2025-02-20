<?php
require_once 'config.php';

try {
    $taskId = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

    // Get stage data
    $stageQuery = "SELECT 
        ts.id as item_id,
        'stage' as item_type,
        ts.created_at as event_time,
        CASE 
            WHEN ts.created_at = ts.updated_at THEN 'created'
            ELSE 'updated'
        END as event_type,
        CONCAT('Stage ', ts.stage_number) as title,
        'Stage update' as description,
        ts.priority,
        ts.status,
        ts.stage_number,
        ts.assigned_to as created_by,
        ts.created_at,
        ts.updated_at
    FROM task_stages ts
    WHERE ts.task_id = :task_id";

    // Get status history
    $statusQuery = "SELECT 
        tsh.id as item_id,
        'status_change' as item_type,
        tsh.changed_at as event_time,
        'status_changed' as event_type,
        CONCAT('Stage ', ts.stage_number, ' Status Changed') as title,
        CONCAT('Status changed from ', 
            COALESCE(tsh.old_status, 'Initial Status'), 
            ' to ', tsh.new_status) as description,
        NULL as priority,
        tsh.new_status as status,
        ts.stage_number,
        tsh.changed_by as created_by,
        tsh.changed_at as created_at,
        tsh.changed_at as updated_at
    FROM task_status_history tsh
    JOIN task_stages ts ON tsh.entity_id = ts.id
    WHERE tsh.entity_type = 'stage'
    AND ts.task_id = :task_id
    ORDER BY tsh.changed_at DESC";

    // Execute stage query
    $stageStmt = $pdo->prepare($stageQuery);
    $stageStmt->execute(['task_id' => $taskId]);
    $stages = $stageStmt->fetchAll(PDO::FETCH_ASSOC);

    // Execute status history query
    $statusStmt = $pdo->prepare($statusQuery);
    $statusStmt->execute(['task_id' => $taskId]);
    $statusHistory = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    // Combine stage and status history
    $timeline = array_merge($stages, $statusHistory);

    // Sort by event_time
    usort($timeline, function($a, $b) {
        return strtotime($b['event_time']) - strtotime($a['event_time']);
    });

    // Get your existing files and substages queries
    // ... (keep your existing code for files and substages)

    echo json_encode([
        'success' => true,
        'timeline' => [
            'stage' => $timeline,  // This now includes both stages and status changes
            'files' => $files ?? [],
            'substages' => $substages ?? []
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
