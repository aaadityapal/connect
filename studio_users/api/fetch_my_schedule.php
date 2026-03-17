<?php
session_start();
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = intval($_SESSION['user_id']);
// We assume we want tasks within the current loaded timeframe, but for simplicity we fetch the user's active/completed tasks

try {
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
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        $timeStr = $task['due_time'] ? date('H:i', strtotime($task['due_time'])) : '09:00';
        $timeStrRaw = $task['due_time'] ? date('g:i A', strtotime($task['due_time'])) : '11:59 PM';

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

        $formatted[] = [
            'id' => $task['id'],
            'title' => $title,
            'projectStage' => $projectStageTitle,
            'desc' => $task['task_description'],
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
            'status' => $myStatus,           // User's specific status overrides global status for modal UI
            'global_status' => $task['status'], // Just in case we need the real global
            'person' => $myAssignedName ?? (count($personsMap) > 0 ? $personsMap[0] : 'Unassigned'),
            'assignedBy' => $task['assigned_by_name'] ?? 'System Admin',
            'persons' => $personsMap,
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
