<?php
/**
 * manager_pages/food_reimbursement_setup/api/get_food_prices.php
 * Returns all active users with their configured per-meal food reimbursement price.
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../../../config/db_connect.php';

try {
    // Auto-create table if missing
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS food_reimbursement_price (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            user_id         INT NOT NULL,
            price_per_meal  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user (user_id),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Fetch all active users LEFT JOINed with their price
    $stmt = $pdo->query("
        SELECT
            u.id,
            u.username   AS name,
            u.position,
            u.role,
            u.email,
            COALESCE(p.price_per_meal, 100.00) AS price_per_meal
        FROM users u
        LEFT JOIN food_reimbursement_price p ON p.user_id = u.id
        WHERE u.deleted_at IS NULL AND u.status = 'Active'
        ORDER BY u.username ASC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'users' => $users]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
