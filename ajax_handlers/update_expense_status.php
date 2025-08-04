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
    case 'Senior Manager (Site)':
    case 'Project Manager':
    case 'Senior Manager (Studio)':
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
        
        // Add cascade fields directly to the update query based on the current role
        // Use direct string values instead of parameter binding for cascade reasons
        $cascadeMsg = "Auto-rejected: " . ucfirst(str_replace('_', ' ', $statusField)) . " rejected - " . substr($reason, 0, 50);
        $cascadeMsgEscaped = $pdo->quote($cascadeMsg); // Safely escape the string
        $rejectionFlag = strtoupper(str_replace('_status', '', $statusField)) . '_REJECTED';
        $rejectionFlagEscaped = $pdo->quote($rejectionFlag);
        
        if ($statusField === 'manager_status') {
            $updateQuery .= ", accountant_status = 'rejected', accountant_reason = $cascadeMsgEscaped";
            $updateQuery .= ", hr_status = 'rejected', hr_reason = $cascadeMsgEscaped";
        } elseif ($statusField === 'accountant_status') {
            $updateQuery .= ", manager_status = 'rejected', manager_reason = $cascadeMsgEscaped";
            $updateQuery .= ", hr_status = 'rejected', hr_reason = $cascadeMsgEscaped";
        } elseif ($statusField === 'hr_status') {
            $updateQuery .= ", manager_status = 'rejected', manager_reason = $cascadeMsgEscaped";
            $updateQuery .= ", accountant_status = 'rejected', accountant_reason = $cascadeMsgEscaped";
        }
        
        // Add rejection_cascade field to track who initiated the rejection
        $updateQuery .= ", rejection_cascade = $rejectionFlagEscaped";
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
    
    // No need to bind cascade parameters anymore as we're using direct string values with quote()
    
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
    
    // Log detailed error information
    $errorMessage = "Database error: " . $e->getMessage();
    $errorCode = $e->getCode();
    $errorTrace = $e->getTraceAsString();
    $errorQuery = isset($updateQuery) ? $updateQuery : 'Unknown query';
    
    // Create detailed error log
    $detailedError = date('Y-m-d H:i:s') . " - ERROR DETAILS:\n" .
                    "Message: " . $errorMessage . "\n" .
                    "Code: " . $errorCode . "\n" .
                    "Query: " . $errorQuery . "\n" .
                    "User ID: " . $currentUserId . "\n" .
                    "User Role: " . $userRole . "\n" .
                    "Action: " . $action . "\n" .
                    "Expense ID: " . $expenseId . "\n" .
                    "Status Field: " . $statusField . "\n" .
                    "Trace: " . $errorTrace . "\n\n";
    
    // Log to PHP error log
    error_log($errorMessage);
    
    // Log to custom file with more details
    $detailedLogFile = '../logs/expense_status_errors.log';
    file_put_contents($detailedLogFile, $detailedError, FILE_APPEND);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $errorMessage\n", FILE_APPEND);
    
    // Return error response with more details in development environment
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'debug_info' => [
            'error' => $errorMessage,
            'code' => $errorCode
        ]
    ]);
}