<?php
// Mock a session for testing the API
session_start();
$_SESSION['user_id'] = 11; // Testing with Manager ID 11 which we know has notifications

echo "<h2>Testing fetch_overtime.php with Manager ID 11</h2>";
require_once '../../config.php';
include 'api/fetch_overtime.php';
