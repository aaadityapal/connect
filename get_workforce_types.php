<?php
// get_workforce_types.php
header('Content-Type: application/json');
require_once 'config/db_connect.php';

try {
    // Fetch Vendor Types
    $stmt = $pdo->query("SELECT DISTINCT vendor_type_category FROM pm_vendor_registry_master WHERE vendor_type_category IS NOT NULL AND vendor_type_category != '' ORDER BY vendor_type_category ASC");
    $vendorTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch Labour Types
    // The user specifically requested "permanent" and "temporary" for labours
    // We will fetch all usage to be safe, but primarily we expect 'permanent' and 'temporary' to be in this column based on the request
    $stmt = $pdo->query("SELECT DISTINCT labour_type FROM labour_records WHERE labour_type IS NOT NULL AND labour_type != '' ORDER BY labour_type ASC");
    $labourTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'grouped_types' => [
            'Labours' => array_values(array_unique($labourTypes)),
            'Vendors' => array_values(array_unique($vendorTypes))
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>