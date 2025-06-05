<?php
// Start session for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Authentication required', 'success' => false]);
    exit();
}

// Check if user has the correct role
$allowed_roles = ['Senior Manager (Site)', 'Purchase Manager', 'HR'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Permission denied', 'success' => false]);
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Check if required parameters are provided
if (!isset($_GET['user_id']) || !isset($_GET['travel_date']) || !isset($_GET['type'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required parameters', 'success' => false]);
    exit();
}

$user_id = intval($_GET['user_id']);
$travel_date = $_GET['travel_date'];
$type = $_GET['type'];

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $travel_date)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid date format', 'success' => false]);
    exit();
}

try {
    // Determine which photo to fetch based on type
    $photo_column = ($type === 'from') ? 'punch_in_photo' : 'punch_out_photo';
    $time_column = ($type === 'from') ? 'punch_in' : 'punch_out';
    $location_column = ($type === 'from') ? 'location' : 'punch_out_address';
    $latitude_column = ($type === 'from') ? 'punch_in_latitude' : 'punch_out_latitude';
    $longitude_column = ($type === 'from') ? 'punch_in_longitude' : 'punch_out_longitude';
    $accuracy_column = ($type === 'from') ? 'punch_in_accuracy' : 'punch_out_accuracy';
    
    // Fetch the attendance record for the given user and date
    $stmt = $conn->prepare("
        SELECT 
            $photo_column AS photo,
            $time_column AS time,
            date AS attendance_date,
            $location_column AS location_address,
            $latitude_column AS latitude,
            $longitude_column AS longitude,
            $accuracy_column AS accuracy
        FROM 
            attendance
        WHERE 
            user_id = ? 
            AND DATE(date) = DATE(?)
        LIMIT 1
    ");
    
    $stmt->bind_param("is", $user_id, $travel_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Format date and time
        $date = new DateTime($row['attendance_date']);
        $formatted_date = $date->format('M d, Y');
        
        $time = !empty($row['time']) ? $row['time'] : null;
        $formatted_time = $time ? date('h:i A', strtotime($time)) : 'N/A';
        
        // Process photo data
        $photo = null;
        if (!empty($row['photo'])) {
            $photo = $row['photo'];
            
            // Check if the photo is already a data URL
            if (strpos($photo, 'data:image') !== 0) {
                // Check if it's a file path
                if (file_exists($photo)) {
                    $image_type = pathinfo($photo, PATHINFO_EXTENSION);
                    $image_data = file_get_contents($photo);
                    $photo = 'data:image/' . $image_type . ';base64,' . base64_encode($image_data);
                }
                // Check if it's a URL
                else if (filter_var($photo, FILTER_VALIDATE_URL)) {
                    // Keep as is - it's already a valid URL
                }
                // If it's not a data URL, file path, or URL, it might be a base64 string without the prefix
                else if (base64_encode(base64_decode($photo, true)) === $photo) {
                    // It's likely a base64 string without the prefix
                    $photo = 'data:image/jpeg;base64,' . $photo;
                }
            }
        }
        
        $has_photo = !empty($photo);
        
        // Process location address data
        $location_data = [];
        $formatted_address = 'N/A';
        
        if (!empty($row['location_address'])) {
            // Try to parse as JSON first
            $json_data = json_decode($row['location_address'], true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                // It's valid JSON
                $location_data = $json_data;
                
                // Extract address from JSON if available
                if (isset($json_data['address'])) {
                    $formatted_address = $json_data['address'];
                }
            } else {
                // Not JSON, use as plain text
                $formatted_address = $row['location_address'];
            }
        }
        
        // Get coordinates
        $latitude = !empty($row['latitude']) ? $row['latitude'] : null;
        $longitude = !empty($row['longitude']) ? $row['longitude'] : null;
        $accuracy = !empty($row['accuracy']) ? $row['accuracy'] : null;
        
        // Prepare map URL if coordinates are available
        $map_url = null;
        if ($latitude && $longitude) {
            $map_url = "https://www.google.com/maps?q={$latitude},{$longitude}";
        }
        
        // Return photo data
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $has_photo,
            'photo' => $has_photo ? $photo : null,
            'date' => $formatted_date,
            'time' => $formatted_time,
            'formatted_address' => $formatted_address,
            'location_data' => $location_data,
            'coordinates' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => $accuracy
            ],
            'map_url' => $map_url,
            'error' => $has_photo ? null : 'No photo available for this date'
        ]);
    } else {
        // No attendance record found
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'No attendance record found for this date'
        ]);
    }
    
} catch (Exception $e) {
    // Database error
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 