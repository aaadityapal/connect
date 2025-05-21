<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Process password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Initialize message variables
    $message = '';
    $messageType = '';
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = "All password fields are required.";
        $messageType = "danger";
    } elseif ($new_password !== $confirm_password) {
        $message = "New password and confirmation do not match.";
        $messageType = "danger";
    } elseif (strlen($new_password) < 8) {
        $message = "Password must be at least 8 characters long.";
        $messageType = "danger";
    } else {
        try {
            // First check if current password is correct
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($current_password, $user['password'])) {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password in database
                $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $result = $update_stmt->execute([$hashed_password, $_SESSION['user_id']]);
                
                if ($result) {
                    $message = "Password updated successfully!";
                    $messageType = "success";
                } else {
                    $message = "Failed to update password. Please try again.";
                    $messageType = "danger";
                }
            } else {
                $message = "Current password is incorrect.";
                $messageType = "danger";
            }
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
    
    // Store message in session and redirect back to profile page
    $_SESSION['password_message'] = $message;
    $_SESSION['password_message_type'] = $messageType;
    header("Location: site_supervisor_profile.php");
    exit();
} else {
    // If accessed directly, redirect to profile page
    header("Location: site_supervisor_profile.php");
    exit();
}
?>