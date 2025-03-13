<?php
session_start();
header('Content-Type: application/json');

require_once '../config/db_connect.php';

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }

    $query = "SELECT id, username, role FROM users WHERE id = :user_id AND status = 'active'";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    echo json_encode([
        'status' => 'success',
        'data' => $user
    ]);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
exit; 