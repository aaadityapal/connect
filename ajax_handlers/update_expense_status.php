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

// Get current user ID
$currentUserId = $_SESSION['user_id'];

// Get parameters from request
$expenseId = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

// Get verification checkbox values
$checkDestination = isset($_POST['check_destination']) && $_POST['check_destination'] === '1';
$checkPolicy = isset($_POST['check_policy']) && $_POST['check_policy'] === '1';
$checkMeter = isset($_POST['check_meter']) && $_POST['check_meter'] === '1';

// Validate input
if ($expenseId <= 0 || empty($action) || !in_array($action, ['approve', 'reject'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input parameters'
    ]);
    exit;
}

// Validate all checkboxes are checked
if (!$checkDestination || !$checkPolicy || !$checkMeter) {
    echo json_encode([
        'success' => false,
        'message' => 'All verification checkboxes must be checked'
    ]);
    exit;
}

// For rejection, ensure reason has at least 10 words
if ($action === 'reject' && str_word_count($reason) < 10) {
    echo json_encode([
        'success' => false,
        'message' => 'Rejection reason must be at least 10 words'
    ]);
    exit;
}

// Get current user role to determine which status field to update
$userRole = '';
try {
    $roleQuery = "SELECT role FROM users WHERE id = :user_id";
    $roleStmt = $pdo->prepare($roleQuery);
    $roleStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
    $roleStmt->execute();
    $userRole = $roleStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error getting user role: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred while checking user role'
    ]);
    exit;
}

// Get current user name for logging
try {
    $userQuery = "SELECT username FROM users WHERE id = :user_id";
    $userStmt = $pdo->prepare($userQuery);
    $userStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
    $userStmt->execute();
    $username = $userStmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error getting username: " . $e->getMessage());
    $username = "User #" . $currentUserId;
}

// Determine which status field to update based on user role
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

// Debug log
$logFile = '../logs/expense_status_updates.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - User $username ($userRole) is {$action}ing expense ID $expenseId. Updating $statusField. Reason: $reason\n", FILE_APPEND);

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // First, get the current expense status to check if it's already processed
    $checkQuery = "SELECT status FROM travel_expenses WHERE id = :expense_id";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->bindParam(':expense_id', $expenseId, PDO::PARAM_INT);
    $checkStmt->execute();
    $currentStatus = $checkStmt->fetchColumn();
    
    // Check if expense is already approved or rejected
    if ($currentStatus && strtolower($currentStatus) !== 'pending') {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => "This expense has already been {$currentStatus} and cannot be modified"
        ]);
        exit;
    }
    
    // Update the specific status field based on user role
    $updateQuery = "UPDATE travel_expenses 
                   SET $statusField = :new_status,
                       $reasonField = :reason,
                       updated_by = :updated_by,
                       updated_at = NOW(),
                       destination_verified = :destination_verified,
                       policy_verified = :policy_verified,
                       meter_verified = :meter_verified";
    
    // If all required approvals are in place, update the overall status
    if ($action === 'approve') {
        // For approval, check if all required approvals are in place
        $updateQuery .= ", status = CASE 
                            WHEN (
                                (manager_status = 'approved' OR manager_status IS NULL) AND 
                                (accountant_status = 'approved' OR accountant_status IS NULL) AND 
                                (hr_status = 'approved' OR hr_status IS NULL)
                            ) THEN 'approved'
                            ELSE status
                          END";
    } else {
        // For rejection, immediately set the overall status to rejected
        $updateQuery .= ", status = 'rejected'";
    }
    
    $updateQuery .= " WHERE id = :expense_id";
    
    $updateStmt = $pdo->prepare($updateQuery);
    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
    $updateStmt->bindParam(':new_status', $newStatus, PDO::PARAM_STR);
    $updateStmt->bindParam(':reason', $reason, PDO::PARAM_STR);
    $updateStmt->bindParam(':updated_by', $currentUserId, PDO::PARAM_INT);
    $updateStmt->bindParam(':expense_id', $expenseId, PDO::PARAM_INT);
    
    // Bind verification checkbox values
    $updateStmt->bindValue(':destination_verified', $checkDestination ? 1 : 0, PDO::PARAM_INT);
    $updateStmt->bindValue(':policy_verified', $checkPolicy ? 1 : 0, PDO::PARAM_INT);
    $updateStmt->bindValue(':meter_verified', $checkMeter ? 1 : 0, PDO::PARAM_INT);
    
    $updateStmt->execute();
    $rowsAffected = $updateStmt->rowCount();
    
    // Log the result
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Updated $rowsAffected records\n", FILE_APPEND);
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Expense status updated successfully',
        'rows_affected' => $rowsAffected,
        'action' => $action,
        'status' => $newStatus
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