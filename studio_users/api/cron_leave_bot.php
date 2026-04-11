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

try {
    // 1. Sweep the database for any Leave Applications still stuck functionally 'pending'
    $stmt = $pdo->prepare("SELECT lr.id, lr.user_id, lr.leave_type, lr.start_date, lr.end_date, lr.reason, lr.manager_action_by, lr.hr_action_by, lr.manager_approval, lr.hr_approval, u.username as employee_name, lt.name as leave_type_name FROM leave_request lr JOIN users u ON lr.user_id = u.id LEFT JOIN leave_types lt ON lr.leave_type = lt.id WHERE lr.status = 'pending'");
    $stmt->execute();
    $pendingLeaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logger("Found " . count($pendingLeaves) . " pending leave applications.");

    $totalSpawned = 0;

    foreach ($pendingLeaves as $lr) {
        $reassignIds = [];
        $reassignNames = [];
        $missingDesc = [];

        // Determine who failed the check
        if (empty($lr['hr_approval'])) {
            $hrStmt = $pdo->prepare("SELECT id, username FROM users WHERE LOWER(role) = 'hr'");
            $hrStmt->execute();
            foreach ($hrStmt->fetchAll() as $hr) {
                $reassignIds[] = $hr['id'];
                $reassignNames[] = $hr['username'];
            }
            $missingDesc[] = "HR";
        }
        
        if (empty($lr['manager_approval']) && !empty($lr['manager_action_by'])) {
            $mgrStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
            $mgrStmt->execute([$lr['manager_action_by']]);
            if ($mgr = $mgrStmt->fetch()) {
                $reassignIds[] = $mgr['id'];
                $reassignNames[] = $mgr['username'];
            }
            $missingDesc[] = "Manager";
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

            // Prevent infinite task duplication logic: Ensure NO active or pending follow-ups currently exist for this action today
            $checkDup = $pdo->prepare("SELECT id FROM studio_assigned_tasks WHERE project_name = 'ArchitectsHive Systems' AND task_description LIKE ? AND `status` != 'Completed' AND `status` != 'Cancelled' LIMIT 1");
            $checkDup->execute(["%(FOLLOW UP) $baseDesc%"]);

            if (!$checkDup->fetch()) {
                // Execute Auto-Assignment Pipeline to spawn for the newly arrived day/morning
                $clonedDesc = "(FOLLOW UP) " . $baseDesc . "\n[System Audit: Still pending formal action from $group]";
                
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
                logger("Skipped leave ID {$lr['id']}: Duplicate task already exists.");
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
