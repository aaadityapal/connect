<?php
// ===================================================================
// api/cron_travel_bot.php
// "Conneqts Bot" — Nightly Travel Expense Accountability Cron
//
// Rule:
//   Every night, scan all PENDING travel expenses.
//   If the assigned approvers already marked the task as "Done/Completed"
//   but the expense is STILL pending (not approved or rejected),
//   spawn a fresh follow-up task for those same approvers due tomorrow.
// ===================================================================
require_once __DIR__ . '/../../config/db_connect.php';

date_default_timezone_set('Asia/Kolkata');

$logFile = __DIR__ . '/../../logs/cron_travel_bot.log';

function tlog($message) {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$ts] $message" . PHP_EOL, FILE_APPEND);
}

tlog("=== Conneqts Bot (Travel) Started ===");

try {
    // ─── 1. Find all PENDING travel expenses ──────────────────────────
    $pendingStmt = $pdo->query("
        SELECT
            te.id            AS expense_id,
            te.user_id       AS employee_id,
            u.username       AS employee_name,
            te.purpose,
            te.travel_date,
            te.amount,
            tem.manager_id,
            tem.hr_id,
            tem.senior_manager_id
        FROM travel_expenses te
        JOIN users u ON u.id = te.user_id
        LEFT JOIN travel_expense_mapping tem ON tem.employee_id = te.user_id
        WHERE te.status = 'pending'
    ");
    $pendingExpenses = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

    tlog("Found " . count($pendingExpenses) . " pending travel expense(s).");

    // Group expenses by employee_id so we send 1 task per employee's approvers
    $grouped = [];
    foreach ($pendingExpenses as $exp) {
        $eid = $exp['employee_id'];
        if (!isset($grouped[$eid])) {
            $grouped[$eid] = [
                'employee_name'      => $exp['employee_name'],
                'manager_id'         => $exp['manager_id'],
                'hr_id'              => $exp['hr_id'],
                'senior_manager_id'  => $exp['senior_manager_id'],
                'expenses'           => []
            ];
        }
        $grouped[$eid]['expenses'][] = $exp;
    }

    $totalSpawned = 0;

    foreach ($grouped as $employeeId => $data) {
        $employeeName = $data['employee_name'];
        $expenseCount = count($data['expenses']);
        $totalAmount  = array_sum(array_column($data['expenses'], 'amount'));

        // ─── 2. Collect approver IDs ──────────────────────────────────
        $approverIds = array_filter([
            $data['manager_id'],
            $data['hr_id'],
            $data['senior_manager_id']
        ]);

        if (empty($approverIds)) {
            tlog("Skipped employee ID $employeeId ($employeeName): No approvers mapped.");
            continue;
        }

        // ─── 3. Check if original task was marked done without approval ─
        // Logic: find any studio_assigned_tasks for this employee's expenses
        // that are Completed but the expense is still pending.
        $completedTaskStmt = $pdo->prepare("
            SELECT id FROM studio_assigned_tasks
            WHERE project_name = 'ArchitectsHive Systems'
              AND task_description LIKE ?
              AND status IN ('Completed', 'Done')
            LIMIT 1
        ");
        $completedTaskStmt->execute(["%Travel expense claim submitted by {$employeeName}%"]);
        $hasCompletedTask = $completedTaskStmt->fetch();

        // Also check: is there already a pending FOLLOW-UP task for today?
        $existingFollowUp = $pdo->prepare("
            SELECT id FROM studio_assigned_tasks
            WHERE project_name = 'ArchitectsHive Systems'
              AND task_description LIKE ?
              AND status NOT IN ('Completed', 'Done', 'Cancelled')
            LIMIT 1
        ");
        $existingFollowUp->execute(["%[FOLLOW UP] Travel expense by {$employeeName}%"]);
        $alreadyPending = $existingFollowUp->fetch();

        if ($alreadyPending) {
            tlog("Skipped $employeeName: A follow-up task already exists and is pending.");
            continue;
        }

        // ─── 4. Determine reason for spawning ────────────────────────
        // Spawn if:
        //   A) Task was marked done but expense is still pending (approver closed without acting)
        //   B) No task exists at all (edge case — original task may have been deleted)
        $noActiveTask = !$hasCompletedTask;

        $checkActiveStmt = $pdo->prepare("
            SELECT id FROM studio_assigned_tasks
            WHERE project_name = 'ArchitectsHive Systems'
              AND task_description LIKE ?
              AND status NOT IN ('Completed', 'Done', 'Cancelled')
            LIMIT 1
        ");
        $checkActiveStmt->execute(["%Travel expense claim submitted by {$employeeName}%"]);
        $hasActivePrimaryTask = $checkActiveStmt->fetch();

        // Only spawn follow-up if task was closed BUT expense is still pending
        if (!$hasCompletedTask && $hasActivePrimaryTask) {
            tlog("Skipped $employeeName: Primary task still open — no follow-up needed yet.");
            continue;
        }

        // ─── 5. Fetch approver names ──────────────────────────────────
        $placeholders = implode(',', array_fill(0, count($approverIds), '?'));
        $nameStmt = $pdo->prepare("SELECT id, username FROM users WHERE id IN ($placeholders)");
        $nameStmt->execute(array_values($approverIds));
        $approverRows     = $nameStmt->fetchAll(PDO::FETCH_ASSOC);
        $assignedToCSV    = implode(',', array_column($approverRows, 'id'));
        $assignedNamesCSV = implode(', ', array_column($approverRows, 'username'));

        // ─── 6. Build follow-up task description ─────────────────────
        $reason    = $hasCompletedTask ? "task was closed without approval" : "no active task found";
        $taskDesc  = "[FOLLOW UP] Travel expense by {$employeeName} — {$expenseCount} entr" . ($expenseCount > 1 ? 'ies' : 'y') . " (₹{$totalAmount}) is still PENDING.";
        $taskDesc .= "\nReason: The {$reason}. Please log in and approve or reject the claim.";
        $taskDesc .= "\n[Conneqts Bot · " . date('d M Y') . "]";

        // ─── 7. Insert follow-up task ─────────────────────────────────
        $tStmt = $pdo->prepare("
            INSERT INTO studio_assigned_tasks
                (project_name, stage_number, task_description, priority,
                 assigned_to, assigned_names, due_date, due_time,
                 status, created_by, is_system_task, created_at)
            VALUES
                ('ArchitectsHive Systems', 'Verification', ?, 'High',
                 ?, ?, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '18:00:00',
                 'Pending', ?, 1, NOW())
        ");
        $tStmt->execute([$taskDesc, $assignedToCSV, $assignedNamesCSV, $employeeId]);
        $newTaskId = $pdo->lastInsertId();

        // ─── 8. Activity log per approver ────────────────────────────
        $logMeta = json_encode([
            'task_id'          => $newTaskId,
            'assigned_by_name' => 'Conneqts Bot',
            'project_name'     => 'ArchitectsHive Systems',
            'assigned_to'      => $assignedToCSV,
            'assigned_names'   => $assignedNamesCSV,
            'due_date'         => date('Y-m-d', strtotime('+1 day')),
            'due_time'         => '18:00:00',
            'employee_name'    => $employeeName,
            'expense_count'    => $expenseCount,
            'total_amount'     => $totalAmount,
            'reason'           => $reason
        ]);

        $logStmt = $pdo->prepare("
            INSERT INTO global_activity_logs
                (user_id, action_type, entity_type, entity_id,
                 description, metadata, created_at, is_read)
            VALUES (?, 'task_assigned', 'task', ?, ?, ?, NOW(), 0)
        ");

        foreach (array_column($approverRows, 'id') as $aUid) {
            $logStmt->execute([
                $aUid,
                $newTaskId,
                "Conneqts Bot: {$employeeName}'s travel expense claim (₹{$totalAmount}) is still pending. You previously closed the task without acting — please resolve by tomorrow 6:00 PM.",
                $logMeta
            ]);
        }

        tlog("Spawned follow-up task ID $newTaskId for $employeeName (Assigned to: $assignedNamesCSV). Reason: $reason.");
        $totalSpawned++;
    }

    tlog("=== Conneqts Bot (Travel) Done. Spawned $totalSpawned follow-up task(s). ===");
    echo json_encode([
        'status'  => 'success',
        'message' => "Conneqts Bot swept travel expenses. Spawned $totalSpawned follow-up task(s)."
    ]);

} catch (Exception $e) {
    tlog("CRITICAL ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
