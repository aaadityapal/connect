<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set header to return JSON
header('Content-Type: application/json');

// Include database connection
require_once '../config/db_connect.php';

try {
    // Get current date in Y-m-d format
    $today = date('Y-m-d');
    
    // Simple query to test database connection with correct table name
    $query = "
        SELECT COUNT(*) as count
        FROM leave_request
        LIMIT 1
    ";
    
    $result = $conn->query($query);
    $count = 0;
    
    if ($result) {
        $row = $result->fetch_assoc();
        $count = $row['count'];
    }
    
    // Create a mock response with minimal data
    $supervisors_on_leave = [
        [
            'id' => 1,
            'user_id' => 100,
            'name' => 'John Doe',
            'leave_type' => 'annual',
            'start_date' => $today,
            'end_date' => $today,
            'duration' => 'Full day',
            'profile_image' => 'assets/default-avatar.png'
        ],
        [
            'id' => 2,
            'user_id' => 101,
            'name' => 'Jane Smith',
            'leave_type' => 'sick',
            'start_date' => $today,
            'end_date' => date('Y-m-d', strtotime('+2 days')),
            'duration' => '3 days',
            'profile_image' => 'assets/default-avatar.png'
        ]
    ];
    
    // Create response data
    $response_data = [
        'success' => true,
        'supervisors_on_leave' => $supervisors_on_leave,
        'total_supervisors' => 10,
        'count_on_leave' => count($supervisors_on_leave),
        'db_test' => [
            'table_exists' => ($result !== false),
            'record_count' => $count
        ]
    ];
    
    // Return the data
    echo json_encode($response_data);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}