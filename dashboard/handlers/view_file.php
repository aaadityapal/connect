<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['file_id'])) {
    echo json_encode(['success' => false, 'message' => 'File ID is required']);
    exit;
}

$fileId = filter_var($_GET['file_id'], FILTER_SANITIZE_NUMBER_INT);

try {
    $query = "SELECT file_path, file_name FROM substage_files WHERE id = ? AND deleted_at IS NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $fileId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($file = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'data' => [
                'file_path' => $file['file_path'],
                'file_name' => $file['file_name']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'File not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error retrieving file']);
}
$conn->close();