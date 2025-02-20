<?php
session_start();
require_once 'config.php';

// Add this at the top for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the POST data
error_log("Leave Application POST data: " . print_r($_POST, true));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get form data
        $userId = $_SESSION['user_id'];
        $leaveType = isset($_POST['leave_type']) ? trim($_POST['leave_type']) : ''; // Add trim()
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $reason = $_POST['reason'];
        
        // Validate leave type
        if (empty($leaveType)) {
            throw new Exception('Leave type is required');
        }
        
        // Prepare the SQL statement
        $stmt = $pdo->prepare("
            INSERT INTO leaves (
                user_id, 
                leave_type,  /* Make sure this column exists */
                start_date, 
                end_date, 
                reason, 
                status, 
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, 'Pending', NOW(), NOW()
            )
        ");
        
        // Execute the statement
        $result = $stmt->execute([
            $userId,
            $leaveType,
            $startDate,
            $endDate,
            $reason
        ]);
        
        if ($result) {
            // Log successful insertion
            error_log("Leave successfully inserted with type: " . $leaveType);
            header('Location: apply_leave.php?success=1');
        } else {
            throw new Exception('Failed to insert leave application');
        }
        
    } catch (Exception $e) {
        error_log("Leave Application Error: " . $e->getMessage());
        header('Location: apply_leave.php?error=1&message=' . urlencode($e->getMessage()));
    }
    exit();
}
