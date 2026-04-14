<?php
// =====================================================
// api/cron_overtime_bot.php
// Nightly "Midnight Auditor" Cron Job — Overtime Edition
// Scans submitted overtime requests that are still
// pending (not approved / rejected) and spawns a
// follow-up task for the responsible manager until
// the overtime is formally actioned.
// =====================================================
require_once __DIR__ . '/../../config/db_connect.php';

date_default_timezone_set('Asia/Kolkata');

$logFile = __DIR__ . '/../../logs/cron_overtime_bot.log';

function logger($message)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

logger("Overtime Cron Job Started.");

try {
    // ─── 1. Find all submitted overtime requests still unresolved ─────────────
    // "Unresolved" = status is 'submitted' (employee submitted, manager hasn't acted)
    // We also include 'pending' rows in overtime_requests in case the status
    // was set that way, and check attendance.overtime_status as a fallback.
    $stmt = $pdo->prepare("
        SELECT
            oreq.id                   AS oreq_id,
            oreq.attendance_id,
            oreq.user_id              AS employee_id,
            oreq.date,
            oreq.overtime_hours,
            oreq.manager_id,
            oreq.status               AS oreq_status,
            u.username                AS employee_name,
            mgr.id                    AS mapped_manager_id,
            mgr.username              AS mapped_manager_name
        FROM overtime_requests oreq
        JOIN users u ON oreq.user_id = u.id
        -- Fetch the manager from overtime_approval_mapping (authoritative source)
        LEFT JOIN overtime_approval_mapping oam ON oam.employee_id = oreq.user_id
        LEFT JOIN users mgr ON mgr.id = oam.manager_id
        WHERE oreq.status NOT IN ('approved', 'rejected', 'paid')
          AND oreq.status IN ('submitted', 'pending')
          AND CAST(oreq.overtime_hours AS DECIMAL(10,2)) >= 1.5
          AND oreq.date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
    ");
    $stmt->execute();
    $pendingOvertimes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logger("Found " . count($pendingOvertimes) . " unresolved submitted overtime requests.");

    $totalSpawned = 0;

    foreach ($pendingOvertimes as $ot) {

        // ─── 2. Resolve the responsible manager ──────────────────────────────
        // Priority: overtime_approval_mapping → fallback to manager_id on oreq
        $managerId = $ot['mapped_manager_id'] ?: $ot['manager_id'];
        $managerName = $ot['mapped_manager_name'] ?: null;

        // If still no manager name, fetch it
        if ($managerId && !$managerName) {
            $mStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $mStmt->execute([$managerId]);
            $mRow = $mStmt->fetch(PDO::FETCH_ASSOC);
            $managerName = $mRow ? $mRow['username'] : 'Manager';
        }

        if (!$managerId) {
            logger("Skipped oreq ID {$ot['oreq_id']}: No manager could be resolved for employee {$ot['employee_name']}.");
            continue;
        }

        $assignedToCSV = (string) $managerId;
        $assignedNamesCSV = $managerName;

        $otDate = $ot['date'];
        $otHours = number_format(floatval($ot['overtime_hours']), 1);
        $empName = $ot['employee_name'];

        // ─── 3. Build the base description (used for de-duplication) ─────────
        $baseDesc = "Review and approve the overtime submission from {$empName} for {$otDate}. Calculated OT: {$otHours}h.";

        // ─── 4. De-duplication check ─────────────────────────────────────────
        // Do NOT spawn a new follow-up if ANY active task (original OR follow-up)
        // already exists for this overtime and is still alive:
        //   • status   NOT IN ('Completed', 'Cancelled')
        //   • due_date >= TODAY  ← catches tasks that were EXTENDED to a future date
        //
        // This means: if the manager extended the task instead of completing it,
        // the bot will NOT create a second follow-up task.
        $checkDup = $pdo->prepare("
            SELECT id FROM studio_assigned_tasks
            WHERE project_name = 'ArchitectsHive Systems'
              AND task_description LIKE ?
              AND `status` NOT IN ('Completed', 'Cancelled')
              AND due_date >= CURDATE()
            LIMIT 1
        ");
        // Match on base description — catches BOTH the original task and any FOLLOW UP
        $checkDup->execute(["%$baseDesc%"]);

        if ($checkDup->fetch()) {
            logger("Skipped oreq ID {$ot['oreq_id']}: An active/extended task already exists for this OT.");
            continue;
        }

        // ─── 5. Spawn the follow-up task ──────────────────────────────────────
        $followUpDesc = "(FOLLOW UP) " . $baseDesc
            . "\n[System Audit: Overtime still awaiting formal Approval / Rejection by {$managerName}]";

        // Resolve project_id for FK constraint
        $projStmt = $pdo->prepare("SELECT id FROM projects WHERE LOWER(title) LIKE '%architectshive systems%' LIMIT 1");
        $projStmt->execute();
        $projRow = $projStmt->fetch(PDO::FETCH_ASSOC);
        $botProjectId = $projRow ? $projRow['id'] : null;

        $tStmt = $pdo->prepare("
            INSERT INTO studio_assigned_tasks
                (project_id, project_name, stage_number, task_description, priority,
                 assigned_to, assigned_names, due_date, due_time, status,
                 created_by, is_system_task, created_at)
            VALUES
                (?, 'ArchitectsHive Systems', 'Verification', ?, 'High',
                 ?, ?, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '17:45:00', 'Pending',
                 ?, 1, NOW())
        ");
        $tStmt->execute([
            $botProjectId,
            $followUpDesc,
            $assignedToCSV,
            $assignedNamesCSV,
            $ot['employee_id']    // created_by = employee, same FK pattern as cron_leave_bot.php
        ]);
        $newTaskId = $pdo->lastInsertId();

        // ─── 6. Activity log for the manager ─────────────────────────────────
        $logMetadata = json_encode([
            'task_id' => $newTaskId,
            'assigned_by_name' => 'Conneqts Bot',
            'project_name' => 'ArchitectsHive Systems',
            'assigned_to' => $assignedToCSV,
            'assigned_names' => $assignedNamesCSV,
            'due_date' => date('Y-m-d', strtotime('+1 day')),
            'due_time' => '17:45:00',
            'overtime_date' => $otDate,
            'overtime_hours' => $otHours
        ]);

        $logStmt = $pdo->prepare("
            INSERT INTO global_activity_logs
                (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read)
            VALUES (?, 'task_assigned', 'task', ?, ?, ?, NOW(), 0)
        ");
        $logStmt->execute([
            $managerId,
            $newTaskId,
            "Conneqts Bot: You have a pending overtime request from {$empName} ({$otDate}, {$otHours}h) that is still unactioned. Please Approve or Reject it by 05:45 PM.",
            $logMetadata
        ]);

        logger("Spawned follow-up task ID $newTaskId for oreq ID {$ot['oreq_id']} — Employee: {$empName}, Manager: {$managerName}.");
        $totalSpawned++;
    }

    logger("Overtime Cron Job Finished. Total follow-up tasks spawned: $totalSpawned.");
    echo json_encode([
        "status" => "success",
        "message" => "Conneqts Bot (OT) swept the board. Spawned $totalSpawned follow-up task(s)."
    ]);

} catch (Exception $e) {
    logger("CRITICAL ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>