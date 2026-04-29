<?php
session_start();
require_once '../../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, username, department, designation, role, phone
         FROM users
         WHERE deleted_at IS NULL
           AND LOWER(status) = 'active'
         ORDER BY username ASC"
    );
    $stmt->execute();
    $users = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
} catch (Exception $e) {
    error_log('Saturday agenda users fetch error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch users'
    ]);
}
