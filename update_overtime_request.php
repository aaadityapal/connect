<?php
session_start();

// Include database connection
require_once 'config/db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Get the JSON data from the request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
if (!$data || !isset($data['attendance_id']) || !isset($data['work_report']) || !isset($data['overtime_description'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$attendance_id = intval($data['attendance_id']);
$work_report = trim($data['work_report']);
$overtime_description = trim($data['overtime_description']);

// Validate that overtime description has at least 15 words
$word_count = str_word_count($overtime_description);
if ($word_count < 15) {
    echo json_encode(['success' => false, 'error' => 'Overtime description must be at least 15 words']);
    exit;
}

try {
    // Check if an overtime request already exists for this attendance record
    $check_query = "SELECT id FROM overtime_requests WHERE attendance_id = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$attendance_id]);
    $existing_request = $check_stmt->fetch();
    
    if ($existing_request) {
        // Update existing overtime request
        $update_query = "UPDATE overtime_requests SET work_report = ?, overtime_description = ?, updated_at = NOW() WHERE attendance_id = ?";
        $update_stmt = $pdo->prepare($update_query);
        $result = $update_stmt->execute([$work_report, $overtime_description, $attendance_id]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Overtime request updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update overtime request']);
        }
    } else {
        // Create new overtime request
        $insert_query = "INSERT INTO overtime_requests (attendance_id, work_report, overtime_description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())";
        $insert_stmt = $pdo->prepare($insert_query);
        $result = $insert_stmt->execute([$attendance_id, $work_report, $overtime_description]);
        
        if ($result) {
            // Also update the attendance record to mark it as submitted
            $update_attendance_query = "UPDATE attendance SET overtime_status = 'submitted' WHERE id = ?";
            $update_attendance_stmt = $pdo->prepare($update_attendance_query);
            $update_attendance_stmt->execute([$attendance_id]);
            
            echo json_encode(['success' => true, 'message' => 'Overtime request created successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create overtime request']);
        }
    }
} catch (Exception $e) {
    error_log("Error updating overtime request: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred while processing your request']);
}
?>