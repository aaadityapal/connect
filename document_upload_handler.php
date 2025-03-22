<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';
require_once 'error_logger.php';

class DocumentUploadHandler {
    private $db;
    private $uploadDirectory = 'uploads/documents/';
    private $maxFileSize = 10485760; // 10MB
    private $allowedTypes = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png'
    ];

    public function __construct($db) {
        $this->db = $db;
        $this->createUploadDirectories();
    }

    private function createUploadDirectories() {
        $directories = [
            $this->uploadDirectory . 'official/',
            $this->uploadDirectory . 'personal/',
            $this->uploadDirectory . 'temp/'
        ];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    public function handleUpload($file, $documentType, $assignedUserId, $docCategory) {
        try {
            // Validate file
            $this->validateFile($file);

            // Generate unique filename
            $uniqueFilename = $this->generateUniqueFilename($file['name']);
            
            // Determine upload path based on document category
            $uploadPath = $this->uploadDirectory . $docCategory . '/' . $uniqueFilename;

            // Store file
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Failed to move uploaded file');
            }

            // Store in database
            $documentId = $this->saveToDatabase($file, $uniqueFilename, $documentType, $assignedUserId, $docCategory);

            return [
                'success' => true,
                'document_id' => $documentId,
                'message' => 'Document uploaded successfully'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function validateFile($file) {
        // Validate file size (10MB limit)
        $maxSize = 10 * 1024 * 1024; // 10MB in bytes
        if ($file['size'] > $maxSize) {
            throw new Exception('File size exceeds limit (10MB)');
        }

        // Get file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file type based on extension
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        if (!in_array($extension, $allowed_extensions)) {
            throw new Exception('Invalid file type. Allowed types: PDF, JPEG, PNG, DOC, DOCX');
        }

        // Additional check using mime_content_type if available
        if (function_exists('mime_content_type')) {
            $mime_type = mime_content_type($file['tmp_name']);
            $allowed_mimes = [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            if (!in_array($mime_type, $allowed_mimes)) {
                throw new Exception('Invalid file type detected');
            }
        }

        return [
            'extension' => $extension,
            'mime_type' => $file['type']
        ];
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

    private function saveToDatabase($file, $storedFilename, $documentType, $assignedUserId, $docCategory) {
        $table = ($docCategory === 'official') ? 'official_documents' : 'personal_documents';
        
        $sql = "INSERT INTO $table (
            document_name,
            original_filename,
            stored_filename,
            document_type,
            file_size,
            file_type,
            assigned_user_id,
            uploaded_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Failed to prepare database statement');
        }

        $documentName = pathinfo($file['name'], PATHINFO_FILENAME);
        $fileType = $file['type'];
        $fileSize = $file['size'];
        $uploadedBy = $_SESSION['user_id'];

        $stmt->bind_param(
            'sssssiii',
            $documentName,
            $file['name'],
            $storedFilename,
            $documentType,
            $fileSize,
            $fileType,
            $assignedUserId,
            $uploadedBy
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to save document to database');
        }

        return $stmt->insert_id;
    }

    public function handleOfficialDocUpload($file, $documentType, $assignedUserId) {
        return $this->handleUpload($file, $documentType, $assignedUserId, 'official');
    }

    public function handlePersonalDocUpload($file, $documentType, $assignedUserId) {
        return $this->handleUpload($file, $documentType, $assignedUserId, 'personal');
    }
}

// Create a function to log errors
function logUploadError($message, $data = []) {
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
        $log_dir . '/upload_error.log'
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
    if (!isset($_FILES['file']) || !isset($_POST['type']) || !isset($_POST['assigned_user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    try {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) {
            throw new Exception('Database connection failed');
        }

        $uploader = new DocumentUploadHandler($db);
        $docCategory = $_POST['category'] ?? 'official'; // 'official' or 'personal'
        
        if ($docCategory === 'official') {
            $result = $uploader->handleOfficialDocUpload(
                $_FILES['file'],
                $_POST['type'],
                $_POST['assigned_user_id']
            );
        } else {
            $result = $uploader->handlePersonalDocUpload(
                $_FILES['file'],
                $_POST['type'],
                $_POST['assigned_user_id']
            );
        }

        echo json_encode($result);

    } catch (Throwable $e) {
        logUploadError("Upload failed", [
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