<?php
/**
 * manager_pages/food_reimbursement_approval/api/update_claim_status.php
 *
 * Handles Manager/HR approval, rejection, and payment marking for food reimbursement claims.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../../../config/db_connect.php';
require_once '../../../studio_users/api/activity_helper.php';

$userId = (int) $_SESSION['user_id'];
$body = json_decode(file_get_contents('php://input'), true);

$attendanceId = isset($body['attendance_id']) ? (int) $body['attendance_id'] : 0;
$action = $body['action'] ?? ''; // 'approve', 'reject', 'pay'
$level = $body['level'] ?? '';  // 'manager', 'hr', 'payment'
$note = $body['note'] ?? '';   // reviewer note

if ($attendanceId <= 0 || !in_array($action, ['approve', 'reject', 'pay']) || !in_array($level, ['manager', 'hr', 'payment'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Verify mapping and current status
    $stmt = $pdo->prepare("
        SELECT a.user_id AS employee_id, a.date, frc.manager_status, frc.hr_status, frc.payment_status, frc.amount, frm.manager_id, frm.hr_id
        FROM attendance a
        JOIN food_reimbursement_claims frc ON a.id = frc.attendance_id
        LEFT JOIN food_reimbursement_mapping frm ON a.user_id = frm.employee_id
        WHERE a.id = :id AND frc.claim_status = 'submitted'
        FOR UPDATE
    ");
    $stmt->execute([':id' => $attendanceId]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$claim) {
        throw new Exception("Claim not found or not submitted.");
    }

    $updateSql = "";
    $params = [':id' => $attendanceId];

    if ($level === 'manager') {
        if ($claim['manager_id'] != $userId) {
            throw new Exception("You are not mapped as the Manager for this employee.");
        }
        $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
        if ($newStatus === 'rejected') {
            $updateSql = "UPDATE food_reimbursement_claims SET manager_status = :st, hr_status = :st_hr, manager_note = :nt WHERE attendance_id = :id";
            $params[':st_hr'] = 'rejected';
        } else {
            $updateSql = "UPDATE food_reimbursement_claims SET manager_status = :st, manager_note = :nt WHERE attendance_id = :id";
        }
        $params[':st'] = $newStatus;
        $params[':nt'] = $note;

    } elseif ($level === 'hr') {
        $userStmt = $pdo->prepare("SELECT role FROM users WHERE id = :uid");
        $userStmt->execute([':uid' => $userId]);
        $userRole = strtolower($userStmt->fetchColumn() ?: '');

        if (!in_array($userRole, ['admin', 'hr'])) {
            throw new Exception("You are not authorized as HR/Admin to approve this claim.");
        }
        if ($claim['manager_status'] !== 'approved') {
            throw new Exception("Cannot process HR approval: Manager must approve the claim first.");
        }
        $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
        if ($newStatus === 'rejected') {
            $updateSql = "UPDATE food_reimbursement_claims SET hr_status = :st, manager_status = :st_mgr, hr_note = :nt WHERE attendance_id = :id";
            $params[':st_mgr'] = 'rejected';
        } else {
            $updateSql = "UPDATE food_reimbursement_claims SET hr_status = :st, hr_note = :nt WHERE attendance_id = :id";
        }
        $params[':st'] = $newStatus;
        $params[':nt'] = $note;

    } elseif ($level === 'payment') {
        if ($action !== 'pay') {
            throw new Exception("Invalid action for payment level.");
        }
        if ($claim['manager_status'] !== 'approved' || $claim['hr_status'] !== 'approved') {
            throw new Exception("Claim must be approved by both Manager and HR before payment.");
        }

        $permCheck = $pdo->prepare("SELECT can_mark_paid FROM food_reimbursement_payment_permissions WHERE user_id = :uid");
        $permCheck->execute([':uid' => $userId]);
        if (!$permCheck->fetchColumn()) {
            throw new Exception("You do not have permission to mark claims as paid.");
        }

        $updateSql = "UPDATE food_reimbursement_claims SET payment_status = 'paid' WHERE attendance_id = :id";
    }

    if ($updateSql) {
        $upd = $pdo->prepare($updateSql);
        $upd->execute($params);
    }

    // Fetch employee name for the reviewer's log
    $empStmt = $pdo->prepare("SELECT username FROM users WHERE id = :uid");
    $empStmt->execute([':uid' => $claim['employee_id']]);
    $empName = $empStmt->fetchColumn() ?: 'Employee';

    // Log the manager activity
    $logActionMap = [
        'approve' => 'food_claim_approved',
        'reject'  => 'food_claim_rejected',
        'pay'     => 'food_claim_paid'
    ];
    $actionType = $logActionMap[$action] ?? 'food_claim_updated';
    
    $levelName = ucfirst($level);
    $actionName = ($action === 'pay') ? 'marked as paid' : $action . 'd';
    
    $employeeDesc = "Your food reimbursement claim for " . date('d M Y', strtotime($claim['date'])) . " was {$actionName} by {$levelName}.";
    if ($action === 'reject' && $note) {
        $employeeDesc .= " Reason: {$note}";
    }
    
    $reviewerDesc = "You {$actionName} the food reimbursement claim of {$empName} for " . date('d M Y', strtotime($claim['date'])) . ".";
    
    $logMeta = [
        'level' => $level, 
        'action' => $action,
        'note' => $note,
        'reviewer_id' => $userId,
        'claim_amount' => $claim['amount'],
        'attendance_date' => $claim['date']
    ];
    
    // Log for Employee
    logUserActivity($pdo, $claim['employee_id'], $actionType, 'food_reimbursement', $employeeDesc, $attendanceId, $logMeta);
    // Log for Reviewer (Manager/HR/Accounts)
    logUserActivity($pdo, $userId, $actionType, 'food_reimbursement', $reviewerDesc, $attendanceId, $logMeta);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>