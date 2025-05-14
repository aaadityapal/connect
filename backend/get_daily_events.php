<?php
/**
 * This file fetches all calendar events for a specific date
 * Used for displaying events in the date events modal
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
include_once('../includes/db_connect.php');

// Set the content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not authenticated'
    ]);
    exit;
}

// Get date parameter from request
$date = isset($_GET['date']) ? $_GET['date'] : '';

// Validate date
if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid date format. Expected YYYY-MM-DD'
    ]);
    exit;
}

try {
    // Query to get all events for the specified date
    $stmt = $conn->prepare("
        SELECT 
            e.event_id, 
            e.title, 
            e.event_date, 
            e.created_by,
            e.created_at,
            u.username as creator_name,
            
            /* Count related items to determine event type */
            (SELECT COUNT(*) FROM sv_event_vendors WHERE event_id = e.event_id) AS vendor_count,
            (SELECT COUNT(*) FROM sv_company_labours WHERE event_id = e.event_id) AS company_labour_count,
            (SELECT COUNT(*) FROM sv_event_beverages WHERE event_id = e.event_id) AS beverage_count,
            (SELECT COUNT(*) FROM sv_work_progress WHERE event_id = e.event_id) AS work_progress_count,
            (SELECT COUNT(*) FROM sv_inventory_items WHERE event_id = e.event_id) AS inventory_count
            
        FROM sv_calendar_events e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.event_date = ?
        ORDER BY e.created_at ASC
    ");
    
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    
    while ($row = $result->fetch_assoc()) {
        // Determine the primary event type based on what items it contains
        $event_type = 'meeting'; // Default type
        
        if ($row['inventory_count'] > 0) {
            $event_type = 'delivery';
        } elseif ($row['work_progress_count'] > 0) {
            $event_type = 'inspection';
        } elseif ($row['vendor_count'] > 0 || $row['company_labour_count'] > 0) {
            $event_type = 'report';
        }
        
        // Format created date
        $created_date = date('F j, Y h:i A', strtotime($row['created_at']));
        
        // Add to events array
        $events[] = [
            'id' => $row['event_id'],
            'title' => $row['title'],
            'type' => $event_type,
            'created_by' => [
                'id' => $row['created_by'],
                'name' => $row['creator_name']
            ],
            'created_at' => $created_date,
            'counts' => [
                'vendors' => $row['vendor_count'],
                'company_labours' => $row['company_labour_count'],
                'beverages' => $row['beverage_count'],
                'work_progress' => $row['work_progress_count'],
                'inventory' => $row['inventory_count']
            ]
        ];
    }
    
    // Format date for display
    $formatted_date = date('F j, Y', strtotime($date));
    
    // Return success response with events
    echo json_encode([
        'status' => 'success',
        'date' => [
            'raw' => $date,
            'formatted' => $formatted_date
        ],
        'events' => $events,
        'count' => count($events)
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log('Error in get_daily_events.php: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 