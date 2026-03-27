<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID not provided']);
    exit();
}

$user_id = $_SESSION['user_id'];
$id = $_POST['id'];

// Remove "EXP-" prefix if present
$numeric_id = str_replace('EXP-', '', $id);

try {
    // 1. Fetch current status to check if it's already approved
    $checkQuery = "SELECT id, status, manager_status, accountant_status, hr_status, purpose, amount, travel_date, from_location, to_location
                    FROM travel_expenses 
                    WHERE id = ? AND user_id = ?";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$numeric_id, $user_id]);
    $expense = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        echo json_encode(['success' => false, 'message' => 'Expense not found or unauthorized']);
        exit();
    }

    // 2. STRICTURE: If ANY status is "approved", it CANNOT be deleted
    if (
        $expense['status'] === 'approved' ||
        $expense['manager_status'] === 'approved' ||
        $expense['accountant_status'] === 'approved' ||
        $expense['hr_status'] === 'approved'
    ) {
        echo json_encode(['success' => false, 'message' => 'This expense has already been approved and cannot be deleted.']);
        exit();
    }

    // 3. Perform Deletion
    $deleteQuery = "DELETE FROM travel_expenses WHERE id = ?";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $deleteStmt->execute([$numeric_id]);

    // 4. Log Activity
    $logDescription = "Deleted travel expense EXP-" . str_pad($numeric_id, 4, '0', STR_PAD_LEFT) . " for " . $expense['purpose'] . ". Trip: " . $expense['from_location'] . " to " . $expense['to_location'] . " on " . $expense['travel_date'] . ". Total: ₹" . $expense['amount'];
    $metadata = json_encode([
        'id' => $numeric_id,
        'action' => 'delete',
        'details' => [
            'date' => $expense['travel_date'],
            'purpose' => $expense['purpose'],
            'from' => $expense['from_location'],
            'to' => $expense['to_location'],
            'amount' => $expense['amount']
        ]
    ]);

    $logQuery = "INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at) 
                 VALUES (?, 'travel_deleted', 'travel', ?, ?, ?, NOW())";
    $logStmt = $pdo->prepare($logQuery);
    $logStmt->execute([$user_id, $numeric_id, $logDescription, $metadata]);

    echo json_encode(['success' => true, 'message' => 'Expense deleted successfully']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>