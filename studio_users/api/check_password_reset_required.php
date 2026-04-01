<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';

date_default_timezone_set('Asia/Kolkata');

$userId = (int)$_SESSION['user_id'];

function ensureMustChangePasswordColumns(PDO $pdo): void {
    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'must_change_password'")->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
            $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
        }

        $col2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_changed_at'")->fetch(PDO::FETCH_ASSOC);
        if (!$col2) {
            $pdo->exec("ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL");
        }
    } catch (Throwable $e) {
        // If permissions prevent ALTER, we still try to query below.
    }
}

try {
    ensureMustChangePasswordColumns($pdo);

    // If the column still doesn't exist, do not block the user.
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'must_change_password'")->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        echo json_encode(['success' => true, 'required' => false]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT must_change_password FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $required = (int)($stmt->fetchColumn() ?? 0) === 1;

    echo json_encode(['success' => true, 'required' => $required]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
