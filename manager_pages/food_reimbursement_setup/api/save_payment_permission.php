<?php
/**
 * manager_pages/food_reimbursement_setup/api/save_payment_permission.php
 * UPSERT the can_mark_paid flag for a single user.
 * POST JSON: { user_id: int, can_mark_paid: 0|1 }
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../../../config/db_connect.php';

$body  = json_decode(file_get_contents('php://input'), true);
$uid   = isset($body['user_id'])       ? (int)$body['user_id']       : 0;
$perm  = isset($body['can_mark_paid']) ? ($body['can_mark_paid'] ? 1 : 0) : -1;

if ($uid <= 0 || $perm === -1) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO food_reimbursement_payment_permissions (user_id, can_mark_paid)
        VALUES (:uid, :perm)
        ON DUPLICATE KEY UPDATE
            can_mark_paid = VALUES(can_mark_paid),
            updated_at    = CURRENT_TIMESTAMP
    ");
    $stmt->execute([':uid' => $uid, ':perm' => $perm]);

    echo json_encode(['success' => true, 'message' => 'Permission updated.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
