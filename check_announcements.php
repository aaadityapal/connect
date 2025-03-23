<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

$lastSeen = $_GET['lastSeen'] ?? null;

$query = "SELECT COUNT(*) as count FROM announcements 
          WHERE is_active = 1 
          AND created_at > ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $lastSeen);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

echo json_encode([
    'hasNew' => $data['count'] > 0
]); 