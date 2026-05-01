<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../config/db_connect.php';
require_once '../../api/activity_helper.php';

$body = json_decode(file_get_contents('php://input'), true);
$userId = (int)$_SESSION['user_id'];
$attendanceId = isset($body['attendance_id']) ? (int)$body['attendance_id'] : 0;

if ($attendanceId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid claim.']);
    exit();
}

try {
    // Check if the claim belongs to the user and is rejected
    $stmt = $pdo->prepare("
        SELECT frc.*, a.date as attendance_date 
        FROM food_reimbursement_claims frc
        JOIN attendance a ON frc.attendance_id = a.id
        WHERE frc.attendance_id = :aid AND frc.user_id = :uid
    ");
    $stmt->execute([':aid' => $attendanceId, ':uid' => $userId]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$claim) {
        throw new Exception("Claim not found.");
    }
    
    // Enforce 15-day expiration policy for resubmissions too
    if (!empty($claim['attendance_date'])) {
        $attDate = new DateTime($claim['attendance_date']);
        $now = new DateTime();
        $diff = $attDate->diff($now)->days;
        if ($diff > 15) {
            throw new Exception("This claim has expired. You cannot resubmit a claim more than 15 days after the eligible date.");
        }
    }

    if ($claim['manager_status'] !== 'rejected' && $claim['hr_status'] !== 'rejected') {
        throw new Exception("You can only resubmit a rejected claim.");
    }

    if ($claim['resubmit_count'] >= 3) {
        throw new Exception("Maximum resubmission limit (3) reached. This claim is permanently rejected.");
    }

    $upd = $pdo->prepare("
        UPDATE food_reimbursement_claims SET 
            manager_status = 'pending', hr_status = 'pending',
            manager_note = NULL, hr_note = NULL,
            resubmit_count = resubmit_count + 1,
            notes = :n,
            updated_at = NOW()
        WHERE attendance_id = :aid AND user_id = :uid
    ");
    
    $upd->execute([
        ':n'    => $body['note'] ?? null,
        ':aid'  => $attendanceId,
        ':uid'  => $userId
    ]);

    // Fetch Employee and Manager details for logging
    $empStmt = $pdo->prepare("
        SELECT u.username AS emp_name, frm.manager_id, frm.hr_id 
        FROM users u 
        LEFT JOIN food_reimbursement_mapping frm ON u.id = frm.employee_id 
        WHERE u.id = :uid
    ");
    $empStmt->execute([':uid' => $userId]);
    $empInfo = $empStmt->fetch(PDO::FETCH_ASSOC);
    $empName = $empInfo ? $empInfo['emp_name'] : 'Employee';
    $managerId = $empInfo ? $empInfo['manager_id'] : null;
    $hrId = $empInfo ? $empInfo['hr_id'] : null;

    $newCount = $claim['resubmit_count'] + 1;
    $logMeta = [
        'note' => $body['note'] ?? null,
        'resubmit_count' => $newCount
    ];
    
    // Log for Employee
    $empLogDesc = "You resubmitted your rejected food reimbursement claim. (Attempt {$newCount}/3).";
    logUserActivity($pdo, $userId, 'food_claim_resubmitted', 'food_reimbursement', $empLogDesc, $attendanceId, $logMeta);

    // Log for Manager and HR (if mapped)
    if ($managerId || $hrId) {
        $mgrLogDesc = "{$empName} has resubmitted their rejected food reimbursement claim. (Attempt {$newCount}/3).";
        if ($managerId) {
            logUserActivity($pdo, $managerId, 'food_claim_resubmitted', 'food_reimbursement', $mgrLogDesc, $attendanceId, $logMeta);
        }
        if ($hrId) {
            logUserActivity($pdo, $hrId, 'food_claim_resubmitted', 'food_reimbursement', $mgrLogDesc, $attendanceId, $logMeta);
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
