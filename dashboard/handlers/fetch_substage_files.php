<?php
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

// Define valid status constants
define('VALID_STATUSES', [
    'pending',
    'sent_for_approval',
    'in_review',
    'approved',
    'rejected',
    'completed'
]);

if (!isset($_GET['substage_id'])) {
    echo json_encode(['success' => false, 'message' => 'Substage ID is required']);
    exit;
}

$substageId = filter_var($_GET['substage_id'], FILTER_SANITIZE_NUMBER_INT);

try {
    // Query matching your table structure
    $query = "SELECT 
                id,
                file_name,
                file_path,
                type,
                status,
                uploaded_by,
                uploaded_at,
                created_at,
                updated_at
              FROM substage_files 
              WHERE substage_id = ? 
              AND deleted_at IS NULL 
              ORDER BY created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $substageId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $files = [];
    while ($row = $result->fetch_assoc()) {
        // Ensure status is set, default to 'pending' if null
        $row['status'] = $row['status'] ?: 'pending';
        $files[] = $row;
    }

    // Add debug information
    $debug = [
        'substage_id' => $substageId,
        'num_files' => count($files),
        'query' => $query
    ];

    echo json_encode([
        'success' => true,
        'data' => $files,
        'debug' => $debug
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching files: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}

$conn->close(); 