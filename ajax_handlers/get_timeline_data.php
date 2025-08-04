<?php
require_once '../config/db_connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'data' => [], 'message' => ''];

try {
    if (!isset($_GET['user_id']) || !isset($_GET['travel_date'])) {
        throw new Exception('Missing required parameters');
    }

    $userId = $_GET['user_id'];
    $travelDate = $_GET['travel_date'];

    // First get the travel expense data for context
    $expenseQuery = "SELECT * FROM travel_expenses 
                    WHERE user_id = :user_id 
                    AND travel_date = :travel_date";
    
    $expenseStmt = $pdo->prepare($expenseQuery);
    $expenseStmt->execute([
        ':user_id' => $userId,
        ':travel_date' => $travelDate
    ]);
    $expenseData = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get site in/out logs with detailed geofence information
    $logsQuery = "SELECT 
                    l.*,
                    g.name as location_name,
                    g.address as geofence_address,
                    g.latitude as geofence_latitude,
                    g.longitude as geofence_longitude,
                    g.radius as geofence_radius,
                    g.is_active as geofence_is_active,
                    CASE 
                        WHEN g.is_active = 0 THEN 'Inactive Site'
                        ELSE g.name
                    END as display_name,
                    CASE 
                        WHEN g.is_active = 0 THEN 'warning'
                        ELSE 'primary'
                    END as status_type
                  FROM site_in_out_logs l
                  LEFT JOIN geofence_locations g ON l.geofence_location_id = g.id
                  WHERE l.user_id = :user_id 
                  AND DATE(l.timestamp) = :travel_date
                  ORDER BY l.timestamp ASC";
    
    $logsStmt = $pdo->prepare($logsQuery);
    $logsStmt->execute([
        ':user_id' => $userId,
        ':travel_date' => $travelDate
    ]);
    $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get user details
    $userQuery = "SELECT username, profile_picture FROM users WHERE id = :user_id";
    $userStmt = $pdo->prepare($userQuery);
    $userStmt->execute([':user_id' => $userId]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

    // Get all active geofence locations for reference
    $geofenceQuery = "SELECT * FROM geofence_locations WHERE is_active = 1";
    $geofenceStmt = $pdo->prepare($geofenceQuery);
    $geofenceStmt->execute();
    $geofenceLocations = $geofenceStmt->fetchAll(PDO::FETCH_ASSOC);

    // Process logs to add additional information
    foreach ($logs as &$log) {
        // Calculate actual distance using coordinates if available
        if (!empty($log['latitude']) && !empty($log['longitude']) && 
            !empty($log['geofence_latitude']) && !empty($log['geofence_longitude'])) {
            
            $distance = calculateDistance(
                $log['latitude'], 
                $log['longitude'], 
                $log['geofence_latitude'], 
                $log['geofence_longitude']
            );
            
            $log['calculated_distance'] = $distance;
            $log['is_within_radius'] = $distance <= ($log['geofence_radius'] ?? 100);
        }

        // Add status indicators
        $log['status_indicators'] = [
            'is_active_site' => !empty($log['geofence_is_active']),
            'is_within_radius' => $log['is_within_radius'] ?? false,
            'has_coordinates' => !empty($log['latitude']) && !empty($log['longitude'])
        ];
    }
    unset($log); // Break reference

    $response['success'] = true;
    $response['data'] = [
        'user' => $userData,
        'expenses' => $expenseData,
        'logs' => $logs,
        'geofence_locations' => $geofenceLocations
    ];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Timeline data error: " . $e->getMessage());
}

// Helper function to calculate distance between two points
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Earth's radius in meters

    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);

    $latDelta = $lat2 - $lat1;
    $lonDelta = $lon2 - $lon1;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($lat1) * cos($lat2) * pow(sin($lonDelta / 2), 2)));
    
    return $angle * $earthRadius;
}

echo json_encode($response);