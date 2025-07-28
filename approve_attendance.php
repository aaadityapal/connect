<?php
// Enable error reporting for debugging
ini_set('display_errors', 0); // Don't display errors to the browser
ini_set('log_errors', 1);     // Log errors
error_log("Attendance approval script started");

// Ensure no output before JSON response
ob_start();

try {
    require_once 'config/db_connect.php';
    require_once 'includes/auth_check.php';
    require_once 'includes/role_check.php';
    
    // Initialize response array
    $response = [
        'success' => false,
        'message' => ''
    ];
    
    // Function to safely return JSON response
    function returnJsonResponse($response) {
        // Clear any previous output
        if (ob_get_length()) ob_clean();
        
        // Set headers
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        
        // Return JSON encoded response
        echo json_encode($response);
        exit;
    }
    
    // Check if user has required role
    checkUserRole(['admin', 'manager', 'senior manager (site)', 'senior manager (studio)', 'hr']);
    
    // Log request data for debugging
    error_log("POST data: " . print_r($_POST, true));
    
    // Check if required parameters are provided
    if (!isset($_POST['attendance_id']) || !isset($_POST['action'])) {
        $response['message'] = 'Missing required parameters';
        returnJsonResponse($response);
    }
    
    $attendance_id = intval($_POST['attendance_id']);
    $action = $_POST['action'];
    $comments = isset($_POST['comments']) ? $_POST['comments'] : '';
    
    // Log parameters
    error_log("Processing attendance ID: $attendance_id, Action: $action");
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Update attendance status based on action
    if ($action === 'approve') {
        $sql = "UPDATE attendance SET 
                approval_status = 'approved', 
                manager_id = ?, 
                approval_timestamp = NOW(), 
                manager_comments = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param('isi', $_SESSION['user_id'], $comments, $attendance_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $affected_rows = $stmt->affected_rows;
        error_log("Approve query affected rows: $affected_rows");
    } elseif ($action === 'reject') {
        $sql = "UPDATE attendance SET 
                approval_status = 'rejected', 
                manager_id = ?, 
                approval_timestamp = NOW(), 
                manager_comments = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param('isi', $_SESSION['user_id'], $comments, $attendance_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $affected_rows = $stmt->affected_rows;
        error_log("Reject query affected rows: $affected_rows");
    } else {
        throw new Exception('Invalid action');
    }
    
    // Check if update was successful
    if ($affected_rows > 0) {
        // Commit transaction
        $conn->commit();
        error_log("Transaction committed");
        
        try {
            // Create notification for employee
            $get_user_sql = "SELECT user_id, date FROM attendance WHERE id = ?";
            $user_stmt = $conn->prepare($get_user_sql);
            
            if (!$user_stmt) {
                throw new Exception("User query prepare failed: " . $conn->error);
            }
            
            $user_stmt->bind_param('i', $attendance_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            
            if ($user_data = $user_result->fetch_assoc()) {
                $user_id = $user_data['user_id'];
                $date = $user_data['date'];
                
                $notification_title = "Attendance " . ucfirst($action === 'approve' ? 'approved' : 'rejected');
                $notification_content = "Your attendance for " . date('d M Y', strtotime($date)) . 
                                       " has been " . ($action === 'approve' ? 'approved' : 'rejected');
                
                if (!empty($comments)) {
                    $notification_content .= ". Comments: " . $comments;
                }
                
                // Check if notifications table exists before trying to insert
                $check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
                if ($check_table->num_rows > 0) {
                    $notify_sql = "INSERT INTO notifications (
                        user_id, title, content, link, type, created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())";
                    
                    $notification_link = "view_attendance.php?id=" . $attendance_id;
                    $notification_type = "attendance_" . ($action === 'approve' ? 'approved' : 'rejected');
                    
                    $notify_stmt = $conn->prepare($notify_sql);
                    
                    if (!$notify_stmt) {
                        throw new Exception("Notification prepare failed: " . $conn->error);
                    }
                    
                    $notify_stmt->bind_param(
                        'issss',
                        $user_id,
                        $notification_title,
                        $notification_content,
                        $notification_link,
                        $notification_type
                    );
                    
                    $notify_stmt->execute();
                    error_log("Notification created");
                } else {
                    error_log("Notifications table doesn't exist, skipping notification creation");
                }
            }
        } catch (Exception $notifyError) {
            // Just log notification errors but don't fail the whole process
            error_log("Notification error: " . $notifyError->getMessage());
        }
        
        $response['success'] = true;
        $response['message'] = 'Attendance ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully';
    } else {
        // Rollback transaction
        $conn->rollback();
        error_log("Transaction rolled back - no rows affected");
        
        $response['message'] = 'No attendance record found with the provided ID or it has already been processed';
    }
    
    // Return JSON response
    returnJsonResponse($response);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in approve_attendance.php: " . $e->getMessage());
    
    // Rollback transaction if connection exists
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
            error_log("Transaction rolled back due to error");
        } catch (Exception $rollbackError) {
            error_log("Rollback failed: " . $rollbackError->getMessage());
        }
    }
    
    // Prepare error response
    $response = [
        'success' => false,
        'message' => 'Error processing request: ' . $e->getMessage()
    ];
    
    // Return error response
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}