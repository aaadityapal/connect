<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];

$query = "
    UPDATE notifications 
    SET read_status = 1 
    WHERE user_id = :userId AND read_status = 0
";

$stmt = $pdo->prepare($query);
$success = $stmt->execute(['userId' => $userId]);

echo json_encode(['success' => $success]); 