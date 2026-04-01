<?php
// =====================================================
// api/update_task_status.php
// Updates the status of a task (Pending / In Progress /
// Completed / Cancelled) for the logged-in user
// =====================================================
session_start();
require_once '../../config/db_connect.php';
require_once __DIR__ . '/activity_helper.php';

// ── Force IST timezone so all timestamps are consistent ──
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = intval($_SESSION['user_id']);

// Accept JSON body
$input  = json_decode(file_get_contents('php://input'), true);
$rawTaskId = isset($input['task_id']) ? $input['task_id'] : 0;
$status    = isset($input['status'])  ? trim($input['status'])    : '';

// Detect virtual ID (Recurring expansion) — Format: "ID_YYYYMMDD"
$allowed   = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
$isVirtual = false;
$virtualDate = null;
if (is_string($rawTaskId) && strpos($rawTaskId, '_') !== false) {
    list($taskId, $dateTimeStr) = explode('_', $rawTaskId);
    $taskId = intval($taskId);
    $isVirtual = true;
    
    // Attempt YmdHi first (Hourly), fallback to Ymd (Daily+)
    $dt = DateTime::createFromFormat('YmdHi', $dateTimeStr);
    if (!$dt) $dt = DateTime::createFromFormat('Ymd', $dateTimeStr);
    
    if ($dt) {
        $virtualDate = $dt->format('Y-m-d');
        $virtualTime = $dt->format('H:i:s');
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid virtual date format']);
        exit();
    }
} else {
    $taskId = intval($rawTaskId);
}

if (!$taskId || !in_array($status, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

try {
    // ── Handle Virtual Task Materialisation ──────────────────────────
    if ($isVirtual) {
        // First, check if a REAL task for this date already exists to avoid duplicates
        // (This happens if someone else already completed this recurring instance)
        $checkExisting = $pdo->prepare("
            SELECT id FROM studio_assigned_tasks 
            WHERE recurrence_parent_id = ? 
              AND due_date = ? 
              AND (due_time = ? OR (due_time IS NULL AND ? = '00:00:00'))
              AND deleted_at IS NULL
        ");
        $checkExisting->execute([$taskId, $virtualDate, $virtualTime, $virtualTime]);
        $existingId = $checkExisting->fetchColumn();

        if ($existingId) {
            $taskId = $existingId; // Switch to the real task ID
        } else {
            // Materialize: Copy the original task into a new real entry for this date
            $getOrig = $pdo->prepare("SELECT * FROM studio_assigned_tasks WHERE id = ?");
            $getOrig->execute([$taskId]);
            $orig = $getOrig->fetch(PDO::FETCH_ASSOC);

            if ($orig) {
                unset($orig['id']);
                $orig['due_date']             = $virtualDate;
                $orig['due_time']             = $virtualTime;
                $orig['is_recurring']         = 0; // The instance itself isn't a master template
                $orig['recurrence_parent_id'] = $taskId; // Link to master
                $orig['created_at']           = date('Y-m-d H:i:s');
                $orig['status']               = 'Pending'; // Start as pending
                $orig['completed_by']         = null;
                $orig['completed_at']         = null;
                $orig['completion_history']   = null;

                $cols = implode(',', array_keys($orig));
                $vals = ':' . implode(',:', array_keys($orig));
                $ins = $pdo->prepare("INSERT INTO studio_assigned_tasks ($cols) VALUES ($vals)");
                $ins->execute($orig);
                $taskId = $pdo->lastInsertId();
            } else {
                echo json_encode(['success' => false, 'error' => 'Original recurring task not found']);
                exit();
            }
        }
    }
    // ─────────────────────────────────────────────────────────────────
    // Make sure the task is actually assigned to this user before updating
    $check = $pdo->prepare("
                SELECT id,
                             project_name,
                             stage_number,
                             task_description,
                             created_by,
                             assigned_to,
                             assigned_names,
                             completed_by,
                             completed_at,
                             completion_history,
                             status
        FROM studio_assigned_tasks
        WHERE id = :id
          AND deleted_at IS NULL
          AND FIND_IN_SET(:userId, REPLACE(assigned_to, ', ', ','))
    ");
    $check->execute([':id' => $taskId, ':userId' => $userId]);
    $taskRow = $check->fetch();

    if (!$taskRow) {
        echo json_encode(['success' => false, 'error' => 'Task not found or access denied']);
        exit();
    }

    $previousGlobalStatus = $taskRow['status'] ?? null;

    $assignedToArr = array_filter(array_map('trim', explode(',', $taskRow['assigned_to'])));
    $completedByArr = array_filter(array_map('trim', explode(',', $taskRow['completed_by'] ?? '')));
    $history = json_decode($taskRow['completion_history'] ?? '{}', true);

    // If 'Completed' was requested by frontend, mark it done for THIS user
    if ($status === 'Completed') {
        if (!in_array($userId, $completedByArr)) {
            $completedByArr[] = $userId;
            // Store IST timestamp so undo timer is correct for Indian users
            $history[$userId] = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
        }
    } else {
        // Otherwise, they want to undo/Pending
        $completedByArr = array_diff($completedByArr, [$userId]);
        unset($history[$userId]); // Clear individual time
    }
    $historyJson = json_encode($history);

    // Check if every assignee has now completed the task
    $allCompleted = count($assignedToArr) > 0;
    foreach ($assignedToArr as $uid) {
        if (!in_array($uid, $completedByArr)) {
            $allCompleted = false;
            break;
        }
    }

    // Compute the global state
    $newGlobalStatus = $allCompleted ? 'Completed' : (count($completedByArr) > 0 ? 'In Progress' : 'Pending');
    $completedStr = implode(',', $completedByArr);
    // Use IST-aware timestamp for completed_at
    $nowIst = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
    $completedAtUpdate = $allCompleted ? "completed_at = '{$nowIst}'," : "completed_at = NULL,";

    $stmt = $pdo->prepare("
        UPDATE studio_assigned_tasks
        SET status             = :newStatus,
            completed_by       = :completedBy,
            completion_history = :history,
            $completedAtUpdate
            updated_at = NOW(),
            updated_by = :userId
        WHERE id = :id
    ");
    $stmt->execute([
        ':newStatus'   => $newGlobalStatus, 
        ':completedBy' => $completedStr, 
        ':history'     => $historyJson,
        ':userId'      => $userId, 
        ':id'          => $taskId
    ]);

    // ── Log Activity ────────────────────────────────────────────────────────
    $title = trim(($taskRow['project_name'] ?: 'General Task') . ($taskRow['stage_number'] ? ' — Stage ' . $taskRow['stage_number'] : ''));
    $actionType = ($status === 'Completed') ? 'task_completed' : 'task_status_changed';
    $descPrefix = ($status === 'Completed') ? 'Task completed: ' : "Task marked as {$status}: ";

    try {
        logUserActivity($pdo, $userId, $actionType, 'task', $descPrefix . '"' . $title . '"', $taskId, [
            'task_id' => $taskId,
            'title' => $title,
            'requested_status' => $status,
            'previous_global_status' => $previousGlobalStatus,
            'new_global_status' => $newGlobalStatus,
            'all_assignees_completed' => $allCompleted,
            'created_by' => isset($taskRow['created_by']) ? (int)$taskRow['created_by'] : null,
            'assigned_to' => array_values(array_filter(array_map('intval', $assignedToArr), fn($v) => $v > 0)),
            'assigned_names' => $taskRow['assigned_names'] ?? null,
            'completed_by' => $completedStr,
            'completed_at' => $allCompleted ? $nowIst : null,
            'completion_history' => $history,
        ]);
    } catch (Exception $e) {} // non-fatal

    // If the task just became fully completed, log an "awaiting approval" activity for the creator
    $creatorId = isset($taskRow['created_by']) ? (int)$taskRow['created_by'] : 0;
    if ($creatorId > 0 && $creatorId !== $userId && $previousGlobalStatus !== 'Completed' && $newGlobalStatus === 'Completed') {
        try {
            logUserActivity($pdo, $creatorId, 'task_completed_for_approval', 'task', 'Task completed and awaiting your approval: ' . '"' . $title . '"', $taskId, [
                'task_id' => $taskId,
                'title' => $title,
                'event' => 'needs_approval',
                'previous_global_status' => $previousGlobalStatus,
                'new_global_status' => $newGlobalStatus,
                'created_by' => $creatorId,
                'assigned_to' => array_values(array_filter(array_map('intval', $assignedToArr), fn($v) => $v > 0)),
                'assigned_names' => $taskRow['assigned_names'] ?? null,
                'completed_by' => $completedStr,
                'completed_at' => $nowIst,
                'completion_history' => $history,
            ]);
        } catch (Exception $e) {} // non-fatal
    }
    // ────────────────────────────────────────────────────────────────────────

    echo json_encode([
        'success' => true,
        'task_id' => $taskId,
        'status'  => $status
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Database error',
        'details' => $e->getMessage()
    ]);
}
?>
