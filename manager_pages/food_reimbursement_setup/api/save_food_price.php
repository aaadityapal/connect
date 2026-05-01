<?php
/**
 * manager_pages/food_reimbursement_setup/api/save_food_price.php
 * UPSERT the food reimbursement price for a single user.
 * POST JSON: { user_id: int, price_per_meal: float }
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../../../config/db_connect.php';

$body  = json_decode(file_get_contents('php://input'), true);
$uid   = isset($body['user_id'])       ? (int)$body['user_id']           : 0;
$price = isset($body['price_per_meal']) ? (float)$body['price_per_meal']  : -1;

if ($uid <= 0 || $price < 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO food_reimbursement_price (user_id, price_per_meal)
        VALUES (:uid, :price)
        ON DUPLICATE KEY UPDATE
            price_per_meal = VALUES(price_per_meal),
            updated_at     = CURRENT_TIMESTAMP
    ");
    $stmt->execute([':uid' => $uid, ':price' => $price]);

    echo json_encode(['success' => true, 'message' => 'Price saved.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
