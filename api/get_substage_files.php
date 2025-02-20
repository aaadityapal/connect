<?php
header('Content-Type: application/json');
require_once '../config/db_connect.php';
session_start();

// Enable detailed error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("get_substage_files.php called");

if (!isset($_SESSION['user_id'])) {
    error_log("User not authorized");
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

if (!isset($_GET['substageId'])) {
    error_log("No substageId provided");
    echo json_encode(['success' => false, 'message' => 'Substage ID is required']);
    exit;
}

try {
    $substageId = $_GET['substageId'];
    error_log("Fetching files for substageId: " . $substageId);
    
    // First, verify the substage exists
    $checkQuery = "SELECT id FROM project_substages WHERE id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param('i', $substageId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        error_log("Substage ID $substageId not found in project_substages table");
        echo json_encode(['success' => false, 'message' => 'Substage not found']);
        exit;
    }
    
    // Debug: Print the table structure
    $tableQuery = "DESCRIBE substage_files";
    $tableResult = $conn->query($tableQuery);
    while ($row = $tableResult->fetch_assoc()) {
        error_log("Column: " . $row['Field'] . " Type: " . $row['Type']);
    }
    
    // Debug: Print the actual query
    $query = "SELECT sf.*, u.username as uploaded_by_name 
              FROM substage_files sf
              LEFT JOIN users u ON sf.uploaded_by = u.id
              WHERE sf.substage_id = ? 
              AND sf.deleted_at IS NULL
              ORDER BY sf.created_at DESC";
              
    error_log("Query: " . $query);
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $substageId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Debug: Print the number of rows returned
    error_log("Number of rows found: " . $result->num_rows);
    
    $files = [];
    while ($row = $result->fetch_assoc()) {
        error_log("Found file: " . json_encode($row)); // Debug each file
        $files[] = [
            'id' => $row['id'],
            'name' => $row['file_name'],
            'path' => $row['file_path'],
            'type' => $row['type'],
            'status' => $row['status'] ?? 'pending',
            'uploaded_by' => $row['uploaded_by_name'],
            'uploaded_at' => $row['uploaded_at']
        ];
    }
    
    error_log("Found " . count($files) . " files");
    
    echo json_encode([
        'success' => true,
        'files' => $files,
        'debug' => [
            'substageId' => $substageId,
            'rowCount' => $result->num_rows
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get_substage_files.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching files: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
} 