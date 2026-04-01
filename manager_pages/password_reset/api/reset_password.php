<?php
/**
 * api/reset_password.php
 * Handles password reset for a given user_id.
 * Expects POST: user_id, new_password
 */

session_start();
header('Content-Type: application/json');

// Auth guard
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit();
}

require_once '../../../config/db_connect.php';

// Validate inputs
$userId      = intval($_POST['user_id'] ?? 0);
$newPassword = trim($_POST['new_password'] ?? '');

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user specified.']);
    exit();
}

if (strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit();
}

if (!preg_match('/[A-Z]/', $newPassword)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter.']);
    exit();
}

if (!preg_match('/[a-z]/', $newPassword)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one lowercase letter.']);
    exit();
}

if (!preg_match('/[0-9]/', $newPassword)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least one number.']);
    exit();
}

// Prevent resetting own password via this tool
if ($userId === (int)$_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'You cannot reset your own password using this tool.']);
    exit();
}

// Verify target user exists, is active, and fetch their role
$stmtCheck = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ? AND status = 'active' LIMIT 1");
$stmtCheck->execute([$userId]);
$targetUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    echo json_encode(['success' => false, 'message' => 'User not found or is inactive.']);
    exit();
}

// Block resetting admin passwords
if (strtolower($targetUser['role']) === 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin account passwords cannot be reset through this tool.']);
    exit();
}

// Hash and update
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

try {
    $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmtUpdate->execute([$hashedPassword, $userId]);

    if ($stmtUpdate->rowCount() > 0) {
        // Optional: log the action
        // You can insert into an activity log table here if one exists.

        echo json_encode([
            'success'  => true,
            'message'  => "Password for '{$targetUser['username']}' has been reset successfully."
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes were made. Please try again.']);
    }
} catch (PDOException $e) {
    error_log("Password reset error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please contact support.']);
}
?>
