<?php
/**
 * check_pending_task_approvals.php
 * Returns tasks created by the logged-in user that have at least one assignee
 * completion and are waiting for (or re-waiting for) the creator's approval.
 *
 * Storage:
 * - Uses a lightweight table `studio_task_completion_approvals` to persist approvals
 *   without altering the `studio_assigned_tasks` schema.
 */
session_start();
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

function ensureApprovalTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS studio_task_completion_approvals (
            id INT(11) NOT NULL AUTO_INCREMENT,
            task_id INT(11) NOT NULL,
            approved_by INT(11) NOT NULL,
            approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_task_id (task_id),
            KEY idx_approved_by (approved_by),
            KEY idx_approved_at (approved_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

try {
    ensureApprovalTable($pdo);

        // 1) Get tasks created by me (assigned to others) where at least one assignee
        //    has completed. We include approval timestamp so we can re-trigger approvals
        //    whenever a new assignee completes after a previous approval.
        $stmt = $pdo->prepare("
        SELECT 
            sat.*,
                        u.username as creator_name,
                        a.approved_at as approved_at
        FROM studio_assigned_tasks sat
        LEFT JOIN users u ON sat.created_by = u.id
        LEFT JOIN studio_task_completion_approvals a ON a.task_id = sat.id
        WHERE sat.deleted_at IS NULL
          AND sat.created_by = :uid
                    AND (sat.status IS NULL OR sat.status NOT IN ('Cancelled', 'Incomplete'))
          AND IFNULL(TRIM(sat.completed_by), '') <> ''
          AND sat.project_name != 'ArchitectsHive Systems'
          AND (sat.assigned_to IS NULL OR FIND_IN_SET(:uid_not_assignee, REPLACE(sat.assigned_to, ' ', '')) = 0)
        ORDER BY sat.completed_at DESC, sat.updated_at DESC, sat.id DESC
        LIMIT 50
    ");

    $stmt->execute([
        ':uid' => $userId,
        ':uid_not_assignee' => (string)$userId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tasks = [];

    foreach ($rows as $task) {
        // Build assignee statuses (if we have snapshots)
        $assignedIds = array_filter(array_map('trim', explode(',', $task['assigned_to'] ?? '')));
        $names = array_values(array_filter(array_map('trim', explode(',', $task['assigned_names'] ?? ''))));
        $completedIds = array_filter(array_map('trim', explode(',', $task['completed_by'] ?? '')));

        $completionHistory = json_decode($task['completion_history'] ?? '{}', true);
        if (!is_array($completionHistory)) $completionHistory = [];

        $assigneeStatuses = [];
        $latestCompleterName = null;
        $latestCompleterAtTs = 0;
        $latestCompletionAtRaw = null;

        // Latest approval timestamp for this task (if any)
        $approvedAtTs = !empty($task['approved_at']) ? strtotime($task['approved_at']) : 0;

        for ($i = 0; $i < count($assignedIds); $i++) {
            $cId = $assignedIds[$i];
            $cName = $names[$i] ?? ("User " . $cId);
            $isDone = in_array($cId, $completedIds);

            if ($isDone) {
                $completedAtRaw = $completionHistory[$cId] ?? null;
                $completedAtTs = $completedAtRaw ? strtotime($completedAtRaw) : 0;
                if ($completedAtTs > $latestCompleterAtTs) {
                    $latestCompleterAtTs = $completedAtTs;
                    $latestCompletionAtRaw = $completedAtRaw;
                    $latestCompleterName = $cName;
                }
            }

            $assigneeStatuses[] = [
                'name' => $cName,
                'status' => $isDone ? 'Completed' : 'Pending',
                'extended' => false,
                'extension_count' => 0,
                'completed_at' => $completionHistory[$cId] ?? null
            ];
        }

        // Fallback: if completion history is missing timestamps, use completed_at from row
        if ($latestCompleterAtTs <= 0 && !empty($task['completed_at'])) {
            $latestCompleterAtTs = strtotime($task['completed_at']) ?: 0;
            $latestCompletionAtRaw = $task['completed_at'];
            if (!$latestCompleterName) {
                // Best-effort fallback name from first completed assignee
                for ($i = 0; $i < count($assignedIds); $i++) {
                    if (in_array($assignedIds[$i], $completedIds)) {
                        $latestCompleterName = $names[$i] ?? ("User " . $assignedIds[$i]);
                        break;
                    }
                }
            }
        }

        // If already approved after latest completion, do NOT show in approval list.
        // If a new completion happened after approval, show again.
        if ($latestCompleterAtTs > 0 && $approvedAtTs > 0 && $approvedAtTs >= $latestCompleterAtTs) {
            continue;
        }

        $projectStageTitle = trim(($task['project_name'] ?? '') . (!empty($task['stage_number']) ? ' - Stage ' . $task['stage_number'] : ''));
        if (!$projectStageTitle) $projectStageTitle = 'Untitled Task';

        $createdTime = $task['created_at'] ? strtotime($task['created_at']) : time();
        $created = $task['created_at'] ? date('M j, Y - g:i A', $createdTime) : 'Unknown';

        $dueTimestamp = null;
        if (!empty($task['due_date']) && !empty($task['due_time'])) {
            $dueTimestamp = strtotime($task['due_date'] . ' ' . $task['due_time']);
        }

        $timeStrRaw = !empty($task['due_time']) ? date('g:i A', strtotime($task['due_time'])) : null;
        $due = ($dueTimestamp && !empty($task['due_date']))
            ? date('M j, Y', strtotime($task['due_date'])) . ($timeStrRaw ? ' - ' . $timeStrRaw : '')
            : 'No Deadline';

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

        $badgeLabel = $latestCompleterName
            ? ('Completed by ' . $latestCompleterName)
            : 'Completed';

        if ($latestCompletionAtRaw) {
            $ts = strtotime($latestCompletionAtRaw);
            if ($ts) $badgeLabel .= ' • ' . date('g:i A', $ts);
        }

        $tasks[] = [
            'id' => (string)$task['id'],
            'title' => $task['task_description'],
            'projectStage' => $projectStageTitle,
            'desc' => $task['task_description'],
            'due_date' => $task['due_date'] ?? null,
            'due_time_24' => !empty($task['due_time']) ? date('H:i', strtotime($task['due_time'])) : null,
            'status' => $task['status'] ?? 'In Progress',
            'assignedBy' => $task['creator_name'] ?? 'You',
            'persons' => $names,
            'assignee_statuses' => $assigneeStatuses,
            'dateFrom' => $created,
            'dateTo' => $due,
            'modalDateFrom' => $created,
            'modalDateTo' => $due,
            'dotColor' => '#16a34a',
            'bgColor' => '#dcfce7',
            'titlePrefix' => 'Approval Needed ✅',
            'time_remaining_label' => $badgeLabel,
        ];
    }

    echo json_encode(['success' => true, 'tasks' => $tasks]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
