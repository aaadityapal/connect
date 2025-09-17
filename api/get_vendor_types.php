<?php
// Database connection
require_once '../config/db_connect.php';

// Check if the request method is GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Query to get distinct vendor types from the database using PDO
        $sql = "SELECT DISTINCT vendor_type FROM hr_vendors ORDER BY vendor_type";
        $stmt = $pdo->query($sql);
        
        // Always return an array, even if empty
        $vendorTypes = [];
        if ($stmt) {
            $vendorTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Ensure we always return an array, even if null
        if (!is_array($vendorTypes)) {
            $vendorTypes = [];
        }
        
        echo json_encode([
            'status' => 'success',
            'vendor_types' => array_values($vendorTypes) // Re-index array to ensure proper JSON format
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'success', // Still return success but with empty array
            'vendor_types' => [],
            'message' => 'No vendor types found or database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?>