<?php
/**
 * Get Daily Events API
 * Fetches events from sv_calendar_events table for a specific date
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once dirname(__FILE__) . '/../config/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not authenticated'
    ]);
    exit;
}

// Check for required parameters
if (!isset($_GET['date'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required parameter (date)'
    ]);
    exit;
}

$date = $_GET['date'];

// Validate date format (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid date format. Use YYYY-MM-DD'
    ]);
    exit;
}

try {
    // Prepare and execute the query to fetch events for the specific date
    $query = "SELECT e.event_id, e.title, e.event_date, e.created_by, u.username as created_by_name, e.created_at,
              (SELECT COUNT(*) FROM sv_event_vendors WHERE event_id = e.event_id) AS vendors,
              (SELECT COUNT(*) FROM sv_company_labours WHERE event_id = e.event_id) AS company_labours,
              (SELECT COUNT(*) FROM sv_event_beverages WHERE event_id = e.event_id) AS beverages,
            (SELECT COUNT(*) FROM sv_work_progress WHERE event_id = e.event_id) AS work_progress_count,
            (SELECT COUNT(*) FROM sv_inventory_items WHERE event_id = e.event_id) AS inventory_count
        FROM sv_calendar_events e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.event_date = ?
              ORDER BY e.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    
    if ($result) {
    while ($row = $result->fetch_assoc()) {
            // Determine event type based on counts
            $eventType = determineEventType($row);
            
            // Format event for output
        $events[] = [
            'id' => $row['event_id'],
            'title' => $row['title'],
                'type' => $eventType,
            'created_by' => [
                'id' => $row['created_by'],
                    'name' => $row['created_by_name'] ?? 'Unknown'
            ],
                'created_at' => date('M d, Y h:i A', strtotime($row['created_at'])),
            'counts' => [
                    'vendors' => (int)$row['vendors'],
                    'company_labours' => (int)$row['company_labours'],
                    'beverages' => (int)$row['beverages'],
                    'work_progress_count' => (int)$row['work_progress_count'],
                    'inventory_count' => (int)$row['inventory_count']
            ]
        ];
    }
    }
    
    // Return success response with events
    echo json_encode([
        'status' => 'success',
        'message' => 'Events fetched successfully',
        'date' => $date,
        'events' => $events
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch events: ' . $e->getMessage()
    ]);
}

/**
 * Determine the event type based on associated data
 */
function determineEventType($row) {
    // First check if counts suggest a specific type
    if ((int)$row['inventory_count'] > 0) {
        return 'delivery';
    } elseif ((int)$row['work_progress_count'] > 0) {
        return 'inspection';
    } elseif ((int)$row['vendors'] > 0) {
        return 'report';
    }
    
    // If no counts suggest a type, check title keywords
    $title = strtolower($row['title']);
    
    if (strpos($title, 'inspect') !== false || strpos($title, 'safety') !== false || strpos($title, 'check') !== false) {
        return 'inspection';
    } elseif (strpos($title, 'delivery') !== false || strpos($title, 'material') !== false || strpos($title, 'supply') !== false) {
        return 'delivery';
    } elseif (strpos($title, 'meeting') !== false || strpos($title, 'review') !== false || strpos($title, 'planning') !== false) {
        return 'meeting';
    } elseif (strpos($title, 'report') !== false || strpos($title, 'document') !== false) {
        return 'report';
    } elseif (strpos($title, 'issue') !== false || strpos($title, 'problem') !== false || strpos($title, 'fix') !== false) {
        return 'issue';
    }
    
    // Default to meeting if no other type matches
    return 'meeting';
} 