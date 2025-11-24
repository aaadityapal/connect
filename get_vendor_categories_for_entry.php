<?php
// Get Vendor Categories by Type for Entry Dropdown
// Fetches from pm_vendor_registry_master and groups by vendor_type_category

header('Content-Type: application/json');

try {
    require_once(__DIR__ . '/config/db_connect.php');

    // First check if table exists and has data
    $checkQuery = "SELECT COUNT(*) as count FROM pm_vendor_registry_master";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute();
    $tableCheck = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($tableCheck['count'] == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No vendors found in pm_vendor_registry_master table',
            'debug' => 'Table exists but is empty'
        ]);
        exit;
    }

    // Fetch all distinct vendor type categories grouped by their vendor category type
    $query = "SELECT DISTINCT vendor_category_type, vendor_type_category 
              FROM pm_vendor_registry_master 
              WHERE vendor_type_category IS NOT NULL 
              AND vendor_type_category != ''
              AND vendor_type_category != 'NULL'
              AND vendor_category_type IS NOT NULL
              AND vendor_category_type != ''
              ORDER BY vendor_category_type ASC, vendor_type_category ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by vendor_category_type (Material Supplier, Labour Contractor, Material Contractor)
    // and collect vendor_type_category values (material_steel, Pig Labour Contractor, etc)
    $groupedData = [];
    foreach ($results as $row) {
        $categoryType = $row['vendor_category_type'];      // Material Supplier, Labour Contractor, Material Contractor
        $typeCategory = $row['vendor_type_category'];      // material_steel, Pig Labour Contractor, supplier_cement, etc
        
        if (!isset($groupedData[$categoryType])) {
            $groupedData[$categoryType] = [];
        }
        // Only add if not already there
        if (!in_array($typeCategory, $groupedData[$categoryType])) {
            $groupedData[$categoryType][] = $typeCategory;
        }
    }

    echo json_encode([
        'success' => true,
        'categories' => $groupedData,
        'debug' => 'Found ' . count($results) . ' category records',
        'keys' => array_keys($groupedData)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
