<?php
require_once 'config/db_connect.php';
require_once 'includes/profile_completion_helper.php';
$id = 1;
$fetchStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$fetchStmt->execute([':id' => $id]);
$userRow = $fetchStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$userRow['role'] = 'Senior Manager';
try {
    $completionPercent = compute_profile_completion_percent($userRow);
    echo "Completion Percent: " . $completionPercent . "\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
