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
    // Ensure the claim hasn't been approved yet
    $stmt = $pdo->prepare("SELECT claim_status, manager_status, hr_status FROM food_reimbursement_claims WHERE attendance_id = :aid AND user_id = :uid");
    $stmt->execute([':aid' => $attendanceId, ':uid' => $userId]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($claim) {
        if ($claim['manager_status'] === 'approved' || $claim['hr_status'] === 'approved') {
            throw new Exception("Cannot edit claim as it has already been acted upon.");
        }

        $upd = $pdo->prepare("
            UPDATE food_reimbursement_claims SET 
                category = :cat, amount = :amt, meal_type = :mt, 
                vendor_name = :vn, description = :desc, notes = :n, 
                updated_at = NOW()
            WHERE attendance_id = :aid AND user_id = :uid
        ");
        $upd->execute([
            ':cat'  => $body['category'] ?? null,
            ':amt'  => $body['amount'] ?? null,
            ':mt'   => $body['meal_type'] ?? null,
            ':vn'   => $body['vendor_name'] ?? null,
            ':desc' => $body['description'] ?? null,
            ':n'    => $body['notes'] ?? null,
            ':aid'  => $attendanceId,
            ':uid'  => $userId
        ]);
    } else {
        $ins = $pdo->prepare("
            INSERT INTO food_reimbursement_claims 
            (attendance_id, user_id, category, amount, meal_type, vendor_name, description, notes) 
            VALUES (:aid, :uid, :cat, :amt, :mt, :vn, :desc, :n)
        ");
        $ins->execute([
            ':aid'  => $attendanceId,
            ':uid'  => $userId,
            ':cat'  => $body['category'] ?? null,
            ':amt'  => $body['amount'] ?? null,
            ':mt'   => $body['meal_type'] ?? null,
            ':vn'   => $body['vendor_name'] ?? null,
            ':desc' => $body['description'] ?? null,
            ':n'    => $body['notes'] ?? null
        ]);
    }

    $logDesc = "Updated food reimbursement claim details.";
    $logMeta = [
        'amount'      => $body['amount'] ?? null,
        'category'    => $body['category'] ?? null,
        'meal_type'   => $body['meal_type'] ?? null,
        'vendor_name' => $body['vendor_name'] ?? null,
        'description' => $body['description'] ?? null
    ];
    logUserActivity($pdo, $userId, 'food_claim_updated', 'food_reimbursement', $logDesc, $attendanceId, $logMeta);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
