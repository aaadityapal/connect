<?php
/**
 * Direct test for approval handlers
 */
session_start();
require_once 'config/db_connect.php';

// Set test data
$_POST['missing_punch_id'] = 1;
$_POST['status'] = 'approved';
$_POST['admin_notes'] = 'Test approval';

// Simulate session
$_SESSION['user_id'] = 1;

// Include the handler directly to test it
echo "<h2>Testing approve_missing_punch_in.php</h2>";
echo "<pre>";
include 'ajax_handlers/approve_missing_punch_in.php';
echo "</pre>";

echo "<h2>Testing approve_missing_punch_out.php</h2>";
echo "<pre>";
// Reset POST data for second test
$_POST['missing_punch_id'] = 1;
$_POST['status'] = 'approved';
$_POST['admin_notes'] = 'Test approval';
include 'ajax_handlers/approve_missing_punch_out.php';
echo "</pre>";
?>