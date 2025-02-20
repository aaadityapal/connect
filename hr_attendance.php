<?php
session_start();
require_once 'config.php';

// Only allow access with valid HR token
if (!isset($_GET['hr_token']) || $_GET['hr_token'] !== HR_TOKEN) {
    header('Location: login.php');
    exit();
}

// Set a temporary session flag for HR access
$_SESSION['hr_attendance_access'] = true;

// Redirect to the main attendance page
header('Location: admin_attendance.php');
exit();
