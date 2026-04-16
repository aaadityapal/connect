<?php
// =====================================================
// api/extend_task_deadline.php
// Updates due_date and due_time for a task
// Only allows users assigned to the task to extend it
// =====================================================
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = intval($_SESSION['user_id']);
$input  = json_decode(file_get_contents('php://input'), true);

$taskId  = isset($input['task_id'])  ? intval($input['task_id'])       : 0;
$dueDate = isset($input['due_date']) ? trim($input['due_date'])        : null;
$dueTime = isset($input['due_time']) ? trim($input['due_time'])        : null;

// Basic validation
if (!$taskId) {
    echo json_encode(['success' => false, 'error' => 'Invalid task ID']);
    exit();
}
if ($dueDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
    echo json_encode(['success' => false, 'error' => 'Invalid date format']);
    exit();
}
if ($dueTime && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $dueTime)) {
    echo json_encode(['success' => false, 'error' => 'Invalid time format']);
    exit();
}

function ensureRejectCountColumn(PDO $pdo): void {
    $q = $pdo->query("SHOW COLUMNS FROM studio_assigned_tasks LIKE 'completion_reject_count'");
    $exists = $q && $q->fetch(PDO::FETCH_ASSOC);
    if (!$exists) {
        $pdo->exec("ALTER TABLE studio_assigned_tasks ADD COLUMN completion_reject_count INT NOT NULL DEFAULT 0");
    }
}

try {
    ensureRejectCountColumn($pdo);

    // Verify task is assigned to this user
    $check = $pdo->prepare("
         SELECT sat.id, sat.created_by, sat.due_date, sat.due_time, sat.extended_by,
               sat.extension_count, sat.extension_history,
             sat.completion_reject_count,
               u.username as my_username,
               sat.assigned_to
        FROM studio_assigned_tasks sat
        LEFT JOIN users u ON u.id = :userId
        WHERE sat.id = :id
          AND sat.deleted_at IS NULL
          AND FIND_IN_SET(:userId2, REPLACE(sat.assigned_to, ', ', ','))
    ");
    $check->execute([':id' => $taskId, ':userId' => $userId, ':userId2' => $userId]);
    $task = $check->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        echo json_encode(['success' => false, 'error' => 'Task not found or access denied']);
        exit();
    }

    $assignedToIds = array_filter(array_map('intval', explode(',', $task['assigned_to'])));
    $isSharedTask = count($assignedToIds) > 1;

    // If it's a shared task, store the extended due date in the meta table
    if ($isSharedTask) {
        $metaKey = 'extended_due_date';
        $metaValue = json_encode(['date' => $dueDate, 'time' => $dueTime]);

        $metaStmt = $pdo->prepare("
            INSERT INTO studio_task_user_meta (task_id, user_id, meta_key, meta_value)
            VALUES (:task_id, :user_id, :meta_key, :meta_value)
            ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)
        ");
        $metaStmt->execute([
            'task_id' => $taskId,
            'user_id' => $userId,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue
        ]);

        // We still need to update the history on the main task for tracking
        // but we won't change the main due_date
    }


    $extendedByArr = array_filter(array_map('trim', explode(',', $task['extended_by'] ?? '')));
    if (!in_array($userId, $extendedByArr)) {
        $extendedByArr[] = $userId;
    }
    $extendedByStr = implode(',', $extendedByArr);

    // ── Build extension_history entry ─────────────────────────────────────
    $newExtNumber = ($task['extension_count'] ?? 0) + 1;
    $userName     = $task['my_username'] ?: ('User #' . $userId);

    // Calculate days added (positive = future extension, negative = moved earlier)
    $oldTs   = $task['due_date'] ? strtotime($task['due_date']) : null;
    $newTs   = $dueDate          ? strtotime($dueDate)          : null;
    $daysAdded = ($oldTs && $newTs) ? (int) round(($newTs - $oldTs) / 86400) : null;

    $newEntry = [
        'extension_number'  => $newExtNumber,
        'user_id'           => $userId,
        'user_name'         => $userName,
        'previous_due_date' => $task['due_date']  ?: null,
        'previous_due_time' => $task['due_time']  ?: null,
        'new_due_date'      => $dueDate            ?: null,
        'new_due_time'      => $dueTime            ?: null,
        'extended_at'       => date('Y-m-d H:i:s'),
        'days_added'        => $daysAdded,
    ];

    // Decode existing history (null → empty array), append, re-encode
    $historyArr   = json_decode($task['extension_history'] ?? 'null', true);
    if (!is_array($historyArr)) $historyArr = [];
    $historyArr[] = $newEntry;
    $historyJson  = json_encode($historyArr, JSON_UNESCAPED_UNICODE);
    // ─────────────────────────────────────────────────────────────────────

    // If it's a shared task, we only update the history and other metadata,
    // but we do NOT change the main due date.
    if ($isSharedTask) {
        $stmt = $pdo->prepare("
            UPDATE studio_assigned_tasks
            SET extension_count    = extension_count + 1,
                extended_by        = :extendedBy,
                extension_history  = :extensionHistory,
                updated_at         = NOW(),
                updated_by         = :userId
            WHERE id = :id
        ");
        $stmt->execute([
            ':extendedBy'       => $extendedByStr,
            ':extensionHistory' => $historyJson,
            ':userId'           => $userId,
            ':id'               => $taskId
        ]);
    } else {
        // For non-shared tasks, update the main due date as before
        $stmt = $pdo->prepare("
            UPDATE studio_assigned_tasks
            SET previous_due_date  = due_date,
                previous_due_time  = due_time,
                due_date           = :dueDate,
                due_time           = :dueTime,
                extension_count    = extension_count + 1,
                extended_by        = :extendedBy,
                extension_history  = :extensionHistory,
                completion_reject_count = 0,
                updated_at         = NOW(),
                updated_by         = :userId
            WHERE id = :id
        ");
        $stmt->execute([
            ':dueDate'          => $dueDate  ?: null,
            ':dueTime'          => $dueTime  ?: null,
            ':extendedBy'       => $extendedByStr,
            ':extensionHistory' => $historyJson,
            ':userId'           => $userId,
            ':id'               => $taskId
        ]);
    }

    // Log to global_activity_logs
    // ($userName, $newExtNumber, $daysAdded already computed above)
    $oldDeadlineStr = $task['due_date']
        ? ($task['due_date'] . ($task['due_time'] ? ' ' . date('h:i A', strtotime($task['due_time'])) : ''))
        : 'No Deadline';
    $newDeadlineStr = $dueDate
        ? ($dueDate . ($dueTime ? ' ' . date('h:i A', strtotime($dueTime)) : ''))
        : 'No Deadline';

    $logDesc = "{$userName} extended the task deadline (Extension #{$newExtNumber}) from {$oldDeadlineStr} to {$newDeadlineStr}"
             . ($daysAdded !== null ? " (+{$daysAdded} day" . (abs($daysAdded) !== 1 ? 's' : '') . ')' : '') . '.';

    $meta = json_encode([
        'extended_by_user'  => $userName,
        'old_date'          => $task['due_date'],
        'old_time'          => $task['due_time'],
        'new_date'          => $dueDate,
        'new_time'          => $dueTime,
        'days_added'        => $daysAdded,
        'extension_number'  => $newExtNumber,
    ]);

    $logStmt = $pdo->prepare("
        INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata)
        VALUES (:uid, 'extend_deadline', 'task', :eid, :desc, :meta)
    ");

    $recipientIds = array_unique([(int)$userId, (int)($task['created_by'] ?? 0)]);
    foreach ($recipientIds as $recipientId) {
        if ($recipientId <= 0) continue;

        $descForRecipient = ($recipientId === (int)$userId)
            ? $logDesc
            : "{$userName} has extended the task deadline from {$oldDeadlineStr} to {$newDeadlineStr}"
                . ($daysAdded !== null ? " (+{$daysAdded} day" . (abs($daysAdded) !== 1 ? 's' : '') . ')' : '')
                . '.';

        $logStmt->execute([
            ':uid'  => $recipientId,
            ':eid'  => $taskId,
            ':desc' => $descForRecipient,
            ':meta' => $meta,
        ]);
    }

    echo json_encode([
        'success'  => true,
        'task_id'  => $taskId,
        'due_date' => $dueDate,
        'due_time' => $dueTime
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Database error',
        'details' => $e->getMessage()
    ]);
}
?>
