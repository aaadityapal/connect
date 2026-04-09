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
// We assume we want tasks within the current loaded timeframe, but for simplicity we fetch the user's active/completed tasks

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

try {
    ensureTaskUserProgressTable($pdo);

    $query = "
        SELECT sat.*, u.username as assigned_by_name
        FROM studio_assigned_tasks sat
        LEFT JOIN users u ON sat.created_by = u.id
        WHERE sat.deleted_at IS NULL
          AND FIND_IN_SET(:userId, REPLACE(sat.assigned_to, ', ', ','))
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $rawTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Expand recurring tasks ─────────────────────────────────────────
    // We expand for a window of roughly 4 months (-30 to +90 days)
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

    $myProgressByTask = [];
    if (!empty($taskIds)) {
        $ph = implode(',', array_fill(0, count($taskIds), '?'));
        $sql = "SELECT task_id, progress_percent FROM studio_task_user_progress WHERE user_id = ? AND task_id IN ($ph)";
        $params = array_merge([$userId], $taskIds);
        $pst = $pdo->prepare($sql);
        $pst->execute($params);
        foreach ($pst->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $myProgressByTask[(int)$r['task_id']] = (int)$r['progress_percent'];
        }
    }

    $formatted = [];
    foreach ($tasks as $task) {
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
        $myAssignedName = null;
        for ($i = 0; $i < count($assignedIds); $i++) {
            $cId = $assignedIds[$i];
            $cName = $personsMap[$i] ?? "User $cId";
            if ($cId == $userId) {
                $myAssignedName = $cName;
            }

            // Count how many times THIS user ID appears in history
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

        $taskIdInt = is_numeric($task['id']) ? (int)$task['id'] : 0;
        $displayProgress = array_key_exists($taskIdInt, $myProgressByTask)
            ? (int)$myProgressByTask[$taskIdInt]
            : 0;

        $formatted[] = [
            'id' => $task['id'],
            'title' => $title,
            'projectStage' => $projectStageTitle,
            'desc' => $task['task_description'],
            'progress' => $displayProgress,
            'progress_percent' => $displayProgress,
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
                    // The stored timestamp is IST — parse it as IST to get correct ISO
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
            'status' => $myStatus,           // User's specific status overrides global status for modal UI
            'global_status' => $task['status'], // Just in case we need the real global
            'person' => $myAssignedName ?? (count($personsMap) > 0 ? $personsMap[0] : 'Unassigned'),
            'assignedBy' => ($task['project_name'] === 'ArchitectsHive Back Office') ? 'Conneqts Bot' : ($task['assigned_by_name'] ?? 'System Admin'),
            'persons' => $personsMap,
            'can_act' => true,
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
