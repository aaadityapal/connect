<?php
// =====================================================
// api/fetch_my_tasks.php
// Returns tasks assigned to the logged-in user,
// optionally filtered by period (daily/weekly/monthly/yearly/other)
// =====================================================
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = intval($_SESSION['user_id']);
$dateParam = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');

// ── Date window ─────────────────────────────────────────────
$dateWhere = "AND (sat.due_date = :queryDate1 OR (sat.due_date IS NULL AND :queryDate2 = :today))";

try {
    // Match user ID inside comma-separated assigned_to column
    $query = "
        SELECT
            sat.id,
            sat.project_id,
            sat.project_name,
            sat.stage_id,
            sat.stage_number,
            sat.task_description,
            sat.priority,
            sat.assigned_to,
            sat.assigned_names,
            sat.completed_by,
            sat.completion_history,
            sat.extension_history,
            sat.due_date,
            sat.due_time,
            sat.extension_count,
            sat.previous_due_date,
            sat.previous_due_time,
            sat.status as global_status,
            sat.is_recurring,
            sat.recurrence_freq,
            sat.created_at,
            sat.completed_at as global_completed_at,
            u.username AS created_by_name
        FROM studio_assigned_tasks sat
        LEFT JOIN users u ON sat.created_by = u.id
        WHERE sat.deleted_at IS NULL
          AND FIND_IN_SET(:userId, REPLACE(sat.assigned_to, ', ', ','))
          {$dateWhere}
        ORDER BY
            FIELD(sat.status, 'In Progress', 'Pending', 'Completed', 'Cancelled'),
            sat.due_date ASC,
            sat.due_time ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':queryDate1', $dateParam, PDO::PARAM_STR);
    $stmt->bindValue(':queryDate2', $dateParam, PDO::PARAM_STR);
    $stmt->bindValue(':today', date('Y-m-d'), PDO::PARAM_STR);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Format for JS consumption ─────────────────────────────────────────────
    $formatted = [];
    foreach ($tasks as $task) {
        $assignedIds = array_filter(array_map('trim', explode(',', $task['assigned_to'])));
        $assignedNames = array_filter(array_map('trim', explode(',', $task['assigned_names'] ?? '')));
        $completedIds = array_filter(array_map('trim', explode(',', $task['completed_by'] ?? '')));
        $isUserDone = in_array((string)$userId, $completedIds);
        
        // ── Assignee Statuses logic for the detailed modal ──────────────────
        $extHistory = json_decode($task['extension_history'] ?? '[]', true);
        if (!is_array($extHistory)) $extHistory = [];
        
        $assigneeStatuses = [];
        for ($i = 0; $i < count($assignedIds); $i++) {
            $cId = $assignedIds[$i];
            $cName = $assignedNames[$i] ?? "User $cId";

            $userExtCount = 0;
            foreach ($extHistory as $h) {
                if (isset($h['user_id']) && $h['user_id'] == $cId) {
                    $userExtCount++;
                }
            }
            $assigneeStatuses[] = [
                'name'            => $cName,
                'status'          => in_array($cId, $completedIds) ? 'Completed' : 'Pending',
                'extended'        => $userExtCount > 0,
                'extension_count' => $userExtCount
            ];
        }

        // ── Formatting dates exactly as TaskModal.open expects them ───────
        $timeStrRaw = $task['due_time'] ? date('g:i A', strtotime($task['due_time'])) : '11:59 PM';
        $createdTime = strtotime($task['created_at']);
        $dueTime = $task['due_date'] ? strtotime($task['due_date'] . ' ' . ($task['due_time'] ?: '23:59:59')) : null;
        
        $modalDateFrom = date('M j, Y - g:i A', $createdTime);
        $modalDateTo = $dueTime ? date('M j, Y', strtotime($task['due_date'])) . ' - ' . $timeStrRaw : 'No Deadline';

        // Urgency logic
        $deadline = null;
        if ($task['due_date']) {
            $dateStr = $task['due_date'];
            $timeStr = $task['due_time'] ? $task['due_time'] : '23:59:59';
            $deadline = (new DateTime("$dateStr $timeStr", new DateTimeZone('Asia/Kolkata')))->format('c');
        }

        // Project Stage formatting
        $projectStageTitle = trim(($task['project_name'] ?? '') . ($task['stage_number'] ? ' - Stage ' . $task['stage_number'] : ''));
        if (!$projectStageTitle) $projectStageTitle = 'Untitled Project';

        // Personal completion timestamp
        $compHist = json_decode($task['completion_history'] ?? '{}', true);
        $myTs = $compHist[$userId] ?? null;
        $isoCompAt = null;
        if ($myTs) {
            try { $isoCompAt = (new DateTime($myTs, new DateTimeZone('Asia/Kolkata')))->format('c'); } catch(Exception $e) {}
        }

        // Badge: map DB enum to JS badge tokens
        $badgeMap = ['High' => 'High', 'Medium' => 'Med', 'Low' => 'Low'];

        $formatted[] = [
            'id'                => (int)$task['id'],
            'title'             => $task['task_description'] ?? 'General Task',
            'desc'              => $task['task_description'] ?? '',
            'projectStage'      => $projectStageTitle,
            'badge'             => $badgeMap[$task['priority']] ?? 'Low',
            'time'              => $isUserDone ? 'Completed' : ($task['due_time'] ? date('h:i A', strtotime($task['due_time'])) : ($task['due_date'] ? date('M j', strtotime($task['due_date'])) : 'No Deadline')),
            'deadline'          => $deadline,
            'checked'           => $isUserDone,
            'status'            => $isUserDone ? 'Completed' : ($task['global_status'] === 'Cancelled' ? 'Cancelled' : ($task['global_status'] === 'In Progress' ? 'In Progress' : 'Pending')),
            'global_status'     => $task['global_status'],
            'completed_at'      => $isoCompAt,
            'my_completed_at'   => $isoCompAt,
            'assignees'         => array_values($assignedNames),
            'assignee_statuses' => $assigneeStatuses,
            'assignedBy'        => $task['created_by_name'] ?? 'System Admin',
            'modalDateFrom'     => $modalDateFrom,
            'modalDateTo'       => $modalDateTo,
            'project_id'        => $task['project_id'],
            'project_name'      => $task['project_name'],
            'stage_id'          => $task['stage_id'],
            'stage_number'      => $task['stage_number'],
            'due_date'          => $task['due_date'],
            'due_time'          => $task['due_time'] ? date('h:i A', strtotime($task['due_time'])) : null,
            'due_time_24'       => $task['due_time'] ? date('H:i', strtotime($task['due_time'])) : null,
            'extension_count'   => (int)$task['extension_count'],
            'extension_history' => $extHistory,
            'previous_due_date' => $task['previous_due_date'],
            'previous_due_time' => $task['previous_due_time'] ? date('h:i A', strtotime($task['previous_due_time'])) : null,
        ];
    }

    echo json_encode([
        'success' => true,
        'date'    => $dateParam,
        'count'   => count($formatted),
        'tasks'   => $formatted,
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Database error',
        'details' => $e->getMessage()
    ]);
}
?>
