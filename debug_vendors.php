<?php
// Debug script to check vendor data
header('Content-Type: application/json');

require_once(__DIR__ . '/config/db_connect.php');

try {
    // Check if table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'pm_vendor_registry_master'")->fetch();
    
    if (!$checkTable) {
        echo json_encode([
            'error' => 'Table pm_vendor_registry_master does not exist',
            'tables' => $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN)
        ]);
        exit;
    }

    // Get total vendors
    $totalVendors = $pdo->query("SELECT COUNT(*) FROM pm_vendor_registry_master")->fetchColumn();
    
    // Get vendors by type
    $query = $pdo->query("SELECT vendor_type_category, COUNT(*) as count FROM pm_vendor_registry_master GROUP BY vendor_type_category");
    $vendorsByType = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all vendors
    $allVendors = $pdo->query("SELECT vendor_id, vendor_full_name, vendor_type_category, vendor_status FROM pm_vendor_registry_master LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'total_vendors' => $totalVendors,
        'vendors_by_type' => $vendorsByType,
        'sample_vendors' => $allVendors
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
