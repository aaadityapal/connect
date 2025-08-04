<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit;
}

// Get current user ID
$currentUserId = $_SESSION['user_id'];

// Check if user has appropriate role
try {
    $roleQuery = "SELECT role FROM users WHERE id = :user_id";
    $roleStmt = $pdo->prepare($roleQuery);
    $roleStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
    $roleStmt->execute();
    
    $userRole = $roleStmt->fetchColumn();
    
    // Allow access only to appropriate roles
    $allowedRoles = ['Purchase Manager', 'HR', 'Finance Manager', 'Accountant', 'Senior Manager (Site)', 'Senior Manager (Studio)'];
    if (!in_array($userRole, $allowedRoles)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized access'
        ]);
        exit;
    }
} catch (PDOException $e) {
    error_log('Error checking user role: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred while checking permissions'
    ]);
    exit;
}

// Function to determine which status field to update based on user role
function getStatusField($role) {
    $role = strtolower($role);
    
    if (strpos($role, 'manager') !== false && strpos($role, 'purchase') === false && strpos($role, 'finance') === false) {
        return 'manager_status';
    } elseif (strpos($role, 'accountant') !== false || strpos($role, 'purchase') !== false || strpos($role, 'finance') !== false) {
        return 'accountant_status';
    } elseif (strpos($role, 'hr') !== false) {
        return 'hr_status';
    }
    
    // Default to overall status if role doesn't match specific criteria
    return 'status';
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get POST data
$action = isset($_POST['action']) ? $_POST['action'] : '';
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
$expenseIds = isset($_POST['expense_ids']) ? json_decode($_POST['expense_ids'], true) : [];

// For bulk actions by date and user
$userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$travelDate = isset($_POST['travel_date']) ? $_POST['travel_date'] : '';

// Validate required fields
if (empty($action) || ($action !== 'approve' && $action !== 'reject')) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
    exit;
}

// If rejecting, reason is required and must be at least 10 words
if ($action === 'reject' && (empty($reason) || str_word_count($reason) < 10)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Rejection reason must be at least 10 words'
    ]);
    exit;
}

// Determine which status field to update based on user role
$statusField = getStatusField($userRole);
$reasonField = str_replace('status', 'reason', $statusField);

// Determine the new status value
$newStatus = $action === 'approve' ? 'approved' : 'rejected';

try {
    // Start transaction
    $pdo->beginTransaction();
    
    $rowsAffected = 0;
    
    // If specific expense IDs are provided
    if (!empty($expenseIds) && is_array($expenseIds)) {
        // Prepare the query to update specific expenses
        $query = "UPDATE travel_expenses 
                 SET $statusField = :status, 
                     $reasonField = :reason,
                     updated_at = NOW(), 
                     updated_by = :updated_by";
        
        // If rejecting, cascade to other roles and set overall status to rejected
        if ($action === 'reject') {
            // Generate cascade message and escape it for direct inclusion in SQL
            $cascadeMsg = "Auto-rejected: " . ucfirst(str_replace('_status', '', $statusField)) . " rejected - " . substr($reason, 0, 50);
            $cascadeMsgEscaped = $pdo->quote($cascadeMsg);
            
            // Set overall status to rejected
            $query .= ", status = 'rejected'";
            
            // Add rejection_cascade field to track who initiated the rejection
            $rejectionFlag = strtoupper(str_replace('_status', '', $statusField)) . '_REJECTED';
            $rejectionFlagEscaped = $pdo->quote($rejectionFlag);
            $query .= ", rejection_cascade = $rejectionFlagEscaped";
            
            // Add cascade fields directly to the update query based on the current role
            // Use direct string values instead of parameter binding to avoid the parameter binding issue
            if ($statusField === 'manager_status') {
                $query .= ", accountant_status = 'rejected', accountant_reason = $cascadeMsgEscaped";
                $query .= ", hr_status = 'rejected', hr_reason = $cascadeMsgEscaped";
            } elseif ($statusField === 'accountant_status') {
                $query .= ", manager_status = 'rejected', manager_reason = $cascadeMsgEscaped";
                $query .= ", hr_status = 'rejected', hr_reason = $cascadeMsgEscaped";
            } elseif ($statusField === 'hr_status') {
                $query .= ", manager_status = 'rejected', manager_reason = $cascadeMsgEscaped";
                $query .= ", accountant_status = 'rejected', accountant_reason = $cascadeMsgEscaped";
            }
        }
        
        $query .= " WHERE id IN (" . implode(',', array_map('intval', $expenseIds)) . ")
                 AND status = 'pending'";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
        $stmt->bindParam(':reason', $reason, PDO::PARAM_STR);
        $stmt->bindParam(':updated_by', $currentUserId, PDO::PARAM_INT);
        
        // No need to bind cascade parameters as we're using direct string values with quote()
        
        $stmt->execute();
        
        $rowsAffected = $stmt->rowCount();
        
        // For approval, update the overall status if this is the final approver
        if ($action === 'approve' && ($statusField === 'hr_status' || 
            ($statusField === 'accountant_status' && $userRole === 'Purchase Manager') ||
            ($statusField === 'manager_status' && in_array($userRole, ['HR Manager', 'Finance Manager'])))) {
            
            $overallQuery = "UPDATE travel_expenses 
                           SET status = :status
                           WHERE id IN (" . implode(',', array_map('intval', $expenseIds)) . ")
                           AND status = 'pending'";
            
            $overallStmt = $pdo->prepare($overallQuery);
            $overallStmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
            $overallStmt->execute();
        }
    }
    // If user_id and travel_date are provided for bulk action
    elseif ($userId > 0 && !empty($travelDate)) {
        // Prepare the query to update all pending expenses for the user on the given date
        $query = "UPDATE travel_expenses 
                 SET $statusField = :status, 
                     $reasonField = :reason,
                     updated_at = NOW(), 
                     updated_by = :updated_by";
        
        // If rejecting, cascade to other roles and set overall status to rejected
        if ($action === 'reject') {
            // Generate cascade message and escape it for direct inclusion in SQL
            $cascadeMsg = "Auto-rejected: " . ucfirst(str_replace('_status', '', $statusField)) . " rejected - " . substr($reason, 0, 50);
            $cascadeMsgEscaped = $pdo->quote($cascadeMsg);
            
            // Set overall status to rejected
            $query .= ", status = 'rejected'";
            
            // Add rejection_cascade field to track who initiated the rejection
            $rejectionFlag = strtoupper(str_replace('_status', '', $statusField)) . '_REJECTED';
            $rejectionFlagEscaped = $pdo->quote($rejectionFlag);
            $query .= ", rejection_cascade = $rejectionFlagEscaped";
            
            // Add cascade fields directly to the update query based on the current role
            // Use direct string values instead of parameter binding to avoid the parameter binding issue
            if ($statusField === 'manager_status') {
                $query .= ", accountant_status = 'rejected', accountant_reason = $cascadeMsgEscaped";
                $query .= ", hr_status = 'rejected', hr_reason = $cascadeMsgEscaped";
            } elseif ($statusField === 'accountant_status') {
                $query .= ", manager_status = 'rejected', manager_reason = $cascadeMsgEscaped";
                $query .= ", hr_status = 'rejected', hr_reason = $cascadeMsgEscaped";
            } elseif ($statusField === 'hr_status') {
                $query .= ", manager_status = 'rejected', manager_reason = $cascadeMsgEscaped";
                $query .= ", accountant_status = 'rejected', accountant_reason = $cascadeMsgEscaped";
            }
        }
        
        $query .= " WHERE user_id = :user_id 
                 AND travel_date = :travel_date
                 AND status = 'pending'";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
        $stmt->bindParam(':reason', $reason, PDO::PARAM_STR);
        $stmt->bindParam(':updated_by', $currentUserId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':travel_date', $travelDate, PDO::PARAM_STR);
        
        // No need to bind cascade parameters as we're using direct string values with quote()
        
        $stmt->execute();
        
        $rowsAffected = $stmt->rowCount();
        
        // For approval, update the overall status if this is the final approver
        if ($action === 'approve' && ($statusField === 'hr_status' || 
            ($statusField === 'accountant_status' && $userRole === 'Purchase Manager') ||
            ($statusField === 'manager_status' && in_array($userRole, ['HR Manager', 'Finance Manager'])))) {
            
            $overallQuery = "UPDATE travel_expenses 
                           SET status = :status
                           WHERE user_id = :user_id 
                           AND travel_date = :travel_date
                           AND status = 'pending'";
            
            $overallStmt = $pdo->prepare($overallQuery);
            $overallStmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
            $overallStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $overallStmt->bindParam(':travel_date', $travelDate, PDO::PARAM_STR);
            $overallStmt->execute();
        }
    } else {
        // Neither expense IDs nor user_id+travel_date provided
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No expenses specified for update'
        ]);
        exit;
    }
    
    // Log the action
    $logQuery = "INSERT INTO expense_status_updates 
                (user_id, action, status_field, new_status, reason, affected_rows, created_at) 
                VALUES (:user_id, :action, :status_field, :new_status, :reason, :affected_rows, NOW())";
    
    $logStmt = $pdo->prepare($logQuery);
    $logStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
    $logStmt->bindParam(':action', $action, PDO::PARAM_STR);
    $logStmt->bindParam(':status_field', $statusField, PDO::PARAM_STR);
    $logStmt->bindParam(':new_status', $newStatus, PDO::PARAM_STR);
    $logStmt->bindParam(':reason', $reason, PDO::PARAM_STR);
    $logStmt->bindParam(':affected_rows', $rowsAffected, PDO::PARAM_INT);
    $logStmt->execute();
    
    // Also log to file for debugging
    $logFile = '../logs/expense_status_updates.log';
    $timestamp = date('Y-m-d H:i:s');
    $userQuery = "SELECT username, role FROM users WHERE id = :user_id";
    $userStmt = $pdo->prepare($userQuery);
    $userStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
    $userStmt->execute();
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    $username = $userData['username'] ?? 'Unknown';
    $userRole = $userData['role'] ?? 'Unknown';
    
    $logMessage = "$timestamp - User $username ($userRole) is {$action}ing " . 
                 (empty($expenseIds) ? "expenses for user $userId on date $travelDate" : "multiple expenses (" . implode(', ', $expenseIds) . ")") .
                 ". Updating $statusField. Reason: $reason\n";
    $logMessage .= "$timestamp - Updated $rowsAffected records\n\n";
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $action === 'approve' ? 'Expenses approved successfully' : 'Expenses rejected successfully',
        'rows_affected' => $rowsAffected,
        'status_field' => $statusField
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    // Log detailed error information
    $errorMessage = "Database error: " . $e->getMessage();
    $errorCode = $e->getCode();
    $errorTrace = $e->getTraceAsString();
    $errorQuery = isset($query) ? $query : 'Unknown query';
    
    // Create detailed error log
    $detailedError = date('Y-m-d H:i:s') . " - BULK UPDATE ERROR DETAILS:\n" .
                    "Message: " . $errorMessage . "\n" .
                    "Code: " . $errorCode . "\n" .
                    "Query: " . $errorQuery . "\n" .
                    "User ID: " . $currentUserId . "\n" .
                    "User Role: " . $userRole . "\n" .
                    "Action: " . $action . "\n" .
                    "Status Field: " . $statusField . "\n" .
                    "Expense IDs: " . (empty($expenseIds) ? 'None' : implode(',', $expenseIds)) . "\n" .
                    "User ID/Date: " . $userId . " / " . $travelDate . "\n" .
                    "Trace: " . $errorTrace . "\n\n";
    
    // Log to PHP error log
    error_log('Error updating expense status: ' . $e->getMessage());
    
    // Log to custom file with more details
    $detailedLogFile = '../logs/expense_status_errors.log';
    file_put_contents($detailedLogFile, $detailedError, FILE_APPEND);
    
    // Return error response with more details in development environment
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred while updating expense status',
        'debug_info' => [
            'error' => $errorMessage,
            'code' => $errorCode
        ]
    ]);
}