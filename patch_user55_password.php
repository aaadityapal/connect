<?php
require_once __DIR__ . '/config/db_connect.php';

$userId = 33;
$newPassword = 'Qwer@1234';
$hash = password_hash($newPassword, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->execute([$hash, $userId]);

if ($stmt->rowCount() > 0) {
    echo "✅ Password for user ID {$userId} has been updated successfully.\n";
} else {
    echo "⚠️  No user found with ID {$userId}, or the password was already the same.\n";
}

// Self-delete after running
unlink(__FILE__);
echo "🗑️  Script removed.\n";
?>