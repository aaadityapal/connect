<?php
// Include database connection
require_once '../../config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone to India Standard Time
date_default_timezone_set('Asia/Kolkata');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit;
}

// Get user ID from query parameters or use logged-in user's ID
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : $_SESSION['user_id'];

// Current date in IST
$today = date('Y-m-d');

try {
    // Check if user has an active punch-in for today
    $stmt = $pdo->prepare("SELECT id, punch_in, punch_out FROM attendance 
                           WHERE user_id = ? AND date = ? 
                           ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId, $today]);
    
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record && $record['punch_in'] && !$record['punch_out']) {
        // User is currently punched in
        $punchIn = new DateTime($record['punch_in']);
        $now = new DateTime();
        $interval = $punchIn->diff($now);
        
        // Calculate time worked
        $hoursWorked = $interval->h + ($interval->days * 24);
        $minutesWorked = $interval->i;
        $secondsWorked = ($hoursWorked * 3600) + ($minutesWorked * 60) + $interval->s;
        
        echo json_encode([
            'success' => true,
            'is_punched_in' => true,
            'attendance_id' => $record['id'],
            'punch_in_time' => $record['punch_in'],
            'hours_worked' => $hoursWorked,
            'minutes_worked' => $minutesWorked,
            'seconds_worked' => $secondsWorked
        ]);
    } else {
        // User is not currently punched in
        echo json_encode([
            'success' => true,
            'is_punched_in' => false
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    
    // Log the error
    error_log('Attendance Status Check Error: ' . $e->getMessage());
} 