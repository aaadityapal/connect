<?php
// Web-based test for vendor types
header('Content-Type: application/json');

require_once 'config/db_connect.php';

try {
    // Check if table exists
    $sql = "SHOW TABLES LIKE 'hr_vendors'";
    $stmt = $pdo->query($sql);
    $tables = $stmt->fetchAll();
    
    if (count($tables) > 0) {
        // Get vendor types
        $sql = "SELECT DISTINCT vendor_type FROM hr_vendors ORDER BY vendor_type";
        $stmt = $pdo->query($sql);
        $vendorTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Vendor types retrieved successfully',
            'vendor_types' => $vendorTypes,
            'count' => count($vendorTypes)
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Table hr_vendors does not exist'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>