<?php
// Test file to debug the resubmit functionality
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response = array(
        'success' => false,
        'message' => 'User not logged in'
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Check if database connection is successful
if ($conn->connect_error) {
    $response = array(
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    );
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Test response
$response = array(
    'success' => true,
    'message' => 'Database connection successful',
    'user_id' => $_SESSION['user_id'],
    'timestamp' => date('Y-m-d H:i:s')
);

header('Content-Type: application/json');
echo json_encode($response);
exit();
?>