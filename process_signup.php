<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validate and sanitize inputs
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
        $password = $_POST['password'];
        $reporting_manager = filter_input(INPUT_POST, 'reporting_manager', FILTER_SANITIZE_STRING);

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Generate unique ID based on role
        $prefix = '';
        switch($role) {
            case 'admin':
                $prefix = 'ADM';
                break;
            case 'HR':
                $prefix = 'HR';
                break;
            case strpos($role, 'Senior Manager') !== false:
                $prefix = 'SM';
                break;
            case strpos($role, 'Manager') !== false:
                $prefix = 'MGR';
                break;
            default:
                $prefix = 'EMP';
        }

        // Get the next available number for this prefix
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(unique_id, :len) AS UNSIGNED)) as max_id 
                              FROM users WHERE unique_id LIKE :prefix");
        $stmt->execute([
            'len' => strlen($prefix) + 1,
            'prefix' => $prefix . '%'
        ]);
        $result = $stmt->fetch();
        $next_id = $result['max_id'] ? $result['max_id'] + 1 : 1;
        $unique_id = $prefix . str_pad($next_id, 3, '0', STR_PAD_LEFT);

        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (unique_id, username, email, password, role, reporting_manager) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $unique_id,
            $username,
            $email,
            $hashed_password,
            $role,
            $reporting_manager
        ]);

        $_SESSION['success'] = "Registration successful! Your ID is: " . $unique_id;
        header('Location: login.php');
        exit();

    } catch(PDOException $e) {
        $_SESSION['error'] = "Registration failed: " . $e->getMessage();
        header('Location: signup.php');
        exit();
    }
}
?>
