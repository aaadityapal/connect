<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['substage_id'])) {
    echo json_encode(['success' => false, 'error' => 'Substage ID is required']);
    exit;
}

try {
    $substage_id = $_GET['substage_id'];
    
    $query = "SELECT 
        sf.*,
        u1.username as uploaded_by_name,
        u2.username as last_modified_by_name
    FROM substage_files sf
    LEFT JOIN users u1 ON sf.uploaded_by = u1.id
    LEFT JOIN users u2 ON sf.last_modified_by = u2.id
    WHERE sf.substage_id = ? AND sf.deleted_at IS NULL
    ORDER BY sf.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$substage_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'files' => $files
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}