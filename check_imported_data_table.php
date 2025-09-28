<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

// Check user role for access control
$allowed_roles = ['HR', 'admin', 'Senior Manager (Studio)', 'Senior Manager (Site)', 'Site Manager'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}

// Database connection
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Check if the table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'imported_excel_data'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;
    
    echo json_encode([
        'success' => true,
        'table_exists' => $tableExists
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error checking table: ' . $e->getMessage()
    ]);
}
?>