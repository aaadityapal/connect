<?php
session_start();
// Require authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Database connection
require_once 'config/db_connect.php';

// Validate file ID
if (!isset($_GET['file_id']) || !is_numeric($_GET['file_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
    exit;
}

$fileId = intval($_GET['file_id']);

// Get file information from database
$query = "SELECT id, file_path, status 
          FROM project_files 
          WHERE id = $fileId";

$result = mysqli_query($connection, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo json_encode(['success' => false, 'message' => 'File not found']);
    exit;
}

$fileData = mysqli_fetch_assoc($result);

// Check file status
if ($fileData['status'] == 'rejected') {
    echo json_encode(['success' => false, 'message' => 'This file has been rejected and cannot be downloaded']);
    exit;
}

// Check if file physically exists
$filePath = $fileData['file_path'];
$absoluteFilePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $filePath;

if (!file_exists($absoluteFilePath) && !file_exists($filePath)) {
    echo json_encode(['success' => false, 'message' => 'File not found on server']);
    exit;
}

// All checks passed
echo json_encode(['success' => true, 'message' => 'File is available for download']);
?>