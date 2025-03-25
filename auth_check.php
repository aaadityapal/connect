<?php
session_start();
require_once 'config.php';

// Check if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user has the correct role
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, r.role_name 
        FROM users u
        JOIN user_roles ur ON u.id = ur.user_id
        JOIN roles r ON ur.role_id = r.id
        WHERE u.id = ? AND r.role_name = 'Senior Manager (Studio)'
    ");
    
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // User doesn't have the required role
        session_destroy();
        header('Location: login.php?error=unauthorized');
        exit();
    }
    
    // Store user data in session for use in dashboard
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role_name'];
    
} catch (PDOException $e) {
    error_log("Authentication Error: " . $e->getMessage());
    header('Location: login.php?error=system');
    exit();
}
?> 