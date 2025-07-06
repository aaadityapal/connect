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
    
    // First, get the attendance ID for the specific record we want to update
    $attendanceIdQuery = "SELECT id FROM attendance 
                         WHERE user_id = ? 
                         AND DATE(date) = ? 
                         LIMIT 1";
    
    $attendanceIdStmt = mysqli_prepare($conn, $attendanceIdQuery);
    mysqli_stmt_bind_param($attendanceIdStmt, 'is', $userId, $formattedDate);
    mysqli_stmt_execute($attendanceIdStmt);
    $attendanceIdResult = mysqli_stmt_get_result($attendanceIdStmt);
    
    if ($attendanceRow = mysqli_fetch_assoc($attendanceIdResult)) {
        $attendanceId = $attendanceRow['id'];
        error_log("Found attendance ID: $attendanceId for user $userId on date $formattedDate");
        
        // Now update this specific record with the new status
        $updateQuery = "UPDATE attendance 
                       SET overtime_status = ?, 
                           overtime_approved_by = ?, 
                           overtime_actioned_at = NOW() 
                       WHERE id = ?";
        
        $updateStmt = mysqli_prepare($conn, $updateQuery);
        mysqli_stmt_bind_param($updateStmt, 'sii', $status, $managerId, $attendanceId);
        $updateResult = mysqli_stmt_execute($updateStmt);
        
        error_log("Update result for attendance ID $attendanceId: " . ($updateResult ? "Success" : "Failed: " . mysqli_error($conn)));
        
        if (!$updateResult) {
            throw new Exception("Failed to update attendance record: " . mysqli_error($conn));
        }
        
        // Check if any rows were affected
        $affectedRows = mysqli_stmt_affected_rows($updateStmt);
        error_log("Affected rows: $affectedRows");
        
        if ($affectedRows === 0) {
            throw new Exception("No rows were updated for attendance ID $attendanceId");
        }
        
        // Now handle the notification
        $checkNotificationQuery = "SELECT id, message FROM overtime_notifications WHERE overtime_id = ? LIMIT 1";
        $checkNotificationStmt = mysqli_prepare($conn, $checkNotificationQuery);
        mysqli_stmt_bind_param($checkNotificationStmt, 'i', $attendanceId);
        mysqli_stmt_execute($checkNotificationStmt);
        $checkNotificationResult = mysqli_stmt_get_result($checkNotificationStmt);
        
        // Default message if we need to create a new notification
        $defaultMessage = $status === 'approved' 
            ? "Your overtime has been approved" 
            : "Your overtime has been rejected";
        
        if ($existingNotification = mysqli_fetch_assoc($checkNotificationResult)) {
            // Update existing notification
            $notificationId = $existingNotification['id'];
            error_log("Found existing notification ID: $notificationId - updating instead of creating new");
            
            // Keep the existing message instead of overwriting it
            $existingMessage = $existingNotification['message'];
            error_log("Preserving existing message: $existingMessage");
            
            $updateNotificationQuery = "UPDATE overtime_notifications 
                                      SET manager_id = ?, 
                                          status = ?, 
                                          manager_response = ?,
                                          created_at = NOW() 
                                      WHERE id = ?";
            
            $updateNotificationStmt = mysqli_prepare($conn, $updateNotificationQuery);
            mysqli_stmt_bind_param(
                $updateNotificationStmt, 
                'issi', 
                $managerId, 
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
                $attendanceId, 
                $userId, 
                $managerId, 
                $defaultMessage, // Use default message only for new notifications
                $status, 
                $reason
            );
            $notificationResult = mysqli_stmt_execute($notificationStmt);
        }
        
        if (!$notificationResult) {
            throw new Exception("Failed to update notification: " . mysqli_error($conn));
        }
    } else {
        // Fallback: If we couldn't find the exact date, try using month and year
        error_log("Couldn't find exact date match, trying month/year fallback");
        
        $fallbackQuery = "SELECT id FROM attendance 
                         WHERE user_id = ? 
                         AND MONTH(date) = ? 
                         AND YEAR(date) = ? 
                         ORDER BY date DESC 
                         LIMIT 1";
        
        $fallbackStmt = mysqli_prepare($conn, $fallbackQuery);
        mysqli_stmt_bind_param($fallbackStmt, 'iii', $userId, $month, $year);
        mysqli_stmt_execute($fallbackStmt);
        $fallbackResult = mysqli_stmt_get_result($fallbackStmt);
        
        if ($fallbackRow = mysqli_fetch_assoc($fallbackResult)) {
            $attendanceId = $fallbackRow['id'];
            error_log("Found attendance ID via fallback: $attendanceId");
            
            // Update this specific record
            $updateQuery = "UPDATE attendance 
                           SET overtime_status = ?, 
                               overtime_approved_by = ?, 
                               overtime_actioned_at = NOW() 
                           WHERE id = ?";
            
            $updateStmt = mysqli_prepare($conn, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, 'sii', $status, $managerId, $attendanceId);
            $updateResult = mysqli_stmt_execute($updateStmt);
            
            error_log("Fallback update result: " . ($updateResult ? "Success" : "Failed: " . mysqli_error($conn)));
            
            if (!$updateResult) {
                throw new Exception("Failed to update attendance record: " . mysqli_error($conn));
            }
            
            // Now handle the notification
            $checkNotificationQuery = "SELECT id, message FROM overtime_notifications WHERE overtime_id = ? LIMIT 1";
            $checkNotificationStmt = mysqli_prepare($conn, $checkNotificationQuery);
            mysqli_stmt_bind_param($checkNotificationStmt, 'i', $attendanceId);
            mysqli_stmt_execute($checkNotificationStmt);
            $checkNotificationResult = mysqli_stmt_get_result($checkNotificationStmt);
            
            // Default message if we need to create a new notification
            $defaultMessage = $status === 'approved' 
                ? "Your overtime has been approved" 
                : "Your overtime has been rejected";
            
            if ($existingNotification = mysqli_fetch_assoc($checkNotificationResult)) {
                // Update existing notification
                $notificationId = $existingNotification['id'];
                error_log("Found existing notification ID: $notificationId - updating instead of creating new");
                
                // Keep the existing message instead of overwriting it
                $existingMessage = $existingNotification['message'];
                error_log("Preserving existing message: $existingMessage");
                
                $updateNotificationQuery = "UPDATE overtime_notifications 
                                          SET manager_id = ?, 
                                              status = ?, 
                                              manager_response = ?,
                                              created_at = NOW() 
                                          WHERE id = ?";
                
                $updateNotificationStmt = mysqli_prepare($conn, $updateNotificationQuery);
                mysqli_stmt_bind_param(
                    $updateNotificationStmt, 
                    'issi', 
                    $managerId, 
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
                    $attendanceId, 
                    $userId, 
                    $managerId, 
                    $defaultMessage, // Use default message only for new notifications
                    $status, 
                    $reason
                );
                $notificationResult = mysqli_stmt_execute($notificationStmt);
            }
            
            if (!$notificationResult) {
                throw new Exception("Failed to update notification: " . mysqli_error($conn));
            }
        } else {
            throw new Exception("Could not find any attendance record for user $userId on or near date $formattedDate");
        }
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