<?php
session_start();
require_once '../../config/db_connect.php';
require_once 'recurrence_helper.php';

// Force IST timezone for consistent timestamp handling
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = intval($_SESSION['user_id']);

function ensureTaskUserProgressTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS studio_task_user_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        user_id INT NOT NULL,
        progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_task_user (task_id, user_id),
        INDEX idx_task (task_id),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function getSubordinates($pdo, $managerId) {
    $ids = [$managerId];
    $toProcess = [$managerId];
    
    while (!empty($toProcess)) {
        $placeholders = implode(',', array_fill(0, count($toProcess), '?'));
        // Query the new many-to-many reporting table
        $stmt = $pdo->prepare("SELECT subordinate_id FROM user_reporting WHERE manager_id IN ($placeholders)");
        $stmt->execute($toProcess);
        $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $toProcess = [];
        foreach ($children as $cid) {
            $cid = (int)$cid;
            if (!in_array($cid, $ids)) {
                $ids[] = $cid;
                $toProcess[] = $cid;
            }
        }
    }
    return $ids;
}

function getPeerIds($pdo, $userId) {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT ur2.subordinate_id
         FROM user_reporting ur1
         INNER JOIN user_reporting ur2 ON ur1.manager_id = ur2.manager_id
         INNER JOIN users u ON u.id = ur2.subordinate_id
         WHERE ur1.subordinate_id = ?
           AND ur2.subordinate_id <> ?
           AND u.deleted_at IS NULL
           AND u.status = 'Active'"
    );
    $stmt->execute([$userId, $userId]);
    return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
}

function resolveProgressTaskId($task): int {
    if (isset($task['id']) && is_numeric($task['id'])) {
        return (int)$task['id'];
    }

    if (!empty($task['id']) && is_string($task['id']) && strpos($task['id'], '_') !== false) {
        $parts = explode('_', $task['id'], 2);
        if (isset($parts[0]) && is_numeric($parts[0])) {
            return (int)$parts[0];
        }
    }

    return 0;
}

try {
    ensureTaskUserProgressTable($pdo);

    // 1. Build authority scope: self tree + colleague trees (same manager level)
    $rootIds = array_values(array_unique(array_merge([$userId], getPeerIds($pdo, $userId))));
    $allowedUserIds = [];
    foreach ($rootIds as $rid) {
        $allowedUserIds = array_merge($allowedUserIds, getSubordinates($pdo, (int)$rid));
    }
    $allowedUserIds = array_values(array_unique(array_map('intval', $allowedUserIds)));

    // 2. See if the frontend is requesting a specific user within that tree
    // If explicitly provided from hierarchy click, we should show ONLY that user's tasks.
    $hasExplicitTarget = isset($_GET['target_user_id']) && $_GET['target_user_id'] !== '';
    $targetUserId = $hasExplicitTarget ? intval($_GET['target_user_id']) : $userId;

    // Security check: Make sure they aren't trying to view someone outside their tree
    if (!in_array($targetUserId, $allowedUserIds)) {
        echo json_encode(['success' => false, 'error' => 'Target user is outside of your authority.']);
        exit();
    }

    // 3. Filtering rule:
    //    - Explicit hierarchy click  -> selected user only
    //    - Default view (no click)   -> keep existing behaviour (target tree)
    $targetTreeIds = $hasExplicitTarget
        ? [$targetUserId]
        : getSubordinates($pdo, $targetUserId);

    $query = "
        SELECT sat.*, u.username as assigned_by_name
        FROM studio_assigned_tasks sat
        LEFT JOIN users u ON sat.created_by = u.id
        WHERE sat.deleted_at IS NULL
          AND sat.assigned_to IS NOT NULL AND sat.assigned_to != ''
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $rawTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Expand recurring tasks ─────────────────────────────────────────
    $expandStart = date('Y-m-d', strtotime('-30 days'));
    $expandEnd   = date('Y-m-d', strtotime('+120 days'));
    $tasks = expandRecurringTasks($rawTasks, $expandStart, $expandEnd);

    $taskIds = [];
    foreach ($tasks as $t) {
        if (isset($t['id']) && is_numeric($t['id'])) {
            $taskIds[] = (int)$t['id'];
        }
    }
    $taskIds = array_values(array_unique($taskIds));

    $progressByTaskUser = [];
    if (!empty($taskIds)) {
        $ph = implode(',', array_fill(0, count($taskIds), '?'));
        $pstmt = $pdo->prepare("SELECT task_id, user_id, progress_percent FROM studio_task_user_progress WHERE task_id IN ($ph)");
        $pstmt->execute($taskIds);
        foreach ($pstmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $tid = (int)$r['task_id'];
            $uid = (int)$r['user_id'];
            if (!isset($progressByTaskUser[$tid])) $progressByTaskUser[$tid] = [];
            $progressByTaskUser[$tid][$uid] = (int)$r['progress_percent'];
        }
    }

    $formatted = [];
    foreach ($tasks as $task) {
        $assignedIds = array_filter(array_map('trim', explode(',', $task['assigned_to'] ?? '')));
        
        // ── Hierarchy Shield: Filter tasks based on target user IDs ──
        $hasOverlap = false;
        foreach ($assignedIds as $aid) {
            if (in_array((int)$aid, $targetTreeIds)) {
                $hasOverlap = true;
                break;
            }
        }
        if (!$hasOverlap) continue; // Skip if no one in the task belongs to this target tree
        // ─────────────────────────────────────────────────────────────

        $persons = array_filter(array_map('trim', explode(',', $task['assigned_names'] ?? '')));
        $personsMap = array_values($persons);
        
        // Simple mapping:
        // dayIndex = 0-6 (0=Mon, 6=Sun) based on due_date
        // dateNum = day of month (1-31) based on due_date
        $dueDate = $task['due_date'] ? new DateTime($task['due_date']) : null;
        $dayIndex = null;
        $dateNum = null;
        if ($task['due_date']) {
            $time = strtotime($task['due_date']);
            // PHP 'N' is 1 (Mon) to 7 (Sun)
            $dayIndex = date('N', $time) - 1; 
            $dateNum = (int)date('j', $time);
        }
        
        $assignedIds = array_filter(array_map('trim', explode(',', $task['assigned_to'] ?? '')));
        $completedIds = array_filter(array_map('trim', explode(',', $task['completed_by'] ?? '')));
        $extendedIds = array_filter(array_map('trim', explode(',', $task['extended_by'] ?? '')));
        
        $myStatus = in_array($userId, $completedIds) ? 'Completed' : 'Pending';
        
        $history = isset($task['extension_history']) ? json_decode($task['extension_history'], true) : [];
        if (!is_array($history)) $history = [];

        $assigneeStatuses = [];
        for ($i = 0; $i < count($assignedIds); $i++) {
            $cId = $assignedIds[$i];
            $cName = $personsMap[$i] ?? "User $cId";

            // Count extensions for this user
            $userExtCount = 0;
            foreach ($history as $h) {
                if (isset($h['user_id']) && $h['user_id'] == $cId) {
                    $userExtCount++;
                }
            }

            $assigneeStatuses[] = [
                'name' => $cName,
                'status' => in_array($cId, $completedIds) ? 'Completed' : 'Pending',
                'extended' => $userExtCount > 0,
                'extension_count' => $userExtCount
            ];
        }

        // Time logic: use due_time as start, or '09:00' default. duration 60.
        // Safety: old virtual-recurring rows may have been materialized at 00:00:00;
        // show them at business default so they remain visible in day lanes.
        $rawDueTime = $task['due_time'] ?? null;
        if (!empty($task['recurrence_parent_id']) && $rawDueTime === '00:00:00') {
            $rawDueTime = null;
        }
        $timeStr = $rawDueTime ? date('H:i', strtotime($rawDueTime)) : '09:00';
        $timeStrRaw = $rawDueTime ? date('g:i A', strtotime($rawDueTime)) : '9:00 AM';

        $title = $task['task_description'];
        $projectStageTitle = trim(($task['project_name'] ?? '') . ($task['stage_number'] ? ' - Stage ' . $task['stage_number'] : ''));
        if (!$projectStageTitle) {
            $projectStageTitle = 'Untitled Project';
        }
        
        $createdTime = $task['created_at'] ? strtotime($task['created_at']) : time();
        $dueTime = $task['due_date'] ? strtotime($task['due_date'] . ' ' . ($task['due_time'] ?: '23:59:59')) : null;
        
        $created = $task['created_at'] ? date('M j, Y - g:i A', $createdTime) : 'Unknown';
        $due = $dueTime ? date('M j, Y', strtotime($task['due_date'])) . ' - ' . $timeStrRaw : 'No Deadline';

        $durationStr = 'N/A';
        $durationDays = 1;
        if ($dueTime && $dueTime > $createdTime) {
            $diff = $dueTime - $createdTime;
            $d = floor($diff / 86400);
            $h = floor(($diff % 86400) / 3600);
            $m = floor(($diff % 3600) / 60);
            $parts = [];
            if ($d > 0) $parts[] = $d . ($d == 1 ? ' Day' : ' Days');
            if ($h > 0) $parts[] = $h . ' hr';
            if ($m > 0 || empty($parts)) $parts[] = $m . ' mins';
            $durationStr = implode(' ', $parts);
            $durationDays = max(1, ceil($diff / 86400));
        } else if ($dueTime) {
            $durationStr = '0 mins';
        } else {
            $durationStr = 'No time limit';
        }

        // Personal status for this user
        $completedByArr = array_filter(array_map('trim', explode(',', $task['completed_by'] ?? '')));
        $isUserDone = in_array((string)$userId, $completedByArr);
        $myStatus = $isUserDone ? 'Completed' : ($task['status'] === 'Cancelled' ? 'Cancelled' : ($task['status'] === 'Completed' ? 'Completed' : 'Pending'));

        $taskIdInt = resolveProgressTaskId($task);
        $isViewerAssignee = in_array((string)$userId, array_map('strval', $assignedIds), true);

        $combinedProgress = 0.0;
        if (!empty($assignedIds)) {
            $sumProgress = 0;
            foreach ($assignedIds as $aid) {
                $aidInt = (int)$aid;
                $sumProgress += isset($progressByTaskUser[$taskIdInt][$aidInt])
                    ? (int)$progressByTaskUser[$taskIdInt][$aidInt]
                    : 0;
            }
            $combinedProgress = $sumProgress / count($assignedIds);
        }

        $myProgress = isset($progressByTaskUser[$taskIdInt][$userId])
            ? (int)$progressByTaskUser[$taskIdInt][$userId]
            : 0;

        $displayProgress = $isViewerAssignee ? $myProgress : $combinedProgress;

        // Fallback for non-assignee/manager view when per-user rows are not present
        // (e.g. legacy tasks before per-user table backfill).
        if (!$isViewerAssignee && $combinedProgress === 0 && isset($task['progress_percent'])) {
            $displayProgress = (int)$task['progress_percent'];
        }

        $formatted[] = [
            'id' => $task['id'],
            'title' => $title,
            'projectStage' => $projectStageTitle,
            'desc' => $task['task_description'],
            'progress' => $displayProgress,
            'progress_percent' => $displayProgress,
            'my_progress_percent' => $myProgress,
            'combined_progress_percent' => $combinedProgress,
            'due_date' => $task['due_date'],
            'due_time_24' => $task['due_time'] ? date('H:i', strtotime($task['due_time'])) : null,
            'extension_count' => $task['extension_count'] ?? 0,
            'extension_history' => isset($task['extension_history']) ? json_decode($task['extension_history'], true) : [],
            'completion_history' => isset($task['completion_history']) ? json_decode($task['completion_history'], true) : [],
            'my_completed_at' => (function() use($task, $userId) {
                $hist = json_decode($task['completion_history'] ?? '{}', true);
                $ts = $hist[$userId] ?? null;
                if (!$ts) return null;
                try {
                    return (new DateTime($ts, new DateTimeZone('Asia/Kolkata')))->format('c');
                } catch(Exception $e) { return null; }
            })(),
            'previous_due_date' => $task['previous_due_date'] ?? null,
            'previous_due_time' => $task['previous_due_time'] ?? null,
            'completed_at' => $task['completed_at'] ? (new DateTime($task['completed_at'], new DateTimeZone('Asia/Kolkata')))->format('c') : null,
            'time' => $timeStr,
            'duration' => 60,
            'durationDays' => $durationDays,
            'durationStr' => $durationStr,
            'dayIndex' => $dayIndex,
            'dateNum' => $dateNum,
            'status' => $myStatus, // User specific status for modal logic
            'global_status' => $task['status'],
            'person' => count($personsMap) > 0 ? $personsMap[0] : 'Unassigned',
            'assignedBy' => ($task['project_name'] === 'ArchitectsHive Back Office') ? 'Conneqts Bot' : ($task['assigned_by_name'] ?? 'System Admin'),
            'persons' => $personsMap,
            'can_act' => $isViewerAssignee,
            'assignee_statuses' => $assigneeStatuses,
            'modalDateFrom' => $created,
            'modalDateTo' => $due,
            // Randomly map to a row 0, 1, 2 for timeline vertical spacing
            'row' => $task['id'] % 3
        ];
    }

    echo json_encode(['success' => true, 'events' => $formatted]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
