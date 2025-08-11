<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db_connect.php';
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $action = isset($_POST['action']) ? strtolower(trim($_POST['action'])) : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

    if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    // Determine new status and which columns to fill
    $new_status = $action === 'approve' ? 'approved' : 'rejected';

    $user_id = intval($_SESSION['user_id']);
    $now = date('Y-m-d H:i:s');

    // By spec, we will store reason in generic action columns as well as manager_* since this UI is for Senior Manager (Site)
    $sql = "UPDATE leave_request 
            SET status = ?, 
                action_reason = ?, action_by = ?, action_at = ?, 
                manager_approval = ?, manager_action_reason = ?, manager_action_by = ?, manager_action_at = ?, 
                updated_at = ?, updated_by = ? 
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare failed']);
        exit;
    }

    $manager_approval = $new_status === 'approved' ? 'approved' : 'rejected';

    $stmt->bind_param(
        'ssisssissii',
        $new_status,
        $reason,
        $user_id,
        $now,
        $manager_approval,
        $reason,
        $user_id,
        $now,
        $now,
        $user_id,
        $id
    );

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'error' => 'Execution failed']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Leave request updated', 'id' => $id, 'status' => $new_status]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


