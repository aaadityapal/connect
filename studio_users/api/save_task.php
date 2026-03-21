<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'No data received']);
    exit();
}

try {
    // Extract and sanitize all fields
    $project_id         = !empty($input['project_id']) ? intval($input['project_id']) : null;
    $project_name       = !empty($input['project_name']) ? trim($input['project_name']) : null;
    $stage_id           = !empty($input['stage_id']) ? intval($input['stage_id']) : null;
    $stage_number       = !empty($input['stage_number']) ? trim($input['stage_number']) : null;
    $task_description   = !empty($input['task_description']) ? trim($input['task_description']) : '';
    $priority           = in_array($input['priority'] ?? '', ['Low', 'Medium', 'High']) ? $input['priority'] : 'Low';
    $assigned_to        = !empty($input['assigned_to']) ? trim($input['assigned_to']) : null;        // comma-separated user IDs
    $assigned_names     = !empty($input['assigned_names']) ? trim($input['assigned_names']) : null;  // comma-separated names
    $due_date           = !empty($input['due_date']) ? $input['due_date'] : null;
    $due_time           = !empty($input['due_time']) ? $input['due_time'] : null;
    $is_recurring       = !empty($input['is_recurring']) && $input['is_recurring'] === true ? 1 : 0;
    $recurrence_freq    = !empty($input['recurrence_freq']) ? trim($input['recurrence_freq']) : null;
    $custom_freq_value  = !empty($input['custom_freq_value']) ? intval($input['custom_freq_value']) : null;
    $custom_freq_unit   = !empty($input['custom_freq_unit']) ? trim($input['custom_freq_unit']) : null;
    $created_by         = intval($_SESSION['user_id']);

    if (empty($task_description)) {
        echo json_encode(['success' => false, 'error' => 'Task description is required']);
        exit();
    }

    $query = "INSERT INTO studio_assigned_tasks 
              (project_id, project_name, stage_id, stage_number, task_description, priority, 
               assigned_to, assigned_names, due_date, due_time, 
               is_recurring, recurrence_freq, custom_freq_value, custom_freq_unit, 
               status, created_by, created_at)
              VALUES 
              (:project_id, :project_name, :stage_id, :stage_number, :task_description, :priority,
               :assigned_to, :assigned_names, :due_date, :due_time,
               :is_recurring, :recurrence_freq, :custom_freq_value, :custom_freq_unit,
               'Pending', :created_by, NOW())";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'project_id'        => $project_id,
        'project_name'      => $project_name,
        'stage_id'          => $stage_id,
        'stage_number'      => $stage_number,
        'task_description'  => $task_description,
        'priority'          => $priority,
        'assigned_to'       => $assigned_to,
        'assigned_names'    => $assigned_names,
        'due_date'          => $due_date,
        'due_time'          => $due_time,
        'is_recurring'      => $is_recurring,
        'recurrence_freq'   => $recurrence_freq,
        'custom_freq_value' => $custom_freq_value,
        'custom_freq_unit'  => $custom_freq_unit,
        'created_by'        => $created_by,
    ]);

    $new_id = $pdo->lastInsertId();

    // ── Log the activity ──────────────────────────────────────────────
    $taskLabel = $project_name ?: 'Unnamed Task';
    if ($stage_number) $taskLabel .= ' — Stage ' . $stage_number;

    $assignedTo = $assigned_names ?: 'No one';
    $logDesc    = "Task assigned: \"{$taskLabel}\" → {$assignedTo}";

    $logMeta = json_encode([
        'task_id'          => $new_id,
        'project_name'     => $project_name,
        'stage_number'     => $stage_number,
        'task_description' => $task_description,
        'priority'         => $priority,
        'assigned_names'   => $assigned_names,
        'due_date'         => $due_date,
    ]);

    try {
        // assigned_to is a comma-separated list of user IDs e.g. "3,7,12"
        // We insert one notification row per assignee so each of them sees it in their panel
        $assignedIds = array_filter(array_map('intval', explode(',', $assigned_to ?? '')));

        // Also log for the creator (so they see their own action too)
        $recipientIds = array_unique(array_merge([$created_by], $assignedIds));

        $logStmt = $pdo->prepare(
            "INSERT INTO global_activity_logs
                (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read, is_dismissed)
             VALUES
                (:user_id, 'task_assigned', 'task', :entity_id, :description, :metadata, NOW(), 0, 0)"
        );

        foreach ($recipientIds as $recipientId) {
            if ($recipientId <= 0) continue;

            // Personalise the description for the assignee vs the creator
            if ($recipientId === $created_by) {
                $personalDesc = "You assigned: \"{$taskLabel}\" → {$assignedTo}";
            } else {
                $personalDesc = "You have been assigned a task: \"{$taskLabel}\"";
            }

            $logStmt->execute([
                'user_id'     => $recipientId,
                'entity_id'   => $new_id,
                'description' => $personalDesc,
                'metadata'    => $logMeta,
            ]);
        }
    } catch (Exception $logEx) {
        // Non-fatal — task was still saved successfully
        error_log('Activity log error: ' . $logEx->getMessage());
    }
    // ─────────────────────────────────────────────────────────────────

    echo json_encode([
        'success' => true,
        'message' => 'Task assigned successfully',
        'task_id' => $new_id
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Database error',
        'details' => $e->getMessage()
    ]);
}
?>
