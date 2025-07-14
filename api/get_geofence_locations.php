<?php
/**
 * API endpoint to fetch geofence locations
 * Returns active geofence locations for the current user
 */

// Start session and include database connection
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Get the user ID from session
$user_id = $_SESSION['user_id'];

// Prepare response array
$response = [
    'status' => 'success',
    'locations' => []
];

try {
    // First, try to get locations specifically assigned to this user
    $query = "SELECT gl.* 
              FROM geofence_locations gl
              JOIN user_geofence_locations ugl ON gl.id = ugl.geofence_location_id
              WHERE ugl.user_id = ? 
              AND gl.is_active = 1
              AND (ugl.effective_to IS NULL OR ugl.effective_to >= CURDATE())
              AND ugl.effective_from <= CURDATE()";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // If no specific locations for this user, get all active locations
    if ($result->num_rows == 0) {
        $query = "SELECT * FROM geofence_locations WHERE is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    // Fetch all locations
    while ($row = $result->fetch_assoc()) {
        $response['locations'][] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'address' => $row['address'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'radius' => $row['radius']
        ];
    }
    
    $stmt->close();
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?> 