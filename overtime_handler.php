<?php
// Include database connection
require_once 'config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit;
}

// Get the current user ID
$employee_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_overtime_status' && isset($_SESSION['is_manager']) && $_SESSION['is_manager']) {
        // This action is for managers to approve/reject overtime
        if (!isset($_POST['overtime_id']) || !isset($_POST['status'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            exit;
        }
        
        // Validate status
        $status = $_POST['status'];
        if (!in_array($status, ['approved', 'rejected'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid status'
            ]);
            exit;
        }
        
        $overtime_id = intval($_POST['overtime_id']);
        $manager_response = isset($_POST['response']) ? trim($_POST['response']) : '';
        
        try {
            // Update the attendance record
            $updateAttendance = $pdo->prepare("
                UPDATE attendance 
                SET overtime_status = :status,
                    overtime_approved_by = :manager_id,
                    overtime_actioned_at = NOW()
                WHERE id = :overtime_id
            ");
            
            $updateAttendance->execute([
                ':status' => $status,
                ':manager_id' => $employee_id,  // Current user (manager) is the one approving
                ':overtime_id' => $overtime_id
            ]);
            
            // Update notification status
            $updateNotification = $pdo->prepare("
                UPDATE overtime_notifications 
                SET status = :status,
                    manager_response = :response,
                    actioned_at = NOW(),
                    read_at = NOW()
                WHERE overtime_id = :overtime_id AND manager_id = :manager_id
            ");
            
            $updateNotification->execute([
                ':status' => $status,
                ':response' => $manager_response,
                ':overtime_id' => $overtime_id,
                ':manager_id' => $employee_id
            ]);
            
            // Return success response
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Overtime request has been ' . $status
            ]);
            exit;
            
        } catch (PDOException $e) {
            // Log the error
            error_log("Overtime Status Update Error: " . $e->getMessage());
            
            // Return error response
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'An error occurred while updating overtime status'
            ]);
            exit;
        }
    } 
    else if ($_POST['action'] === 'submit_overtime_notification') {
        // Validate required fields
        if (!isset($_POST['overtime_id']) || !isset($_POST['manager_id'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields'
            ]);
            exit;
        }
        
        // Sanitize inputs
        $overtime_id = intval($_POST['overtime_id']);
        $manager_id = intval($_POST['manager_id']);
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        
        try {
            // First, update the attendance record to mark overtime as submitted
            $updateAttendance = $pdo->prepare("
                UPDATE attendance 
                SET overtime_status = 'submitted',
                    overtime_approved_by = NULL,
                    overtime_actioned_at = NULL
                WHERE id = :overtime_id AND user_id = :employee_id
            ");
            
            $updateAttendance->execute([
                ':overtime_id' => $overtime_id,
                ':employee_id' => $employee_id
            ]);
            
            // Then insert notification into overtime_notifications table
            $insertNotification = $pdo->prepare("
                INSERT INTO overtime_notifications (
                    overtime_id, 
                    employee_id, 
                    manager_id, 
                    message, 
                    status, 
                    created_at
                ) VALUES (
                    :overtime_id,
                    :employee_id,
                    :manager_id,
                    :message,
                    'submitted',
                    NOW()
                )
            ");
            
            $insertNotification->execute([
                ':overtime_id' => $overtime_id,
                ':employee_id' => $employee_id,
                ':manager_id' => $manager_id,
                ':message' => $message
            ]);
            
            // Return success response
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Overtime notification submitted successfully'
            ]);
            exit;
            
        } catch (PDOException $e) {
            // Log the error
            error_log("Overtime Notification Error: " . $e->getMessage());
            
            // Return error response
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'An error occurred while saving your request'
            ]);
            exit;
        }
    } else {
        // Invalid action
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        exit;
    }
} else {
    // Invalid request
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
    exit;
} 