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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid payload'
    ]);
    exit();
}

$employeeId = isset($data['id']) ? (int)$data['id'] : 0;
if ($employeeId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid employee id'
    ]);
    exit();
}

$allowedFields = [
    'username', 'email', 'phone', 'gender', 'dob',
    'address', 'city', 'state', 'country', 'postal_code',
    'bio', 'languages', 'role', 'department', 'designation',
    'reporting_manager',
    'status', 'joining_date', 'education', 'education_background',
    'work_experience', 'work_experiences', 'skills', 'bank_details',
    'marital_status', 'nationality'
];

$updates = [];
$params = [];

foreach ($allowedFields as $field) {
    if (array_key_exists($field, $data)) {
        $updates[] = "`{$field}` = :{$field}";
        $value = $data[$field];
        if (is_string($value)) {
            $value = trim($value);
        }
        $params[":{$field}"] = $value;
    }
}

if (empty($updates)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No valid fields to update'
    ]);
    exit();
}

$params[':id'] = $employeeId;

try {
    require_once '../../../config/db_connect.php';

    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Employee profile updated successfully'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update employee profile'
    ]);
}
