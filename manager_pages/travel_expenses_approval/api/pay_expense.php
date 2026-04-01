<?php
session_start();
require_once '../../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$id = $_POST['id'] ?? '';

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';
$is_admin = (strtolower($user_role) === 'admin' || strtolower($user_role) === 'administrator');

try {
    $pay_auth_stmt = $pdo->prepare("SELECT 1 FROM travel_payment_auth WHERE user_id = ?");
    $pay_auth_stmt->execute([$current_user_id]);
    $can_pay = $pay_auth_stmt->fetchColumn() ? true : false;

    if (!$is_admin && !$can_pay) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to process travel payments.']);
        exit();
    }

    // Fetch claim data for formatting notification
    $sel = $pdo->prepare("SELECT user_id, purpose, amount FROM travel_expenses WHERE id = ? AND status = 'approved'");
    $sel->execute([$id]);
    $exp = $sel->fetch(PDO::FETCH_ASSOC);

    if (!$exp) {
        echo json_encode(['success' => false, 'message' => 'Expense not found or not fully approved.']);
        exit();
    }

    $query = "UPDATE travel_expenses 
              SET payment_status = 'Paid', 
                  paid_on_date = NOW(), 
                  paid_by = ? 
              WHERE id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$current_user_id, $id]);
    
    if ($stmt->rowCount() > 0) {
        $performerName = $_SESSION['username'] ?? 'Accounts';
        $desc = "Payment for your travel expense '{$exp['purpose']}' (₹{$exp['amount']}) has been processed by {$performerName}.";
        
        $meta = json_encode([
            'amount' => $exp['amount'],
            'purpose' => $exp['purpose'],
            'paid_by' => $performerName,
            'paid_by_id' => $current_user_id,
            'paid_to_id' => $exp['user_id']
        ]);
        
        $logStmt = $pdo->prepare("INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at) VALUES (?, 'travel_paid', 'travel_expense', ?, ?, ?, NOW())");
        $logStmt->execute([$exp['user_id'], $id, $desc, $meta]);

        if ($current_user_id != $exp['user_id']) {
            $payerDesc = "You securely processed payment for travel expense '{$exp['purpose']}' (₹{$exp['amount']}).";
            $logStmt->execute([$current_user_id, $id, $payerDesc, $meta]);
        }

        echo json_encode(['success' => true, 'message' => 'Expense marked as Paid successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Expense could not be marked as Paid. Ensure it is fully approved.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
