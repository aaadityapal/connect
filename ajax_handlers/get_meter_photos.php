<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get parameters
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$travelDate = isset($_GET['travel_date']) ? $_GET['travel_date'] : '';

// Validate parameters
if (!$userId || empty($travelDate)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    // Query to get meter photos for the user on the specified date
    $query = "SELECT 
                te.meter_start_photo_path,
                te.meter_end_photo_path,
                te.travel_date,
                te.from_location,
                te.to_location
              FROM travel_expenses te
              WHERE te.user_id = :user_id 
              AND DATE(te.travel_date) = :travel_date
              LIMIT 1";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':travel_date', $travelDate);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // Format the response
        $response = [
            'success' => true,
            'meter_start_photo_path' => !empty($result['meter_start_photo_path']) ? $result['meter_start_photo_path'] : null,
            'meter_end_photo_path' => !empty($result['meter_end_photo_path']) ? $result['meter_end_photo_path'] : null,
            'travel_date' => $result['travel_date'],
            'from_location' => $result['from_location'],
            'to_location' => $result['to_location']
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'No meter photos found for this date'
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    // Log error
    error_log('Error fetching meter photos: ' . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred while fetching meter photos'
    ]);
}
?>