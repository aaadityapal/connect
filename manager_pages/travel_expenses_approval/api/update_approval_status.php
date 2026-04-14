<?php
/**
 * UPDATE APPROVAL STATUS (HIERARCHICAL)
 * manager_pages/travel_expenses_approval/api/update_approval_status.php
 */
session_start();
require_once '../../../config/db_connect.php';
require_once '../../../studio_users/api/travel_task_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';
$is_admin = (strtolower($user_role) === 'admin' || strtolower($user_role) === 'administrator');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing ID or Status']);
    exit();
}

$expense_id = $data['id'];
$new_status = strtolower($data['status']); // 'approved' or 'rejected'
$reason = $data['reason'] ?? '';

try {
    // 1. Fetch current expense and mapping for the submitting user
    $query = "
        SELECT te.*, m.manager_id, m.hr_id, m.senior_manager_id
        FROM travel_expenses te
        JOIN users u ON te.user_id = u.id
        LEFT JOIN travel_expense_mapping m ON te.user_id = m.employee_id
        WHERE te.id = ?
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$expense_id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        throw new Exception("Expense claim not found.");
    }

    // 1.5 Evaluate Approval Window constraints (per-day schedule)
    if (!$is_admin) {
        date_default_timezone_set('Asia/Kolkata');
        $current_day  = date('l');    // e.g. "Monday"
        $current_time = date('H:i:s');

        $sched_stmt = $pdo->prepare("
            SELECT is_active, start_time, end_time
            FROM travel_approver_day_schedules
            WHERE approver_id = ? AND day_name = ?
        ");
        $sched_stmt->execute([$current_user_id, $current_day]);
        $sched = $sched_stmt->fetch(PDO::FETCH_ASSOC);

        // Default: Mon-Fri 09:00-18:00 active, weekends off
        if (!$sched) {
            $isWeekend  = in_array($current_day, ['Saturday','Sunday']);
            $is_active  = $isWeekend ? 0 : 1;
            $start_time = '09:00:00';
            $end_time   = '18:00:00';
        } else {
            $is_active  = (int)$sched['is_active'];
            $start_time = $sched['start_time'];
            $end_time   = $sched['end_time'];
        }

        if (!$is_active) {
            throw new Exception("Approvals are not allowed on {$current_day}.");
        }
        if ($current_time < $start_time || $current_time > $end_time) {
            $formatted_start = date('h:i A', strtotime($start_time));
            $formatted_end   = date('h:i A', strtotime($end_time));
            throw new Exception("Approvals on {$current_day} are only allowed between {$formatted_start} and {$formatted_end}.");
        }
    }


    // 2. Identify acting role — first find WHO the user is in this claim,
    //    then separately verify if their stage is ready to act.
    $update_field = "";
    $reason_field = "";

    // Admin Override: walk through stages in sequence
    if ($is_admin) {
        if ($expense['manager_status'] == 'pending') {
            $update_field = 'manager_status';
            $reason_field = 'manager_reason';
        } elseif ($expense['manager_status'] == 'approved' && $expense['hr_status'] == 'pending') {
            $update_field = 'hr_status';
            $reason_field = 'hr_reason';
        } elseif ($expense['hr_status'] == 'approved' && $expense['accountant_status'] == 'pending') {
            $update_field = 'accountant_status';
            $reason_field = 'accountant_reason';
        } else {
            throw new Exception("This claim is already finalized.");
        }

    // Standard User Role Logic — IDENTIFY ROLE FIRST, then check stage
    } elseif ($expense['manager_id'] == $current_user_id) {
        // This user IS the mapped Manager (L1)
        if ($expense['manager_status'] !== 'pending') {
            throw new Exception("You have already acted on this claim as Manager (L1).");
        }
        $update_field = 'manager_status';
        $reason_field = 'manager_reason';

    } elseif ($expense['hr_id'] == $current_user_id) {
        // This user IS the mapped HR (L2)
        if ($expense['hr_status'] !== 'pending') {
            throw new Exception("You have already acted on this claim as HR (L2).");
        }
        $update_field = 'hr_status';
        $reason_field = 'hr_reason';

    } elseif ($expense['senior_manager_id'] == $current_user_id) {
        // This user IS the mapped Senior Manager (L3)
        // Enforce dependency: L1 and L2 MUST have approved first
        if ($expense['manager_status'] !== 'approved' || $expense['hr_status'] !== 'approved') {
            throw new Exception("Manager (L1) and HR (L2) must approve before Senior Manager can act.");
        }
        if ($expense['accountant_status'] !== 'pending') {
            throw new Exception("You have already acted on this claim as Senior Manager (L3).");
        }
        $update_field = 'accountant_status'; // Senior Manager maps to accountant_status in DB
        $reason_field = 'accountant_reason';

    } else {
        throw new Exception("You are not a designated approver for this claim.");
    }


    $pdo->beginTransaction();

    // 3. Update the specific level status
    $sql = "UPDATE travel_expenses SET $update_field = ?, $reason_field = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$new_status, $reason, $expense_id]);

    // 4. Update the FINAL status of the claim
    // Re-fetch all statuses to make an accurate global decision
    $stmt = $pdo->prepare("SELECT manager_status, hr_status, accountant_status FROM travel_expenses WHERE id = ?");
    $stmt->execute([$expense_id]);
    $stati = $stmt->fetch(PDO::FETCH_ASSOC);

    $is_any_rejected = ($stati['manager_status'] === 'rejected' || $stati['hr_status'] === 'rejected' || $stati['accountant_status'] === 'rejected');
    $is_all_approved = ($stati['manager_status'] === 'approved' && $stati['hr_status'] === 'approved' && $stati['accountant_status'] === 'approved');

    if ($is_any_rejected) {
        $stmt = $pdo->prepare("UPDATE travel_expenses SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$expense_id]);
    } elseif ($is_all_approved) {
        $stmt = $pdo->prepare("UPDATE travel_expenses SET status = 'approved' WHERE id = ?");
        $stmt->execute([$expense_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE travel_expenses SET status = 'pending' WHERE id = ?");
        $stmt->execute([$expense_id]);
    }

    // 5. Notify Employee via Activity Logs
    $logQuery = "INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at) VALUES (?, ?, 'travel_expense', ?, ?, ?, NOW())";
    
    $performerName = $_SESSION['username'] ?? 'System';
    $roleName = "Approver";
    if (strpos($update_field, 'manager') !== false) $roleName = "Manager";
    if (strpos($update_field, 'hr') !== false) $roleName = "HR";
    if (strpos($update_field, 'accountant') !== false) $roleName = "Admin/Senior Manager";
    
    $action_type = ($new_status === 'approved') ? 'travel_approved' : 'travel_rejected';
    $desc = "Your travel expense claim for '{$expense['purpose']}' was {$new_status} by {$roleName} ({$performerName}).";
    if ($reason) $desc .= " Reason: {$reason}";

    $meta = json_encode([
        'purpose' => $expense['purpose'],
        'acted_by_name' => $performerName,
        'acted_by_id' => $current_user_id,
        'acted_by_role' => $roleName,
        'reason' => $reason
    ]);

    $stmtLog = $pdo->prepare($logQuery);
    $stmtLog->execute([
        $expense['user_id'],
        $action_type,
        $expense_id,
        $desc,
        $meta
    ]);

    // Insert for the Approver themselves
    if ($current_user_id != $expense['user_id']) {
        $approverDesc = "You marked travel expense claim for '{$expense['purpose']}' as {$new_status}.";
        if ($reason) $approverDesc .= " Reason: {$reason}";
        
        $stmtLog->execute([
            $current_user_id,
            $action_type,
            $expense_id,
            $approverDesc,
            $meta
        ]);
    }

    $pdo->commit();

    // ═══════════════════════════════════════════════════════════════
    //  CONNEQTS BOT: Spawn Senior Manager task when Manager + HR both approve
    // ═══════════════════════════════════════════════════════════════
    try {
        // Re-fetch latest statuses after commit
        $checkStmt = $pdo->prepare("
            SELECT te.manager_status, te.hr_status, te.accountant_status,
                   te.purpose, te.amount, te.user_id,
                   u.username AS employee_name,
                   m.senior_manager_id
            FROM travel_expenses te
            JOIN users u ON u.id = te.user_id
            LEFT JOIN travel_expense_mapping m ON m.employee_id = te.user_id
            WHERE te.id = ?
        ");
        $checkStmt->execute([$expense_id]);
        $latest = $checkStmt->fetch(PDO::FETCH_ASSOC);

        $managerApproved  = ($latest['manager_status']    === 'approved');
        $hrApproved       = ($latest['hr_status']         === 'approved');
        $srAlreadyActed   = ($latest['accountant_status'] !== 'pending');
        $anyRejected      = ($latest['manager_status']    === 'rejected' || $latest['hr_status'] === 'rejected');
        $seniorManagerId  = $latest['senior_manager_id'] ?? null;

        // Only spawn if: both approved + senior manager exists + hasn't acted yet + not rejected
        if ($managerApproved && $hrApproved && !$srAlreadyActed && !$anyRejected && $seniorManagerId) {
            date_default_timezone_set('Asia/Kolkata');
            $employeeName = $latest['employee_name'];

            // Fetch Senior Manager name
            $smStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
            $smStmt->execute([$seniorManagerId]);
            $smUser = $smStmt->fetch(PDO::FETCH_ASSOC);

            if ($smUser) {
                // Dedup: don't spawn if senior manager task already exists
                $dupStmt = $pdo->prepare("
                    SELECT id FROM studio_assigned_tasks
                    WHERE project_name = 'ArchitectsHive Systems'
                      AND task_description LIKE ?
                      AND status NOT IN ('Completed', 'Cancelled')
                    LIMIT 1
                ");
                $dupStmt->execute(["%[SR. MANAGER] Travel expense by {$employeeName}%"]);

                if (!$dupStmt->fetch()) {
                    // Find next open window for Senior Manager specifically
                    $smWindow  = getNextApprovalWindow($pdo, [$smUser['id']]);
                    $dueDate   = $smWindow['due_date'];
                    $dueTime   = $smWindow['due_time'];

                    $taskDesc  = "[SR. MANAGER] Travel expense by {$employeeName} — Rs." . number_format((float)$latest['amount'], 2) . " for '{$latest['purpose']}' has been approved by Manager & HR. Your final approval is required.";
                    $taskDesc .= "\n[Conneqts Bot | " . date('d M Y, h:i A') . "]";

                    $tStmt = $pdo->prepare("
                        INSERT INTO studio_assigned_tasks
                            (project_name, stage_number, task_description, priority,
                             assigned_to, assigned_names, due_date, due_time,
                             status, created_by, is_system_task, created_at)
                        VALUES ('ArchitectsHive Systems', 'Final Approval', ?, 'High',
                                ?, ?, ?, ?, 'Pending', ?, 1, NOW())
                    ");
                    $tStmt->execute([$taskDesc, $smUser['id'], $smUser['username'], $dueDate, $dueTime, $latest['user_id']]);
                    $newTaskId = $pdo->lastInsertId();

                    // Activity log for Senior Manager
                    $logMeta = json_encode([
                        'task_id'          => $newTaskId,
                        'assigned_by_name' => 'Conneqts Bot',
                        'project_name'     => 'ArchitectsHive Systems',
                        'expense_id'       => $expense_id,
                        'employee_name'    => $employeeName,
                        'due_date'         => $dueDate,
                        'due_time'         => '18:00:00'
                    ]);
                    $actStmt = $pdo->prepare("
                        INSERT INTO global_activity_logs
                            (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read)
                        VALUES (?, 'task_assigned', 'task', ?, ?, ?, NOW(), 0)
                    ");
                    $actStmt->execute([
                        $smUser['id'], $newTaskId,
                        "Conneqts Bot: Travel expense by {$employeeName} (Rs." . number_format((float)$latest['amount'], 2) . ") is awaiting your final approval. Manager & HR have approved.",
                        $logMeta
                    ]);
                }
            }
        }
    } catch (Exception $botEx) {
        error_log('[Conneqts Bot] Sr. Manager task spawn failed: ' . $botEx->getMessage());
    }
    // ═══════════════════════════════════════════════════════════════

    echo json_encode(['success' => true, 'message' => "Claim successfully updated as $new_status."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
