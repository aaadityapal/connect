<?php
// Endpoint: update_leave_request_20250810.php
// Purpose: Allow a logged-in user to update their own pending leave request

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

    $userId      = (int)$_SESSION['user_id'];
    $leaveId     = isset($payload['id']) ? (int)$payload['id'] : 0;
    $leaveTypeId = isset($payload['leave_type']) ? (int)$payload['leave_type'] : 0;
    $startDate   = trim($payload['start_date'] ?? '');
    $endDate     = trim($payload['end_date'] ?? '');
    $reason      = trim($payload['reason'] ?? '');
    $isHalfDay   = isset($payload['half_day']) && (string)$payload['half_day'] !== '0' && $payload['half_day'] !== false;
    $halfDayType = $payload['half_day_type'] ?? null; // first_half | second_half | null

    if ($leaveId <= 0) {
        respond(400, ['success' => false, 'error' => 'id is required']);
    }
    if ($leaveTypeId <= 0) {
        respond(400, ['success' => false, 'error' => 'leave_type is required']);
    }
    if ($startDate === '' || $endDate === '') {
        respond(400, ['success' => false, 'error' => 'start_date and end_date are required']);
    }
    // Validate new leave type exists & active
    $chk = $pdo->prepare("SELECT id FROM leave_types WHERE id = ? AND status = 'active'");
    $chk->execute([$leaveTypeId]);
    if (!$chk->fetch(PDO::FETCH_ASSOC)) {
        respond(400, ['success' => false, 'error' => 'Invalid or inactive leave type']);
    }


    $sd = date_create($startDate);
    $ed = date_create($endDate);
    if (!$sd || !$ed) {
        respond(400, ['success' => false, 'error' => 'Invalid date format']);
    }
    if ($ed < $sd) {
        respond(400, ['success' => false, 'error' => 'end_date cannot be before start_date']);
    }

    // Ensure the leave belongs to the user and is pending
    $stmt = $pdo->prepare("SELECT id, user_id, status, leave_type FROM leave_request WHERE id = ? AND user_id = ?");
    $stmt->execute([$leaveId, $userId]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$leave) {
        respond(404, ['success' => false, 'error' => 'Leave request not found']);
    }
    $status = strtolower((string)$leave['status']);
    if (in_array($status, ['approved', 'rejected'], true)) {
        respond(400, ['success' => false, 'error' => 'This leave cannot be edited']);
    }

    // Recompute duration
    $interval = date_diff($sd, $ed);
    $daysInclusive = (int)$interval->format('%a') + 1;
    $durationType = 'full';
    $dayTypeValue = 'full'; // Default to full
    
    if ($isHalfDay && $daysInclusive === 1) {
        $durationType = 'first_half'; // Duration type for half day
        $dayTypeValue = in_array($halfDayType, ['first_half', 'second_half'], true) ? $halfDayType : 'first_half';
        $computedDuration = 0.5; // Half day duration
    } else {
        $durationType = 'full';
        $dayTypeValue = 'full';
        $computedDuration = $daysInclusive;
    }

    // Persist changes
    $upd = $pdo->prepare(
        "UPDATE leave_request
         SET start_date = :start_date,
             end_date = :end_date,
             reason = :reason,
             leave_type = :leave_type,
             duration = :duration,
             duration_type = :duration_type,
             day_type = :day_type,
             updated_at = NOW(),
             updated_by = :updated_by
         WHERE id = :id AND user_id = :user_id"
    );

    $ok = $upd->execute([
        ':start_date'    => $sd->format('Y-m-d'),
        ':end_date'      => $ed->format('Y-m-d'),
        ':reason'        => $reason,
        ':leave_type'    => $leaveTypeId,
        ':duration'      => $computedDuration,
        ':duration_type' => $durationType,
        ':day_type'      => $dayTypeValue,
        ':updated_by'    => $userId,
        ':id'            => $leaveId,
        ':user_id'       => $userId,
    ]);

    if (!$ok) {
        respond(500, ['success' => false, 'error' => 'Failed to update leave request']);
    }

    // Insert notification for update
    try {
        $title = 'Leave request updated';
        $message = sprintf('Leave #%d updated: %s → %s, type %d, duration %s day(s).',
            $leaveId,
            $sd->format('Y-m-d'),
            $ed->format('Y-m-d'),
            $leaveTypeId,
            number_format((float)$computedDuration, 2)
        );
        $payload = json_encode([
            'leave_request_id' => $leaveId,
            'start_date' => $sd->format('Y-m-d'),
            'end_date' => $ed->format('Y-m-d'),
            'leave_type_id' => $leaveTypeId,
            'duration' => (float)$computedDuration,
            'duration_type' => $durationType,
            'day_type' => $dayTypeValue,
        ], JSON_UNESCAPED_UNICODE);

        // attempt to find last action_by (approver) for this leave to set recipient
        $recStmt = $pdo->prepare("SELECT action_by FROM leave_request WHERE id = ?");
        $recStmt->execute([$leaveId]);
        $recRow = $recStmt->fetch(PDO::FETCH_ASSOC);
        $recipientId = isset($recRow['action_by']) ? (int)$recRow['action_by'] : null;

        $ins = $pdo->prepare("INSERT INTO all_notifications (user_id, recipient_id, event, leave_request_id, title, message, payload) VALUES (?, ?, 'leave_updated', ?, ?, ?, ?)");
        $ins->execute([$userId, $recipientId, $leaveId, $title, $message, $payload]);
    } catch (Throwable $ne) {
        error_log('Notification insert failed (update): ' . $ne->getMessage());
    }

    respond(200, ['success' => true, 'message' => 'Leave updated successfully']);
} catch (Throwable $e) {
    error_log('Leave update error: ' . $e->getMessage());
    respond(500, ['success' => false, 'error' => 'Internal server error']);
}


