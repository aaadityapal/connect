<?php
session_start();
require_once 'config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        header('Location: login.php?error=empty_fields');
        exit();
    }
    
    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, role, password, status FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user && password_verify($password, $user['password'])) {
        // Check if user account is active
        if ($user['status'] !== 'active') {
            header('Location: login.php?error=account_inactive');
            exit();
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        
        // For debugging
        error_log("User logged in - ID: {$user['id']}, Role: {$user['role']}");
        
        // Redirect based on role
        switch($user['role']) {
            case 'studio_manager':
                header('Location: studio_manager_dashboard.php');
                break;
            case 'architect':
                header('Location: architect_dashboard.php');
                break;
            case 'client':
                header('Location: client_dashboard.php');
                break;
            default:
                header('Location: error.php?type=invalid_role');
                break;
        }
    } else {
        header('Location: index.php?error=invalid_credentials');
    }
    exit();
} 