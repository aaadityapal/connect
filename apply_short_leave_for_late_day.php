<?php
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$targetUserId = intval($input['user_id'] ?? 0);
$date         = trim($input['date'] ?? '');        // Y-m-d
$reason       = trim($input['reason'] ?? '');
$lateType     = trim($input['late_type'] ?? 'late'); // 'late' or 'one_hour_late'

// Validate
if ($targetUserId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit;
}

$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid date format']);
    exit;
}

if (strlen($reason) < 3) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Reason is required']);
    exit;
}

// Short Leave type ID = 11
$SHORT_LEAVE_TYPE_ID = 11;
$currentYear = intval($d->format('Y'));

try {
    $pdo->beginTransaction();

    // 1. Check if short leave already applied for this date
    $dupCheck = $pdo->prepare("
        SELECT id FROM leave_request
        WHERE user_id = ?
          AND leave_type = ?
          AND DATE(start_date) = ?
          AND status = 'approved'
        LIMIT 1
    ");
    $dupCheck->execute([$targetUserId, $SHORT_LEAVE_TYPE_ID, $date]);
    if ($dupCheck->fetch()) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Short leave already applied for this date']);
        exit;
    }

    // 2. Check short leave balance for this month (2 per month, computed from leave_request)
    $mStart = date('Y-m-01', strtotime($date));
    $mEnd   = date('Y-m-t',  strtotime($date));

    $usedStmt = $pdo->prepare("
        SELECT COUNT(*) as used
        FROM leave_request
        WHERE user_id = ?
          AND leave_type = (SELECT id FROM leave_types WHERE LOWER(name) LIKE '%short%' LIMIT 1)
          AND status != 'rejected'
          AND start_date BETWEEN ? AND ?
    ");
    $usedStmt->execute([$targetUserId, $mStart, $mEnd]);
    $usedRow  = $usedStmt->fetch(PDO::FETCH_ASSOC);
    $mUsed    = $usedRow ? intval($usedRow['used']) : 0;
    $remaining = max(0, 2 - $mUsed);

    if ($remaining <= 0) {
        $pdo->rollBack();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Employee has no short leave balance remaining for this month'
        ]);
        exit;
    }

    // 3. Get employee shift times for time_from / time_to
    $shiftStmt = $pdo->prepare("
        SELECT s.start_time, s.end_time
        FROM user_shifts us
        JOIN shifts s ON us.shift_id = s.id
        WHERE us.user_id = ?
          AND (us.effective_from IS NULL OR us.effective_from <= ?)
          AND (us.effective_to IS NULL OR us.effective_to >= ?)
        ORDER BY us.effective_from DESC
        LIMIT 1
    ");
    $shiftStmt->execute([$targetUserId, $date, $date]);
    $shift = $shiftStmt->fetch(PDO::FETCH_ASSOC);

    // Default short leave window: shift_start to shift_start + 2 hours
    $timeFrom = $shift ? $shift['start_time'] : '09:00:00';
    $timeTo   = $shift
        ? date('H:i:s', strtotime($shift['start_time'] . ' +2 hours'))
        : '11:00:00';

    // 4. Insert leave_request as auto-approved
    $insertStmt = $pdo->prepare("
        INSERT INTO leave_request
            (user_id, leave_type, start_date, end_date, reason, duration,
             time_from, time_to, status, duration_type,
             action_by, action_at, action_comments,
             manager_approval, manager_action_by, manager_action_at, manager_action_reason,
             hr_approval, hr_action_by, hr_action_at,
             created_at, updated_at)
        VALUES
            (?, ?, ?, ?, ?, 1,
             ?, ?, 'approved', 'full',
             ?, NOW(), 'Auto-approved via late day short leave application',
             'approved', ?, NOW(), 'Auto-approved via manager salary module',
             'approved', ?, NOW(),
             NOW(), NOW())
    ");
    $insertStmt->execute([
        $targetUserId,
        $SHORT_LEAVE_TYPE_ID,
        $date,
        $date,
        $reason,
        $timeFrom,
        $timeTo,
        $_SESSION['user_id'],  // action_by (HR/manager who applied)
        $_SESSION['user_id'],  // manager_action_by
        $_SESSION['user_id'],  // hr_action_by
    ]);

    $newLeaveId = $pdo->lastInsertId();

    $pdo->commit();

    // Remaining balance = 2 - (already used + 1 just inserted)
    $newBal = max(0, 2 - ($mUsed + 1));

    echo json_encode([
        'status'            => 'success',
        'message'           => 'Short leave applied and approved successfully',
        'leave_id'          => $newLeaveId,
        'remaining_balance' => $newBal
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("DB error in apply_short_leave_for_late_day.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error occurred']);
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error in apply_short_leave_for_late_day.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred']);
    exit;
}
?>
