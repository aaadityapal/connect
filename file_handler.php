<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'User not authenticated']));
}

$action = $_GET['action'] ?? '';
$fileId = $_GET['file_id'] ?? '';

if (empty($fileId)) {
    die(json_encode(['success' => false, 'message' => 'File ID is required']));
}

try {
    // Get file details
    $stmt = $pdo->prepare("
        SELECT sf.*, 
               ps.stage_id,
               pst.project_id
        FROM substage_files sf
        JOIN project_substages ps ON sf.substage_id = ps.id
        JOIN project_stages pst ON ps.stage_id = pst.id
        WHERE sf.id = ? AND sf.deleted_at IS NULL
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        throw new Exception('File not found');
    }

    $filePath = $file['file_path'];
    
    if (!file_exists($filePath)) {
        throw new Exception('File does not exist on server');
    }

    switch ($action) {
        case 'view':
            // Get file mime type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);

            // Set headers for viewing
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: inline; filename="' . basename($file['file_name']) . '"');
            header('Cache-Control: public, max-age=0');
            readfile($filePath);
            break;

        case 'download':
            // Set headers for download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 