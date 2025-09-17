<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user has HR role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'HR') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Include database connection
require_once '../config/db_connect.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id']) || !isset($input['filter_month']) || !isset($input['use_for_one_hour'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$user_id = $input['user_id'];
$filter_month = $input['filter_month'];
$use_for_one_hour = $input['use_for_one_hour'] ? 1 : 0;

try {
    // Check if record exists for this user and month
    $check_query = "SELECT id FROM short_leave_preferences 
                    WHERE user_id = ? AND filter_month = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$user_id, $filter_month]);
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing record
        $update_query = "UPDATE short_leave_preferences 
                        SET use_for_one_hour_late = ?, updated_at = NOW() 
                        WHERE user_id = ? AND filter_month = ?";
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([$use_for_one_hour, $user_id, $filter_month]);
    } else {
        // Insert new record
        $insert_query = "INSERT INTO short_leave_preferences 
                        (user_id, filter_month, use_for_one_hour_late, created_at, updated_at) 
                        VALUES (?, ?, ?, NOW(), NOW())";
        $insert_stmt = $pdo->prepare($insert_query);
        $insert_stmt->execute([$user_id, $filter_month, $use_for_one_hour]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Short leave preference updated']);
    
} catch (PDOException $e) {
    error_log("Error updating short leave preference: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>