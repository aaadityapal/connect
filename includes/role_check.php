<?php
/**
 * Role check utility
 * Used to restrict access to pages based on user roles
 */

// Function to check if user has one of the required roles
function checkUserRole($allowed_roles = []) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    
    // If no roles specified, just check login
    if (empty($allowed_roles)) {
        return true;
    }
    
    // Check if user role is in allowed roles (case insensitive)
    $user_role = strtolower($_SESSION['role'] ?? '');
    $allowed_roles = array_map('strtolower', $allowed_roles);
    
    if (!in_array($user_role, $allowed_roles)) {
        // Redirect to unauthorized page
        header('Location: unauthorized.php');
        exit;
    }
    
    return true;
}
?> 