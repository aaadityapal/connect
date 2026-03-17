<?php
/**
 * API endpoint to fetch active geofence locations
 * Used by the punch in/out modal to validate employee location
 */

session_start();
require_once '../config/db_connect.php';

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = ['status' => 'success', 'locations' => []];

try {
    // Step 1: Try to get locations specifically assigned to this user (via user_geofence_locations if it exists)
    $user_specific_query = "SELECT gl.id, gl.name, gl.address, gl.latitude, gl.longitude, gl.radius
                            FROM geofence_locations gl
                            INNER JOIN user_geofence_locations ugl ON gl.id = ugl.geofence_location_id
                            WHERE ugl.user_id = ?
                              AND gl.is_active = 1
                              AND (ugl.effective_to IS NULL OR ugl.effective_to >= CURDATE())
                              AND ugl.effective_from <= CURDATE()
                            ORDER BY gl.name ASC";

    $stmt = $conn->prepare($user_specific_query);
    $locations_found = false;

    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $response['locations'][] = [
                    'id'        => (int)$row['id'],
                    'name'      => $row['name'],
                    'address'   => $row['address'],
                    'latitude'  => (float)$row['latitude'],
                    'longitude' => (float)$row['longitude'],
                    'radius'    => (float)$row['radius'],
                ];
            }
            $locations_found = true;
        }

        $stmt->close();
    }

    // Step 2: Fallback — fetch ALL active geofence locations if none assigned to this user
    if (!$locations_found) {
        $all_query = "SELECT id, name, address, latitude, longitude, radius
                      FROM geofence_locations
                      WHERE is_active = 1
                      ORDER BY name ASC";

        $stmt2 = $conn->prepare($all_query);
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        while ($row = $result2->fetch_assoc()) {
            $response['locations'][] = [
                'id'        => (int)$row['id'],
                'name'      => $row['name'],
                'address'   => $row['address'],
                'latitude'  => (float)$row['latitude'],
                'longitude' => (float)$row['longitude'],
                'radius'    => (float)$row['radius'],
            ];
        }

        $stmt2->close();
    }

} catch (Exception $e) {
    error_log("Geofence API Error: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
}

echo json_encode($response);
exit;
?>