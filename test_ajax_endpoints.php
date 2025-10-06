<?php
// Test script for AJAX endpoints
session_start();
$_SESSION['user_id'] = 1; // Set test user ID

echo "Testing check_notification_read_status.php\n";

// Simulate POST data
$_POST['dates'] = [date('Y-m-d')];

// Include the check notification read status script
chdir('ajax_handlers');
include 'check_notification_read_status.php';
?>