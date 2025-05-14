<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Check if user has the 'Site Supervisor' role
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Site Supervisor') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Check if date is provided
if (!isset($_GET['date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
    exit();
}

$date = $_GET['date'];
$response = ['success' => false];

try {
    // Fetch events for the specified date
    $query = "SELECT e.*, u.username as created_by_name 
              FROM sv_calendar_events e 
              LEFT JOIN users u ON e.created_by = u.id 
              WHERE e.event_date = ?
              ORDER BY e.created_at ASC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    while ($event = $result->fetch_assoc()) {
        $events[] = $event;
    }
    
    $stmt->close();
    
    $response = [
        'success' => true,
        'date' => $date,
        'events' => $events
    ];
    
} catch (Exception $e) {
    error_log("Error fetching events: " . $e->getMessage());
    $response = [
        'success' => false,
        'error' => 'An error occurred while fetching events'
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit(); 