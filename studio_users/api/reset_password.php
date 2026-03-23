<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['identity_verified']) || $_SESSION['identity_verified'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Identity not verified. Complete Step 2 first.']);
    exit;
}

require_once '../../config/db_connect.php';

$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (empty($newPassword)) {
    echo json_encode(['status' => 'error', 'message' => 'New password is required.']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['status' => 'error', 'message' => 'Passwords do not match.']);
    exit;
}

// Password rules: 8 chars, 1 capital, 1 number
if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters long and include an uppercase letter and a number.']);
    exit;
}

try {
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    if ($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
        // Log to activity log
        try {
            $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], 'password_reset_success', 'Password successfully changed via OTP verification flow.']);
        } catch (Exception $e) {}

        // Success! Clear the verification flag
        unset($_SESSION['identity_verified']);
        echo json_encode(['status' => 'success', 'message' => 'Password reset successfully!']);
    } else {
        throw new Exception("Failed to update password in database.");
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
