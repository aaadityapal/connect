<?php
session_start();
header('Content-Type: application/json');

require_once '../config/timezone_config.php';
require_once '../includes/db_connect.php';

// Ensure correct timezone in MySQL
ensureCorrectTimezone($conn);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';
$latitude = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
$longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;
$address = isset($_POST['address']) ? $_POST['address'] : null;
if (!is_string($address) || $address === '0') {
    $address = null;
}
$device_info = isset($_POST['device_info']) ? $_POST['device_info'] : null;

if (!$action || !$latitude || !$longitude) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required data']);
    exit();
}

// Optional: Try to match geofence location
$geofence_location_id = null;
$distance_from_geofence = null;
if ($latitude && $longitude) {
    $sql = "SELECT id, latitude, longitude, (
                6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )
            ) AS distance
            FROM geofence_locations
            HAVING distance < 0.1
            ORDER BY distance ASC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ddd', $latitude, $longitude, $latitude);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $geofence_location_id = $row['id'];
        $distance_from_geofence = $row['distance'];
    }
    $stmt->close();
}

// Insert into site_in_out_logs with explicit timestamp in IST
$current_time = getCurrentIST();

if ($geofence_location_id === null) {
    $insert = $conn->prepare("INSERT INTO site_in_out_logs (user_id, action, latitude, longitude, address, device_info, distance_from_geofence, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insert->bind_param('issdssds', $user_id, $action, $latitude, $longitude, $address, $device_info, $distance_from_geofence, $current_time);
} else {
    $insert = $conn->prepare("INSERT INTO site_in_out_logs (user_id, action, latitude, longitude, address, geofence_location_id, device_info, distance_from_geofence, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insert->bind_param('issdsisss', $user_id, $action, $latitude, $longitude, $address, $geofence_location_id, $device_info, $distance_from_geofence, $current_time);
}

if ($insert->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Site ' . $action . ' recorded successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}
$insert->close();
$conn->close(); 