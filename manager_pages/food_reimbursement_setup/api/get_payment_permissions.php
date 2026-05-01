<?php
/**
 * manager_pages/food_reimbursement_setup/api/get_payment_permissions.php
 * Returns all active users with their can_mark_paid permission flag.
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
        CREATE TABLE IF NOT EXISTS food_reimbursement_payment_permissions (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            user_id       INT NOT NULL,
            can_mark_paid TINYINT(1) NOT NULL DEFAULT 0,
            created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user (user_id),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Fetch all active users with their permission status
    $stmt = $pdo->query("
        SELECT
            u.id,
            u.username  AS name,
            u.position,
            u.role,
            u.email,
            COALESCE(pp.can_mark_paid, 0) AS can_mark_paid
        FROM users u
        LEFT JOIN food_reimbursement_payment_permissions pp ON pp.user_id = u.id
        WHERE u.deleted_at IS NULL AND u.status = 'Active'
        ORDER BY u.username ASC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'users' => $users]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
