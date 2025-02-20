<?php
session_start();
require_once 'config/db_connect.php';

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Check current punch status
$check_punch = $conn->prepare("SELECT punch_in, punch_out FROM attendance WHERE user_id = ? AND date = ?");
$check_punch->bind_param("is", $user_id, $today);
$check_punch->execute();
$result = $check_punch->get_result();
$attendance = $result->fetch_assoc();

$response = [
    'is_punched_in' => false,
    'last_punch_in' => null
];

if ($attendance && $attendance['punch_in']) {
    $response['is_punched_in'] = true;
    $response['last_punch_in'] = date('h:i A', strtotime($attendance['punch_in']));
}

echo json_encode($response);
?> 