<?php
session_start();
require_once 'config.php';

// Add debug logging to help troubleshoot
error_log('User accessing assign_task.php - User ID: ' . ($_SESSION['user_id'] ?? 'not set'));

// Simplified access check
if (!isset($_SESSION['user_id'])) {
    error_log('User not logged in, redirecting to login');
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("INSERT INTO tasks (created_by, assigned_to, title, description, due_date, priority, status, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['selected_user_id'],
            $_POST['task_title'],
            $_POST['task_description'],
            $_POST['due_date'],
            $_POST['priority']
        ]);

        header('Location: studio_manager_dashboard.php?success=1');
        exit();
    } catch (PDOException $e) {
        header('Location: studio_manager_dashboard.php?error=1');
        exit();
    }
}

header('Location: studio_manager_dashboard.php');
exit();
?>
