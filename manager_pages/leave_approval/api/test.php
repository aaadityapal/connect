<?php
$_GET['user'] = 1;
$_GET['year'] = 2026;
$_GET['month'] = 5;

// Mock session and include
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

ob_start();
require 'api/fetch_leave_bank.php';
$output = ob_get_clean();

echo $output;
