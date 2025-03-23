<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Helper function to log errors
function logActionError($message, $data = []) {
    $log_dir = 'logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'data' => $data,
        'get' => $_GET,
        'post' => $_POST,
        'session' => [
            'user_id' => $_SESSION['user_id'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ]
    ];
    
    error_log(
        date('Y-m-d H:i:s') . " - " . json_encode($log_data, JSON_PRETTY_PRINT) . "\n",
        3,
        $log_dir . '/document_actions.log'
    );
}

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($db->connect_error) {
        throw new Exception('Database connection failed: ' . $db->connect_error);
    }
    
    // Get action parameters
    $action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : null);
    $docId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : null);
    $docType = isset($_GET['type']) ? $_GET['type'] : (isset($_POST['type']) ? $_POST['type'] : null);
    
    if (!$action || !$docId || !$docType) {
        throw new Exception('Missing required parameters');
    }
    
    // Determine which table and directory to use based on document type
    switch ($docType) {
        case 'official':
            $table = 'official_documents';
            $storedFileField = 'stored_filename';
            $originalNameField = 'original_filename';
            $fileTypeField = 'file_type';
            $uploadDirectory = 'uploads/documents/official/';
            break;
        
        case 'personal':
            $table = 'personal_documents';
            $storedFileField = 'stored_filename';
            $originalNameField = 'original_filename';
            $fileTypeField = 'file_type';
            $uploadDirectory = 'uploads/documents/personal/';
            break;
            
        case 'policy':
            $table = 'policy_documents';
            $storedFileField = 'stored_filename';
            $originalNameField = 'original_filename';
            $fileTypeField = 'file_type';
            $uploadDirectory = 'uploads/documents/policy/';
            break;
            
        default:
            throw new Exception('Invalid document type');
    }
    
    // Check for document existence
    $stmt = $db->prepare("SELECT $storedFileField, $originalNameField, $fileTypeField FROM $table WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare database query: ' . $db->error);
    }
    
    $stmt->bind_param('i', $docId);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows === 0) {
        logActionError("Document not found", [
            'action' => $action,
            'document_id' => $docId,
            'document_type' => $docType,
            'table' => $table
        ]);
        throw new Exception('Document not found');
    }
    
    $stmt->bind_result($storedFilename, $originalFilename, $fileType);
    $stmt->fetch();
    $stmt->close();
    
    $filePath = $uploadDirectory . $storedFilename;
    
    if (!file_exists($filePath)) {
        logActionError("File not found on disk", [
            'action' => $action,
            'document_id' => $docId,
            'document_type' => $docType,
            'file_path' => $filePath
        ]);
        throw new Exception('File not found on server');
    }
    
    // Handle different actions
    switch ($action) {
        case 'view':
            // Send appropriate headers for file viewing
            header('Content-Type: ' . $fileType);
            header('Content-Disposition: inline; filename="' . $originalFilename . '"');
            header('Cache-Control: public, max-age=0');
            readfile($filePath);
            exit;
            
        case 'download':
            // Send appropriate headers for file download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $originalFilename . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
            readfile($filePath);
            exit;
            
        case 'delete':
            // Check if user has permission to delete
            // HR can delete any document, regular users can only delete their personal documents
            if ($_SESSION['role'] !== 'HR') {
                // For regular users, check if this is their document
                if ($docType === 'personal' || $docType === 'official') {
                    $userCheck = $db->prepare("SELECT id FROM $table WHERE id = ? AND assigned_user_id = ?");
                    $userCheck->bind_param('ii', $docId, $_SESSION['user_id']);
                    $userCheck->execute();
                    $userCheck->store_result();
                    
                    if ($userCheck->num_rows === 0) {
                        throw new Exception('You do not have permission to delete this document');
                    }
                    $userCheck->close();
                } else {
                    throw new Exception('You do not have permission to delete this document');
                }
            }
            
            // Delete the file
            if (file_exists($filePath) && !unlink($filePath)) {
                throw new Exception('Failed to delete file from server');
            }
            
            // Delete database record
            $deleteStmt = $db->prepare("DELETE FROM $table WHERE id = ?");
            $deleteStmt->bind_param('i', $docId);
            
            if (!$deleteStmt->execute()) {
                throw new Exception('Failed to delete document record: ' . $deleteStmt->error);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if (!headers_sent()) {
        http_response_code(400);
        header('Content-Type: application/json');
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    logActionError($e->getMessage(), [
        'action' => $action ?? null,
        'document_id' => $docId ?? null,
        'document_type' => $docType ?? null,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} 