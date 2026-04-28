<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db_connect.php';

$username = trim($_GET['username'] ?? '');

if ($username === '') {
    echo json_encode(['available' => null, 'message' => '']);
    exit();
}

// Minimum length guard (mirrors typical validation)
if (strlen($username) < 3) {
    echo json_encode(['available' => null, 'message' => 'Keep typing…']);
    exit();
}

try {
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $exists = (bool) $stmt->fetchColumn();

    if ($exists) {
        echo json_encode([
            'available' => false,
            'message'   => 'Username is already taken, use another.'
        ]);
    } else {
        echo json_encode([
            'available' => true,
            'message'   => 'Username is available.'
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
