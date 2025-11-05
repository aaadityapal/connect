<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['attendance_id']) || !isset($input['overtime_hours'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$attendance_id = (int)$input['attendance_id'];
$overtime_hours = (float)$input['overtime_hours'];

// Validate overtime hours (minimum 1.5 hours)
if ($overtime_hours < 1.5) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Overtime hours cannot be less than 1.5 hours']);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update overtime hours in the attendance table
    $attendance_query = "UPDATE attendance SET overtime_hours = ? WHERE id = ?";
    $attendance_stmt = $pdo->prepare($attendance_query);
    $attendance_result = $attendance_stmt->execute([$overtime_hours, $attendance_id]);
    
    // Check if any rows were affected
    if ($attendance_stmt->rowCount() === 0) {
        throw new Exception('No attendance record found with the provided ID');
    }
    
    // Check if there's a corresponding record in overtime_requests table
    $overtime_request_query = "SELECT id FROM overtime_requests WHERE attendance_id = ?";
    $overtime_request_stmt = $pdo->prepare($overtime_request_query);
    $overtime_request_stmt->execute([$attendance_id]);
    $overtime_request_record = $overtime_request_stmt->fetch();
    
    // If there's a corresponding overtime request, update its overtime_hours
    if ($overtime_request_record) {
        $overtime_update_query = "UPDATE overtime_requests SET overtime_hours = ?, updated_at = NOW() WHERE attendance_id = ?";
        $overtime_update_stmt = $pdo->prepare($overtime_update_query);
        $overtime_update_stmt->execute([$overtime_hours, $attendance_id]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Overtime hours updated successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollback();
    
    // Log error for debugging
    error_log("Error updating overtime hours: " . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update overtime hours. Please try again.'
    ]);
}
?>