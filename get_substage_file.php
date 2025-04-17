<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }
    
    // Get JSON input data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['substage_id'])) {
        throw new Exception('Substage ID is required');
    }
    
    $substageId = (int)$data['substage_id'];
    
    // Get files for the substage
    $stmt = $pdo->prepare("
        SELECT f.id, f.file_name, f.original_name, f.file_path, f.file_type, f.file_size, f.status, 
               f.uploaded_at, u.username as uploaded_by_name
        FROM substage_files f
        LEFT JOIN users u ON f.uploaded_by = u.id
        WHERE f.substage_id = ? AND f.deleted_at IS NULL
        ORDER BY f.uploaded_at DESC
    ");
    
    $stmt->execute([$substageId]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return success response with files
    echo json_encode([
        'success' => true,
        'files' => $files
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>