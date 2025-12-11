<?php
session_start();
require_once '../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$id = $input['id'];
$user_id = $_SESSION['user_id'];

try {
    // 1. Verify ownership and status
    $checkQuery = "SELECT id, status FROM leave_request WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($checkQuery);
    $stmt->execute([$id, $user_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    if ($request['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Cannot delete non-pending requests']);
        exit;
    }

    // 2. Delete
    $deleteQuery = "DELETE FROM leave_request WHERE id = ?";
    $delStmt = $pdo->prepare($deleteQuery);
    $delStmt->execute([$id]);

    if ($delStmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>