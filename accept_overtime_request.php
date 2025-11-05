<?php
header('Content-Type: application/json');
session_start();

// Include database connection
require_once 'config/db_connect.php';

try {
    // Get the attendance ID and reason from the request
    $input = json_decode(file_get_contents('php://input'), true);
    $attendance_id = isset($input['attendance_id']) ? (int)$input['attendance_id'] : 0;
    $reason = isset($input['reason']) ? trim($input['reason']) : '';
    
    if ($attendance_id <= 0) {
        throw new Exception('Invalid attendance ID');
    }
    
    // Check if the attendance date is from November 2025 onwards
    $date_check_query = "SELECT date FROM attendance WHERE id = ?";
    $date_stmt = $pdo->prepare($date_check_query);
    $date_stmt->execute([$attendance_id]);
    $date_row = $date_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$date_row) {
        throw new Exception('Attendance record not found');
    }
    
    $attendance_date = new DateTime($date_row['date']);
    $nov2025 = new DateTime('2025-11-01');
    
    // Only accept overtime requests for records from November 2025 onwards
    if ($attendance_date < $nov2025) {
        throw new Exception('Overtime requests can only be accepted for records from November 2025 onwards');
    }
    
    // Get current user ID (manager ID)
    $manager_id = $_SESSION['user_id'] ?? 0;
    
    if ($manager_id <= 0) {
        throw new Exception('Manager not authenticated');
    }
    
    // Update the overtime request status to approved
    $update_query = "UPDATE overtime_requests 
                     SET status = 'approved', 
                         manager_id = ?, 
                         manager_comments = ?, 
                         actioned_at = NOW(), 
                         updated_at = NOW() 
                     WHERE attendance_id = ?";
    
    $update_stmt = $pdo->prepare($update_query);
    $result = $update_stmt->execute([$manager_id, $reason, $attendance_id]);
    
    if (!$result) {
        throw new Exception('Failed to update overtime request status');
    }
    
    // Also update the attendance record status
    $update_attendance_query = "UPDATE attendance 
                                SET overtime_status = 'approved' 
                                WHERE id = ?";
    
    $update_attendance_stmt = $pdo->prepare($update_attendance_query);
    $update_attendance_stmt->execute([$attendance_id]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Overtime request has been successfully accepted'
    ]);
    
} catch (Exception $e) {
    error_log("Error accepting overtime request: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>