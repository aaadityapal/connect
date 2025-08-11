<?php
// Endpoint: delete_leave_request_20250810.php
// Purpose: Allow a logged-in user to delete/cancel their own pending leave request

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db_connect.php';

function read_input_payload(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function respond(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload);
    exit();
}

try {
    $payload = read_input_payload();
    $userId  = (int)$_SESSION['user_id'];
    $leaveId = isset($payload['id']) ? (int)$payload['id'] : 0;

    if ($leaveId <= 0) {
        respond(400, ['success' => false, 'error' => 'id is required']);
    }

    // Ensure the leave belongs to the user and is pending
    $stmt = $pdo->prepare("SELECT id, user_id, status FROM leave_request WHERE id = ? AND user_id = ?");
    $stmt->execute([$leaveId, $userId]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$leave) {
        respond(404, ['success' => false, 'error' => 'Leave request not found']);
    }

    $status = strtolower((string)$leave['status']);
    if (in_array($status, ['approved', 'rejected'], true)) {
        respond(400, ['success' => false, 'error' => 'This leave cannot be deleted']);
    }

    // Perform deletion
    $del = $pdo->prepare("DELETE FROM leave_request WHERE id = ? AND user_id = ?");
    $ok = $del->execute([$leaveId, $userId]);

    if (!$ok) {
        respond(500, ['success' => false, 'error' => 'Failed to delete leave request']);
    }

    respond(200, ['success' => true, 'message' => 'Leave deleted successfully']);
} catch (Throwable $e) {
    error_log('Leave delete error: ' . $e->getMessage());
    respond(500, ['success' => false, 'error' => 'Internal server error']);
}


