<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Return JSON with error message
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Include database connection
require_once 'config/db_connect.php';

// Get parameters
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Validate inputs
if ($user_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

// Define the date range for the month
$month_start = $month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

try {
    // Fetch user shift information
    $shift_query = "SELECT s.shift_name, s.start_time, s.end_time
                    FROM user_shifts us
                    JOIN shifts s ON us.shift_id = s.id
                    WHERE us.user_id = ?
                    AND (us.effective_to IS NULL OR us.effective_to >= CURDATE())
                    ORDER BY us.effective_from DESC
                    LIMIT 1";
    $shift_stmt = $pdo->prepare($shift_query);
    $shift_stmt->execute([$user_id]);
    $shift_info = $shift_stmt->fetch(PDO::FETCH_ASSOC);
    
    $shift_start = $shift_info['start_time'] ?? '09:00:00'; // Default to 9 AM if no shift assigned
    
    // Add 15 minutes grace period to shift start time
    $grace_end_time = date('H:i:s', strtotime($shift_start . ' +15 minutes'));
    
    // Query to find late punch-ins
    $query = "SELECT a.date, a.punch_in, TIME(a.punch_in) as punch_in_time, 
                    ? as shift_start_time, ? as grace_end_time,
                    TIMEDIFF(TIME(a.punch_in), ?) as late_by
              FROM attendance a
              WHERE a.user_id = ?
              AND a.date BETWEEN ? AND ?
              AND a.status = 'present'
              AND TIME(a.punch_in) > ?
              ORDER BY a.date";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        $shift_start,
        $grace_end_time,
        $grace_end_time,
        $user_id, 
        $month_start,
        $month_end,
        $grace_end_time
    ]);
    
    $late_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format times for display
    foreach ($late_records as &$record) {
        // Format the punch in time for display
        if (!empty($record['punch_in_time'])) {
            $punch_in_time = new DateTime($record['punch_in_time']);
            $record['punch_in_time'] = $punch_in_time->format('h:i A');
        }
        
        // Format the shift start time for display
        if (!empty($record['shift_start_time'])) {
            $shift_start_time = new DateTime($record['shift_start_time']);
            $record['shift_start_time'] = $shift_start_time->format('h:i A');
        }
        
        // Format the grace end time for display
        if (!empty($record['grace_end_time'])) {
            $grace_end_time = new DateTime($record['grace_end_time']);
            $record['grace_end_time'] = $grace_end_time->format('h:i A');
        }
    }
    unset($record); // Break the reference
    
    header('Content-Type: application/json');
    echo json_encode($late_records);
    
} catch (PDOException $e) {
    // Log the error
    error_log('Database error in get_late_punch_details.php: ' . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error occurred']);
}
?> 