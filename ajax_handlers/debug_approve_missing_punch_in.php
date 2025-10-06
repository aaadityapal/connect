<?php
/**
 * Debug Approve Missing Punch In Handler
 * This script helps debug issues with missing punch-in request approval
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/db_connect.php'; // Adjust path as needed

header('Content-Type: application/json');

// Log all incoming data for debugging
error_log("=== DEBUG MISSING PUNCH IN APPROVAL ===");
error_log("POST data: " . print_r($_POST, true));
error_log("GET data: " . print_r($_GET, true));
error_log("REQUEST data: " . print_r($_REQUEST, true));
error_log("Session data: " . print_r($_SESSION, true));
error_log("Content type: " . ($_SERVER['CONTENT_TYPE'] ?? 'Not set'));
error_log("Request method: " . ($_SERVER['REQUEST_METHOD'] ?? 'Not set'));

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'User not logged in', 'debug' => 'User not logged in']);
    exit;
}

try {
    // Get POST data
    $missing_punch_id = $_POST['missing_punch_id'] ?? $_REQUEST['missing_punch_id'] ?? '';
    $approval_status = $_POST['status'] ?? $_REQUEST['status'] ?? ''; // 'approved' or 'rejected'
    $admin_notes = $_POST['admin_notes'] ?? $_REQUEST['admin_notes'] ?? '';
    
    error_log("Extracted values - ID: '$missing_punch_id', Status: '$approval_status', Notes: '$admin_notes'");
    
    // Validate inputs
    if (empty($missing_punch_id) || empty($approval_status)) {
        error_log("Validation failed - Missing punch ID or status");
        echo json_encode([
            'success' => false, 
            'message' => 'Missing punch ID and status are required',
            'debug' => [
                'missing_punch_id' => $missing_punch_id,
                'status' => $approval_status,
                'admin_notes' => $admin_notes,
                'post_data' => $_POST,
                'request_data' => $_REQUEST
            ]
        ]);
        exit;
    }
    
    // Validate status
    if (!in_array($approval_status, ['approved', 'rejected'])) {
        error_log("Invalid status: $approval_status");
        echo json_encode(['success' => false, 'message' => 'Invalid status', 'debug' => "Invalid status: $approval_status"]);
        exit;
    }
    
    // If we get here, the data is valid
    error_log("Data validation passed - proceeding with approval");
    echo json_encode([
        'success' => true, 
        'message' => 'Debug: Data received successfully',
        'debug' => [
            'missing_punch_id' => $missing_punch_id,
            'status' => $approval_status,
            'admin_notes' => $admin_notes
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Exception in debug handler: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error processing request: ' . $e->getMessage()]);
}
?>