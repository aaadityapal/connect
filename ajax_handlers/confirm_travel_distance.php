<?php
// Include database connection
require_once '../config/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit;
}

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get current user ID (the person confirming the distance)
$currentUserId = $_SESSION['user_id'];

// Get parameters from request
$userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$travelDate = isset($_POST['travel_date']) ? $_POST['travel_date'] : '';
$confirmedDistance = isset($_POST['confirmed_distance']) ? floatval($_POST['confirmed_distance']) : 0;

// Get current user role for determining which status to update if needed
$userRole = '';
try {
    $roleQuery = "SELECT role FROM users WHERE id = :user_id";
    $roleStmt = $pdo->prepare($roleQuery);
    $roleStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
    $roleStmt->execute();
    $userRole = $roleStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error getting user role: " . $e->getMessage());
    // Default to Purchase Manager/Accountant if role cannot be determined
    $userRole = 'Purchase Manager';
}

// Validate input
if ($userId <= 0 || empty($travelDate) || $confirmedDistance < 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input parameters'
    ]);
    exit;
}

// Get current user name for logging
try {
    $userQuery = "SELECT username FROM users WHERE id = :user_id";
    $userStmt = $pdo->prepare($userQuery);
    $userStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
    $userStmt->execute();
    $confirmedByName = $userStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error getting user name: " . $e->getMessage());
    $confirmedByName = "User #" . $currentUserId;
}

// Format current date and time
$confirmedAt = date('Y-m-d H:i:s');

// Debug log
$logFile = '../logs/update_expense_debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Confirming distance for user $userId on $travelDate: $confirmedDistance km by $confirmedByName\n", FILE_APPEND);

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update all travel expenses for this user on this date
    $updateQuery = "UPDATE travel_expenses 
                   SET confirmed_distance = :confirmed_distance,
                       distance_confirmed_by = :confirmed_by,
                       distance_confirmed_at = :confirmed_at,
                       updated_by = :updated_by,
                       updated_at = :updated_at
                   WHERE user_id = :user_id 
                   AND travel_date = :travel_date";
    
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->bindParam(':confirmed_distance', $confirmedDistance, PDO::PARAM_STR);
    $updateStmt->bindParam(':confirmed_by', $confirmedByName, PDO::PARAM_STR);
    $updateStmt->bindParam(':confirmed_at', $confirmedAt, PDO::PARAM_STR);
    $updateStmt->bindParam(':updated_by', $currentUserId, PDO::PARAM_INT);
    $updateStmt->bindParam(':updated_at', $confirmedAt, PDO::PARAM_STR);
    $updateStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $updateStmt->bindParam(':travel_date', $travelDate, PDO::PARAM_STR);
    
    $updateStmt->execute();
    $rowsAffected = $updateStmt->rowCount();
    
    // Log the result
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Updated $rowsAffected records\n", FILE_APPEND);
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Distance confirmed successfully',
        'rows_affected' => $rowsAffected,
        'confirmed_by' => $confirmedByName,
        'confirmed_at' => date('d M Y H:i', strtotime($confirmedAt))
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    // Log error
    $errorMessage = "Database error: " . $e->getMessage();
    error_log($errorMessage);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $errorMessage\n", FILE_APPEND);
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} 