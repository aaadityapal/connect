<?php
/**
 * check_upcoming_deadlines.php
 * Polls the DB for tasks assigned to the user that are within a 2-minute due window.
 * Returns full task objects compatible with TaskModal and ExtendModal.
 */
session_start();
require_once '../../config/db_connect.php';
require_once 'recurrence_helper.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
date_default_timezone_set('Asia/Kolkata');

function ensureRejectCountColumn(PDO $pdo): void {
    $q = $pdo->query("SHOW COLUMNS FROM studio_assigned_tasks LIKE 'completion_reject_count'");
    $exists = $q && $q->fetch(PDO::FETCH_ASSOC);
    if (!$exists) {
        $pdo->exec("ALTER TABLE studio_assigned_tasks ADD COLUMN completion_reject_count INT NOT NULL DEFAULT 0");
    }
}

$now = time();

try {
    ensureRejectCountColumn($pdo);

    // Select active tasks where user is assignee and not completed
    $query = "
        SELECT sat.*, u.username as assigned_by_name
        FROM studio_assigned_tasks sat
        LEFT JOIN users u ON sat.created_by = u.id
        WHERE sat.deleted_at IS NULL
          AND sat.status NOT IN ('Completed', 'Cancelled', 'Incomplete')
          AND FIND_IN_SET(:uid, sat.assigned_to) > 0
          AND NOT FIND_IN_SET(:uid_comp, IFNULL(sat.completed_by, ''))
          AND sat.due_date IS NOT NULL
          AND sat.due_time IS NOT NULL
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['uid' => $userId, 'uid_comp' => $userId]);
    $rawTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Expand recurring tasks ─────────────────────────────────────────
    // We check from 2 days ago to 2 mins from now.
    $expandStart = date('Y-m-d', strtotime('-2 days'));
    $expandEnd   = date('Y-m-d H:i:s', $now + 120); // Now + 2 mins
    $tasks = expandRecurringTasks($rawTasks, $expandStart, $expandEnd);

    $upcoming = [];

    foreach ($tasks as $task) {
        $dueTimestamp = strtotime($task['due_date'] . ' ' . $task['due_time']);
        $diffSeconds = $dueTimestamp - $now;

        // Condition: 
        // 1. Due in the future within 2 minutes (0 to 120s)
        // 2. OR Due in the past (Overdue)
        $isUpcoming = ($diffSeconds >= 0 && $diffSeconds <= 120);
        $isOverdue  = ($diffSeconds < 0);

        if ($isUpcoming || $isOverdue) {
            $rejectCount = (int)($task['completion_reject_count'] ?? 0);
            $mustExtendBeforeDone = $rejectCount >= 2;

            if ($isOverdue) {
                $absDiff = abs($diffSeconds);
                $d = floor($absDiff / 86400);
                $h = floor(($absDiff % 86400) / 3600);
                $m = floor(($absDiff % 3600) / 60);
                
                if ($d > 0) $timeLabel = "Overdue by $d day" . ($d > 1 ? "s" : "");
                else if ($h > 0) $timeLabel = "Overdue by $h hr " . ($m > 0 ? "$m m" : "");
                else $timeLabel = "Overdue by $m minute" . ($m == 1 ? "" : "s");
                
                $color = '#991b1b';
                $bgColor = '#fee2e2';
                $titlePrefix = "Missed Deadline! ⚠️";
            } else {
                $minutes = ceil($diffSeconds / 60);
                $timeLabel = "Due in " . $minutes . ($minutes == 1 ? ' minute' : ' minutes');
                $color = '#e11d48';
                $bgColor = '#fff1f2';
                $titlePrefix = "Deadline Approaching ⏱️";
            }

            if ($mustExtendBeforeDone) {
                $color = '#b45309';
                $bgColor = '#fffbeb';
                $titlePrefix = 'Extension Required ⚠️';
                $timeLabel = 'Please extend deadline before marking done';
            }

            // Carried-over (from Incomplete) gets distinct orange styling
            $isCarriedOver = !empty($task['carried_over_from']);
            if ($isCarriedOver) {
                $color      = '#ea580c';
                $bgColor    = '#fff7ed';
                $titlePrefix = "Incomplete — Carried Forward 📋";
                $timeLabel   = "Review by Mon 8:30 AM";
            }

            // ── Format object for TaskModal/ExtendModal ──
            $persons = array_filter(array_map('trim', explode(',', $task['assigned_names'] ?? '')));
            $personsMap = array_values($persons);
            $assignedIds = array_filter(array_map('trim', explode(',', $task['assigned_to'] ?? '')));
            $completedIds = array_filter(array_map('trim', explode(',', $task['completed_by'] ?? '')));
            
            $history = isset($task['extension_history']) ? json_decode($task['extension_history'], true) : [];
            if (!is_array($history)) $history = [];

            $assigneeStatuses = [];
            $myAssignedName = null;
            for ($i = 0; $i < count($assignedIds); $i++) {
                $cId = $assignedIds[$i];
                $cName = $personsMap[$i] ?? "User $cId";
                if ($cId == $userId) $myAssignedName = $cName;

                $userExtCount = 0;
                foreach ($history as $h) {
                    if (isset($h['user_id']) && $h['user_id'] == $cId) $userExtCount++;
                }
                $assigneeStatuses[] = [
                    'name' => $cName,
                    'status' => in_array($cId, $completedIds) ? 'Completed' : 'Pending',
                    'extended' => $userExtCount > 0,
                    'extension_count' => $userExtCount
                ];
            }

            $timeStr = $task['due_time'] ? date('H:i', strtotime($task['due_time'])) : '09:00';
            $timeStrRaw = $task['due_time'] ? date('g:i A', strtotime($task['due_time'])) : '11:59 PM';
            $projectStageTitle = trim(($task['project_name'] ?? '') . ($task['stage_number'] ? ' - Stage ' . $task['stage_number'] : ''));
            if (!$projectStageTitle) $projectStageTitle = 'Untitled Product';

            $createdTime = $task['created_at'] ? strtotime($task['created_at']) : time();
            $created = $task['created_at'] ? date('M j, Y - g:i A', $createdTime) : 'Unknown';
            $due = $dueTimestamp ? date('M j, Y', strtotime($task['due_date'])) . ' - ' . $timeStrRaw : 'No Deadline';

            $durationStr = 'N/A';
            if ($dueTimestamp && $dueTimestamp > $createdTime) {
                $diff = $dueTimestamp - $createdTime;
                $d = floor($diff / 86400);
                $h = floor(($diff % 86400) / 3600);
                $m = floor(($diff % 3600) / 60);
                $parts = [];
                if ($d > 0) $parts[] = $d . ($d == 1 ? ' Day' : ' Days');
                if ($h > 0) $parts[] = $h . ' hr';
                if ($m > 0 || empty($parts)) $parts[] = $m . ' mins';
                $durationStr = implode(' ', $parts);
            }

            $taskData = [
                'id'                   => $task['id'],
                'title'                => $task['task_description'],
                'projectStage'         => $projectStageTitle,
                'desc'                 => $task['task_description'],
                'due_date'             => $task['due_date'],
                'due_time_24'          => $task['due_time'] ? date('H:i', strtotime($task['due_time'])) : null,
                'extension_count'      => $task['extension_count'] ?? 0,
                'extension_history'    => $history,
                'previous_due_date'    => $task['previous_due_date'] ?? null,
                'previous_due_time'    => $task['previous_due_time'] ?? null,
                'time'                 => $timeStr,
                'durationStr'          => $durationStr,
                'status'               => 'Pending',
                'person'               => $myAssignedName ?? (count($personsMap) > 0 ? $personsMap[0] : 'Unassigned'),
                'assignedBy'           => $task['assigned_by_name'] ?? 'System Admin',
                'persons'              => $personsMap,
                'assignee_statuses'    => $assigneeStatuses,
                'modalDateFrom'        => $created,
                'modalDateTo'          => $due,
                'dateFrom'             => $created,
                'dateTo'               => $due,
                'dotColor'             => $color, 
                'bgColor'              => $bgColor,
                'titlePrefix'          => $titlePrefix,
                'time_remaining_label' => $timeLabel,
                'completion_reject_count' => $rejectCount,
                'requires_extension_before_completion' => $mustExtendBeforeDone,
                'is_carried_over'      => !empty($task['carried_over_from']),
                'carried_over_from'    => $task['carried_over_from'] ?? null,
            ];

            $upcoming[] = $taskData;
        }
    }

    echo json_encode(['success' => true, 'tasks' => $upcoming]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
