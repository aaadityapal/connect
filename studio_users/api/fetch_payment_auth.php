<?php
session_start();
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

try {
    $query = "SELECT u.id, u.username, u.role, u.employee_id, u.unique_id,
            CASE WHEN tpa.user_id IS NOT NULL THEN 1 ELSE 0 END as can_pay
            FROM users u
            LEFT JOIN travel_payment_auth tpa ON u.id = tpa.user_id
            WHERE u.status = 'active'
            ORDER BY u.role, u.username";
            
    $stmt = $pdo->query($query);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'auth_users' => $users]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
