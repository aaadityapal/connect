<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

if (!isset($_GET['id']) || !ctype_digit((string)$_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid employee id'
    ]);
    exit();
}

$employeeId = (int)$_GET['id'];

try {
    require_once '../../../config/db_connect.php';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $employeeId]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Employee not found'
        ]);
        exit();
    }

    // Hide sensitive fields from API output
    $sensitiveFields = [
        'password', 'password_hash', 'pass',
        'reset_token', 'reset_token_expires_at',
        'remember_token', 'otp', 'otp_code',
        'two_factor_secret', 'two_factor_recovery_codes'
    ];

    foreach ($sensitiveFields as $field) {
        if (array_key_exists($field, $employee)) {
            unset($employee[$field]);
        }
    }

    echo json_encode([
        'success' => true,
        'employee' => $employee
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load employee profile'
    ]);
}
