<?php
// Prevent any output before JSON response
ob_start();

session_start();
require_once 'config/db_connect.php';

// Set error handling to suppress HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

header('Content-Type: application/json');

try {
    // Clear any previous output
    ob_clean();

    // Validate required fields
    if (empty($_POST['title']) || empty($_POST['description']) || empty($_POST['event_date'])) {
        throw new Exception('Title, description and event date are required');
    }

    // Prepare data
    $data = [
        'title' => $_POST['title'],
        'description' => $_POST['description'],
        'event_date' => $_POST['event_date'],
        'start_date' => $_POST['event_date'],
        'end_date' => $_POST['event_date'],
        'start_time' => !empty($_POST['start_time']) ? $_POST['start_time'] : null,
        'end_time' => !empty($_POST['end_time']) ? $_POST['end_time'] : null,
        'location' => !empty($_POST['location']) ? $_POST['location'] : null,
        'event_type' => !empty($_POST['event_type']) ? $_POST['event_type'] : 'other',
        'created_by' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1,
        'status' => 'active'
    ];

    $sql = "INSERT INTO events (
        title, 
        description, 
        event_date,
        start_date,
        end_date,
        start_time,
        end_time,
        location,
        event_type,
        created_by,
        status
    ) VALUES (
        :title,
        :description,
        :event_date,
        :start_date,
        :end_date,
        :start_time,
        :end_time,
        :location,
        :event_type,
        :created_by,
        :status
    )";

    $stmt = $pdo->prepare($sql);
    
    if (!$stmt->execute($data)) {
        throw new Exception("Database error: " . implode(" ", $stmt->errorInfo()));
    }

    echo json_encode([
        'success' => true,
        'message' => 'Event added successfully'
    ]);

} catch (Exception $e) {
    // Log error
    error_log("Error in add_event.php: " . $e->getMessage());
    
    // Clear any output
    ob_clean();
    
    // Send error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// End output buffer
ob_end_flush(); 