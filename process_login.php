<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Debug information
    error_log("Login attempt - Identifier: " . $_POST['login_identifier']);
    
    $login_identifier = $_POST['login_identifier'];
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ? OR unique_id = ?");
        $stmt->execute([$login_identifier, $login_identifier, $login_identifier]);
        $user = $stmt->fetch();

        // Debug information
        error_log("User found: " . ($user ? "Yes" : "No"));
        if ($user) {
            error_log("Role: " . $user['role']);
        }

        if ($user && password_verify($password, $user['password'])) {
            error_log("Password verified successfully");
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['role'] == 'admin') {
                header("Location: admin_dashboard.php");
                exit();
            } else {
                header("Location: employee_dashboard.php");
                exit();
            }
        } else {
            // Invalid credentials
            header("Location: login.php?error=invalid");
            exit();
        }
    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        die("Error: " . $e->getMessage());
    }
}
?>
