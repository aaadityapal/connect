<?php
session_start();
require_once '../../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$managerId = (int)($_SESSION['user_id'] ?? 0);
$managerRole = strtolower($_SESSION['role'] ?? 'user');

$input = json_decode(file_get_contents('php://input'), true);
$requestId = isset($input['request_id']) ? (int)$input['request_id'] : 0;

if ($requestId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid request id']);
    exit();
}

try {
    $pdo->beginTransaction();

    $seedStmt = $pdo->prepare("SELECT id, user_id, leave_type, start_date, end_date, status, created_at, reason, duration FROM leave_request WHERE id = ?");
    $seedStmt->execute([$requestId]);
    $seed = $seedStmt->fetch(PDO::FETCH_ASSOC);

    if (!$seed) {
        throw new Exception('Leave request not found');
    }

    $status = strtolower((string)$seed['status']);
    if ($status !== 'pending') {
        throw new Exception('Only pending requests can be deleted');
    }

    if (!in_array($managerRole, ['admin', 'hr'], true)) {
        $mapStmt = $pdo->prepare("SELECT 1 FROM leave_approval_mapping WHERE manager_id = ? AND employee_id = ? LIMIT 1");
        $mapStmt->execute([$managerId, $seed['user_id']]);
        if (!$mapStmt->fetchColumn()) {
            throw new Exception('You do not have permission to delete this request');
        }
    }

    $ltStmt = $pdo->prepare("SELECT name FROM leave_types WHERE id = ?");
    $ltStmt->execute([$seed['leave_type']]);
    $leaveTypeName = (string)$ltStmt->fetchColumn();
    $leaveTypeLower = strtolower($leaveTypeName);

    $groupStmt = $pdo->prepare("SELECT id, duration FROM leave_request WHERE user_id = ? AND created_at = ? AND leave_type = ? AND reason <=> ? AND status = 'pending'");
    $groupStmt->execute([$seed['user_id'], $seed['created_at'], $seed['leave_type'], $seed['reason']]);
    $rows = $groupStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        throw new Exception('No pending rows found for this request');
    }

    $totalRefund = 0.0;
    $idsToDelete = [];

    foreach ($rows as $row) {
        $idsToDelete[] = (int)$row['id'];
        if (strpos($leaveTypeLower, 'short') !== false) {
            $totalRefund += 1.0;
        } else {
            $totalRefund += (float)$row['duration'];
        }
    }

    $isUnpaid = (int)$seed['leave_type'] === 13 || strpos($leaveTypeLower, 'unpaid') !== false;
    $isDynamic = strpos($leaveTypeLower, 'casual') !== false || strpos($leaveTypeLower, 'compensation') !== false || strpos($leaveTypeLower, 'comp off') !== false || strpos($leaveTypeLower, 'compensate') !== false;

    if ($isUnpaid || $isDynamic) {
        $totalRefund = 0.0;
    }

    if ($totalRefund > 0) {
        $year = date('Y', strtotime($seed['start_date']));
        $bankStmt = $pdo->prepare("UPDATE leave_bank SET remaining_balance = remaining_balance + ? WHERE user_id = ? AND leave_type_id = ? AND year = ?");
        $bankStmt->execute([$totalRefund, $seed['user_id'], $seed['leave_type'], $year]);
    }

    $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
    $delStmt = $pdo->prepare("DELETE FROM leave_request WHERE id IN ($placeholders)");
    $delStmt->execute($idsToDelete);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Leave request deleted and balance restored.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
