<?php
/**
 * API Endpoint: Update Overtime Status
 * 
 * This API updates the overtime status in the attendance table
 * and creates/updates a notification in the overtime_notifications table.
 * 
 * Required POST parameters:
 * - user_id: ID of the employee
 * - status: 'approved' or 'rejected'
 * - reason: Reason for approval/rejection (optional for approval, required for rejection)
 * - date: Date of the overtime
 * 
 * Response: JSON with success/error message
 */

// Include database connection
require_once '../config/db_connect.php';

// Ensure we're using the correct database
mysqli_query($conn, "USE crm");

// Set headers for JSON response
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';
$reason = isset($_POST['reason']) ? $_POST['reason'] : '';
$date = isset($_POST['date']) ? $_POST['date'] : '';

// Debug log
error_log("Overtime update request: User ID: $userId, Status: $status, Date: $date, Reason: $reason");

// Log all POST data
error_log("POST data: " . print_r($_POST, true));

// Validate required fields
if (!$userId || !$status || !$date) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate status
if (!in_array($status, ['approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// If status is rejected, reason is required
if ($status === 'rejected' && empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Reason is required for rejection']);
    exit;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current user (manager) ID from session
$managerId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

// Debug log for manager ID
error_log("Manager ID from session: $managerId");

// If no valid manager ID in session, return error
if (!$managerId) {
    echo json_encode(['success' => false, 'message' => 'Not authorized: No valid manager ID']);
    exit;
}

// Get manager username for better display
$managerUsername = isset($_SESSION['username']) ? $_SESSION['username'] : '';
if (empty($managerUsername) && $managerId > 0) {
    // Fetch username from database if not in session
    $managerQuery = "SELECT username FROM users WHERE id = ?";
    $managerStmt = mysqli_prepare($conn, $managerQuery);
    mysqli_stmt_bind_param($managerStmt, 'i', $managerId);
    mysqli_stmt_execute($managerStmt);
    $managerResult = mysqli_stmt_get_result($managerStmt);
    if ($managerRow = mysqli_fetch_assoc($managerResult)) {
        $managerUsername = $managerRow['username'];
    }
}

error_log("Manager Username: $managerUsername");

try {
    // Start transaction
    mysqli_begin_transaction($conn);
    
    // Format date for database query (convert from "Month Day, Year" to "YYYY-MM-DD")
    $formattedDate = date('Y-m-d', strtotime($date));
    
    // Debug log for date formatting
    error_log("Original date: $date, Formatted date: $formattedDate");
    
    // Try to extract month and year for fallback query
    $dateObj = new DateTime($formattedDate);
    $month = $dateObj->format('m');
    $year = $dateObj->format('Y');
    error_log("Extracted month: $month, year: $year");
    
    // 1. Update attendance table
    // First, try a direct SQL query for maximum compatibility
    $directUpdateQuery = "UPDATE attendance 
                         SET overtime_status = '$status', 
                             overtime_approved_by = $managerId, 
                             overtime_actioned_at = NOW() 
                         WHERE user_id = $userId 
                         AND overtime_status = 'submitted'
                         ORDER BY date DESC
                         LIMIT 1";
    
    error_log("Direct update query: $directUpdateQuery");
    $directUpdateResult = mysqli_query($conn, $directUpdateQuery);
    
    if ($directUpdateResult && mysqli_affected_rows($conn) > 0) {
        error_log("Direct update successful, affected rows: " . mysqli_affected_rows($conn));
        // If the direct update worked, we can skip the prepared statement
        $updateResult = true;
        $affectedRows = mysqli_affected_rows($conn);
    } else {
        // If direct update failed, try the prepared statement with exact date match
        error_log("Direct update failed, trying prepared statement");
        
        $updateQuery = "UPDATE attendance 
                       SET overtime_status = ?, 
                           overtime_approved_by = ?, 
                           overtime_actioned_at = NOW() 
                       WHERE user_id = ? AND date = ?";
        
        // Debug log for SQL query
        $debugQuery = str_replace('?', "'%s'", $updateQuery);
        $debugQuery = sprintf($debugQuery, $status, $managerId, $userId, $formattedDate);
        error_log("Update query: $debugQuery");
        
        $stmt = mysqli_prepare($conn, $updateQuery);
        mysqli_stmt_bind_param($stmt, 'siis', $status, $managerId, $userId, $formattedDate);
        $updateResult = mysqli_stmt_execute($stmt);
        
        // Log result
        error_log("Update result: " . ($updateResult ? "Success" : "Failed: " . mysqli_error($conn)));
        
        // Check if any rows were affected
        $affectedRows = mysqli_stmt_affected_rows($stmt);
        error_log("Affected rows: $affectedRows");
    }
    
    if (!$updateResult) {
        throw new Exception("Failed to update attendance record: " . mysqli_error($conn));
    }
    
    // Check if any rows were affected (only for prepared statement case)
    if (!isset($affectedRows) || $affectedRows === 0) {
        // If we're here and $affectedRows is already set, it means the direct update worked
        // Otherwise, we need to check if the record exists
        if (!isset($affectedRows)) {
            // Try a direct query to see if the record exists
            $checkQuery = "SELECT id FROM attendance WHERE user_id = '$userId' AND date = '$formattedDate'";
            error_log("Check query: $checkQuery");
            $checkResult = mysqli_query($conn, $checkQuery);
            $recordExists = mysqli_num_rows($checkResult) > 0;
            error_log("Record exists: " . ($recordExists ? "Yes" : "No"));
            
            if (!$recordExists) {
                // Try a fallback query using MONTH() and YEAR() functions
                error_log("Trying fallback query with month and year");
                $fallbackQuery = "UPDATE attendance 
                                 SET overtime_status = '$status', 
                                     overtime_approved_by = $managerId, 
                                     overtime_actioned_at = NOW() 
                                 WHERE user_id = $userId 
                                 AND MONTH(date) = $month 
                                 AND YEAR(date) = $year
                                 AND overtime_status = 'submitted'
                                 LIMIT 1";
                
                error_log("Fallback query: $fallbackQuery");
                $fallbackResult = mysqli_query($conn, $fallbackQuery);
                
                if (!$fallbackResult) {
                    throw new Exception("Failed to update with fallback query: " . mysqli_error($conn));
                }
                
                $fallbackAffectedRows = mysqli_affected_rows($conn);
                error_log("Fallback affected rows: $fallbackAffectedRows");
                
                if ($fallbackAffectedRows === 0) {
                    throw new Exception("No matching attendance record found with fallback query");
                }
            } else {
                throw new Exception("Record exists but update failed");
            }
        }
    }
    
    // Get the attendance ID for the notification
    $attendanceQuery = "SELECT id FROM attendance 
                       WHERE user_id = ? 
                       AND (date = ? OR (MONTH(date) = ? AND YEAR(date) = ? AND overtime_status = ?))
                       ORDER BY date DESC LIMIT 1";
    $attendanceStmt = mysqli_prepare($conn, $attendanceQuery);
    $submittedStatus = $status; // Changed from "submitted" to use the current status
    mysqli_stmt_bind_param($attendanceStmt, 'isiis', $userId, $formattedDate, $month, $year, $submittedStatus);
    mysqli_stmt_execute($attendanceStmt);
    $attendanceResult = mysqli_stmt_get_result($attendanceStmt);
    
    if (!$attendanceRow = mysqli_fetch_assoc($attendanceResult)) {
        // Try a direct query as fallback
        $directQuery = "SELECT id FROM attendance WHERE user_id = $userId AND overtime_status = '$status' ORDER BY date DESC LIMIT 1";
        error_log("Direct attendance query: $directQuery");
        $directResult = mysqli_query($conn, $directQuery);
        
        if (!$directRow = mysqli_fetch_assoc($directResult)) {
            throw new Exception("Could not find attendance record");
        }
        
        $overtimeId = $directRow['id'];
        error_log("Found attendance ID via direct query: $overtimeId");
    } else {
        $overtimeId = $attendanceRow['id'];
        error_log("Found attendance ID: $overtimeId");
    }
    
    // 2. Check if notification already exists and update it instead of creating a new one
    $checkNotificationQuery = "SELECT id FROM overtime_notifications WHERE overtime_id = ? LIMIT 1";
    $checkNotificationStmt = mysqli_prepare($conn, $checkNotificationQuery);
    mysqli_stmt_bind_param($checkNotificationStmt, 'i', $overtimeId);
    mysqli_stmt_execute($checkNotificationStmt);
    $checkNotificationResult = mysqli_stmt_get_result($checkNotificationStmt);
    
    $message = $status === 'approved' 
        ? "Your overtime has been approved" 
        : "Your overtime has been rejected";
    
    if ($existingNotification = mysqli_fetch_assoc($checkNotificationResult)) {
        // Update existing notification
        $notificationId = $existingNotification['id'];
        error_log("Found existing notification ID: $notificationId - updating instead of creating new");
        
        $updateNotificationQuery = "UPDATE overtime_notifications 
                                  SET manager_id = ?, 
                                      message = ?, 
                                      status = ?, 
                                      manager_response = ?,
                                      created_at = NOW() 
                                  WHERE id = ?";
        
        $updateNotificationStmt = mysqli_prepare($conn, $updateNotificationQuery);
        mysqli_stmt_bind_param(
            $updateNotificationStmt, 
            'isssi', 
            $managerId, 
            $message, 
            $status, 
            $reason,
            $notificationId
        );
        $notificationResult = mysqli_stmt_execute($updateNotificationStmt);
    } else {
        // Create new notification if none exists
        $notificationQuery = "INSERT INTO overtime_notifications 
                           (overtime_id, employee_id, manager_id, message, status, manager_response, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $notificationStmt = mysqli_prepare($conn, $notificationQuery);
        mysqli_stmt_bind_param(
            $notificationStmt, 
            'iiisss', 
            $overtimeId, 
            $userId, 
            $managerId, 
            $message, 
            $status, 
            $reason
        );
        $notificationResult = mysqli_stmt_execute($notificationStmt);
    }
    
    if (!$notificationResult) {
        throw new Exception("Failed to update notification: " . mysqli_error($conn));
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => "Overtime $status successfully",
        'data' => [
            'user_id' => $userId,
            'status' => $status,
            'date' => $date,
            'manager_id' => $managerId,
            'manager_username' => $managerUsername
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    
    // Return error response
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} finally {
    // Close connection
    mysqli_close($conn);
} 