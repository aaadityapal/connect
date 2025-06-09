<?php
// Include database connection
require_once '../config/db_connect.php';

// Set headers for JSON response
header('Content-Type: application/json');

try {
    // Get today's date
    $today = date('Y-m-d');
    
    // Query to find users with role 'Site Supervisor' who haven't added events today
    // This query gets all active Site Supervisors who don't have an entry in sv_calendar_events for today
    $query = "
        SELECT u.id as user_id, u.username, u.updated_at
        FROM users u
        WHERE u.status = 'active'
        AND u.role = 'Site Supervisor'
        AND u.id NOT IN (
            SELECT DISTINCT created_by 
            FROM sv_calendar_events 
            WHERE DATE(event_date) = :today
        )
        ORDER BY u.username ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':today', $today, PDO::PARAM_STR);
    $stmt->execute();
    
    $users_without_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return the result as JSON
    echo json_encode([
        'success' => true,
        'users_without_events' => $users_without_events,
        'count' => count($users_without_events),
        'date' => $today
    ]);
    
} catch (PDOException $e) {
    // Return error message
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
?> 