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
if (!isset($input['attendance_id']) || !isset($input['reason'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$attendance_id = (int)$input['attendance_id'];
$reason = trim($input['reason']);

// Validate reason is not empty
if (empty($reason)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Reason cannot be empty']);
    exit;
}

// Validate reason has at least 10 words
$word_count = str_word_count($reason);
if ($word_count < 10) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Reason must be at least 10 words']);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // First, get the attendance record to check if there's a corresponding overtime request
    $attendance_query = "SELECT id, user_id, date FROM attendance WHERE id = ?";
    $attendance_stmt = $pdo->prepare($attendance_query);
    $attendance_stmt->execute([$attendance_id]);
    $attendance_record = $attendance_stmt->fetch();
    
    if (!$attendance_record) {
        throw new Exception('No attendance record found with the provided ID');
    }
    
    // Update attendance record status to rejected
    $attendance_update_query = "UPDATE attendance SET overtime_status = 'rejected', overtime_actioned_at = NOW(), overtime_approved_by = ? WHERE id = ?";
    $attendance_update_stmt = $pdo->prepare($attendance_update_query);
    $attendance_update_stmt->execute([$_SESSION['user_id'], $attendance_id]);
    
    // Check if there's a corresponding record in overtime_requests table
    $overtime_request_query = "SELECT id FROM overtime_requests WHERE attendance_id = ?";
    $overtime_request_stmt = $pdo->prepare($overtime_request_query);
    $overtime_request_stmt->execute([$attendance_id]);
    $overtime_request_record = $overtime_request_stmt->fetch();
    
    // If there's a corresponding overtime request, update its status and manager comments
    if ($overtime_request_record) {
        $overtime_update_query = "UPDATE overtime_requests SET status = 'rejected', manager_comments = ?, actioned_at = NOW(), manager_id = ?, updated_at = NOW() WHERE attendance_id = ?";
        $overtime_update_stmt = $pdo->prepare($overtime_update_query);
        $overtime_update_stmt->execute([$reason, $_SESSION['user_id'], $attendance_id]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Overtime request has been successfully rejected'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollback();
    
    // Log error for debugging
    error_log("Error rejecting overtime request: " . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Failed to reject overtime request. Please try again.'
    ]);
}
?>