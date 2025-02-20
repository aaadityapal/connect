<?php
session_start();

// Verify this is an admin viewing HR dashboard
if (!isset($_SESSION['temp_admin_access'])) {
    header('Location: login.php');
    exit();
}

// Restore original admin role
$_SESSION['role'] = $_SESSION['original_role'];

// Clean up temporary sessions
unset($_SESSION['temp_admin_access']);
unset($_SESSION['original_role']);
unset($_SESSION['original_user_id']);

// Redirect back to admin dashboard
header('Location: admin_dashboard.php');
exit();
