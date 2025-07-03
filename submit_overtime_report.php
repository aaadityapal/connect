<?php
// Start session for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Get form data
$overtimeId = isset($_POST['overtime_id']) ? intval($_POST['overtime_id']) : 0;
$managerId = isset($_POST['manager_id']) ? intval($_POST['manager_id']) : 0;
$workReport = isset($_POST['work_report']) ? trim($_POST['work_report']) : '';
$confirmWork = isset($_POST['confirm_work']) ? true : false;
$overtimeDate = isset($_POST['date']) ? trim($_POST['date']) : date('Y-m-d');
$overtimeHours = isset($_POST['overtime_hours']) ? trim($_POST['overtime_hours']) : 'N/A';

// Validate required fields
if ($overtimeId <= 0 || $managerId <= 0 || empty($workReport) || !$confirmWork) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'All fields are required'
    ]);
    exit();
}

// Get current user ID
$userId = $_SESSION['user_id'];

// Start transaction
$conn->begin_transaction();

try {
    // 1. Update the overtime_status in the attendance table to "submitted"
    $updateQuery = "UPDATE attendance SET 
                    overtime_status = 'submitted',
                    work_report = ?,
                    modified_by = ?,
                    modified_at = NOW()
                    WHERE id = ? AND user_id = ?";
    
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("siis", $workReport, $userId, $overtimeId, $userId);
    
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update attendance record: " . $conn->error);
    }
    
    // Check if any row was affected
    if ($updateStmt->affected_rows === 0) {
        throw new Exception("No attendance record found or you don't have permission to update this record");
    }
    
    // 2. Insert a notification record in the overtime_notifications table
    // Use the work report content as the message
    $message = $workReport;
    
    $notificationQuery = "INSERT INTO overtime_notifications 
                         (overtime_id, employee_id, manager_id, message, status, created_at)
                         VALUES (?, ?, ?, ?, 'pending', NOW())";
    
    $notificationStmt = $conn->prepare($notificationQuery);
    $notificationStmt->bind_param("iiis", $overtimeId, $userId, $managerId, $message);
    
    if (!$notificationStmt->execute()) {
        // Check if the error is due to the table not existing
        if ($conn->errno === 1146) { // 1146 is the error code for "Table doesn't exist"
            // Create the table
            $createNotificationTableQuery = "CREATE TABLE overtime_notifications (
                                           id INT AUTO_INCREMENT PRIMARY KEY,
                                           overtime_id INT NOT NULL COMMENT 'Reference to attendance record ID',
                                           employee_id INT NOT NULL COMMENT 'User who sent the notification',
                                           manager_id INT NOT NULL COMMENT 'Manager who received the notification',
                                           message TEXT COMMENT 'Optional message from employee',
                                           status ENUM('pending', 'read', 'actioned') DEFAULT 'pending',
                                           manager_response TEXT COMMENT 'Optional response from manager',
                                           created_at DATETIME NOT NULL,
                                           read_at DATETIME NULL,
                                           actioned_at DATETIME NULL,
                                           INDEX (overtime_id),
                                           INDEX (employee_id),
                                           INDEX (manager_id),
                                           INDEX (status)
                                       )";
            
            if (!$conn->query($createNotificationTableQuery)) {
                throw new Exception("Failed to create overtime_notifications table: " . $conn->error);
            }
            
            // Try to insert again
            if (!$notificationStmt->execute()) {
                throw new Exception("Failed to save overtime notification: " . $conn->error);
            }
        } else {
            throw new Exception("Failed to save overtime notification: " . $conn->error);
        }
    }
    
    // 3. Commit the transaction if everything is successful
    $conn->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Overtime notification sent successfully to manager'
    ]);
    
} catch (Exception $e) {
    // Rollback the transaction if any error occurred
    $conn->rollback();
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close the database connection
$conn->close();
?> 