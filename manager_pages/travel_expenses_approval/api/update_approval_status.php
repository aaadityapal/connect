<?php
/**
 * UPDATE APPROVAL STATUS (HIERARCHICAL)
 * manager_pages/travel_expenses_approval/api/update_approval_status.php
 */
session_start();
require_once '../../../config/db_connect.php';

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

    // 1.5 Evaluate Approval Window constraints
    if (!$is_admin) {
        $sched_stmt = $pdo->prepare("SELECT active_days, start_time, end_time FROM travel_approver_schedules WHERE user_id = ?");
        $sched_stmt->execute([$current_user_id]);
        $sched = $sched_stmt->fetch(PDO::FETCH_ASSOC);

        $active_days = $sched ? explode(',', $sched['active_days']) : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $start_time = $sched ? $sched['start_time'] : '09:00:00';
        $end_time = $sched ? $sched['end_time'] : '18:00:00';
        
        // Ensure accurate Indian Standard Time evaluation
        date_default_timezone_set('Asia/Kolkata');
        $current_day = date('l');
        $current_time = date('H:i:s');
        
        if (!in_array($current_day, $active_days)) {
            throw new Exception("Approvals are not allowed on $current_day.");
        }
        if ($current_time < $start_time || $current_time > $end_time) {
            $formatted_start = date('h:i A', strtotime($start_time));
            $formatted_end = date('h:i A', strtotime($end_time));
            throw new Exception("Approvals are only allowed between $formatted_start and $formatted_end.");
        }
    }

    // 2. Identify acting role
    $update_field = "";
    $reason_field = "";
    
    // Admin Override: Identify current pending stage
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
    } 
    // Standard User Role Logic
    elseif ($expense['manager_id'] == $current_user_id && $expense['manager_status'] == 'pending') {
        $update_field = 'manager_status';
        $reason_field = 'manager_reason';
    } elseif ($expense['hr_id'] == $current_user_id && $expense['hr_status'] == 'pending') {
        $update_field = 'hr_status';
        $reason_field = 'hr_reason';
    } elseif ($expense['senior_manager_id'] == $current_user_id && $expense['accountant_status'] == 'pending') {
        $update_field = 'accountant_status'; // Senior Manager maps to accountant_status in existing DB
        $reason_field = 'accountant_reason';
    } else {
        throw new Exception("You are not the designated approver for the current stage of this claim.");
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
    echo json_encode(['success' => true, 'message' => "Claim successfully updated as $new_status."]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
