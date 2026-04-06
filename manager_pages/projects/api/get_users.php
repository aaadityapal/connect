<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

require_once '../../../config/db_connect.php';

try {
    $hasDeletedAt = false;
    $colsStmt = $pdo->query('SHOW COLUMNS FROM users');
    foreach ($colsStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if (($col['Field'] ?? '') === 'deleted_at') {
            $hasDeletedAt = true;
            break;
        }
    }

    $whereParts = [];
    $whereParts[] = "username IS NOT NULL";
    $whereParts[] = "TRIM(username) <> ''";
    if ($hasDeletedAt) {
        $whereParts[] = 'deleted_at IS NULL';
    }

    $sql = 'SELECT id, username FROM users';
    if (!empty($whereParts)) {
        $sql .= ' WHERE ' . implode(' AND ', $whereParts);
    }
    $sql .= ' ORDER BY username ASC';

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $rows
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch users',
        'error' => $e->getMessage()
    ]);
}
