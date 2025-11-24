<?php
/**
 * Get All Vendor Categories API
 * Fetches all unique vendor and recipient type categories
 * Used by purchase_manager_dashboard.php for filtering by vendor/recipient category
 */

session_start();
require_once __DIR__ . '/config/db_connect.php';

// Set response header
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    // Fetch all unique vendor categories from pm_vendor_registry_master
    // Returns both vendor_type_category (value) and vendor_category_type (display label)
    $vendor_query = "
        SELECT DISTINCT 
            vendor_type_category,
            vendor_category_type
        FROM pm_vendor_registry_master
        WHERE vendor_type_category IS NOT NULL 
        AND vendor_type_category != ''
        ORDER BY vendor_category_type ASC
    ";

    $vendor_stmt = $pdo->prepare($vendor_query);
    $vendor_stmt->execute();
    $categories = $vendor_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Remove duplicates based on vendor_type_category
    $uniqueCategories = array();
    $seenTypes = array();
    
    foreach ($categories as $category) {
        if (!in_array($category['vendor_type_category'], $seenTypes)) {
            $uniqueCategories[] = $category;
            $seenTypes[] = $category['vendor_type_category'];
        }
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Vendor categories fetched successfully',
        'categories' => $uniqueCategories,
        'count' => count($uniqueCategories)
    ]);

} catch (Exception $e) {
    error_log('Get Vendor Categories Error: ' . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    exit;
}
?>
