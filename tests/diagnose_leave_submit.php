<?php
// Diagnostic endpoint for leave submit flow
// Purpose: Inspect environment, table schema, and dry-run the insert logic with rollback

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/timezone_config.php';
// Force IST for this connection too
try { $pdo && $pdo->query("SET time_zone = '+05:30'"); } catch (Throwable $e) { /* ignore */ }

$result = [
    'success' => false,
    'meta' => [
        'php_version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'timestamp' => date('c'),
        'session_user_id' => isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null,
    ],
];

try {
    require_once __DIR__ . '/../config/db_connect.php';

    // Helper: read JSON or form input
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) { $payload = []; }
    } else {
        $payload = $_POST + $_GET; // allow simple GET testing
    }

    // Defaults for quick testing if not provided
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (int)($payload['user_id'] ?? 1);
    $leaveTypeId = (int)($payload['leave_type'] ?? 1);
    $startDate = trim($payload['start_date'] ?? $today);
    $endDate = trim($payload['end_date'] ?? $today);
    $reason = trim($payload['reason'] ?? 'diagnostic-test');
    $isHalfDay = !empty($payload['half_day']);
    $halfDayType = $payload['half_day_type'] ?? null; // first_half | second_half | null
    $timeFrom = $payload['time_from'] ?? null;
    $timeTo = $payload['time_to'] ?? null;
    $compOffDate = $payload['comp_off_source_date'] ?? null;
    $shortLeaveTime = $payload['short_leave_time'] ?? null;

    // Normalize dates and compute duration
    $sdObj = date_create($startDate);
    $edObj = date_create($endDate);
    if (!$sdObj || !$edObj) {
        throw new RuntimeException('Invalid date(s) provided');
    }
    if ($edObj < $sdObj) {
        throw new RuntimeException('end_date cannot be before start_date');
    }
    $interval = date_diff($sdObj, $edObj);
    $daysInclusive = (int)$interval->format('%a') + 1;

    $durationType = 'full';
    $halfDayValue = null;
    if ($isHalfDay && $daysInclusive === 1) {
        $durationType = in_array($halfDayType, ['first_half', 'second_half'], true) ? $halfDayType : 'first_half';
        $halfDayValue = $durationType;
        $computedDuration = 1;
    } else {
        $computedDuration = $daysInclusive;
    }

    $tf = $timeFrom && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timeFrom) ? $timeFrom : null;
    $tt = $timeTo && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timeTo) ? $timeTo : null;
    $slt = $shortLeaveTime && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $shortLeaveTime) ? substr($shortLeaveTime, 0, 5) : null;

    // Get existing columns of leave_request
    $existingColumns = [];
    $colsStmt = $pdo->query('SHOW COLUMNS FROM leave_request');
    $columnsReport = [];
    foreach ($colsStmt->fetchAll(PDO::FETCH_ASSOC) as $colRow) {
        $field = $colRow['Field'] ?? '';
        if ($field !== '') {
            $existingColumns[$field] = true;
            $columnsReport[] = $field;
        }
    }
    $result['schema'] = [
        'leave_request_columns' => $columnsReport,
    ];

    // Desired field map mirroring API logic
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
        // Older optional workflow columns
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
            continue;
        }
        $finalColumns[] = $columnName;
        $finalExprs[] = $expr;
        if (substr($expr, 0, 1) === ':') {
            if (array_key_exists($expr, $allParams)) {
                $finalParams[$expr] = $allParams[$expr];
            }
        }
    }

    $sql = 'INSERT INTO leave_request (' . implode(',', $finalColumns) . ') VALUES (' . implode(',', $finalExprs) . ')';
    $result['prepared'] = [
        'sql' => $sql,
        'used_placeholders' => array_keys($finalParams),
        'param_sample' => $finalParams,
    ];

    // Try to prepare
    try {
        $stmt = $pdo->prepare($sql);
        $result['prepare'] = ['ok' => true];
    } catch (Throwable $e) {
        $result['prepare'] = [
            'ok' => false,
            'error' => $e->getMessage(),
        ];
        echo json_encode($result);
        exit;
    }

    // Execute inside a transaction and rollback to avoid persistent writes
    $dryRun = (isset($payload['dry_run']) ? (bool)$payload['dry_run'] : true);
    if ($dryRun) {
        $pdo->beginTransaction();
    }
    try {
        $ok = $stmt->execute($finalParams);
        $result['execute'] = [
            'ok' => (bool)$ok,
            'error_info' => $stmt->errorInfo(),
            'last_insert_id' => $ok ? (int)$pdo->lastInsertId() : null,
        ];
    } catch (Throwable $e) {
        $result['execute'] = [
            'ok' => false,
            'exception' => $e->getMessage(),
        ];
    } finally {
        if ($dryRun && $pdo->inTransaction()) {
            $pdo->rollBack();
            $result['transaction'] = 'rolled_back';
        }
    }

    $result['success'] = true;
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    $result['error'] = $e->getMessage();
    echo json_encode($result);
}


