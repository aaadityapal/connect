<?php
// Include database connection
require_once '../config/db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if event ID is provided
if (!isset($_GET['event_id']) || empty($_GET['event_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Event ID is required'
    ]);
    exit;
}

$event_id = intval($_GET['event_id']);

try {
    // Get event title first
    $event_query = "SELECT title FROM daily_events WHERE id = ?";
    $stmt = $pdo->prepare($event_query);
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Event not found'
        ]);
        exit;
    }
    
    // Get labours associated with this event
    $labour_query = "SELECT 
                        l.id,
                        l.labour_name,
                        l.id_number,
                        l.labour_type,
                        l.contact_number,
                        l.morning_attendance,
                        l.evening_attendance,
                        v.vendor_name
                     FROM event_labours el
                     JOIN company_labours l ON el.labour_id = l.id
                     LEFT JOIN vendors v ON l.vendor_id = v.id
                     WHERE el.event_id = ?
                     ORDER BY l.labour_name";
                     
    $stmt = $pdo->prepare($labour_query);
    $stmt->execute([$event_id]);
    $labours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return success response with labours data
    echo json_encode([
        'status' => 'success',
        'event_title' => $event['title'],
        'labours' => $labours
    ]);
    
} catch (PDOException $e) {
    // Log error
    error_log("Database error in get_event_labours.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred',
        'debug_info' => $e->getMessage() // Remove in production
    ]);
} 