<?php
session_start();
require_once '../../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id   = $_SESSION['user_id'];
$manager_role = strtolower($_SESSION['role'] ?? 'user');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['request_id'], $input['action_type'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

$requestId  = $input['request_id'];
$actionType = $input['action_type']; // 'approve' or 'reject'
$status     = ($actionType === 'approve') ? 'approved' : 'rejected';

$mgrReason  = $input['manager_reason'] ?? '';
$hrReason   = $input['hr_reason'] ?? '';

try {
    // 0. Verify current status for workflow rules
    $vStmt = $pdo->prepare("SELECT manager_approval FROM leave_request WHERE id = ?");
    $vStmt->execute([$requestId]);
    $currentReq = $vStmt->fetch(PDO::FETCH_ASSOC);
    $wasManagerApproved = ($currentReq && strtolower((string)$currentReq['manager_approval']) === 'approved');

    if ($currentReq) {
        $isHRAttemptingApprove = ($manager_role === 'hr' && $actionType === 'approve');
        if ($isHRAttemptingApprove && ($currentReq['manager_approval'] !== 'approved')) {
            echo json_encode(['success' => false, 'message' => 'Approval for the manager is pending.']);
            exit();
        }
    }

    // 1. Determine update fields
    // If Admin, update BOTH Manager and HR fields
    // If Manager, update only Manager fields
    
    if (in_array($manager_role, ['admin', 'hr'])) {
        if ($actionType === 'reject') {
            $hrFinalReason = $hrReason;
            $mgrFinalReason = "HR rejected your leave with reason: " . $hrReason;
            $mgrFinalStatus = 'rejected';
            $hrFinalStatus = 'rejected';
        } else {
            $hrFinalReason = $hrReason;
            $mgrFinalReason = $mgrReason;
            $mgrFinalStatus = $status;
            $hrFinalStatus = $status;
        }

        $query = "UPDATE leave_request 
                  SET manager_approval = :mgr_status,
                      manager_action_reason = :mgr_reason,
                      manager_action_by = :mgr_user_id,
                      manager_action_at = NOW(),
                      status = :hr_status,
                      hr_action_reason = :hr_reason,
                      hr_action_by = :hr_user_id,
                      hr_action_at = NOW()
                  WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':mgr_status'  => $mgrFinalStatus,
            ':mgr_reason'  => $mgrFinalReason,
            ':mgr_user_id' => $user_id,
            ':hr_status'   => $hrFinalStatus,
            ':hr_reason'   => $hrFinalReason,
            ':hr_user_id'  => $user_id,
            ':id'          => $requestId
        ]);
    } else {
        // Just manager - but if they REJECT, it also rejects for HR automatically
        if ($actionType === 'reject') {
            $query = "UPDATE leave_request 
                      SET manager_approval = 'rejected',
                          manager_action_reason = :mgr_reason,
                          manager_action_by = :mgr_user_id,
                          manager_action_at = NOW(),
                          status = 'rejected',
                          hr_action_reason = :hr_linked_reason,
                          hr_action_by = :hr_user_id,
                          hr_action_at = NOW()
                      WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':mgr_reason'  => $mgrReason,
                ':hr_linked_reason' => "Manager rejected your leave with reason: " . $mgrReason,
                ':mgr_user_id' => $user_id,
                ':hr_user_id'  => $user_id,
                ':id'          => $requestId
            ]);
        } else {
            $query = "UPDATE leave_request 
                      SET manager_approval = :status,
                          manager_action_reason = :mgr_reason,
                          manager_action_by = :user_id,
                          manager_action_at = NOW()
                      WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':status'      => $status,
                ':mgr_reason'  => $mgrReason,
                ':user_id'     => $user_id,
                ':id'          => $requestId
            ]);
        }
    }

    if ($stmt->rowCount() > 0) {
        // 2. Fetch the requesting employee's ID for notification
        $empQuery = "SELECT user_id, leave_type FROM leave_request WHERE id = :id";
        $empStmt = $pdo->prepare($empQuery);
        $empStmt->execute([':id' => $requestId]);
        $leaveData = $empStmt->fetch();
        $employeeId = $leaveData['user_id'];

        // 3. Log Activity for the EMPLOYEE (Notification) + PERFORMER (Audit)
        $performerName = $_SESSION['username'] ?? 'System';
        $todayDate = date('Y-m-d');
        $notifType = ($status === 'approved') ? 'leave_approved' : 'leave_rejected';
        
        $desc = "Your leave is {$status} by {$performerName} on {$todayDate}. ";
        if ($mgrReason) $desc .= "Reason: {$mgrReason}. ";
        if ($hrReason)  $desc .= "(HR: {$hrReason}).";

        // Insert for Employee
        $logQuery = "INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, created_at)
                     VALUES (:target_user, :notif_type, 'leave_request', :id, :desc, NOW())";
        $logStmt = $pdo->prepare($logQuery);
        $logStmt->execute([
            ':target_user' => $employeeId,
            ':notif_type'  => $notifType,
            ':id'          => $requestId,
            ':desc'        => trim($desc)
        ]);

        // Insert for Performer (so they see it in their recent activity too if they want)
        $performDesc = "LID#{$requestId} ({$leaveData['leave_type']}) marked as {$status} by you. ";
        if ($mgrReason) $performDesc .= "Mgr Remarks: {$mgrReason}. ";
        if ($hrReason)  $performDesc .= "HR Remarks: {$hrReason}.";

        $logStmt->execute([
            ':target_user' => $user_id, // The Admin/Manager
            ':notif_type'  => 'leave_status_update',
            ':id'          => $requestId,
            ':desc'        => trim($performDesc)
        ]);

        // 3b. Stage-2 assignment: only after manager approves, assign verification task to HR
        $isManagerForwardingToHR = !in_array($manager_role, ['admin', 'hr'])
            && $actionType === 'approve'
            && !$wasManagerApproved;

        if ($isManagerForwardingToHR) {
            $dStmt = $pdo->prepare("SELECT lr.start_date, lr.end_date, lr.reason, u.username AS employee_name, lt.name AS leave_type_name FROM leave_request lr JOIN users u ON lr.user_id = u.id JOIN leave_types lt ON lr.leave_type = lt.id WHERE lr.id = ? LIMIT 1");
            $dStmt->execute([$requestId]);
            $dRow = $dStmt->fetch(PDO::FETCH_ASSOC);

            $employeeName = $dRow['employee_name'] ?? ('User #' . $employeeId);
            $leaveTypeName = $dRow['leave_type_name'] ?? ('Leave #' . ($leaveData['leave_type'] ?? 'N/A'));
            $startDate = $dRow['start_date'] ?? null;
            $endDate = $dRow['end_date'] ?? null;
            $range = ($startDate && $endDate)
                ? (($startDate === $endDate) ? $startDate : ($startDate . ' to ' . $endDate))
                : 'N/A';
            $reasonSnippet = isset($dRow['reason']) ? substr((string)$dRow['reason'], 0, 120) : '';

            $hrStmt = $pdo->prepare("SELECT id, username FROM users WHERE LOWER(role) = 'hr'");
            $hrStmt->execute();
            $hrUsers = $hrStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($hrUsers)) {
                $assignedIds = array_map(static fn($u) => (int)$u['id'], $hrUsers);
                $assignedNames = array_map(static fn($u) => (string)$u['username'], $hrUsers);
                $assignedToCSV = implode(',', $assignedIds);
                $assignedNamesCSV = implode(', ', $assignedNames);

                $taskMarker = "[LEAVE_REQ_ID:" . (int)$requestId . "]";
                $taskDesc = "HR approval required: Manager approved {$leaveTypeName} for {$employeeName} ({$range}). Reason: {$reasonSnippet} {$taskMarker}";

                $dupStmt = $pdo->prepare("SELECT id FROM studio_assigned_tasks WHERE project_name = 'ArchitectsHive Systems' AND task_description LIKE ? AND status NOT IN ('Completed', 'Cancelled') LIMIT 1");
                $dupStmt->execute(['%' . $taskMarker . '%']);

                if (!$dupStmt->fetch(PDO::FETCH_ASSOC)) {
                    $projStmt = $pdo->prepare("SELECT id FROM projects WHERE LOWER(title) LIKE '%architectshive systems%' LIMIT 1");
                    $projStmt->execute();
                    $projRow = $projStmt->fetch(PDO::FETCH_ASSOC);
                    $botProjectId = $projRow ? $projRow['id'] : null;

                    $tStmt = $pdo->prepare("INSERT INTO studio_assigned_tasks (project_id, project_name, stage_number, task_description, priority, assigned_to, assigned_names, due_date, due_time, status, created_by, is_system_task, created_at) VALUES (?, 'ArchitectsHive Systems', 'Verification', ?, 'High', ?, ?, CURDATE(), '18:00:00', 'Pending', ?, 1, NOW())");
                    $tStmt->execute([$botProjectId, $taskDesc, $assignedToCSV, $assignedNamesCSV, $user_id]);
                    $newTaskID = $pdo->lastInsertId();

                    $taskLogStmt = $pdo->prepare("INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read) VALUES (?, 'task_assigned', 'task', ?, ?, ?, NOW(), 0)");
                    $meta = json_encode([
                        'task_id' => $newTaskID,
                        'assigned_by_name' => ($_SESSION['username'] ?? 'Manager'),
                        'project_name' => 'ArchitectsHive Systems',
                        'assigned_to' => $assignedToCSV,
                        'assigned_names' => $assignedNamesCSV,
                        'leave_request_id' => (int)$requestId,
                        'due_date' => date('Y-m-d'),
                        'due_time' => '18:00:00'
                    ]);

                    foreach ($assignedIds as $hrUserId) {
                        $taskLogStmt->execute([
                            $hrUserId,
                            $newTaskID,
                            "Conneqts Bot assigned it to you: Manager approved {$leaveTypeName} for {$employeeName}. Remaining approval is yours.",
                            $meta
                        ]);
                    }
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);

        // --- 4. WhatsApp Notification Logic (Final Decision) ---
        try {
            // Check if it's a final state
            $finalCheck = $pdo->prepare("
                SELECT lr.*, u.username, u.phone, lt.name as leave_type_name
                FROM leave_request lr
                JOIN users u ON lr.user_id = u.id
                JOIN leave_types lt ON lr.leave_type = lt.id
                WHERE lr.id = :id
            ");
            $finalCheck->execute([':id' => $requestId]);
            $req = $finalCheck->fetch();

            if ($req && !empty($req['phone'])) {
                $mApp = $req['manager_approval'];
                $hApp = $req['status']; // HR Status
                
                $isFinalApproval = ($mApp === 'approved' && $hApp === 'approved');
                $isFinalRejection = ($mApp === 'rejected' || $hApp === 'rejected');

                if ($isFinalApproval || $isFinalRejection) {
                    require_once __DIR__ . '/../../../whatsapp/WhatsAppService.php';
                    $waService = new WhatsAppService();
                    
                    $name = $req['username'];
                    $phone = $req['phone'];
                    $start = date('d M Y', strtotime($req['start_date']));
                    $end = date('d M Y', strtotime($req['end_date']));
                    $type = $req['leave_type_name'];

                    if ($isFinalApproval) {
                        // Assemble time context
                        $timeInfo = "Full Day";
                        if ($req['duration'] < 1) {
                            if ($req['time_from']) {
                                $timeInfo = date('h:i A', strtotime($req['time_from'])) . " - " . date('h:i A', strtotime($req['time_to']));
                            } else {
                                $timeInfo = ucfirst(str_replace('_', ' ', $req['day_type']));
                            }
                        }

                        $waService->sendTemplateMessage($phone, 'leave_approved_notification', 'en_US', [
                            $name, $start, $end, $type, "⏰ Time: $timeInfo"
                        ]);
                    } else {
                        // Rejection
                        $reason = $mgrReason ?: $hrReason ?: 'Administrative Decision';
                        $waService->sendTemplateMessage($phone, 'leave_rejected_with_reason', 'en_US', [
                            $name, $start, $end, $type, $reason
                        ]);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log("WhatsApp Error in Leave Approval: " . $e->getMessage());
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made or record not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}



