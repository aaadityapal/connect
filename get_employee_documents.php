<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode([]));
}

try {
    $employeeId = $_GET['employee_id'];
    
    $stmt = $pdo->prepare("SELECT documents FROM users WHERE id = ?");
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();

    $documents = json_decode($employee['documents'] ?? '[]', true);
    
    echo json_encode($documents);
} catch (Exception $e) {
    echo json_encode([]);
} 