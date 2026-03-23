<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$leave_id = $data['id'] ?? null;

if (!$leave_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Fetch the target row to identify its 'application group'
    $stmt = $pdo->prepare("SELECT * FROM leave_request WHERE id = ? AND user_id = ?");
    $stmt->execute([$leave_id, $user_id]);
    $seed = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$seed) {
        throw new Exception("Leave request not found");
    }

    if ($seed['status'] !== 'pending') {
        throw new Exception("Only pending requests can be deleted");
    }

    // 2. We group by same created_at, same type, same user
    // This allows us to delete an entire application at once
    $queryGroup = "SELECT * FROM leave_request 
                   WHERE user_id = ? 
                   AND created_at = ? 
                   AND leave_type = ?
                   AND status = 'pending'";
    $stmtGroup = $pdo->prepare($queryGroup);
    $stmtGroup->execute([$user_id, $seed['created_at'], $seed['leave_type']]);
    $allRows = $stmtGroup->fetchAll(PDO::FETCH_ASSOC);

    if (empty($allRows)) {
        throw new Exception("No pending rows found in this group.");
    }

    // 3. Calculate total refund
    $year = date('Y', strtotime($seed['start_date']));
    $leave_type_id = $seed['leave_type'];
    
    $ltStmt = $pdo->prepare("SELECT name FROM leave_types WHERE id = ?");
    $ltStmt->execute([$leave_type_id]);
    $ltName = $ltStmt->fetchColumn();

    $totalRefund = 0;
    $idsToDelete = [];
    foreach ($allRows as $row) {
        $idsToDelete[] = $row['id'];
        if ($ltName === 'Short Leave') {
            $totalRefund += 1.0;
        } else {
            $totalRefund += (float)$row['duration'];
        }
    }

    // Exempt Unpaid Leave (ID 13) from refunds
    if ($leave_type_id == 13) {
        $totalRefund = 0;
    }

    if ($totalRefund > 0) {
        // 4. Refund full bank
        $updateBank = "UPDATE leave_bank 
                       SET remaining_balance = remaining_balance + ? 
                       WHERE user_id = ? AND leave_type_id = ? AND year = ?";
        $stmtBank = $pdo->prepare($updateBank);
        $stmtBank->execute([$totalRefund, $user_id, $leave_type_id, $year]);
    }

    // 5. Delete all from this group
    $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
    $stmtDel = $pdo->prepare("DELETE FROM leave_request WHERE id IN ($placeholders)");
    $stmtDel->execute($idsToDelete);

    $leave = $seed; // for the activity log below
    $leave['duration'] = $totalRefund; 

    // ─── Activity Logging ──────────────────
    try {
        $logDesc = "Cancelled leave for " . $leave['start_date'];
        if ($leave['start_date'] !== $leave['end_date']) {
            $logDesc = "Cancelled leave range: " . $leave['start_date'] . " to " . $leave['end_date'];
        }
        $logStmt = $pdo->prepare("INSERT INTO global_activity_logs (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read) VALUES (?, 'leave_deleted', 'leave', ?, ?, ?, NOW(), 0)");
        $logStmt->execute([$user_id, $leave_id, $logDesc, json_encode(['refunded' => $refundAmount, 'original_request' => $leave])]);
    } catch (Exception $e) { }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Request deleted and balance restored!']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
