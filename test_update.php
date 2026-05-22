<?php
require_once 'config/db_connect.php';
$id = 1; // Assuming employee 1 exists
$role = "Senior Manager";
try {
    $sql = "UPDATE users SET `role` = :role WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':role' => $role, ':id' => $id]);
    echo "Success!\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
