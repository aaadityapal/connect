<?php
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

try {
    if (!isset($_GET['stage_id'])) {
        throw new Exception('Stage ID is required');
    }

    $stageId = intval($_GET['stage_id']);
    
    // Get stage and task details
    $stmt = $conn->prepare("
        SELECT 
            t.id, t.title, t.description, t.status, t.created_at, t.due_date, 
            t.priority, t.task_type,
            ts.id as stage_id, ts.stage_number, ts.status as stage_status,
            ts.due_date as stage_due_date, ts.start_date as stage_start_date,
            u.username, u.position, u.designation, u.profile_picture,
            creator.username as creator_name, creator.position as creator_position,
            creator.profile_picture as creator_picture
        FROM tasks t
        JOIN task_stages ts ON ts.task_id = t.id
        LEFT JOIN users u ON ts.assigned_to = u.id
        LEFT JOIN users creator ON t.created_by = creator.id
        WHERE ts.id = ?
    ");
    $stmt->bind_param('i', $stageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $taskData = $result->fetch_assoc();

    if (!$taskData) {
        throw new Exception('Task not found');
    }

    // Get substages
    $stmt = $conn->prepare("
        SELECT 
            tss.*, 
            u.username as assigned_to_name, 
            u.position as assigned_to_position,
            u.profile_picture as assigned_to_picture
        FROM task_substages tss
        LEFT JOIN users u ON tss.assignee_id = u.id
        WHERE tss.stage_id = ?
        ORDER BY tss.id
    ");
    $stmt->bind_param('i', $stageId);
    $stmt->execute();
    $substages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get stage files
    $stmt = $conn->prepare("
        SELECT 
            sf.*,
            u.username as uploaded_by_name,
            u.position as uploaded_by_position
        FROM stage_files sf
        LEFT JOIN users u ON sf.uploaded_by = u.id
        WHERE sf.stage_id = ?
        ORDER BY sf.uploaded_at DESC
    ");
    $stmt->bind_param('i', $stageId);
    $stmt->execute();
    $stageFiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get substage files
    foreach ($substages as &$substage) {
        $stmt = $conn->prepare("
            SELECT 
                sf.*,
                u.username as uploaded_by_name,
                u.position as uploaded_by_position
            FROM substage_files sf
            LEFT JOIN users u ON sf.uploaded_by = u.id
            WHERE sf.substage_id = ?
            ORDER BY sf.uploaded_at DESC
        ");
        $stmt->bind_param('i', $substage['id']);
        $stmt->execute();
        $substage['files'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get activity timeline (status changes and comments)
    $stmt = $conn->prepare("
        SELECT 
            'status_change' as activity_type,
            tsh.entity_type,
            tsh.entity_id,
            tsh.old_status,
            tsh.new_status,
            tsh.changed_at as timestamp,
            tsh.comment,
            tsh.file_path,
            u.username,
            u.position,
            u.profile_picture
        FROM task_status_history tsh
        LEFT JOIN users u ON tsh.changed_by = u.id
        WHERE 
            (tsh.entity_type = 'stage' AND tsh.entity_id = ?) OR
            (tsh.entity_type = 'substage' AND tsh.entity_id IN 
                (SELECT id FROM task_substages WHERE stage_id = ?))
        ORDER BY tsh.changed_at DESC
    ");
    $stmt->bind_param('ii', $stageId, $stageId);
    $stmt->execute();
    $activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'task' => [
            'id' => $taskData['id'],
            'title' => $taskData['title'],
            'description' => $taskData['description'],
            'status' => $taskData['status'],
            'priority' => $taskData['priority'],
            'task_type' => $taskData['task_type'],
            'created_at' => $taskData['created_at'],
            'due_date' => $taskData['due_date'],
            'creator' => [
                'name' => $taskData['creator_name'],
                'position' => $taskData['creator_position'],
                'picture' => $taskData['creator_picture']
            ],
            'stage' => [
                'id' => $taskData['stage_id'],
                'stage_number' => $taskData['stage_number'],
                'status' => $taskData['stage_status'],
                'start_date' => $taskData['stage_start_date'],
                'due_date' => $taskData['stage_due_date'],
                'assignee' => [
                    'name' => $taskData['username'],
                    'position' => $taskData['position'],
                    'designation' => $taskData['designation'],
                    'picture' => $taskData['profile_picture']
                ],
                'files' => $stageFiles
            ],
            'substages' => array_map(function($substage) {
                return [
                    'id' => $substage['id'],
                    'description' => $substage['description'],
                    'status' => $substage['status'],
                    'priority' => $substage['priority'],
                    'start_date' => $substage['start_date'],
                    'end_date' => $substage['end_date'],
                    'assignee' => [
                        'name' => $substage['assigned_to_name'],
                        'position' => $substage['assigned_to_position'],
                        'picture' => $substage['assigned_to_picture']
                    ],
                    'files' => $substage['files']
                ];
            }, $substages),
            'activities' => array_map(function($activity) {
                return [
                    'type' => $activity['activity_type'],
                    'entity_type' => $activity['entity_type'],
                    'entity_id' => $activity['entity_id'],
                    'old_status' => $activity['old_status'],
                    'new_status' => $activity['new_status'],
                    'timestamp' => $activity['timestamp'],
                    'comment' => $activity['comment'],
                    'file_path' => $activity['file_path'],
                    'user' => [
                        'name' => $activity['username'],
                        'position' => $activity['position'],
                        'picture' => $activity['profile_picture']
                    ]
                ];
            }, $activities)
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 