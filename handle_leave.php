<?php
session_start();
require_once 'config/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone to IST
date_default_timezone_set('Asia/Kolkata');

// Log the raw input
$raw_input = file_get_contents('php://input');
error_log("Raw input: " . $raw_input);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Get JSON data from POST request
$data = json_decode($raw_input, true);

// Log decoded data
error_log("Decoded data: " . print_r($data, true));

if (!$data) {
    error_log("JSON decode error: " . json_last_error_msg());
    echo json_encode(['success' => false, 'message' => 'Invalid data received: ' . json_last_error_msg()]);
    exit();
}

// Check if this is a leave action request (approve/reject)
if (isset($data['action']) && isset($data['leave_id'])) {
    try {
        // Validate the action
        if (!in_array($data['action'], ['approve', 'reject'])) {
            throw new Exception('Invalid action specified');
        }

        $status = ($data['action'] === 'approve') ? 'Approved' : 'Rejected';
        
        // Update the leave request status
        $update_query = "UPDATE leave_requests SET status = ?, updated_at = NOW(), updated_by = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("sii", $status, $_SESSION['user_id'], $data['leave_id']);

        if ($update_stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => "Leave request {$data['action']}d successfully"
            ]);
        } else {
            throw new Exception("Failed to update leave status: " . $update_stmt->error);
        }
        exit();
    } catch (Exception $e) {
        error_log("Exception caught during leave action: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error processing leave action: ' . $e->getMessage()
        ]);
        exit();
    }
}

// Validate required fields
$required_fields = ['leaveType', 'startDate', 'endDate', 'reason'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        error_log("Missing required field: $field");
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

try {
    // Log the query preparation
    error_log("Preparing to insert leave request");

    // Prepare the base query without duration if it's not in the table
    $query = "INSERT INTO leave_requests (
        user_id, 
        leave_type, 
        start_date, 
        end_date, 
        reason,
        status,
        created_at
    ) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())";

    // Prepare statement
    $stmt = $conn->prepare($query);
    
    // Bind parameters without duration
    $stmt->bind_param(
        "issss",
        $_SESSION['user_id'],
        $data['leaveType'],
        $data['startDate'],
        $data['endDate'],
        $data['reason']
    );

    // Execute the query
    if ($stmt->execute()) {
        $leave_id = $stmt->insert_id;
        error_log("Leave request inserted successfully. ID: $leave_id");
        
        // If it's a short leave or compensate, add additional details
        if ($data['leaveType'] === 'short' && isset($data['startTime']) && isset($data['endTime'])) {
            $time_query = "INSERT INTO leave_time_details (leave_id, start_time, end_time) VALUES (?, ?, ?)";
            $time_stmt = $conn->prepare($time_query);
            $time_stmt->bind_param("iss", $leave_id, $data['startTime'], $data['endTime']);
            $time_stmt->execute();
        }
        
        if ($data['leaveType'] === 'compensate' && isset($data['compensateDate']) && isset($data['compensateHours'])) {
            $comp_query = "INSERT INTO leave_compensate_details (leave_id, compensate_date, hours) VALUES (?, ?, ?)";
            $comp_stmt = $conn->prepare($comp_query);
            $comp_stmt->bind_param("isi", $leave_id, $data['compensateDate'], $data['compensateHours']);
            $comp_stmt->execute();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Leave request submitted successfully',
            'leave_id' => $leave_id
        ]);
    } else {
        error_log("Database error: " . $stmt->error);
        throw new Exception("Failed to submit leave request: " . $stmt->error);
    }

} catch (Exception $e) {
    error_log("Exception caught: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error processing leave request: ' . $e->getMessage()
    ]);
}
?> 