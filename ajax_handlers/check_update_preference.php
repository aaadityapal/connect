<?php
session_start();
require_once '../config/db_connect.php';

header('Content-Type: application/json');

// Default response
$response = [
    'dontShow' => false,
    'success' => true
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get the current update version
// This should be updated whenever you make changes to the update modal content
$current_update_version = '1.1'; // Changed from 1.0 to 1.1 for the geofencing update

// Check if user has opted out for this version
$stmt = $conn->prepare("SELECT * FROM user_update_preferences WHERE user_id = ? AND update_version = ? LIMIT 1");
$stmt->bind_param("is", $user_id, $current_update_version);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $response['dontShow'] = ($row['dont_show'] == 1);
}

echo json_encode($response);
exit; 