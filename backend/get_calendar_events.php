<?php
/**
 * Get Calendar Events API
 * Fetches events from sv_calendar_events table for a specific month and year
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
if (!isset($_GET['year']) || !isset($_GET['month'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required parameters (year, month)'
    ]);
    exit;
}

$year = intval($_GET['year']);
$month = intval($_GET['month']);

// Validate input
if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid date parameters'
    ]);
    exit;
}

// Format month to ensure it has leading zero if needed
$monthFormatted = str_pad($month, 2, '0', STR_PAD_LEFT);

try {
    // Prepare dates for SQL query
    $startDate = "{$year}-{$monthFormatted}-01";
    $endDate = date('Y-m-t', strtotime($startDate)); // Last day of month
    
    // Prepare and execute the query to fetch events
    $query = "SELECT e.event_id, e.title, e.event_date, e.created_by, u.username as created_by_name, e.created_at 
              FROM sv_calendar_events e
              LEFT JOIN users u ON e.created_by = u.id
              WHERE e.event_date BETWEEN ? AND ?
              ORDER BY e.event_date ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Determine event type based on any criteria (e.g., title keywords)
            $eventType = determineEventType($row['title']);
            
            // Format event for output
            $events[] = [
                'id' => $row['event_id'],
                'title' => $row['title'],
                'date' => $row['event_date'],
                'type' => $eventType,
                'created_by' => [
                    'id' => $row['created_by'],
                    'name' => $row['created_by_name'] ?? 'Unknown'
                ],
                'created_at' => date('Y-m-d H:i:s', strtotime($row['created_at']))
            ];
        }
    }
    
    // Return success response with events
    echo json_encode([
        'status' => 'success',
        'message' => 'Events fetched successfully',
        'events' => $events
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch events: ' . $e->getMessage()
    ]);
}

/**
 * Determine the event type based on title keywords
 * This is a simple implementation - enhance as needed
 */
function determineEventType($title) {
    $title = strtolower($title);
    
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