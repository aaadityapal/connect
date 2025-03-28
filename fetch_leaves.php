<?php
require_once 'config/db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $type = isset($_GET['type']) ? $_GET['type'] : 'all';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
    $offset = ($page - 1) * $per_page;
    
    $query = "
        SELECT 
            lr.id,
            lr.user_id,
            lr.start_date,
            lr.end_date,
            lr.reason,
            lr.status,
            lr.created_at,
            u.username,
            u.profile_picture,
            u.designation,
            lt.name as leave_type,
            lt.color_code
        FROM leave_request lr
        JOIN users u ON lr.user_id = u.id
        JOIN leave_types lt ON lr.leave_type = lt.id
    ";
    
    if ($type !== 'all') {
        $query .= " WHERE lr.status = :status";
    }
    
    $query .= " ORDER BY lr.created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    
    if ($type !== 'all') {
        $stmt->bindParam(':status', $type);
    }
    
    $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'leaves' => $leaves,
        'page' => $page,
        'has_more' => count($leaves) === $per_page
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
} 