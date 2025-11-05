<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
error_log('User ID from session: ' . ($user_id ? $user_id : 'null'));
if (!$user_id) {
    // For debugging, let's set a default user ID
    $user_id = 1; // Default user ID for testing
    error_log('Using default user ID for testing: ' . $user_id);
    // echo json_encode(['success' => false, 'error' => 'User not logged in']);
    // exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Debug: Log received data
error_log('Received overtime request data: ' . print_r($data, true));

// Debug: Check if data is null
if ($data === null) {
    error_log('JSON decode failed. Raw input: ' . file_get_contents('php://input'));
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data received']);
    exit;
}

// Validate required fields
$required_fields = ['attendance_id', 'date', 'shift_end_time', 'punch_out_time', 'overtime_hours', 'work_report', 'overtime_description', 'manager_id'];
error_log('Checking required fields. Data: ' . print_r($data, true));
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        error_log("Missing required field: $field. Received data: " . print_r($data, true));
        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
        exit;
    }
}

// Extract data
$attendance_id = (int)$data['attendance_id'];
$date = $data['date'];
$shift_end_time = $data['shift_end_time'];
$punch_out_time = $data['punch_out_time'];
$overtime_hours = (float)$data['overtime_hours'];
$work_report = $data['work_report'];
$overtime_description = $data['overtime_description'];
$manager_id = (int)$data['manager_id'];

// Debug: Log extracted values
error_log('Extracted values: attendance_id=' . $attendance_id . ', date=' . $date . ', shift_end_time=' . $shift_end_time . ', punch_out_time=' . $punch_out_time . ', overtime_hours=' . $overtime_hours . ', manager_id=' . $manager_id);

try {
    // Insert overtime request with status 'submitted'
    $query = "INSERT INTO overtime_requests (
                user_id, 
                attendance_id, 
                date, 
                shift_end_time, 
                punch_out_time, 
                overtime_hours, 
                work_report, 
                overtime_description, 
                manager_id, 
                status
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')";
    
    // Debug: Log query parameters
    error_log('Query parameters: ' . print_r([
        $user_id,
        $attendance_id,
        $date,
        $shift_end_time,
        $punch_out_time,
        $overtime_hours,
        $work_report,
        $overtime_description,
        $manager_id
    ], true));
    
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        $user_id,
        $attendance_id,
        $date,
        $shift_end_time,
        $punch_out_time,
        $overtime_hours,
        $work_report,
        $overtime_description,
        $manager_id
    ]);
    
    if ($result) {
        // Get the ID of the inserted overtime request
        $overtime_request_id = $pdo->lastInsertId();
        
        // Update the attendance table to set overtime_status to 'submitted'
        $update_attendance_query = "UPDATE attendance SET overtime_status = 'submitted' WHERE id = ?";
        $update_stmt = $pdo->prepare($update_attendance_query);
        $update_result = $update_stmt->execute([$attendance_id]);
        
        if ($update_result) {
            echo json_encode(['success' => true, 'message' => 'Overtime request submitted successfully and status updated in both tables']);
        } else {
            // Log the error
            error_log('Database error updating attendance: ' . print_r($update_stmt->errorInfo(), true));
            echo json_encode(['success' => false, 'error' => 'Overtime request submitted but failed to update attendance status']);
        }
    } else {
        // Log the error
        error_log('Database error: ' . print_r($stmt->errorInfo(), true));
        echo json_encode(['success' => false, 'error' => 'Failed to submit overtime request']);
    }
} catch (Exception $e) {
    error_log("Error submitting overtime request: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>