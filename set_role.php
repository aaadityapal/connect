<?php
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['role'])) {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$role = $_POST['role'];
$_SESSION['current_role'] = $role;

// Determine redirect URL based on role
$redirect = '';
switch($role) {
    case 'Senior Marketing Manager':
    case 'Marketing Manager':
        $redirect = 'marketing_manager_dashboard.php';
        break;
    case 'HR Manager':
        $redirect = 'hr_dashboard.php';
        break;
    case 'Admin':
        $redirect = 'admin_dashboard.php';
        break;
    default:
        $redirect = 'dashboard.php';
}

echo json_encode(['success' => true, 'redirect' => $redirect]);
?> 