<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and has HR role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    $_SESSION['error'] = "Access denied. Only HR personnel can access this feature.";
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($user_id) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: hr_password_reset.php");
        exit();
    }

    // Validate password match
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: hr_password_reset.php");
        exit();
    }

    // Validate password strength
    if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/", $new_password)) {
        $_SESSION['error'] = "Password must be at least 8 characters long and include uppercase, lowercase, and numbers.";
        header("Location: hr_password_reset.php");
        exit();
    }

    try {
        // Check if user exists and is not the current HR
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND user_id != ?");
        $stmt->bind_param("ii", $user_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $_SESSION['error'] = "Invalid user selected.";
            header("Location: hr_password_reset.php");
            exit();
        }

        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update the password
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $update_stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($update_stmt->execute()) {
            // Log the password reset action
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, performed_by, performed_by_role) VALUES (?, 'password_reset', ?, 'HR')");
            $log_stmt->bind_param("ii", $user_id, $_SESSION['user_id']);
            $log_stmt->execute();
            
            $_SESSION['success'] = "Password has been successfully reset.";
        } else {
            $_SESSION['error'] = "Failed to reset password. Please try again.";
        }

    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred. Please try again later.";
        // Log the error for administrator
        error_log("Password reset error: " . $e->getMessage());
    }

} else {
    $_SESSION['error'] = "Invalid request method.";
}

header("Location: hr_password_reset.php");
exit();
?>