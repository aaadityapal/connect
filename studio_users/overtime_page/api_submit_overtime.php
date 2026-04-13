<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

require_once '../../config/db_connect.php';

$user_id = $_SESSION['user_id'];
$attendance_id = isset($_POST['attendance_id']) ? (int)$_POST['attendance_id'] : 0;
$report = isset($_POST['report']) ? trim($_POST['report']) : '';

// Count exact words using regex to precisely match JS split(/\s+/) behavior
$words = preg_split('/\s+/', $report, -1, PREG_SPLIT_NO_EMPTY);
$wordCount = count($words);

// MIN 15 words
if ($wordCount < 15) {
    echo json_encode(['status' => 'error', 'message' => "Your report is too short. Please write at least 15 words. (Found $wordCount words)"]);
    exit();
}

try {
    $pdo->beginTransaction();
    // Check if the record is expired (15-day limit)
    $dateCheck = $pdo->prepare("SELECT date, overtime_status FROM attendance WHERE id = :id AND user_id = :user_id");
    $dateCheck->execute([':id' => $attendance_id, ':user_id' => $user_id]);
    $record = $dateCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Record not found.']);
        exit();
    }
    
    $today = new DateTime();
    $attendanceDate = new DateTime($record['date']);
    $interval = $today->diff($attendanceDate);
    
    if ($interval->days > 15 && ($record['overtime_status'] == 'pending' || empty($record['overtime_status']))) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'This overtime request has expired (15-day limit).']);
        exit();
    }

    $manager_id = isset($_POST['manager_id']) ? (int)$_POST['manager_id'] : 0;
    
    if (!$manager_id) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Please select a manager for approval.']);
        exit();
    }

    // Fetch necessary details to calculate OT hours early
    $syncQuery = "
        SELECT a.user_id, a.date, s.end_time, a.punch_out, a.work_report
        FROM attendance a
        LEFT JOIN user_shifts us ON a.user_id = us.user_id 
            AND a.date >= us.effective_from 
            AND (us.effective_to IS NULL OR a.date <= us.effective_to)
        LEFT JOIN shifts s ON us.shift_id = s.id
        WHERE a.id = :aid
    ";
    $syncStmt = $pdo->prepare($syncQuery);
    $syncStmt->execute([':aid' => $attendance_id]);
    $syncData = $syncStmt->fetch(PDO::FETCH_ASSOC);

    $calculatedOt = 0;
    $time_string = '00:00:00';
    if ($syncData && !empty($syncData['end_time']) && !empty($syncData['punch_out'])) {
        $shiftEnd = strtotime($syncData['end_time']);
        $punchOut = strtotime($syncData['punch_out']);
        if ($punchOut > $shiftEnd) {
            $diffMins = floor(($punchOut - $shiftEnd) / 60);
            if ($diffMins >= 90) {
                $calculatedOt = floor($diffMins / 30) * 0.5;
                
                // Also prepare TIME string for attendance table
                $hours = floor($calculatedOt);
                $minutes = round(($calculatedOt - $hours) * 60);
                $time_string = sprintf('%02d:%02d:00', $hours, $minutes);
            }
        }
    }

    // Update the record with the user's overtime reason and mark it as submitted
    $stmt = $pdo->prepare("
        UPDATE attendance 
        SET overtime_reason = :report, overtime_status = 'submitted', overtime_manager_id = :mgr_id, overtime_hours = :ot_hours
        WHERE id = :id AND user_id = :user_id AND overtime_status IN ('pending', 'submitted', 'rejected')
    ");
    
    $stmt->execute([
        ':report' => $report,
        ':id' => $attendance_id,
        ':user_id' => $user_id,
        ':mgr_id' => $manager_id,
        ':ot_hours' => $time_string
    ]);

    if ($stmt->rowCount() >= 0) {
        // SYNC TO overtime_requests table so managers can see it in their dashboard
        try {
            if ($syncData) {
                // Check if already exists
                $checkOreq = $pdo->prepare("SELECT id, resubmit_count, status FROM overtime_requests WHERE attendance_id = ?");
                $checkOreq->execute([$attendance_id]);
                $oreqRow = $checkOreq->fetch(PDO::FETCH_ASSOC);

                if ($oreqRow) {
                    $currentResubmits = (int)$oreqRow['resubmit_count'];
                    
                    // Enforce the limit of 2 resubmissions (only for rejected requests)
                    if ($oreqRow['status'] === 'rejected') {
                        if ($currentResubmits >= 2) {
                             $pdo->rollBack();
                             echo json_encode(['status' => 'error', 'message' => 'This overtime request has already been resubmitted twice and cannot be submitted again.']);
                             exit();
                        }
                        $currentResubmits++;
                    }

                    $updOreq = $pdo->prepare("
                        UPDATE overtime_requests 
                        SET status = 'submitted', 
                            overtime_description = :report, 
                            overtime_hours = :ot_hours,
                            manager_id = :mgr_id,
                            resubmit_count = :resub_count,
                            updated_at = NOW()
                        WHERE id = :oid
                    ");
                    $updOreq->execute([
                        ':report' => $report,
                        ':ot_hours' => $calculatedOt,
                        ':mgr_id' => $manager_id,
                        ':resub_count' => $currentResubmits,
                        ':oid' => $oreqRow['id']
                    ]);
                } else {
                    $insOreq = $pdo->prepare("
                        INSERT INTO overtime_requests 
                        (user_id, attendance_id, date, shift_end_time, punch_out_time, overtime_hours, work_report, overtime_description, manager_id, status, submitted_at, updated_at) 
                        VALUES (:uid, :aid, :date, :set, :pot, :oth, :wr, :desc, :mid, 'submitted', NOW(), NOW())
                    ");
                    $insOreq->execute([
                        ':uid' => $user_id,
                        ':aid' => $attendance_id,
                        ':date' => $syncData['date'],
                        ':set' => $syncData['end_time'] ?: '00:00:00',
                        ':pot' => $syncData['punch_out'] ?: '00:00:00',
                        ':oth' => $calculatedOt,
                        ':wr' => $syncData['work_report'],
                        ':desc' => $report,
                        ':mid' => $manager_id
                    ]);
                }
            }
        } catch (Exception $eSync) {
            error_log("Sync to overtime_requests failed: " . $eSync->getMessage());
            // We continue as the primary update succeeded
        }

        // Log this activity in global_activity_logs
        try {
            $logStmt = $pdo->prepare("
                INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata) 
                VALUES (:uid, 'overtime_submitted', 'attendance', :eid, :description, :meta)
            ");
            
            $logMeta = json_encode([
                'report' => $report,
                'manager_id' => $manager_id,
                'submission_date' => $record['date']
            ]);

            $logStmt->execute([
                ':uid' => $user_id,
                ':eid' => $attendance_id,
                ':description' => "Submitted overtime report for " . date('M j, Y', strtotime($record['date'])),
                ':meta' => $logMeta
            ]);
        } catch (Exception $logE) {
            // Silently fail logging if it fails, but technically we should log error_log
            error_log("Failed to insert activity log: " . $logE->getMessage());
        }

        // ─── Conneqts Bot: Create Task for Manager ───────────────────────────
        try {
            // Fetch employee name
            $empStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $empStmt->execute([$user_id]);
            $empRow   = $empStmt->fetch(PDO::FETCH_ASSOC);
            $empName  = $empRow ? $empRow['username'] : 'Employee';

            // Fetch the assigned manager from overtime_approval_mapping
            $mgrStmt = $pdo->prepare("SELECT u.id, u.username FROM overtime_approval_mapping oam JOIN users u ON oam.manager_id = u.id WHERE oam.employee_id = ? LIMIT 1");
            $mgrStmt->execute([$user_id]);
            $mgrRow = $mgrStmt->fetch(PDO::FETCH_ASSOC);

            // Fallback: use the manager_id from the form if mapping not found
            if (!$mgrRow && $manager_id) {
                $fbStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
                $fbStmt->execute([$manager_id]);
                $mgrRow = $fbStmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($mgrRow) {
                $assignedToCSV    = (string)$mgrRow['id'];
                $assignedNamesCSV = $mgrRow['username'];

                $attendanceDate   = $record['date'];
                $otFormatted      = number_format($calculatedOt, 1);
                $reportPreview    = substr($report, 0, 100) . (strlen($report) > 100 ? '...' : '');

                $taskDesc = "Review and approve the overtime submission from $empName for $attendanceDate. "
                          . "Calculated OT: {$otFormatted}h. Report: \"$reportPreview\"";

                // Resolve project_id for ArchitectsHive Systems (FK constraint)
                $projStmt = $pdo->prepare("SELECT id FROM projects WHERE LOWER(title) LIKE '%architectshive systems%' LIMIT 1");
                $projStmt->execute();
                $projRow      = $projStmt->fetch(PDO::FETCH_ASSOC);
                $botProjectId = $projRow ? $projRow['id'] : null;

                $tStmt = $pdo->prepare("
                    INSERT INTO studio_assigned_tasks
                        (project_id, project_name, stage_number, task_description, priority,
                         assigned_to, assigned_names, due_date, due_time, status, created_by, is_system_task, created_at)
                    VALUES
                        (?, 'ArchitectsHive Systems', 'Verification', ?, 'High',
                         ?, ?, CURDATE(), '17:45:00', 'Pending', ?, 1, NOW())
                ");
                $tStmt->execute([$botProjectId, $taskDesc, $assignedToCSV, $assignedNamesCSV, $user_id]);
                $newTaskId = $pdo->lastInsertId();

                // Activity log for the manager
                $logMetadata = json_encode([
                    'task_id'          => $newTaskId,
                    'assigned_by_name' => 'Conneqts Bot',
                    'project_name'     => 'ArchitectsHive Systems',
                    'assigned_to'      => $assignedToCSV,
                    'assigned_names'   => $assignedNamesCSV,
                    'due_date'         => date('Y-m-d'),
                    'due_time'         => '17:45:00'
                ]);

                $logBotStmt = $pdo->prepare("
                    INSERT INTO global_activity_logs
                        (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read)
                    VALUES (?, 'task_assigned', 'task', ?, ?, ?, NOW(), 0)
                ");
                $logBotStmt->execute([
                    $mgrRow['id'],
                    $newTaskId,
                    "Conneqts Bot assigned you an Overtime Verification task for $empName ($attendanceDate, {$otFormatted}h) — Due Today by 05:45 PM.",
                    $logMetadata
                ]);
            }
        } catch (Throwable $eBotOt) {
            error_log('[ConneqtsBot OT ERROR] ' . $eBotOt->getMessage() . ' | File: ' . $eBotOt->getFile() . ' | Line: ' . $eBotOt->getLine());
        }
        // ─────────────────────────────────────────────────────────────────────

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Report submitted successfully.']);
    } else {
        // Double check if it just wasn't modified because the text was identical
        $check = $pdo->prepare("SELECT id FROM attendance WHERE id = :id AND user_id = :user_id");
        $check->execute([':id' => $attendance_id, ':user_id' => $user_id]);
        if ($check->rowCount() > 0) {
             $pdo->commit();
             echo json_encode(['status' => 'success', 'message' => 'Report saved.']);
        } else {
             $pdo->rollBack();
             echo json_encode(['status' => 'error', 'message' => 'Could not submit report. Record might not exist.']);
        }
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
