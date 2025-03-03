<?php
session_start();
require_once 'config.php';

// Check authentication and HR role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    http_response_code(403);
    exit('Unauthorized access');
}

// Validate request
if (!isset($_GET['action']) || !isset($_GET['id'])) {
    http_response_code(400);
    exit('Invalid request');
}

$action = $_GET['action'];
$documentId = (int)$_GET['id'];

try {
    // Connect to database
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get document details
    $stmt = $pdo->prepare("SELECT filename, original_name, file_type FROM hr_documents WHERE id = ? AND status = 'published'");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        exit('Document not found');
    }
    
    // Construct file path (adjust the base path according to your setup)
    $uploadDir = 'uploads/hr_documents/'; // Adjust this path to match your file storage location
    $filePath = $uploadDir . $document['filename'];
    
    // Verify file exists
    if (!file_exists($filePath)) {
        http_response_code(404);
        exit('File not found');
    }
    
    // Handle the action
    switch ($action) {
        case 'view':
        case 'download':
            $fp = fopen($filePath, 'rb');
            
            // File headers
            header("Content-Type: " . $document['file_type']);
            header("Content-Length: " . filesize($filePath));
            
            if ($action === 'download') {
                header('Content-Disposition: attachment; filename="' . $document['original_name'] . '"');
            } else {
                header('Content-Disposition: inline; filename="' . $document['original_name'] . '"');
            }
            
            // Clear output buffer
            ob_end_clean();
            
            // Output file
            fpassthru($fp);
            exit;
            
        default:
            http_response_code(400);
            exit('Invalid action');
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error: ' . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    exit('Server error: ' . $e->getMessage());
} 