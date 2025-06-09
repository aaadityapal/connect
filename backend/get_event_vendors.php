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
    
    // Get vendors associated with this event
    $vendor_query = "SELECT 
                        v.id,
                        v.vendor_name,
                        v.company_name,
                        v.contact_person,
                        v.phone,
                        v.materials,
                        v.status
                     FROM event_vendors ev
                     JOIN vendors v ON ev.vendor_id = v.id
                     WHERE ev.event_id = ?
                     ORDER BY v.vendor_name";
                     
    $stmt = $pdo->prepare($vendor_query);
    $stmt->execute([$event_id]);
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return success response with vendors data
    echo json_encode([
        'status' => 'success',
        'event_title' => $event['title'],
        'vendors' => $vendors
    ]);
    
} catch (PDOException $e) {
    // Log error
    error_log("Database error in get_event_vendors.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred',
        'debug_info' => $e->getMessage() // Remove in production
    ]);
} 