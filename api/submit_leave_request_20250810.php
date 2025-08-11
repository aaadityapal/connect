<?php
// Unique endpoint: submit_leave_request_20250810.php
// Purpose: Accept a leave application from the logged-in user and create a record in leave_request

session_start();
header('Content-Type: application/json');

// Require authentication; scope to Site Supervisor for this flow
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/timezone_config.php';

// Ensure DB session uses IST regardless of global connection bootstrap
try { $pdo->query("SET time_zone = '+05:30'"); } catch (Throwable $e) { /* log and continue */ error_log('leave_submit tz set failed: '.$e->getMessage()); }

// Helper: read JSON body or form-encoded
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

/**
 * Logs a structured error and responds with a public-safe message.
 * Adds an X-Error-ID header and returns error_id in the JSON body for correlation.
 */
function log_and_respond_error(int $status, string $publicMessage, ?Throwable $exception = null, array $context = []): void {
    try {
        $errorId = bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        // Fallback if random_bytes unavailable
        $errorId = uniqid('err_', true);
    }

    $logRecord = [
        'error_id' => $errorId,
        'public_message' => $publicMessage,
    ];
    if ($exception) {
        $logRecord['exception'] = $exception->getMessage();
    }
    if (!empty($context)) {
        $logRecord['context'] = $context;
    }

    // Server-side log for debugging (check PHP error log on production)
    error_log('[leave_submit] ' . json_encode($logRecord, JSON_UNESCAPED_SLASHES));

    // Surface correlation ID to client for Network tab visibility
    header('X-Error-ID: ' . $errorId);
    respond($status, [
        'success' => false,
        'error' => $publicMessage,
        'error_id' => $errorId,
    ]);
}

try {
    $payload = read_input_payload();

    $userId         = (int)($_SESSION['user_id']);
    $leaveTypeId    = isset($payload['leave_type']) ? (int)$payload['leave_type'] : 0;
    $startDate      = trim($payload['date_from'] ?? $payload['start_date'] ?? '');
    $endDate        = trim($payload['date_to'] ?? $payload['end_date'] ?? '');
    $reason         = trim($payload['reason'] ?? '');
    $isHalfDay      = isset($payload['half_day']) && (string)$payload['half_day'] !== '0' && $payload['half_day'] !== false;
    $halfDayType    = $payload['half_day_type'] ?? null; // first_half | second_half | null
    $timeFrom       = $payload['time_from'] ?? null;     // optional HH:MM
    $timeTo         = $payload['time_to'] ?? null;       // optional HH:MM
    $compOffDate    = $payload['comp_off_source_date'] ?? null; // optional YYYY-MM-DD
    $shortLeaveTime = $payload['short_leave_time'] ?? null; // optional HH:MM for short leave window

    // Basic validation
    if ($leaveTypeId <= 0) {
        respond(400, ['success' => false, 'error' => 'leave_type is required']);
    }
    if ($startDate === '' || $endDate === '') {
        respond(400, ['success' => false, 'error' => 'start_date and end_date are required']);
    }
    // Normalize dates
    $sdObj = date_create($startDate);
    $edObj = date_create($endDate);
    if (!$sdObj || !$edObj) {
        respond(400, ['success' => false, 'error' => 'Invalid date format']);
    }
    if ($edObj < $sdObj) {
        respond(400, ['success' => false, 'error' => 'end_date cannot be before start_date']);
    }

    // Validate leave type exists and is active
    $chk = $pdo->prepare("SELECT id, name, max_days, status FROM leave_types WHERE id = ? AND status = 'active'");
    $chk->execute([$leaveTypeId]);
    $lt = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$lt) {
        respond(400, ['success' => false, 'error' => 'Invalid or inactive leave type']);
    }

    // Compute duration (inclusive). In this system duration is stored as integer days.
    $interval = date_diff($sdObj, $edObj);
    $daysInclusive = (int)$interval->format('%a') + 1; // inclusive

    $durationType = 'full';       // full | first_half | second_half
    $halfDayValue = null;         // alias for DB half_day_type field

    // Half-day handling only when start = end
    if ($isHalfDay && $daysInclusive === 1) {
        $durationType = in_array($halfDayType, ['first_half', 'second_half'], true) ? $halfDayType : 'first_half';
        $halfDayValue = $durationType; // mirror to DB column half_day_type
        // Historical data in this DB stores 1 for half-day. We follow that convention.
        $computedDuration = 1;
    } else {
        $computedDuration = $daysInclusive;
    }

    // Optional time_from / time_to normalization
    $tf = $timeFrom && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timeFrom) ? $timeFrom : null;
    $tt = $timeTo && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timeTo) ? $timeTo : null;
    $slt = $shortLeaveTime && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $shortLeaveTime) ? substr($shortLeaveTime,0,5) : null;

    // Build insert dynamically based on actual table columns in production
    $existingColumns = [];
    try {
        $colsStmt = $pdo->query('SHOW COLUMNS FROM leave_request');
        if ($colsStmt) {
            foreach ($colsStmt->fetchAll(PDO::FETCH_ASSOC) as $colRow) {
                if (!empty($colRow['Field'])) {
                    $existingColumns[$colRow['Field']] = true;
                }
            }
        }
    } catch (Throwable $e) {
        // If schema lookup fails, keep existingColumns empty to avoid accidental inserts to non-existent fields
        $existingColumns = [];
    }

    // Desired field map: column => value expression or placeholder
    $desiredFieldToExpr = [
        'user_id'               => ':user_id',
        'leave_type'            => ':leave_type',
        'start_date'            => ':start_date',
        'end_date'              => ':end_date',
        'reason'                => ':reason',
        'duration'              => ':duration',
        'time_from'             => ':time_from',
        'time_to'               => ':time_to',
        'status'                => ':status',
        'created_at'            => 'NOW()',
        'duration_type'         => ':duration_type',
        'half_day_type'         => ':half_day_type',
        'comp_off_source_date'  => ':comp_off_source_date',
        'short_leave_time'      => ':short_leave_time',
        // Legacy/optional workflow columns
        'action_reason'         => 'NULL',
        'action_by'             => 'NULL',
        'action_at'             => 'NULL',
        'updated_at'            => 'NOW()',
        'updated_by'            => ':updated_by',
        'action_comments'       => 'NULL',
        'manager_approval'      => 'NULL',
        'manager_action_reason' => 'NULL',
        'manager_action_by'     => 'NULL',
        'manager_action_at'     => 'NULL',
        'hr_approval'           => 'NULL',
        'hr_action_reason'      => 'NULL',
        'hr_action_by'          => 'NULL',
        'hr_action_at'          => 'NULL',
    ];

    // Parameter bag; we'll only pass those actually used in the final SQL
    $allParams = [
        ':user_id'              => $userId,
        ':leave_type'           => $leaveTypeId,
        ':start_date'           => $sdObj->format('Y-m-d'),
        ':end_date'             => $edObj->format('Y-m-d'),
        ':reason'               => $reason,
        ':duration'             => $computedDuration,
        ':time_from'            => $tf,
        ':time_to'              => $tt,
        ':status'               => 'pending',
        ':duration_type'        => $durationType,
        ':half_day_type'        => $halfDayValue,
        ':comp_off_source_date' => $compOffDate,
        ':short_leave_time'     => $slt,
        ':updated_by'           => $userId,
    ];

    $finalColumns = [];
    $finalExprs = [];
    $finalParams = [];
    foreach ($desiredFieldToExpr as $columnName => $expr) {
        if (!isset($existingColumns[$columnName])) {
            continue; // Skip fields that do not exist in production schema
        }
        $finalColumns[] = $columnName;
        $finalExprs[] = $expr;
        if (substr($expr, 0, 1) === ':') {
            // Only include params for placeholders we actually used
            if (array_key_exists($expr, $allParams)) {
                $finalParams[$expr] = $allParams[$expr];
            }
        }
    }

    if (empty($finalColumns)) {
        log_and_respond_error(500, 'Internal server error', null, [
            'stage' => 'no_columns_to_insert',
        ]);
    }

    $sql = 'INSERT INTO leave_request (' . implode(',', $finalColumns) . ') VALUES (' . implode(',', $finalExprs) . ')';

    // Prepare and execute with explicit error capture to improve diagnostics
    try {
        $stmt = $pdo->prepare($sql);
    } catch (Throwable $e) {
        log_and_respond_error(500, 'Internal server error', $e, [
            'stage' => 'prepare_failed',
        ]);
    }

    $ok = false;
    try {
        $ok = $stmt->execute($finalParams);
    } catch (Throwable $e) {
        log_and_respond_error(500, 'Internal server error', $e, [
            'stage' => 'execute_exception',
        ]);
    }

    if (!$ok) {
        $errInfo = method_exists($stmt, 'errorInfo') ? $stmt->errorInfo() : null;
        $errStr = is_array($errInfo) ? implode(' | ', array_filter($errInfo, 'strlen')) : 'unknown PDO error';
        log_and_respond_error(500, 'Failed to create leave request', null, [
            'stage' => 'insert_execute_failed',
            'pdo_error' => $errStr,
        ]);
    }

    $newId = (int)$pdo->lastInsertId();

    respond(200, [
        'success' => true,
        'message' => 'Leave request submitted successfully',
        'data' => [
            'id'            => $newId,
            'user_id'       => $userId,
            'leave_type'    => $leaveTypeId,
            'start_date'    => $sdObj->format('Y-m-d'),
            'end_date'      => $edObj->format('Y-m-d'),
            'duration'      => $computedDuration,
            'duration_type' => $durationType,
            'half_day_type' => $halfDayValue,
            'status'        => 'pending'
        ]
    ]);
} catch (Throwable $e) {
    // Avoid exposing internals; provide a correlation id via header/body
    log_and_respond_error(500, 'Internal server error', $e);
}
