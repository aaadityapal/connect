<?php
require_once '../config/db_connect.php';
require_once '../manage_leave_balance.php';

$year = date('Y');

// Get all active users
$query = "SELECT id FROM users WHERE status = 'active' AND deleted_at IS NULL";
$result = $conn->query($query);
$users = $result->fetch_all(MYSQLI_ASSOC);

foreach ($users as $user) {
    initializeUserLeaveBalance($user['id'], $year);
}
?> 