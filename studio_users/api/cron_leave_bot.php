<?php
// =====================================================
// api/cron_leave_bot.php
// Nightly "Midnight Auditor" Cron Job
// Scans internal HR architectures to enforce Accountability.
// =====================================================
require_once __DIR__ . '/../../config/db_connect.php';

date_default_timezone_set('Asia/Kolkata');

$logFile = __DIR__ . '/../../logs/cron_leave_bot.log';

function logger($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

logger("Cron Job Started.");

function hasTakenLeaveAction($value): bool {
    if ($value === null) return false;
    $normalized = strtolower(trim((string)$value));
    return in_array($normalized, ['approved', 'rejected'], true);
}

function hasActiveLeaveFollowUpTask(PDO $pdo, int $leaveId, string $baseDesc, array $assigneeIds): array {
    $leaveMarker = '[LEAVE_REQ_ID:' . $leaveId . ']';
    $legacyLike = '%(FOLLOW UP) ' . $baseDesc . '%';

    foreach ($assigneeIds as $uid) {
        $uid = (int)$uid;
        if ($uid <= 0) continue;

        $q = $pdo->prepare("SELECT id, extension_count, extension_history FROM studio_assigned_tasks WHERE project_name = 'ArchitectsHive Systems' AND status NOT IN ('Completed', 'Cancelled') AND (task_description LIKE :marker OR task_description LIKE :legacy) AND FIND_IN_SET(:uid, REPLACE(assigned_to, ' ', '')) > 0 LIMIT 1");
        $q->execute([
            ':marker' => '%' . $leaveMarker . '%',
            ':legacy' => $legacyLike,
            ':uid' => (string)$uid
        ]);
        $row = $q->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $isExtended = ((int)($row['extension_count'] ?? 0) > 0)
                || (!empty($row['extension_history']) && trim((string)$row['extension_history']) !== '[]');

            // For shared tasks, user-specific extended deadlines are kept in studio_task_user_meta.
            if (!$isExtended) {
                try {
                    $mq = $pdo->prepare("SELECT 1 FROM studio_task_user_meta WHERE task_id = :task_id AND user_id = :uid AND meta_key = 'extended_due_date' LIMIT 1");
                    $mq->execute([':task_id' => (int)$row['id'], ':uid' => $uid]);
                    $isExtended = (bool)$mq->fetchColumn();
                } catch (Throwable $e) {
                    // Ignore if meta table does not exist in older installs.
                }
            }

            return ['exists' => true, 'extended' => $isExtended, 'task_id' => (int)$row['id']];
        }
    }

    return ['exists' => false, 'extended' => false, 'task_id' => null];
}

try {
    // 1. Sweep the database for any Leave Applications still stuck functionally 'pending'
    $stmt = $pdo->prepare("SELECT lr.id, lr.user_id, lr.leave_type, lr.start_date, lr.end_date, lr.reason, lr.manager_action_by, lr.hr_action_by, lr.manager_approval, lr.hr_approval, u.username as employee_name, lt.name as leave_type_name FROM leave_request lr JOIN users u ON lr.user_id = u.id LEFT JOIN leave_types lt ON lr.leave_type = lt.id WHERE lr.status = 'pending' AND u.status = 'Active' AND lr.start_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)");
    $stmt->execute();
    $pendingLeaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logger("Found " . count($pendingLeaves) . " pending leave applications.");

    $totalSpawned = 0;

    foreach ($pendingLeaves as $lr) {
        $reassignIds = [];
        $reassignNames = [];
        $missingDesc = [];

        // Determine who is still pending action
        $managerActed = hasTakenLeaveAction($lr['manager_approval'] ?? null);
        $hrActed = hasTakenLeaveAction($lr['hr_approval'] ?? null);

        if (($lr['manager_approval'] ?? null) === 'approved' && !$hrActed) {
            $hrStmt = $pdo->prepare("SELECT id, username FROM users WHERE LOWER(role) = 'hr' AND LOWER(COALESCE(status, '')) = 'active'");
            $hrStmt->execute();
            foreach ($hrStmt->fetchAll() as $hr) {
                $reassignIds[] = $hr['id'];
                $reassignNames[] = $hr['username'];
            }
            $missingDesc[] = "HR";
        }
        
        if (!$managerActed) {
            // manager_action_by stores approver at creation time in this flow.
            // Fallback to mapping table for older rows where approver id is missing.
            $managerId = !empty($lr['manager_action_by']) ? (int)$lr['manager_action_by'] : 0;
            $mgr = false;
            if ($managerId <= 0) {
                $mapStmt = $pdo->prepare("SELECT manager_id FROM leave_approval_mapping WHERE employee_id = ? LIMIT 1");
                $mapStmt->execute([$lr['user_id']]);
                $mapRow = $mapStmt->fetch(PDO::FETCH_ASSOC);
                $managerId = $mapRow ? (int)$mapRow['manager_id'] : 0;
            }

            if ($managerId > 0) {
                $mgrStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ? AND LOWER(COALESCE(status, '')) = 'active'");
                $mgrStmt->execute([$managerId]);
                $mgr = $mgrStmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($mgr) {
                $reassignIds[] = $mgr['id'];
                $reassignNames[] = $mgr['username'];
                $missingDesc[] = "Manager";
            }
        }

        if (!empty($reassignIds)) {
            $assignedToCSV = implode(',', array_unique($reassignIds));
            $assignedNamesCSV = implode(', ', array_unique($reassignNames));
            $group = implode(' and ', $missingDesc);

            // Fetch Date String appropriately
            $range = ($lr['start_date'] === $lr['end_date']) ? $lr['start_date'] : "{$lr['start_date']} to {$lr['end_date']}";
            
            // Build the Strict Tracker Base String to check against
            $leaveTypeName = !empty($lr['leave_type_name']) ? $lr['leave_type_name'] : 'Leave';
            $baseDesc = "Please verify the {$leaveTypeName} request from {$lr['employee_name']} for {$range}.";

            $dup = hasActiveLeaveFollowUpTask($pdo, (int)$lr['id'], $baseDesc, array_unique($reassignIds));

            if (!$dup['exists']) {
                // Execute Auto-Assignment Pipeline to spawn for the newly arrived day/morning
                $leaveMarker = '[LEAVE_REQ_ID:' . (int)$lr['id'] . ']';
                $clonedDesc = "(FOLLOW UP) " . $baseDesc . " " . $leaveMarker . "\n[System Audit: Still pending formal action from $group]";
                
                $tStmt = $pdo->prepare("INSERT INTO studio_assigned_tasks (project_name, stage_number, task_description, priority, assigned_to, assigned_names, due_date, due_time, status, created_by, is_system_task, created_at) VALUES ('ArchitectsHive Systems', 'Verification', ?, 'High', ?, ?, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '18:00:00', 'Pending', ?, 1, NOW())");
                
                // Set the exact applicant user as creator explicitly to obey FK constraints exactly as before
                $tStmt->execute([$clonedDesc, $assignedToCSV, $assignedNamesCSV, $lr['user_id']]);

                // Create Global Detailed Logs explicitly bypassing humans
                $newTaskID = $pdo->lastInsertId();
                $logSubStmt = $pdo->prepare("INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read) VALUES (?, 'task_assigned', 'task', ?, ?, ?, NOW(), 0)");
                
                $logMetadata = json_encode([
                    'task_id' => $newTaskID,
                    'assigned_by_name' => 'Conneqts Bot',
                    'project_name' => 'ArchitectsHive Systems',
                    'assigned_to' => $assignedToCSV,
                    'assigned_names' => $assignedNamesCSV,
                    'due_date' => date('Y-m-d', strtotime('+1 day')),
                    'due_time' => '18:00:00'
                ]);

                foreach (array_unique($reassignIds) as $aUid) {
                    $logSubStmt->execute([
                        $aUid,
                        $newTaskID,
                        "Conneqts Bot: You missed checking a leave request for {$lr['employee_name']}. Please resolve this today by 06:00 PM.",
                        $logMetadata
                    ]);
                }

                logger("Successfully spawned task ID $newTaskID for leave ID {$lr['id']} (Assigned to: $assignedNamesCSV).");
                $totalSpawned++;
            } else {
                if ($dup['extended']) {
                    logger("Skipped leave ID {$lr['id']}: Active follow-up task #{$dup['task_id']} exists and has already been extended.");
                } else {
                    logger("Skipped leave ID {$lr['id']}: Active follow-up task #{$dup['task_id']} already assigned and pending action.");
                }
            }
        }
    }

    logger("Cron Job Finished. Total tasks spawned: $totalSpawned.");
    echo json_encode(["status" => "success", "message" => "Conneqts Bot swept the board. Spawned $totalSpawned follow-up tasks!"]);

} catch (Exception $e) {
    logger("CRITICAL ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
