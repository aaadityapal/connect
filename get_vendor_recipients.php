<?php
// Get Vendor Recipients
// Fetches vendor records from pm_vendor_registry_master table based on vendor_category_type

header('Content-Type: application/json');

try {
    // Include database connection
    require_once(__DIR__ . '/config/db_connect.php');

    $vendor_category_type = isset($_GET['vendor_category_type']) ? $_GET['vendor_category_type'] : '';

    if (empty($vendor_category_type)) {
        echo json_encode(['success' => false, 'message' => 'vendor_category_type parameter is required']);
        exit;
    }

    // The type parameter can be either vendor_category_type or vendor_type_category
    // First, try to get vendors by vendor_type_category (for custom vendor names like "ABC", "Pig Labour Contractor")
    $query = "SELECT vendor_id as id, vendor_full_name as full_name, vendor_type_category, vendor_category_type
              FROM pm_vendor_registry_master 
              WHERE (vendor_type_category = ? OR vendor_category_type = ?)
              AND vendor_full_name IS NOT NULL
              AND vendor_status = 'active'
              ORDER BY vendor_full_name ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$vendor_category_type, $vendor_category_type]);
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'recipients' => $recipients,
        'count' => count($recipients)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

