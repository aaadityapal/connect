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
    $vendor_query = "
        SELECT DISTINCT vendor_type_category as category
        FROM pm_vendor_registry_master
        WHERE vendor_type_category IS NOT NULL 
        AND vendor_type_category != ''
        ORDER BY vendor_type_category ASC
    ";

    $vendor_stmt = $pdo->prepare($vendor_query);
    $vendor_stmt->execute();
    $vendor_categories = $vendor_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch all unique recipient type categories from line items
    $recipient_query = "
        SELECT DISTINCT recipient_type_category as category
        FROM tbl_payment_entry_line_items_detail
        WHERE recipient_type_category IS NOT NULL 
        AND recipient_type_category != ''
        ORDER BY recipient_type_category ASC
    ";

    $recipient_stmt = $pdo->prepare($recipient_query);
    $recipient_stmt->execute();
    $recipient_categories = $recipient_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Merge and remove duplicates
    $all_categories = array_unique(array_merge($vendor_categories, $recipient_categories));
    $all_categories = array_values($all_categories); // Re-index array
    sort($all_categories); // Sort alphabetically

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Vendor categories fetched successfully',
        'data' => $all_categories
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
