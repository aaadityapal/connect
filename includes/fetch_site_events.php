<?php
/**
 * Fetch Site Events
 * 
 * This file fetches events from the site_events table
 * to be displayed in the calendar on the site_supervision.php page.
 * 
 * @return JSON Array of events with date, title, and other details
 */

// Include database connection
require_once '../config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in',
        'events' => []
    ]);
    exit;
}

$userId = $_SESSION['user_id'];

// Get month and year from request parameters (default to current month/year)
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = date('Y');
}

// Prepare start and end dates for the query
// Include previous and next month days that appear in the calendar view
$startDate = date('Y-m-d', strtotime($year . '-' . $month . '-01 -7 days'));
$endDate = date('Y-m-d', strtotime($year . '-' . $month . '-' . date('t', strtotime($year . '-' . $month . '-01')) . ' +7 days'));

try {
    // Fetch site events for calendar display
    $query = "SELECT 
                id,
                site_name,
                event_date,
                created_by,
                created_at,
                updated_at
              FROM 
                site_events 
              WHERE 
                event_date BETWEEN ? AND ? 
              ORDER BY 
                event_date ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$startDate, $endDate]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format events for calendar display
    $calendarEvents = [];
    foreach ($events as $event) {
        // Determine event type (for color-coding)
        $eventType = determineEventType($event);
        
        $calendarEvents[] = [
            'id' => $event['id'],
            'date' => $event['event_date'],
            'title' => $event['site_name'],
            'created_by' => $event['created_by'],
            'created_at' => $event['created_at'],
            'updated_at' => $event['updated_at'],
            'type' => $eventType
        ];
    }
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'month' => $month,
        'year' => $year,
        'events' => $calendarEvents
    ]);
    
} catch (PDOException $e) {
    // Log error and return empty events
    error_log('Error fetching site events: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching events: ' . $e->getMessage(),
        'events' => []
    ]);
}

/**
 * Determine the event type based on event content
 * This function assigns a category for color-coding in the calendar
 * 
 * @param array $event The event record from database
 * @return string Event type (success, info, warning, danger, primary)
 */
function determineEventType($event) {
    // If site_name contains certain keywords, assign specific colors
    if (isset($event['site_name'])) {
        $siteName = strtolower($event['site_name']);
        
        if (strpos($siteName, 'inspection') !== false || strpos($siteName, 'review') !== false) {
            return 'success'; // Green
        }
        
        if (strpos($siteName, 'meeting') !== false || strpos($siteName, 'discussion') !== false) {
            return 'info'; // Blue
        }
        
        if (strpos($siteName, 'delivery') !== false || strpos($siteName, 'material') !== false) {
            return 'warning'; // Yellow
        }
        
        if (strpos($siteName, 'deadline') !== false || strpos($siteName, 'urgent') !== false) {
            return 'danger'; // Red
        }
        
        if (strpos($siteName, 'client') !== false || strpos($siteName, 'visit') !== false) {
            return 'primary'; // Purple
        }
    }
    
    // Default event type based on the day of the month (for visual variety)
    $day = (int)date('j', strtotime($event['event_date']));
    $types = ['primary', 'success', 'info', 'warning', 'danger'];
    return $types[$day % count($types)];
}
?> 