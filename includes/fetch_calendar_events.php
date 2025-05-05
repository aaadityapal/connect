<?php
/**
 * Fetch Calendar Events
 * 
 * This file fetches site updates from the database and formats them as events
 * to be displayed in the calendar on the site_supervision.php page.
 * 
 * @return JSON Array of events with date, title, type, and other details
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
    // Fetch site updates for calendar display
    $query = "SELECT 
                id,
                site_name,
                update_date,
                update_details,
                total_wages,
                total_misc_expenses,
                grand_total,
                created_at
              FROM 
                site_updates 
              WHERE 
                update_date BETWEEN ? AND ? 
              ORDER BY 
                update_date ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$startDate, $endDate]);
    $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format updates as calendar events
    $events = [];
    foreach ($updates as $update) {
        // Determine event type based on content (example logic)
        $eventType = determineEventType($update);
        
        $events[] = [
            'id' => $update['id'],
            'date' => $update['update_date'],
            'title' => $update['site_name'],
            'details' => substr($update['update_details'], 0, 100) . (strlen($update['update_details']) > 100 ? '...' : ''),
            'type' => $eventType,
            'total' => $update['grand_total'] ?? 0
        ];
    }
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'month' => $month,
        'year' => $year,
        'events' => $events
    ]);
    
} catch (PDOException $e) {
    // Log error and return empty events
    error_log('Error fetching calendar events: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching events: ' . $e->getMessage(),
        'events' => []
    ]);
}

/**
 * Determine the event type based on update content
 * This function assigns a category for color-coding in the calendar
 * 
 * @param array $update The update record from database
 * @return string Event type (success, info, warning, danger, primary)
 */
function determineEventType($update) {
    // Example logic - you can customize this based on your needs
    if (isset($update['update_details'])) {
        $details = strtolower($update['update_details']);
        
        // Check for keywords in the update details
        if (strpos($details, 'inspection') !== false || strpos($details, 'review') !== false) {
            return 'success'; // Green
        }
        
        if (strpos($details, 'meeting') !== false || strpos($details, 'discuss') !== false) {
            return 'info'; // Blue
        }
        
        if (strpos($details, 'delivery') !== false || strpos($details, 'material') !== false) {
            return 'warning'; // Yellow
        }
        
        if (strpos($details, 'deadline') !== false || strpos($details, 'urgent') !== false) {
            return 'danger'; // Red
        }
        
        if (strpos($details, 'client') !== false || strpos($details, 'visit') !== false) {
            return 'primary'; // Purple
        }
    }
    
    // Default event type based on the day of the month (for visual variety)
    $day = (int)date('j', strtotime($update['update_date']));
    $types = ['primary', 'success', 'info', 'warning', 'danger'];
    return $types[$day % count($types)];
}
?> 