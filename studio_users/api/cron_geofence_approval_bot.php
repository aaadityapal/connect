<?php
// =====================================================
// api/cron_geofence_approval_bot.php
// Nightly Cron Job for Geofence Approvals
// Scans for punch-in/out events that occurred outside
// the geofence and are still pending approval. If the
// associated task was marked 'Completed' without the
// punch being approved/rejected, it spawns a new
// follow-up task for the responsible manager.
// =====================================================
require_once __DIR__ . '/../../../config/db_connect.php';

date_default_timezone_set('Asia/Kolkata');

$logFile = __DIR__ . '/../../../logs/cron_geofence_approval_bot.log';

function logger($message)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

logger("Geofence Approval Cron Job Started.");

try {
    // ─── 1. Find all unresolved geofence punches ───────────────────────────
    // "Unresolved" = `within_geofence` is 0, and `geofence_approval_status` is 'pending'.
    // We look back 60 days to avoid processing very old records.
    $stmt = $pdo->prepare("
        SELECT
            a.id AS attendance_id,
            a.user_id AS employee_id,
            a.login_time,
            a.logout_time,
            a.event_date,
            u.username AS employee_name,
            COALESCE(gam.manager_id, lam.manager_id) AS manager_id
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        -- Get manager from geofence mapping first
        LEFT JOIN geofence_approval_mapping gam ON gam.employee_id = a.user_id
        -- Fallback to leave approval mapping
        LEFT JOIN leave_approval_mapping lam ON lam.employee_id = a.user_id
        WHERE a.within_geofence = 0
          AND a.geofence_approval_status = 'pending'
          AND a.event_date >= '2026-04-01'
    ");
    $stmt->execute();
    $pendingPunches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logger("Found " . count($pendingPunches) . " unresolved geofence approval requests.");

    $totalSpawned = 0;

    foreach ($pendingPunches as $punch) {
        $managerId = $punch['manager_id'];
        $employeeName = $punch['employee_name'];
        $eventDate = $punch['event_date'];
        $attendanceId = $punch['attendance_id'];

        if (!$managerId) {
            logger("Skipped attendance ID {$attendanceId}: No manager could be resolved for employee {$employeeName}.");
            continue;
        }

        // Fetch manager's name for logging and descriptions
        $mStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $mStmt->execute([$managerId]);
        $managerName = ($mStmt->fetch(PDO::FETCH_ASSOC)['username']) ?: 'Manager';

        // Determine if it was a punch-in or punch-out based on what time is available
        $punchType = !empty($punch['logout_time']) && $punch['logout_time'] !== '00:00:00' ? 'Punch-Out' : 'Punch-In';
        $punchTime = $punchType === 'Punch-In' ? $punch['login_time'] : $punch['logout_time'];


        // ─── 2. Build the base description for de-duplication ──────────────
        // This must EXACTLY match the description generated in `punch.php`
        $baseDesc = "Geofence {$punchType} Approval for {$employeeName} on " . date('d-m-Y', strtotime($eventDate)) . " at " . date('h:i A', strtotime($punchTime));

        // ─── 3. De-duplication check ─────────────────────────────────────────
        // Check if an active task for this approval already exists.
        // An active task is one NOT 'Completed' or 'Cancelled' OR one whose
        // due date has been extended into the future.
        $checkDup = $pdo->prepare("
            SELECT id, status FROM studio_assigned_tasks
            WHERE project_name = 'ArchitectsHive Systems'
              AND task_description LIKE ?
              AND (
                  status NOT IN ('Completed', 'Cancelled')
                  OR due_date >= CURDATE()
              )
            LIMIT 1
        ");
        $checkDup->execute(["%$baseDesc%"]);
        $existingTask = $checkDup->fetch(PDO::FETCH_ASSOC);

        // If a task exists and its status is NOT 'Completed', it's still active.
        // If a task exists and its status IS 'Completed' but due_date is in the future, it was extended.
        // In either case, we skip. We only proceed if the task was marked 'Completed' on a past due date.
        if ($existingTask) {
             if ($existingTask['status'] !== 'Completed') {
                logger("Skipped attendance ID {$attendanceId}: An active task (status: {$existingTask['status']}) already exists.");
                continue;
             }
             // If status is 'Completed' but due_date is in the future, it means manager extended it then completed it.
             // We should still respect the extension and not create a new one.
             if (strtotime($existingTask['due_date']) >= strtotime(date('Y-m-d'))) {
                 logger("Skipped attendance ID {$attendanceId}: An extended task (due: {$existingTask['due_date']}) already exists.");
                 continue;
             }
        }

        // At this point, either no task was found, or a task was found but it was
        // marked 'Completed' with a past due date, while the punch itself is still 'pending'.
        // This is the exact condition we want to trigger a follow-up.

        // ─── 4. Spawn the follow-up task ──────────────────────────────────────
        $followUpDesc = "(FOLLOW UP) " . $baseDesc
            . "\n[System Audit: Geofence punch still awaiting formal Approval / Rejection by {$managerName}]";

        // Resolve project_id for FK constraint
        $projStmt = $pdo->prepare("SELECT id FROM projects WHERE LOWER(title) LIKE '%architectshive systems%' LIMIT 1");
        $projStmt->execute();
        $botProjectId = ($projStmt->fetch(PDO::FETCH_ASSOC)['id']) ?: null;

        if (!$botProjectId) {
            logger("CRITICAL: Could not find the 'ArchitectsHive Systems' project ID.");
            continue; // Cannot proceed without a valid project ID
        }

        // Determine the new due date based on the original logic from punch.php
        $newDueDate = ($punchType === 'Punch-In') ? date('Y-m-d') : date('Y-m-d', strtotime('+1 day'));
        $newDueTime = ($punchType === 'Punch-In') ? '10:00:00' : '14:00:00';


        $tStmt = $pdo->prepare("
            INSERT INTO studio_assigned_tasks
                (project_id, project_name, stage_number, task_description, priority,
                 assigned_to, assigned_names, due_date, due_time, status,
                 created_by, is_system_task, created_at)
            VALUES
                (?, 'ArchitectsHive Systems', 'Verification', ?, 'High',
                 ?, ?, ?, ?, 'Pending',
                 ?, 1, NOW())
        ");
        $tStmt->execute([
            $botProjectId,
            $followUpDesc,
            (string)$managerId,
            $managerName,
            $newDueDate,
            $newDueTime,
            $punch['employee_id'] // created_by = employee
        ]);
        $newTaskId = $pdo->lastInsertId();

        // ─── 5. Activity log for the manager ─────────────────────────────────
        $logMetadata = json_encode([
            'task_id' => $newTaskId,
            'assigned_by_name' => 'Conneqts Bot',
            'project_name' => 'ArchitectsHive Systems',
            'assigned_to' => (string)$managerId,
            'assigned_names' => $managerName,
            'due_date' => $newDueDate,
            'due_time' => $newDueTime,
            'event_date' => $eventDate,
            'punch_type' => $punchType
        ]);

        $logDesc = "Conneqts Bot: You have a pending geofence approval for {$employeeName} ({$eventDate}) that is still unactioned. Please Approve or Reject it.";

        $logStmt = $pdo->prepare("
            INSERT INTO global_activity_logs
                (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read)
            VALUES (?, 'task_assigned', 'task', ?, ?, ?, NOW(), 0)
        ");
        $logStmt->execute([
            $managerId,
            $newTaskId,
            $logDesc,
            $logMetadata
        ]);

        logger("Spawned follow-up task ID $newTaskId for attendance ID {$attendanceId} — Employee: {$employeeName}, Manager: {$managerName}.");
        $totalSpawned++;
    }

    logger("Geofence Approval Cron Job Finished. Total follow-up tasks spawned: $totalSpawned.");
    echo json_encode([
        "status" => "success",
        "message" => "Conneqts Bot (Geofence) swept for pending approvals. Spawned $totalSpawned follow-up task(s)."
    ]);

} catch (Exception $e) {
    logger("CRITICAL ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>