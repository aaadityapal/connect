<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'User not authenticated']));
}

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

    // Generate unique filename with fingerprint prefix
    $originalFilename = basename($file['file_name']);
    $fileExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);
    $uniquePrefix = 'fingerprint_' . uniqid() . '_' . time();
    $uniqueFilename = $uniquePrefix . '_' . $originalFilename;
    
    // Set headers for download with unique filename
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $uniqueFilename . '"');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 