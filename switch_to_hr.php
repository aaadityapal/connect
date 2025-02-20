<?php
session_start();

// Check if the user is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Store original admin session data if needed
$_SESSION['original_role'] = $_SESSION['role'];
$_SESSION['original_user_id'] = $_SESSION['user_id'];

// Set temporary HR access
$_SESSION['role'] = 'HR';
$_SESSION['temp_admin_access'] = true; // Flag to indicate admin is viewing HR dashboard

// Redirect to HR dashboard
header('Location: hr_dashboard.php');
exit();
