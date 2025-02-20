<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['hr', 'senior_manager'])) {
    http_response_code(403);
    exit('Unauthorized');
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    exit('Bad Request');
}

try {
    $sql = "SELECT ta.*, u.name as employee_name 
            FROM travel_allowances ta 
            JOIN users u ON ta.user_id = u.id 
            WHERE ta.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_GET['id']]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        http_response_code(404);
        exit('Not Found');
    }
    
    header('Content-Type: application/json');
    echo json_encode($request);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Server Error');
} 