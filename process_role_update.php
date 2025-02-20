<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    header('Location: login.php');
    exit();
}

if (isset($_GET['role_id']) && isset($_GET['status'])) {
    try {
        $role_id = $_GET['role_id'];
        $status = $_GET['status'];
        $user_id = null;

        // Get user_id before updating
        $get_user = $pdo->prepare("SELECT user_id FROM user_roles WHERE id = ?");
        $get_user->execute([$role_id]);
        $result = $get_user->fetch();
        $user_id = $result['user_id'];

        // Update role status
        $stmt = $pdo->prepare("
            UPDATE user_roles 
            SET status = ? 
            WHERE id = ?
        ");
        
        if ($stmt->execute([$status, $role_id])) {
            $_SESSION['message'] = "Role status updated successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            throw new Exception("Failed to update role status");
        }

    } catch (Exception $e) {
        $_SESSION['message'] = $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }

    header('Location: manage_roles.php?id=' . $user_id);
    exit();
}
?> 