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
$note = $body['note'] ?? '';

if ($attendanceId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid claim.']);
    exit();
}

try {
    // Fetch attendance record to check expiration
    $attStmt = $pdo->prepare("SELECT date, punch_in, punch_out FROM attendance WHERE id = :aid AND user_id = :uid");
    $attStmt->execute([':aid' => $attendanceId, ':uid' => $userId]);
    $att = $attStmt->fetch(PDO::FETCH_ASSOC);

    if (!$att) throw new Exception("Attendance record not found.");

    // Enforce 15-day expiration policy
    $attDate = new DateTime($att['date']);
    $now = new DateTime();
    $diff = $attDate->diff($now)->days;
    
    // If the difference is greater than 15, block submission
    if ($diff > 15) {
        throw new Exception("This claim has expired. Food reimbursement requests must be submitted within 15 days of the eligible date.");
    }

    // Check if a claim already exists
    $stmt = $pdo->prepare("SELECT id, claim_status FROM food_reimbursement_claims WHERE attendance_id = :aid AND user_id = :uid");
    $stmt->execute([':aid' => $attendanceId, ':uid' => $userId]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($claim) {
        if ($claim['claim_status'] === 'submitted') {
            throw new Exception("Claim already submitted.");
        }
        
        $upd = $pdo->prepare("
            UPDATE food_reimbursement_claims 
            SET claim_status = 'submitted', notes = :n, updated_at = NOW() 
            WHERE attendance_id = :aid
        ");
        $upd->execute([':n' => $note, ':aid' => $attendanceId]);
    } else {
        $ins = $pdo->prepare("
            INSERT INTO food_reimbursement_claims 
            (attendance_id, user_id, claim_status, notes) 
            VALUES (:aid, :uid, 'submitted', :n)
        ");
        $ins->execute([
            ':aid' => $attendanceId,
            ':uid' => $userId,
            ':n'   => $note
        ]);
    }

    // Fetch details for logging
    $logFetch = $pdo->prepare("SELECT a.date, frc.amount, frc.category FROM food_reimbursement_claims frc JOIN attendance a ON frc.attendance_id = a.id WHERE frc.attendance_id = :aid");
    $logFetch->execute([':aid' => $attendanceId]);
    $claimDetails = $logFetch->fetch(PDO::FETCH_ASSOC);

    // Log the activity
    $dateFmt = $claimDetails ? date('d M Y', strtotime($claimDetails['date'])) : '';
    $logDesc = "Submitted food reimbursement claim for processing for {$dateFmt}.";
    $logMeta = [
        'note'     => $note,
        'amount'   => $claimDetails['amount'] ?? null,
        'category' => $claimDetails['category'] ?? null,
        'date'     => $claimDetails['date'] ?? null
    ];
    logUserActivity($pdo, $userId, 'food_claim_submitted', 'food_reimbursement', $logDesc, $attendanceId, $logMeta);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
