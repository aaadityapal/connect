<?php
// Enable error logging to file
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Clear any existing output
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

try {
    require_once __DIR__ . '/../../config/db_connect.php';
    
    if (!isset($_GET['file_id'])) {
        throw new Exception('File ID is required');
    }

    $fileId = intval($_GET['file_id']);
    
    // Log the query parameters
    error_log("Attempting to fetch file ID: " . $fileId);
    
    // Verify PDO connection
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Database connection not available');
    }
    
    $stmt = $pdo->prepare("SELECT id, substage_id, file_name, type, status, uploaded_by, uploaded_at 
                          FROM substage_files 
                          WHERE id = ? AND deleted_at IS NULL");
                          
    if (!$stmt) {
        throw new Exception('Failed to prepare statement');
    }
    
    $success = $stmt->execute([$fileId]);
    if (!$success) {
        throw new Exception('Failed to execute query');
    }
    
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        throw new Exception('File not found');
    }

    // Log successful query
    error_log("Successfully retrieved file data for ID: " . $fileId);
    
    ob_end_clean();
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    echo json_encode([
        'success' => true,
        'file' => $file
    ], JSON_THROW_ON_ERROR);
    exit;

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    ob_end_clean();
    header('Content-Type: application/json');
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ], JSON_THROW_ON_ERROR);
    exit;
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    ob_end_clean();
    header('Content-Type: application/json');
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_THROW_ON_ERROR);
    exit;
} 
