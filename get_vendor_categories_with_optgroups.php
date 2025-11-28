<?php
/**
 * Get All Vendor Categories with Optgroups API
 * Fetches all unique vendor categories grouped by vendor_category_type (optgroup)
 * vendor_type_category = database value to store
 * vendor_category_type = display label for optgroup grouping
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
    // vendor_type_category = the actual value (e.g., 'Labour Contractor', 'Vendor')
    // vendor_category_type = the optgroup label (e.g., 'Contractors', 'Vendors')
    $query = "
        SELECT DISTINCT 
            vendor_type_category,
            vendor_category_type
        FROM pm_vendor_registry_master
        WHERE vendor_type_category IS NOT NULL 
        AND vendor_type_category != ''
        AND vendor_category_type IS NOT NULL
        AND vendor_category_type != ''
        ORDER BY vendor_category_type ASC, vendor_type_category ASC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group categories by optgroup (vendor_category_type)
    $grouped = array();
    foreach ($categories as $category) {
        $optgroup = $category['vendor_category_type'];
        $value = $category['vendor_type_category'];
        
        if (!isset($grouped[$optgroup])) {
            $grouped[$optgroup] = array();
        }
        
        $grouped[$optgroup][] = array(
            'value' => $value,
            'label' => $value
        );
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Vendor categories fetched successfully',
        'categories' => $grouped,
        'count' => count($categories)
    ]);

} catch (Exception $e) {
    error_log('Get Vendor Categories with Optgroups Error: ' . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    exit;
}
?>
