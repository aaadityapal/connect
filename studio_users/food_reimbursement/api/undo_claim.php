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
    $stmt = $pdo->prepare("SELECT claim_status, manager_status, hr_status FROM food_reimbursement_claims WHERE attendance_id = :aid AND user_id = :uid");
    $stmt->execute([':aid' => $attendanceId, ':uid' => $userId]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$claim || $claim['claim_status'] !== 'submitted') {
        throw new Exception("Claim is not submitted.");
    }
    
    if ($claim['manager_status'] === 'approved' || $claim['hr_status'] === 'approved') {
        throw new Exception("Cannot undo claim as it has already been approved.");
    }

    $upd = $pdo->prepare("UPDATE food_reimbursement_claims SET claim_status = 'draft', updated_at = NOW() WHERE attendance_id = :aid");
    $upd->execute([':aid' => $attendanceId]);

    // Fetch date for log description
    $dateFetch = $pdo->prepare("SELECT date FROM attendance WHERE id = :aid");
    $dateFetch->execute([':aid' => $attendanceId]);
    $attDate = $dateFetch->fetchColumn();
    $dateFmt = $attDate ? date('d M Y', strtotime($attDate)) : '';

    $logDesc = "Undid submission of food reimbursement claim for {$dateFmt}.";
    logUserActivity($pdo, $userId, 'food_claim_undone', 'food_reimbursement', $logDesc, $attendanceId, ['date' => $attDate]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
