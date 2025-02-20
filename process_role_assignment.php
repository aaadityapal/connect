<?php
session_start();
require_once 'config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'];
    $roles = $_POST['roles'];
    $assigned_by = $_SESSION['user_id']; // Current HR user's ID
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // First, remove all existing roles for this user
        $delete_query = "DELETE FROM user_roles WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        
        // Then insert new roles
        if (!empty($roles)) {
            $insert_query = "INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_query);
            
            foreach ($roles as $role_id) {
                mysqli_stmt_bind_param($stmt, "iii", $user_id, $role_id, $assigned_by);
                mysqli_stmt_execute($stmt);
            }
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        $_SESSION['success'] = "Roles assigned successfully!";
        header("Location: view_user.php?id=" . $user_id);
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $_SESSION['error'] = "Error assigning roles: " . $e->getMessage();
        header("Location: assign_role.php?user_id=" . $user_id);
        exit();
    }
}
