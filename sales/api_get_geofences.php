<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

try {
    // Fetch active geofence locations
    $query = "
        SELECT id, name, latitude, longitude, radius
        FROM geofence_locations
        WHERE is_active = 1
        ORDER BY name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $geofences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'geofences' => $geofences,
        'count' => count($geofences)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch geofences',
        'message' => $e->getMessage()
    ]);
}
?>
