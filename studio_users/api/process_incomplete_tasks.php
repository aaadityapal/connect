<?php
/**
 * api/process_incomplete_tasks.php
 *
 * Runs automatically on every login (called from script.js on page load).
 * Checks for tasks that:
 *   1. Were due on or before last Sunday 8 PM
 *   2. Are still Pending / In Progress (not completed by the assignee)
 *
 * For each such task it:
 *   a) Marks the original task status = 'Incomplete'
 *   b) Creates a brand-new task for MONDAY 08:30 AM
 *      with carried_over_from = original task ID
 *   c) Logs the carry-over to global_activity_logs
 *
 * Idempotent — safe to call multiple times (checks carried_over_from to avoid duplicates).
 */
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = intval($_SESSION['user_id']);

try {
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

    // ── Find the most recent past Sunday 8 PM ──────────────────────────
    // If today IS Sunday and time < 20:00 → use LAST Sunday
    // If today IS Sunday and time >= 20:00 → deadline already passed today
    $dayOfWeek = (int)$now->format('N'); // 1=Mon … 7=Sun
    
    $lastSunday = clone $now;
    if ($dayOfWeek === 7) {
        // Today is Sunday — deadline is today at 20:00
        $lastSunday->setTime(20, 0, 0);
    } else {
        // Go back to last Sunday
        $daysBack = $dayOfWeek; // Mon=1 back 1 day, Tue=2 back 2 days, etc.
        $lastSunday->modify("-{$daysBack} days");
        $lastSunday->setTime(20, 0, 0);
    }

    // ── Find the coming Monday 08:30 AM ────────────────────────────────
    $nextMonday = clone $now;
    if ($dayOfWeek === 7) {
        // Today is Sunday → Monday is tomorrow
        $nextMonday->modify('+1 day');
    } elseif ($dayOfWeek === 1) {
        // Today IS Monday → carry-forward tasks are due TODAY 08:30
        // (they are created this morning when user logs in)
        // No change needed — nextMonday = today
    } else {
        // Tue–Sat → next Monday
        $daysForward = 8 - $dayOfWeek;
        $nextMonday->modify("+{$daysForward} days");
    }
    $nextMonday->setTime(8, 30, 0);
    $mondayDateStr = $nextMonday->format('Y-m-d');
    $mondayTimeStr = '08:30:00';

    // ── Fetch incomplete tasks for this user ────────────────────────────
    $stmt = $pdo->prepare("
        SELECT sat.*
        FROM studio_assigned_tasks sat
        WHERE sat.deleted_at IS NULL
          AND sat.status NOT IN ('Completed', 'Cancelled', 'Incomplete')
          AND FIND_IN_SET(:uid, REPLACE(sat.assigned_to, ', ', ',')) > 0
          AND NOT FIND_IN_SET(:uid2, IFNULL(sat.completed_by, ''))
          AND sat.due_date IS NOT NULL
          AND sat.carried_over_from IS NULL
          AND CONCAT(sat.due_date, ' ', IFNULL(sat.due_time, '20:00:00')) <= :cutoff
    ");
    $stmt->execute([
        'uid'    => $userId,
        'uid2'   => $userId,
        'cutoff' => $lastSunday->format('Y-m-d H:i:s'),
    ]);
    $incompleteTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($incompleteTasks)) {
        echo json_encode(['success' => true, 'processed' => 0]);
        exit();
    }

    $processed = 0;

    foreach ($incompleteTasks as $task) {
        $originalId = intval($task['id']);

        // ── Guard: Don't create a duplicate carry-over if one already exists ──
        $dupCheck = $pdo->prepare("
            SELECT id FROM studio_assigned_tasks
            WHERE carried_over_from = ? AND deleted_at IS NULL LIMIT 1
        ");
        $dupCheck->execute([$originalId]);
        if ($dupCheck->fetch()) {
            // Already carried forward — just mark original Incomplete if not already
            $pdo->prepare("UPDATE studio_assigned_tasks SET status = 'Incomplete' WHERE id = ?")
                ->execute([$originalId]);
            continue;
        }

        // ── 1. Mark original task as Incomplete ──────────────────────────
        $pdo->prepare("UPDATE studio_assigned_tasks SET status = 'Incomplete' WHERE id = ?")
            ->execute([$originalId]);

        // ── 2. Create new carry-forward task for Monday 08:30 AM ─────────
        $insertStmt = $pdo->prepare("
            INSERT INTO studio_assigned_tasks
                (project_id, project_name, stage_id, stage_number, task_description,
                 priority, assigned_to, assigned_names, due_date, due_time,
                 is_recurring, recurrence_freq, status, created_by, created_at,
                 carried_over_from)
            VALUES
                (:project_id, :project_name, :stage_id, :stage_number, :task_description,
                 :priority, :assigned_to, :assigned_names, :due_date, :due_time,
                 0, NULL, 'Pending', :created_by, NOW(),
                 :carried_over_from)
        ");
        $insertStmt->execute([
            'project_id'       => $task['project_id'],
            'project_name'     => $task['project_name'],
            'stage_id'         => $task['stage_id'],
            'stage_number'     => $task['stage_number'],
            'task_description' => $task['task_description'],
            'priority'         => $task['priority'],
            'assigned_to'      => $task['assigned_to'],
            'assigned_names'   => $task['assigned_names'],
            'due_date'         => $mondayDateStr,
            'due_time'         => $mondayTimeStr,
            'created_by'       => $task['created_by'],
            'carried_over_from'=> $originalId,
        ]);
        $newTaskId = $pdo->lastInsertId();

        // ── 3. Log carry-over to activity logs ───────────────────────────
        $assignedIds = array_filter(array_map('intval', explode(',', $task['assigned_to'] ?? '')));
        $logStmt = $pdo->prepare("
            INSERT INTO global_activity_logs
                (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read, is_dismissed)
            VALUES
                (:uid, 'task_carried_over', 'task', :eid, :desc, :meta, NOW(), 0, 0)
        ");
        foreach ($assignedIds as $recipientId) {
            if ($recipientId <= 0) continue;
            $logStmt->execute([
                'uid'  => $recipientId,
                'eid'  => $newTaskId,
                'desc' => "Incomplete task carried forward to Monday: \"{$task['task_description']}\"",
                'meta' => json_encode([
                    'original_task_id' => $originalId,
                    'new_task_id'      => $newTaskId,
                    'project_name'     => $task['project_name'],
                    'stage_number'     => $task['stage_number'],
                    'task_description' => $task['task_description'],
                    'due_date'         => $mondayDateStr,
                    'due_time'         => '08:30 AM',
                ]),
            ]);
        }
        $processed++;
    }

    echo json_encode([
        'success'   => true,
        'processed' => $processed,
        'monday'    => $mondayDateStr . ' 08:30 AM',
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
