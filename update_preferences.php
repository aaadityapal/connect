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

// Process preference update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get preference values, using defaults if not provided
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
    $daily_report = isset($_POST['daily_report']) ? 1 : 0;
    $theme_preference = $_POST['theme_preference'] ?? 'light';
    $dashboard_layout = $_POST['dashboard_layout'] ?? 'standard';
    
    // Initialize message variables
    $message = '';
    $messageType = '';
    
    try {
        // Check if preferences record exists for user
        $check_stmt = $pdo->prepare("SELECT id FROM user_preferences WHERE user_id = ?");
        $check_stmt->execute([$_SESSION['user_id']]);
        $preference_exists = $check_stmt->fetchColumn();
        
        if ($preference_exists) {
            // Update existing preferences
            $update_stmt = $pdo->prepare("UPDATE user_preferences SET 
                email_notifications = ?,
                sms_notifications = ?,
                daily_report = ?,
                theme_preference = ?,
                dashboard_layout = ?,
                updated_at = NOW()
                WHERE user_id = ?");
                
            $result = $update_stmt->execute([
                $email_notifications,
                $sms_notifications,
                $daily_report,
                $theme_preference,
                $dashboard_layout,
                $_SESSION['user_id']
            ]);
        } else {
            // Insert new preferences
            $insert_stmt = $pdo->prepare("INSERT INTO user_preferences 
                (user_id, email_notifications, sms_notifications, daily_report, theme_preference, dashboard_layout, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                
            $result = $insert_stmt->execute([
                $_SESSION['user_id'],
                $email_notifications,
                $sms_notifications,
                $daily_report,
                $theme_preference,
                $dashboard_layout
            ]);
        }
        
        if ($result) {
            $message = "Preferences updated successfully!";
            $messageType = "success";
        } else {
            $message = "Failed to update preferences.";
            $messageType = "danger";
        }
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
        $messageType = "danger";
    }
    
    // Check if the request is AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        // Send JSON response for AJAX requests
        header('Content-Type: application/json');
        echo json_encode(['message' => $message, 'type' => $messageType]);
    } else {
        // For regular form submissions, store message in session and redirect
        $_SESSION['preferences_message'] = $message;
        $_SESSION['preferences_message_type'] = $messageType;
        header("Location: site_supervisor_profile.php");
    }
    exit();
} else {
    // If accessed directly, redirect to profile page
    header("Location: site_supervisor_profile.php");
    exit();
}
?>