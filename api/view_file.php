<?php
session_start();
require_once 'config.php';
require_once 'file_handlers.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['file_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized or invalid request']);
    exit;
}

try {
    $file_id = $_GET['file_id'];
    
    // Get file path
    $query = "SELECT file_path FROM substage_files WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$file_id]);
    $file_path = $stmt->fetchColumn();
    
    if ($file_path) {
        viewFile($file_path);
    } else {
        echo json_encode(['success' => false, 'error' => 'File not found']);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}