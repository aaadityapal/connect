<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    header('Location: login.php');
    exit();
}

// Handle POST request (Add/Edit role)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $role_name = $_POST['role_name'];
        $description = $_POST['description'];
        
        if (isset($_POST['role_id'])) {
            // Update existing role
            $stmt = $pdo->prepare("
                UPDATE roles 
                SET role_name = ?, description = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$role_name, $description, $_POST['role_id']]);
            $_SESSION['success_message'] = "Role updated successfully!";
        } else {
            // Add new role
            $stmt = $pdo->prepare("
                INSERT INTO roles (role_name, description, created_by) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$role_name, $description, $_SESSION['user_id']]);
            $_SESSION['success_message'] = "Role added successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    try {
        $role_id = $_GET['id'];
        
        // Check if role is assigned to any users
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE role_id = ?");
        $check_stmt->execute([$role_id]);
        
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception("Cannot delete role: It is assigned to users");
        }
        
        $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$role_id]);
        $_SESSION['success_message'] = "Role deleted successfully!";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

header('Location: roles_management.php');
exit();
?>
