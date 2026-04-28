<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

try {
    require_once '../../../config/db_connect.php';

    $search = trim((string)($_GET['q'] ?? ''));

    $sql = "SELECT id, username, email, role, department, joining_date, status, profile_picture
            FROM users
            WHERE LOWER(TRIM(status)) = 'active'";

    $params = [];

    if ($search !== '') {
        $sql .= " AND (
            username LIKE :search
            OR email LIKE :search
            OR role LIKE :search
            OR department LIKE :search
        )";
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY username ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count' => count($users),
        'users' => $users
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch active users'
    ]);
}
