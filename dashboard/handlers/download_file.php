<?php
// Ensure user is authenticated
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

require_once '../../config/db_connect.php';

if (!isset($_GET['file_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'File ID is required']);
    exit;
}

$fileId = filter_var($_GET['file_id'], FILTER_SANITIZE_NUMBER_INT);

try {
    $query = "SELECT file_path, file_name FROM substage_files WHERE id = ? AND deleted_at IS NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $fileId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($file = $result->fetch_assoc()) {
        // Get the absolute path to the project root
        $projectRoot = realpath(__DIR__ . '/../../');
        $filePath = $projectRoot . '/' . $file['file_path'];
        $fileName = $file['file_name'];
        
        // Log the paths for debugging
        error_log("File ID: " . $fileId);
        error_log("Database file_path: " . $file['file_path']);
        error_log("Constructed file path: " . $filePath);
        error_log("File exists check: " . (file_exists($filePath) ? 'true' : 'false'));
        
        if (file_exists($filePath)) {
            // Set appropriate headers for file download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            // Output file content
            readfile($filePath);
            exit;
        } else {
            // Log directory contents for debugging
            $dir = dirname($filePath);
            if (is_dir($dir)) {
                error_log("Directory contents of: " . $dir);
                foreach (scandir($dir) as $item) {
                    error_log("- " . $item);
                }
            } else {
                error_log("Directory does not exist: " . $dir);
            }
        }
    }
    
    // If we get here, file was not found
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'File not found',
        'debug' => [
            'file_id' => $fileId,
            'file_path' => isset($file['file_path']) ? $file['file_path'] : null,
            'absolute_path' => isset($filePath) ? $filePath : null,
            'script_location' => __DIR__
        ]
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Error downloading file: ' . $e->getMessage(),
        'debug' => [
            'error_type' => get_class($e),
            'error_line' => $e->getLine(),
            'error_file' => $e->getFile()
        ]
    ]);
}

$conn->close(); 