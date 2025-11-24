<?php
/**
 * Get Custom Vendor Types Handler
 * 
 * Fetches all custom vendor types from the database
 * Returns them grouped by vendor category type
 */

// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection file
require_once '../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    // Fetch custom vendor types from database
    $sql = "SELECT DISTINCT 
                `vendor_type_category`,
                `vendor_category_type`
            FROM `pm_vendor_registry_master`
            WHERE `is_custom` = 1
            AND `vendor_status` = 'active'
            ORDER BY `vendor_category_type` ASC, `vendor_type_category` ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $custom_vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by vendor_category_type
    $grouped_vendors = [];
    foreach ($custom_vendors as $vendor) {
        $category = $vendor['vendor_category_type'];
        if (!isset($grouped_vendors[$category])) {
            $grouped_vendors[$category] = [];
        }
        $grouped_vendors[$category][] = $vendor['vendor_type_category'];
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $grouped_vendors
    ]);
    
} catch (Exception $e) {
    error_log('Get Custom Vendor Types Error: ' . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit();
?>
