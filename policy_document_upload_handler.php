<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';
require_once 'error_logger.php';

class PolicyDocumentHandler {
    private $db;
    private $uploadDirectory = 'uploads/documents/policy/';
    private $maxFileSize = 52428800; // 50MB

    public function __construct($db) {
        $this->db = $db;
        $this->createUploadDirectory();
    }

    private function createUploadDirectory() {
        if (!file_exists($this->uploadDirectory)) {
            mkdir($this->uploadDirectory, 0755, true);
        }
    }

    public function handleUpload($file, $policyType, $policyName) {
        try {
            // Validate file size
            if ($file['size'] > $this->maxFileSize) {
                throw new Exception('File size exceeds limit (50MB)');
            }

            // Generate unique filename
            $uniqueFilename = $this->generateUniqueFilename($file['name']);
            
            // Determine upload path
            $uploadPath = $this->uploadDirectory . $uniqueFilename;

            // Store file
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Failed to move uploaded file');
            }

            // Store in database
            $documentId = $this->saveToDatabase($file, $uniqueFilename, $policyType, $policyName);

            return [
                'success' => true,
                'document_id' => $documentId,
                'message' => 'Policy document uploaded successfully'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function generateUniqueFilename($originalFilename) {
        $fileExt = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $timestamp = time();
        $randomString = bin2hex(random_bytes(8));
        $userId = $_SESSION['user_id'];
        
        return sprintf(
            '%s_%s_%s.%s',
            $timestamp,
            $userId,
            $randomString,
            $fileExt
        );
    }

    private function saveToDatabase($file, $storedFilename, $policyType, $policyName) {
        $sql = "INSERT INTO policy_documents (
            policy_name,
            policy_type,
            original_filename,
            stored_filename,
            file_size,
            file_type,
            uploaded_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Failed to prepare database statement: ' . $this->db->error);
        }

        $fileType = $file['type'] ?: 'application/octet-stream';
        $fileSize = $file['size'];
        $uploadedBy = $_SESSION['user_id'];

        $stmt->bind_param(
            'ssssssi',
            $policyName,
            $policyType,
            $file['name'],
            $storedFilename,
            $fileSize,
            $fileType,
            $uploadedBy
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to save document to database: ' . $stmt->error);
        }

        return $stmt->insert_id;
    }
}

// Create a function to log errors
function logPolicyUploadError($message, $data = []) {
    $log_dir = 'upload_logs';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'data' => $data,
        'post' => $_POST,
        'files' => $_FILES,
        'server' => $_SERVER
    ];
    
    error_log(
        date('Y-m-d H:i:s') . " - " . json_encode($log_data, JSON_PRETTY_PRINT) . "\n",
        3,
        $log_dir . '/policy_upload_error.log'
    );
}

// Handle the upload request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check authentication
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }

    // Validate request data
    if (!isset($_FILES['file']) || !isset($_POST['type']) || !isset($_POST['policy_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    try {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) {
            throw new Exception('Database connection failed: ' . $db->connect_error);
        }

        $handler = new PolicyDocumentHandler($db);
        $result = $handler->handleUpload(
            $_FILES['file'],
            $_POST['type'],
            $_POST['policy_name']
        );

        echo json_encode($result);

    } catch (Throwable $e) {
        logPolicyUploadError("Policy upload failed", [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Upload failed. Check error logs for details.'
        ]);
    }
}