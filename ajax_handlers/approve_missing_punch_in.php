<?php
/**
 * Approve Missing Punch In Handler
 * This script handles the approval of missing punch-in requests and updates the attendance table
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/db_connect.php'; // Adjust path as needed

header('Content-Type: application/json');

// Log incoming requests for debugging
error_log("Approve missing punch in request received");
error_log("POST data: " . print_r($_POST, true));

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

try {
    // Get POST data - try multiple methods to ensure we get the data
    $missing_punch_id = trim($_POST['missing_punch_id'] ?? $_REQUEST['missing_punch_id'] ?? '');
    $approval_status = trim($_POST['status'] ?? $_REQUEST['status'] ?? ''); // 'approved' or 'rejected'
    $admin_notes = trim($_POST['admin_notes'] ?? $_REQUEST['admin_notes'] ?? '');
    
    error_log("Extracted values - ID: '$missing_punch_id', Status: '$approval_status'");
    
    // Validate inputs
    if (empty($missing_punch_id) || empty($approval_status)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Missing punch ID and status are required',
            'debug' => [
                'missing_punch_id_received' => $missing_punch_id,
                'status_received' => $approval_status,
                'admin_notes_received' => $admin_notes,
                'post_data' => $_POST,
                'request_data' => $_REQUEST
            ]
        ]);
        exit;
    }
    
    // Validate status
    if (!in_array($approval_status, ['approved', 'rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status: ' . $approval_status]);
        exit;
    }
    
    // Use the existing database connection from db_connect.php
    global $conn; // This should be available from db_connect.php
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update the missing_punch_in record status
        $updateMissingPunchQuery = "UPDATE missing_punch_in SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?";
        $updateMissingPunchStmt = $conn->prepare($updateMissingPunchQuery);
        $updateMissingPunchStmt->bind_param("ssi", $approval_status, $admin_notes, $missing_punch_id);
        $updateMissingPunchStmt->execute();
        
        if ($updateMissingPunchStmt->affected_rows === 0) {
            throw new Exception("No missing punch record found with ID: " . $missing_punch_id);
        }
        
        // If approved, update the attendance table
        if ($approval_status === 'approved') {
            // Get the missing punch details
            $selectQuery = "SELECT user_id, date, punch_in_time, reason FROM missing_punch_in WHERE id = ?";
            $selectStmt = $conn->prepare($selectQuery);
            $selectStmt->bind_param("i", $missing_punch_id);
            $selectStmt->execute();
            $result = $selectStmt->get_result();
            $missingPunch = $result->fetch_assoc();
            
            if (!$missingPunch) {
                throw new Exception("Missing punch record not found");
            }
            
            // Check if an attendance record already exists for this date
            $checkQuery = "SELECT id FROM attendance WHERE user_id = ? AND date = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("is", $missingPunch['user_id'], $missingPunch['date']);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $existingRecord = $result->fetch_assoc();
            
            if ($existingRecord) {
                // Update existing record
                $updateQuery = "UPDATE attendance SET punch_in = ?, missing_punch_reason = ?, missing_punch_in_id = ?, missing_punch_approval_status = 'approved' WHERE id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("ssii", $missingPunch['punch_in_time'], $missingPunch['reason'], $missing_punch_id, $existingRecord['id']);
                $updateStmt->execute();
            } else {
                // Create new attendance record
                $insertQuery = "INSERT INTO attendance (user_id, date, punch_in, missing_punch_reason, missing_punch_in_id, missing_punch_approval_status) VALUES (?, ?, ?, ?, ?, 'approved')";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bind_param("isssi", $missingPunch['user_id'], $missingPunch['date'], $missingPunch['punch_in_time'], $missingPunch['reason'], $missing_punch_id);
                $insertStmt->execute();
            }
        } else {
            // If rejected, update the attendance table to reflect rejection
            // Get the missing punch details
            $selectQuery = "SELECT user_id, date FROM missing_punch_in WHERE id = ?";
            $selectStmt = $conn->prepare($selectQuery);
            $selectStmt->bind_param("i", $missing_punch_id);
            $selectStmt->execute();
            $result = $selectStmt->get_result();
            $missingPunch = $result->fetch_assoc();
            
            if ($missingPunch) {
                // Update existing attendance record to reflect rejection
                $updateQuery = "UPDATE attendance SET missing_punch_in_id = ?, missing_punch_approval_status = 'rejected' WHERE user_id = ? AND date = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("iis", $missing_punch_id, $missingPunch['user_id'], $missingPunch['date']);
                $updateStmt->execute();
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Missing punch-in ' . $approval_status . ' successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error in approve_missing_punch_in.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error processing request: ' . $e->getMessage()]);
}
?>