<?php
require_once 'config/db_connect.php';
$id = 1;
$fetchStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$fetchStmt->execute([':id' => $id]);
$data = $fetchStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$data['id'] = $id;
$data['role'] = 'Senior Manager (Studio)';

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

$params[':id'] = $id;

try {
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo "Success!\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
