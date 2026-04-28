<?php
// =====================================================
// api/cron_geofence_bot.php
// Nightly Conneqts Bot — Geofence Outside Approval Follow-up
// Re-spawns manager tasks for outside-geofence attendance points
// that remain unresolved (not approved/rejected), until actioned.
// Skips spawning if an active/extended task already exists.
// =====================================================
require_once __DIR__ . '/../../config/db_connect.php';

date_default_timezone_set('Asia/Kolkata');

$logFile = __DIR__ . '/../../logs/cron_geofence_bot.log';

function gflog($message)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

function resolveManagersForEmployee(PDO $pdo, int $employeeId): array
{
    $managers = [];

    // 1) Preferred: dedicated geofence mapping
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT u.id, u.username
            FROM geofence_approval_mapping gam
            INNER JOIN users u ON u.id = gam.manager_id
            WHERE gam.employee_id = ?");
        $stmt->execute([$employeeId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $mid = (int)($r['id'] ?? 0);
            if ($mid > 0) {
                $managers[$mid] = $r['username'] ?? ('Manager #' . $mid);
            }
        }
    } catch (Throwable $e) {
        // Mapping table might not exist in old environments.
        gflog('resolveManagersForEmployee geofence_approval_mapping fallback: ' . $e->getMessage());
    }

    // 2) Fallback: leave approval mapping
    if (count($managers) === 0) {
        try {
            $stmt = $pdo->prepare("SELECT DISTINCT u.id, u.username
                FROM leave_approval_mapping lam
                INNER JOIN users u ON u.id = lam.manager_id
                WHERE lam.employee_id = ?");
            $stmt->execute([$employeeId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $mid = (int)($r['id'] ?? 0);
                if ($mid > 0) {
                    $managers[$mid] = $r['username'] ?? ('Manager #' . $mid);
                }
            }
        } catch (Throwable $e) {
            gflog('resolveManagersForEmployee leave_approval_mapping fallback failed: ' . $e->getMessage());
        }
    }

    return $managers;
}

function resolveZoneName(PDO $pdo, ?int $geofenceId): string
{
    if (empty($geofenceId)) {
        return 'Unknown Zone';
    }

    try {
        $stmt = $pdo->prepare('SELECT name FROM geofence_locations WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$geofenceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['name'])) {
            return $row['name'];
        }
    } catch (Throwable $e) {
        gflog('resolveZoneName failed: ' . $e->getMessage());
    }

    return 'Unknown Zone';
}

function resolveBotProjectId(PDO $pdo): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE LOWER(title) LIKE '%architectshive systems%' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

function hasActiveOrExtendedTask(PDO $pdo, string $marker): bool
{
    $stmt = $pdo->prepare("SELECT id
        FROM studio_assigned_tasks
        WHERE project_name = 'ArchitectsHive Systems'
          AND task_description LIKE ?
          AND LOWER(COALESCE(status, 'pending')) NOT IN ('completed', 'cancelled')
          AND due_date >= CURDATE()
        LIMIT 1");
    $stmt->execute(['%' . $marker . '%']);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function spawnGeofenceFollowUpTask(PDO $pdo, array $ctx, array $managerMap, ?int $projectId): ?int
{
    $employeeId = (int)$ctx['employee_id'];
    $employeeName = (string)$ctx['employee_name'];
    $attendanceId = (int)$ctx['attendance_id'];
    $actionType = (string)$ctx['action_type'];
    $reason = trim((string)$ctx['outside_reason']);
    $distance = $ctx['distance_from_geofence'];
    $zoneName = (string)$ctx['zone_name'];
    $eventDate = (string)$ctx['attendance_date'];
    $eventTime = (string)$ctx['event_time'];

    $assignedIds = array_keys($managerMap);
    if (count($assignedIds) === 0) {
        return null;
    }

    $assignedToCSV = implode(',', $assignedIds);
    $assignedNamesCSV = implode(', ', array_values($managerMap));

    $humanAction = $actionType === 'punch_out' ? 'Punch Out' : 'Punch In';
    $marker = '[GF_APPROVAL:' . $attendanceId . ':' . $actionType . ']';

    $distanceText = 'N/A';
    if ($distance !== null && $distance !== '') {
        $d = (float)$distance;
        $distanceText = $d >= 1000 ? number_format($d / 1000, 2) . ' km' : round($d) . ' m';
    }

    // Follow-up cadence: next day, with action-specific SLA time.
    $dueDate = date('Y-m-d', strtotime('+1 day'));
    $dueTime = ($actionType === 'punch_out') ? '14:00:00' : '10:00:00';

    $baseDesc = "Geofence outside-radius approval needed for {$employeeName}. Event: {$humanAction} on {$eventDate} at {$eventTime}. Zone: {$zoneName}. Distance: {$distanceText}.";

    $followUpDesc = "(FOLLOW UP) " . $baseDesc
        . (!empty($reason) ? " Reason: {$reason}." : '')
        . " [System Audit: Geofence request still pending manager action] "
        . $marker;

    $ins = $pdo->prepare("INSERT INTO studio_assigned_tasks
        (project_id, project_name, stage_number, task_description, priority,
         assigned_to, assigned_names, due_date, due_time, status,
         created_by, is_system_task, created_at)
        VALUES
        (?, 'ArchitectsHive Systems', 'Geofence Approval', ?, 'High',
         ?, ?, ?, ?, 'Pending',
         ?, 1, NOW())");

    $ins->execute([
        $projectId,
        $followUpDesc,
        $assignedToCSV,
        $assignedNamesCSV,
        $dueDate,
        $dueTime,
        $employeeId
    ]);

    $newTaskId = (int)$pdo->lastInsertId();

    $meta = json_encode([
        'task_id' => $newTaskId,
        'assigned_by_name' => 'Conneqts Bot',
        'project_name' => 'ArchitectsHive Systems',
        'assigned_to' => $assignedToCSV,
        'assigned_names' => $assignedNamesCSV,
        'due_date' => $dueDate,
        'due_time' => $dueTime,
        'attendance_id' => $attendanceId,
        'action_type' => $actionType
    ]);

    $logStmt = $pdo->prepare("INSERT INTO global_activity_logs
        (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read)
        VALUES (?, 'task_assigned', 'task', ?, ?, ?, NOW(), 0)");

    foreach ($assignedIds as $mid) {
        $msg = "Conneqts Bot: {$employeeName}'s {$humanAction} geofence exception is still pending. Please approve or reject by "
             . date('d M Y', strtotime($dueDate)) . ' at ' . date('h:i A', strtotime($dueTime)) . '.';
        $logStmt->execute([$mid, $newTaskId, $msg, $meta]);
    }

    return $newTaskId;
}

gflog('Geofence Cron Job Started.');

try {
    $projectId = resolveBotProjectId($pdo);

    // Scan unresolved outside-geofence requests from recent records.
    // Note: selective approval clears punch_in_outside_reason/punch_out_outside_reason individually.
    $stmt = $pdo->prepare("SELECT
            a.id AS attendance_id,
            a.user_id AS employee_id,
            a.date AS attendance_date,
            a.punch_in,
            a.punch_out,
            a.geofence_id,
            a.distance_from_geofence,
            a.approval_status,
            a.punch_in_outside_reason,
            a.punch_out_outside_reason,
            u.username AS employee_name
        FROM attendance a
        INNER JOIN users u ON u.id = a.user_id
        WHERE a.approval_status = 'pending'
          AND (
                (a.punch_in_outside_reason IS NOT NULL AND TRIM(a.punch_in_outside_reason) <> '')
             OR (a.punch_out_outside_reason IS NOT NULL AND TRIM(a.punch_out_outside_reason) <> '')
          )
          AND a.date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        ORDER BY a.date DESC, a.id DESC");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    gflog('Found ' . count($rows) . ' pending geofence attendance records.');

    $spawned = 0;
    $skippedDueToActive = 0;
    $skippedNoManager = 0;

    foreach ($rows as $r) {
        $employeeId = (int)$r['employee_id'];
        $employeeName = $r['employee_name'] ?: 'Employee';
        $attendanceId = (int)$r['attendance_id'];
        $zoneName = resolveZoneName($pdo, isset($r['geofence_id']) ? (int)$r['geofence_id'] : null);
        $managerMap = resolveManagersForEmployee($pdo, $employeeId);

        if (count($managerMap) === 0) {
            $skippedNoManager++;
            gflog("Skipped attendance {$attendanceId}: no manager mapping for employee {$employeeName} ({$employeeId}).");
            continue;
        }

        $candidates = [];
        if (!empty(trim((string)$r['punch_in_outside_reason']))) {
            $candidates[] = [
                'action_type' => 'punch_in',
                'outside_reason' => (string)$r['punch_in_outside_reason'],
                'event_time' => !empty($r['punch_in']) ? date('H:i:s', strtotime((string)$r['punch_in'])) : '00:00:00'
            ];
        }
        if (!empty(trim((string)$r['punch_out_outside_reason']))) {
            $candidates[] = [
                'action_type' => 'punch_out',
                'outside_reason' => (string)$r['punch_out_outside_reason'],
                'event_time' => !empty($r['punch_out']) ? date('H:i:s', strtotime((string)$r['punch_out'])) : '00:00:00'
            ];
        }

        foreach ($candidates as $cand) {
            $marker = '[GF_APPROVAL:' . $attendanceId . ':' . $cand['action_type'] . ']';

            // If there is an active/extended task for this marker, do not spawn.
            if (hasActiveOrExtendedTask($pdo, $marker)) {
                $skippedDueToActive++;
                gflog("Skipped attendance {$attendanceId} ({$cand['action_type']}): active/extended task exists.");
                continue;
            }

            $ctx = [
                'employee_id' => $employeeId,
                'employee_name' => $employeeName,
                'attendance_id' => $attendanceId,
                'action_type' => $cand['action_type'],
                'outside_reason' => $cand['outside_reason'],
                'distance_from_geofence' => $r['distance_from_geofence'],
                'zone_name' => $zoneName,
                'attendance_date' => $r['attendance_date'],
                'event_time' => $cand['event_time']
            ];

            $newTaskId = spawnGeofenceFollowUpTask($pdo, $ctx, $managerMap, $projectId);
            if ($newTaskId) {
                $spawned++;
                gflog("Spawned geofence follow-up task {$newTaskId} for attendance {$attendanceId} ({$cand['action_type']}).");
            }
        }
    }

    gflog("Geofence Cron Job Finished. Spawned={$spawned}, SkippedActive={$skippedDueToActive}, SkippedNoManager={$skippedNoManager}.");

    echo json_encode([
        'status' => 'success',
        'message' => "Conneqts Bot (Geofence) sweep complete. Spawned {$spawned} follow-up task(s).",
        'spawned' => $spawned,
        'skipped_active_or_extended' => $skippedDueToActive,
        'skipped_no_manager_mapping' => $skippedNoManager
    ]);
} catch (Throwable $e) {
    gflog('CRITICAL ERROR: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>