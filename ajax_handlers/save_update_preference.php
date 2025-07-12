<?php
session_start();
require_once '../config/db_connect.php';

header('Content-Type: application/json');

// Default response
$response = [
    'success' => false,
    'message' => 'Unknown error occurred'
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in';
    echo json_encode($response);
    exit;
}

// Check if the request is POST and has the required parameter
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['dontShow'])) {
    $response['message'] = 'Invalid request';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$dont_show = ($_POST['dontShow'] == '1') ? 1 : 0;

// Get the current update version
// This should be updated whenever you make changes to the update modal content
$current_update_version = '1.1'; // Changed from 1.0 to 1.1 for the geofencing update

// Check if a record already exists for this user and version
$check_stmt = $conn->prepare("SELECT id FROM user_update_preferences WHERE user_id = ? AND update_version = ? LIMIT 1");
$check_stmt->bind_param("is", $user_id, $current_update_version);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    // Update existing record
    $row = $check_result->fetch_assoc();
    $stmt = $conn->prepare("UPDATE user_update_preferences SET dont_show = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ii", $dont_show, $row['id']);
} else {
    // Insert new record
    $stmt = $conn->prepare("INSERT INTO user_update_preferences (user_id, update_version, dont_show, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("isi", $user_id, $current_update_version, $dont_show);
}

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Preference saved successfully';
} else {
    $response['message'] = 'Failed to save preference: ' . $stmt->error;
}

echo json_encode($response);
exit; 