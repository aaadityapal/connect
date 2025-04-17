<?php
/**
 * API Endpoint for downloading files from stage_files or substage_files
 */

session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get file ID parameter
$stageFileId = isset($_GET['stage_file_id']) ? intval($_GET['stage_file_id']) : 0;
$substageFileId = isset($_GET['substage_file_id']) ? intval($_GET['substage_file_id']) : 0;

if (!$stageFileId && !$substageFileId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing file ID parameter']);
    exit();
}

try {
    $userId = $_SESSION['user_id'];
    $filepath = null;
    $filename = null;
    
    if ($stageFileId) {
        // Get stage file details
        $fileQuery = "SELECT sf.*, ps.project_id 
                     FROM stage_files sf
                     JOIN project_stages ps ON sf.stage_id = ps.id
                     WHERE sf.id = ?";
        
        $stmt = $conn->prepare($fileQuery);
        $stmt->bind_param("i", $stageFileId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('File not found');
        }
        
        $file = $result->fetch_assoc();
        $filepath = $file['file_path'];
        $filename = $file['file_name']; // Use file_name directly
    } else if ($substageFileId) {
        // Get substage file details
        $fileQuery = "SELECT sf.*, ps.stage_id, pst.project_id
                     FROM substage_files sf
                     JOIN project_substages ps ON sf.substage_id = ps.id
                     JOIN project_stages pst ON ps.stage_id = pst.id
                     WHERE sf.id = ?";
        
        $stmt = $conn->prepare($fileQuery);
        $stmt->bind_param("i", $substageFileId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('File not found');
        }
        
        $file = $result->fetch_assoc();
        $filepath = $file['file_path'];
        $filename = $file['file_name']; // Use file_name directly for consistency
    }
    
    // Check if filepath was found
    if (!$filepath) {
        throw new Exception('Invalid file data');
    }
    
    // Check if file path is absolute or relative
    $fullPath = $filepath;
    if (!file_exists($fullPath) && strpos($filepath, 'uploads/') === 0) {
        // If path already includes 'uploads/' prefix, use as is
        $fullPath = $filepath;
    } else if (!file_exists($fullPath)) {
        // Try with uploads/ prefix
        $fullPath = 'uploads/' . $filepath;
    }
    
    // Check if file exists
    if (!file_exists($fullPath)) {
        throw new Exception('File not found on server: ' . $fullPath);
    }
    
    // Set headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($fullPath));
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Read file and output to browser
    readfile($fullPath);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
?>