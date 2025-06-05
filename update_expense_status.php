<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Include config file
require_once 'config/db_connect.php';

// Get the JSON data from the request
$data = json_decode(file_get_contents('php://input'), true);

// Check if required data is present
if (!isset($data['id']) || !isset($data['status']) || !isset($data['type'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$expense_id = $data['id'];
$status = $data['status'];
$type = $data['type']; // hr, manager, or accountant
$reason = isset($data['reason']) ? $data['reason'] : null;

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Update the specific status field based on type
    if ($type === 'hr') {
        $stmt = $pdo->prepare("UPDATE travel_expenses SET hr_status = ?, hr_reason = ? WHERE id = ?");
        $stmt->execute([$status, $reason, $expense_id]);
    } elseif ($type === 'manager') {
        $stmt = $pdo->prepare("UPDATE travel_expenses SET manager_status = ?, manager_reason = ? WHERE id = ?");
        $stmt->execute([$status, $reason, $expense_id]);
    } elseif ($type === 'accountant') {
        $stmt = $pdo->prepare("UPDATE travel_expenses SET accountant_status = ?, accountant_reason = ? WHERE id = ?");
        $stmt->execute([$status, $reason, $expense_id]);
    } else {
        throw new Exception("Invalid type specified");
    }
    
    // Check if all statuses match to update the main status
    $stmt = $pdo->prepare("SELECT hr_status, manager_status, accountant_status FROM travel_expenses WHERE id = ?");
    $stmt->execute([$expense_id]);
    $statuses = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If any status is Rejected, set main status to Rejected
    if ($statuses['hr_status'] === 'Rejected' || $statuses['manager_status'] === 'Rejected' || $statuses['accountant_status'] === 'Rejected') {
        $mainStatus = 'Rejected';
    } 
    // If all statuses are Approved, set main status to Approved
    elseif ($statuses['hr_status'] === 'Approved' && $statuses['manager_status'] === 'Approved' && $statuses['accountant_status'] === 'Approved') {
        $mainStatus = 'Approved';
    } 
    // Otherwise, keep as Pending
    else {
        $mainStatus = 'Pending';
    }
    
    // Update the main status
    $stmt = $pdo->prepare("UPDATE travel_expenses SET status = ? WHERE id = ?");
    $stmt->execute([$mainStatus, $expense_id]);
    
    // Log the status change
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("INSERT INTO expense_status_logs (expense_id, user_id, status_type, old_status, new_status, reason, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$expense_id, $user_id, $type, 'Pending', $status, $reason]);
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 