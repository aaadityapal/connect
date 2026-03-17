<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    // 1. Fetch all active users
    $query = "SELECT id, username, position, role FROM users 
              WHERE deleted_at IS NULL AND status = 'Active' 
              ORDER BY username ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch all reporting relations (Matrix Support)
    $stmt = $pdo->query("SELECT subordinate_id, manager_id FROM user_reporting");
    $relations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'users' => $users,
        'relations' => $relations
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'details' => $e->getMessage()
    ]);
}
?>
