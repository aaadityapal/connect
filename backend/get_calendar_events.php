<?php
/**
 * This file fetches all calendar events from the database for display on the calendar
 * Shows events to all users regardless of who created them
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

// Get year and month if provided, otherwise use current
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');

// Validate month and year
if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid month or year'
    ]);
    exit;
}

// Construct date range for the query
$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = sprintf('%04d-%02d-%02d', $year, $month, date('t', strtotime($start_date)));

try {
    // Prepare the query to get all events within the date range
    // No filtering by created_by to show all events to all users
    $stmt = $conn->prepare("
        SELECT 
            e.event_id, 
            e.title, 
            e.event_date, 
            e.created_by,
            u.username as creator_name,
            
            /* Count related items to determine event type */
            (SELECT COUNT(*) FROM sv_event_vendors WHERE event_id = e.event_id) AS vendor_count,
            (SELECT COUNT(*) FROM sv_company_labours WHERE event_id = e.event_id) AS company_labour_count,
            (SELECT COUNT(*) FROM sv_event_beverages WHERE event_id = e.event_id) AS beverage_count,
            (SELECT COUNT(*) FROM sv_work_progress WHERE event_id = e.event_id) AS work_progress_count,
            (SELECT COUNT(*) FROM sv_inventory_items WHERE event_id = e.event_id) AS inventory_count
            
        FROM sv_calendar_events e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.event_date BETWEEN ? AND ?
        ORDER BY e.event_date ASC
    ");
    
    $stmt->bind_param("ss", $start_date, $end_date);
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
        
        // Format date to standard format
        $date_obj = new DateTime($row['event_date']);
        $formatted_date = $date_obj->format('Y-m-d');
        
        // Add to events array
        $events[] = [
            'id' => $row['event_id'],
            'title' => $row['title'],
            'date' => $formatted_date,
            'type' => $event_type,
            'created_by' => [
                'id' => $row['created_by'],
                'name' => $row['creator_name']
            ],
            'counts' => [
                'vendors' => $row['vendor_count'],
                'company_labours' => $row['company_labour_count'],
                'beverages' => $row['beverage_count'],
                'work_progress' => $row['work_progress_count'],
                'inventory' => $row['inventory_count']
            ]
        ];
    }
    
    // Return success response with events
    echo json_encode([
        'status' => 'success',
        'events' => $events,
        'date_range' => [
            'start' => $start_date,
            'end' => $end_date
        ]
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log('Error in get_calendar_events.php: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 