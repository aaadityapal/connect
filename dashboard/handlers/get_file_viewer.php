<?php
// Prevent any HTML output from error reporting
ini_set('display_errors', 0);
error_reporting(0);

// Ensure no whitespace before <?php
header('Content-Type: application/json');

try {
    // Fix the path to db_connect.php - using absolute path
    require_once $_SERVER['DOCUMENT_ROOT'] . '/hr/includes/db_connect.php';

    if (!isset($_GET['file_id'])) {
        throw new Exception('File ID is required');
    }

    $fileId = intval($_GET['file_id']);
    
    $query = "SELECT sf.*, ps.stage_id 
              FROM substage_files sf 
              LEFT JOIN project_substages ps ON sf.substage_id = ps.id 
              WHERE sf.id = ? AND sf.deleted_at IS NULL";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    
    if (!$file) {
        throw new Exception('File not found');
    }

    // Get file extension
    $fileExtension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
    
    // Determine content type
    $contentType = 'application/octet-stream'; // default
    switch ($fileExtension) {
        case 'pdf':
            $contentType = 'application/pdf';
            break;
        case 'jpg':
        case 'jpeg':
            $contentType = 'image/jpeg';
            break;
        case 'png':
            $contentType = 'image/png';
            break;
        // Add more content types as needed
    }
    
    die(json_encode([
        'success' => true,
        'data' => [
            'file_name' => $file['file_name'],
            'file_path' => $file['file_path'],
            'stage_id' => $file['stage_id'],
            'substage_id' => $file['substage_id'],
            'type' => $file['type'],
            'status' => $file['status'],
            'content_type' => $contentType
        ]
    ]));

} catch (Exception $e) {
    die(json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]));
}
?>