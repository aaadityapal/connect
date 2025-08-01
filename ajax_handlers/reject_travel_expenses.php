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

// Get current user ID (the person rejecting the expenses)
$currentUserId = $_SESSION['user_id'];

// Get parameters from request
$userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$travelDate = isset($_POST['travel_date']) ? $_POST['travel_date'] : '';
$confirmedDistance = isset($_POST['confirmed_distance']) ? floatval($_POST['confirmed_distance']) : 0;
$totalDistance = isset($_POST['total_distance']) ? floatval($_POST['total_distance']) : 0;
$rejectionReason = isset($_POST['reason']) ? $_POST['reason'] : 'Distance mismatch: Confirmed distance is less than claimed distance';

// Get current user role for determining which status to update
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

// If no specific reason provided, create one with the distance values
if ($confirmedDistance > 0 && $totalDistance > 0 && $rejectionReason === 'Distance mismatch: Confirmed distance is less than claimed distance') {
    $rejectionReason = "Distance mismatch: Confirmed distance ({$confirmedDistance} km) is less than claimed distance ({$totalDistance} km)";
}

// Validate input
if ($userId <= 0 || empty($travelDate)) {
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
    $rejectedByName = $userStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error getting user name: " . $e->getMessage());
    $rejectedByName = "User #" . $currentUserId;
}

// Format current date and time
$rejectedAt = date('Y-m-d H:i:s');

// Debug log
$logFile = '../logs/update_expense_debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Rejecting expenses for user $userId on $travelDate by $rejectedByName. Reason: $rejectionReason\n", FILE_APPEND);

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Determine which status fields to update based on user role
    $statusField = '';
    $reasonField = '';
    
    switch ($userRole) {
        case 'Manager':
        case 'Senior Manager':
        case 'Project Manager':
        case 'Site Manager':
            $statusField = 'manager_status';
            $reasonField = 'manager_reason';
            break;
            
        case 'Purchase Manager':
        case 'Accountant':
        case 'Finance Manager':
            $statusField = 'accountant_status';
            $reasonField = 'accountant_reason';
            break;
            
        case 'HR':
        case 'HR Manager':
        case 'HR Executive':
            $statusField = 'hr_status';
            $reasonField = 'hr_reason';
            break;
            
        default:
            // Default to accountant for Purchase Manager
            $statusField = 'accountant_status';
            $reasonField = 'accountant_reason';
    }
    
    // Log which status is being updated
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - User role: $userRole - Updating $statusField\n", FILE_APPEND);
    
    // Build the dynamic query based on the user's role
    $updateQuery = "UPDATE travel_expenses 
                   SET status = 'rejected',
                       $statusField = 'rejected',
                       $reasonField = :rejection_reason,
                       confirmed_distance = :confirmed_distance,
                       distance_confirmed_by = :confirmed_by,
                       distance_confirmed_at = :confirmed_at,
                       updated_by = :updated_by,
                       updated_at = :updated_at
                   WHERE user_id = :user_id 
                   AND travel_date = :travel_date";
    
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->bindParam(':rejection_reason', $rejectionReason, PDO::PARAM_STR);
    $updateStmt->bindParam(':confirmed_distance', $confirmedDistance, PDO::PARAM_STR);
    $updateStmt->bindParam(':confirmed_by', $rejectedByName, PDO::PARAM_STR);
    $updateStmt->bindParam(':confirmed_at', $rejectedAt, PDO::PARAM_STR);
    $updateStmt->bindParam(':updated_by', $currentUserId, PDO::PARAM_INT);
    $updateStmt->bindParam(':updated_at', $rejectedAt, PDO::PARAM_STR);
    $updateStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $updateStmt->bindParam(':travel_date', $travelDate, PDO::PARAM_STR);
    
    $updateStmt->execute();
    $rowsAffected = $updateStmt->rowCount();
    
    // Log the result
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Rejected $rowsAffected expense records\n", FILE_APPEND);
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'All expenses rejected successfully',
        'rows_affected' => $rowsAffected,
        'rejected_by' => $rejectedByName,
        'rejected_at' => date('d M Y H:i', strtotime($rejectedAt))
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