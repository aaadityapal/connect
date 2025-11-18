<?php
// Get Vendor Recipients
// Fetches vendor records from pm_vendor_registry_master table based on type

header('Content-Type: application/json');

try {
    // Include database connection
    require_once(__DIR__ . '/config/db_connect.php');

    $type = isset($_GET['type']) ? $_GET['type'] : '';

    if (empty($type)) {
        echo json_encode(['success' => false, 'message' => 'Type parameter is required']);
        exit;
    }

    // Map the type to the vendor_type_category in database
    $typeMapping = [
        'labour_skilled' => 'labour_skilled',
        'material_steel' => 'material_steel',
        'material_bricks' => 'material_bricks',
        'supplier_cement' => 'supplier_cement',
        'supplier_sand_aggregate' => 'supplier_sand_aggregate'
    ];

    if (!isset($typeMapping[$type])) {
        echo json_encode(['success' => false, 'message' => 'Invalid type parameter']);
        exit;
    }

    $vendorType = $typeMapping[$type];

    // Fetch active vendors of the specified type using PDO
    $query = "SELECT vendor_id AS id, vendor_full_name AS name, vendor_type_category
              FROM pm_vendor_registry_master 
              WHERE LOWER(TRIM(vendor_type_category)) = LOWER(TRIM(:vendorType)) 
              AND vendor_status = 'active' 
              ORDER BY vendor_full_name ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':vendorType' => $vendorType]);
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Remove the vendor_type_category from response (it was just for verification)
    foreach ($recipients as &$recipient) {
        unset($recipient['vendor_type_category']);
    }

    echo json_encode([
        'success' => true,
        'recipients' => $recipients
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
