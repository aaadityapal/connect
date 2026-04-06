<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$employeeId = isset($data['employee_id']) ? (int)$data['employee_id'] : 0;
$status = strtolower(trim((string)($data['status'] ?? '')));

if ($employeeId <= 0 || !in_array($status, ['active', 'inactive'], true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid employee or status'
    ]);
    exit();
}

try {
    require_once '../../../config/db_connect.php';

    $insertLog = function(
        int $userId,
        string $actionType,
        string $entityType,
        ?int $entityId,
        string $description,
        array $meta = []
    ) use ($pdo): void {
        $stmt = $pdo->prepare(
            "INSERT INTO global_activity_logs
                (user_id, action_type, entity_type, entity_id, description, metadata, created_at, is_read, is_dismissed)
             VALUES
                (:user_id, :action_type, :entity_type, :entity_id, :description, :metadata, NOW(), 0, 0)"
        );

        $stmt->execute([
            ':user_id' => $userId,
            ':action_type' => $actionType,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':description' => $description,
            ':metadata' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
    };

    $checkStmt = $pdo->prepare('SELECT id, username, status, status_changed_date FROM users WHERE id = :id LIMIT 1');
    $checkStmt->execute([':id' => $employeeId]);
    $employee = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Employee not found'
        ]);
        exit();
    }

    $oldStatus = strtolower(trim((string)($employee['status'] ?? 'inactive')));
    $now = date('Y-m-d H:i:s');
    $newStatusText = ucfirst($status);

    $hasLastActiveColumn = false;
    try {
        $colStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_active'"
        );
        $colStmt->execute();
        $hasLastActiveColumn = (int)$colStmt->fetchColumn() > 0;
    } catch (Throwable $ignore) {
        $hasLastActiveColumn = false;
    }

    if ($hasLastActiveColumn && $status === 'inactive') {
        $updateStmt = $pdo->prepare('UPDATE users SET status = :status, status_changed_date = :changed_at, last_active = :last_active WHERE id = :id LIMIT 1');
        $updateStmt->execute([
            ':status' => $newStatusText,
            ':changed_at' => $now,
            ':last_active' => $now,
            ':id' => $employeeId
        ]);
    } else {
        $updateStmt = $pdo->prepare('UPDATE users SET status = :status, status_changed_date = :changed_at WHERE id = :id LIMIT 1');
        $updateStmt->execute([
            ':status' => $newStatusText,
            ':changed_at' => $now,
            ':id' => $employeeId
        ]);
    }

    $actorId = (int)($_SESSION['user_id'] ?? 0);
    $actorName = (string)($_SESSION['username'] ?? 'System');
    $triggerSource = trim((string)($data['trigger_source'] ?? 'unknown'));

    $meta = [
        'employee_id' => (int)$employee['id'],
        'employee_name' => (string)$employee['username'],
        'old_status' => $oldStatus,
        'new_status' => $status,
        'status_changed_at' => $now,
        'trigger_source' => $triggerSource,
        'changed_by_user_id' => $actorId,
        'changed_by_username' => $actorName,
    ];

    if ($actorId > 0) {
        $insertLog(
            $actorId,
            'employee_status_changed',
            'user',
            (int)$employee['id'],
            "Changed {$employee['username']} status from " . ucfirst($oldStatus) . " to {$newStatusText} at {$now}",
            $meta
        );
    }

    echo json_encode([
        'success' => true,
        'message' => 'Employee status updated successfully',
        'status' => $status,
        'employee_id' => $employeeId,
        'status_changed_at' => $now
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update employee status'
    ]);
}
