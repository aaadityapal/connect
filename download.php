<?php
session_start();
require_once 'config.php';

// Verify user is HR
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get file path and type from URL
$file_path = isset($_GET['file']) ? urldecode($_GET['file']) : '';
$file_type = isset($_GET['type']) ? $_GET['type'] : '';

// Validate file type
$allowed_types = [
    'offer_letter', 'increment_letter', 'resume', 'aadhar_card', 
    'pan_card', 'matriculation', 'intermediate', 'graduation', 
    'post_graduation'
];

if (empty($file_path) || !in_array($file_type, $allowed_types)) {
    die('Invalid file request');
}

// Security check: Ensure the file is within the uploads directory
$uploads_dir = realpath(__DIR__ . '/uploads');
$requested_file = realpath($file_path);

if ($requested_file === false || strpos($requested_file, $uploads_dir) !== 0) {
    die('Invalid file path');
}

// Check if file exists
if (!file_exists($requested_file)) {
    die('File not found');
}

// Get file information
$file_info = pathinfo($requested_file);
$file_name = basename($requested_file);

// Set content type based on file extension
$extension = strtolower($file_info['extension']);
switch ($extension) {
    case 'pdf':
        $content_type = 'application/pdf';
        break;
    case 'doc':
        $content_type = 'application/msword';
        break;
    case 'docx':
        $content_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        break;
    case 'jpg':
    case 'jpeg':
        $content_type = 'image/jpeg';
        break;
    case 'png':
        $content_type = 'image/png';
        break;
    default:
        $content_type = 'application/octet-stream';
}

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers for download
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Length: ' . filesize($requested_file));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Output file contents
readfile($requested_file);
exit();
