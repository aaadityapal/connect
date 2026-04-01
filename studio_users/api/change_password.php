<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../../config/db_connect.php';
require_once 'activity_helper.php';

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
        // If ALTER permissions are missing, ignore and proceed.
    }
}

$userId = $_SESSION['user_id'];
$currentPassword = $_POST['current_password'] ?? '';
$newPassword     = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
    exit();
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['status' => 'error', 'message' => 'New passwords do not match']);
    exit();
}

try {
    ensureMustChangePasswordColumns($pdo);

    $hasMustChange = false;
    try {
        $hasMustChange = (bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'must_change_password'")->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $hasMustChange = false; }

    // 1. Verify current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect']);
        exit();
    }

    // 2. Hash and update
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    if ($hasMustChange) {
        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0, password_changed_at = NOW() WHERE id = ?");
        $updateStmt->execute([$hashedPassword, $userId]);
    } else {
        $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->execute([$hashedPassword, $userId]);
    }

    logUserActivity($pdo, $userId, 'password_change', 'user', 'Changed account password');

    ob_clean();
    echo json_encode(['status' => 'success', 'message' => 'Password updated successfully']);
    exit();

} catch (PDOException $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}
