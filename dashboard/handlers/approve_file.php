<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$fileId = $data['file_id'] ?? null;

if (!$fileId) {
    echo json_encode(['success' => false, 'message' => 'File ID is required']);
    exit;
}

try {
    $query = "UPDATE substage_files 
              SET status = 'approved', 
                  updated_at = NOW()
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $fileId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'File approved successfully'
        ]);
    } else {
        throw new Exception('Failed to update file status');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error approving file: ' . $e->getMessage()
    ]);
}

$conn->close(); 