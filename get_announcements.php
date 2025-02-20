<?php
require_once 'config/db_connect.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($announcements);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error']);
} 