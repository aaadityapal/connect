<?php
// Include database connection
require_once '../config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Get the current user ID
$employeeId = $_SESSION['user_id'];

// Function to sanitize input data
function sanitize($data) {
    return htmlspecialchars(trim($data));
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check the action type
    if (isset($_POST['action']) && $_POST['action'] === 'submit_overtime') {
        // Get and validate data
        if (!isset($_POST['overtime_id']) || !isset($_POST['manager_id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required data']);
            exit;
        }

        $overtimeId = intval($_POST['overtime_id']);
        $managerId = intval($_POST['manager_id']);
        $message = isset($_POST['message']) ? sanitize($_POST['message']) : '';

        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // 1. Update the attendance record to set overtime_status to 'pending'
            $updateAttendance = $pdo->prepare("
                UPDATE attendance
                SET overtime_status = 'pending'
                WHERE id = :id AND user_id = :user_id
            ");
            
            $updateAttendance->execute([
                ':id' => $overtimeId,
                ':user_id' => $employeeId
            ]);
            
            if ($updateAttendance->rowCount() === 0) {
                // No rows were updated, rollback
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to update attendance record']);
                exit;
            }
            
            // 2. Create a notification record in overtime_notification table
            $insertNotification = $pdo->prepare("
                INSERT INTO overtime_notification (
                    overtime_id, employee_id, manager_id, message, status, created_at
                ) VALUES (
                    :overtime_id, :employee_id, :manager_id, :message, 'pending', NOW()
                )
            ");
            
            $insertNotification->execute([
                ':overtime_id' => $overtimeId,
                ':employee_id' => $employeeId,
                ':manager_id' => $managerId,
                ':message' => $message
            ]);
            
            // Commit the transaction
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Overtime request submitted successfully']);
            
        } catch (PDOException $e) {
            // Roll back the transaction if something failed
            $pdo->rollBack();
            error_log("Overtime Request Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        }
    } 
    // Other actions can be added here (for approving/rejecting overtime, etc.)
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 