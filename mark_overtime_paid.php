<?php
header('Content-Type: application/json');
session_start();

// Include database connection
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'User not authenticated'
    ]);
    exit;
}

// Check if user has the correct role
$allowed_roles = ['Senior Manager (Studio)', 'Senior Manager (Site)', 'HR'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

try {
    // Get the attendance ID from the request
    $input = json_decode(file_get_contents('php://input'), true);
    $attendance_id = isset($input['attendance_id']) ? (int)$input['attendance_id'] : 0;
    
    if ($attendance_id <= 0) {
        throw new Exception('Invalid attendance ID');
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Check if the attendance record exists
    $date_check_query = "SELECT id, date, overtime_status FROM attendance WHERE id = ?";
    $date_stmt = $pdo->prepare($date_check_query);
    $date_stmt->execute([$attendance_id]);
    $date_row = $date_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$date_row) {
        throw new Exception('Attendance record not found');
    }
    
    // Check if overtime is already approved (should be approved before marking as paid)
    if ($date_row['overtime_status'] !== 'approved') {
        throw new Exception('Overtime must be approved before marking as paid');
    }
    
    // Get current user ID (manager/HR ID)
    $manager_id = $_SESSION['user_id'];
    
    // Update the attendance record status to paid
    $update_attendance_query = "UPDATE attendance 
                               SET overtime_status = 'paid', 
                                   overtime_actioned_at = NOW(), 
                                   overtime_approved_by = ? 
                               WHERE id = ?";
    
    $update_attendance_stmt = $pdo->prepare($update_attendance_query);
    $result = $update_attendance_stmt->execute([$manager_id, $attendance_id]);
    
    if (!$result) {
        throw new Exception('Failed to update attendance record status');
    }
    
    // Check if there's a corresponding record in overtime_requests table
    $overtime_request_query = "SELECT id FROM overtime_requests WHERE attendance_id = ?";
    $overtime_request_stmt = $pdo->prepare($overtime_request_query);
    $overtime_request_stmt->execute([$attendance_id]);
    $overtime_request_record = $overtime_request_stmt->fetch();
    
    // If there's a corresponding overtime request, update only its payment_status to paid
    if ($overtime_request_record) {
        $overtime_update_query = "UPDATE overtime_requests 
                                 SET payment_status = 'paid',
                                     manager_id = ?, 
                                     actioned_at = NOW(), 
                                     updated_at = NOW() 
                                 WHERE attendance_id = ?";
        
        $overtime_update_stmt = $pdo->prepare($overtime_update_query);
        $overtime_update_result = $overtime_update_stmt->execute([$manager_id, $attendance_id]);
        
        if (!$overtime_update_result) {
            throw new Exception('Failed to update overtime request payment status');
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Overtime has been successfully marked as paid'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error marking overtime as paid: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

