<?php
/**
 * Get All Recipients Grouped by Type (Labour & Material Contractors)
 * Returns all active vendors and labour contractors organized by type
 * Used by purchase_manager_dashboard.php for "Paid To" filter dropdown
 */

header('Content-Type: application/json');

try {
    require_once(__DIR__ . '/config/db_connect.php');

    // Organize by type category
    $groupedContractors = [];

    // ===================================================================
    // FETCH VENDORS - Grouped by vendor_type_category
    // ===================================================================
    $vendorQuery = "SELECT DISTINCT 
                vendor_id,
                vendor_full_name,
                vendor_type_category,
                vendor_category_type
              FROM pm_vendor_registry_master 
              WHERE vendor_full_name IS NOT NULL 
              AND vendor_full_name != ''
              AND vendor_status = 'active'
              ORDER BY 
                vendor_type_category ASC,
                vendor_full_name ASC";

    $vendorStmt = $pdo->prepare($vendorQuery);
    $vendorStmt->execute();
    $vendors = $vendorStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($vendors as $vendor) {
        $typeCategory = $vendor['vendor_type_category'] ?: 'Other Vendors';
        
        if (!isset($groupedContractors[$typeCategory])) {
            $groupedContractors[$typeCategory] = [];
        }

        $contractorData = [
            'id' => $vendor['vendor_id'],
            'name' => $vendor['vendor_full_name'],
            'type' => $typeCategory,
            'recipient_type' => 'vendor'
        ];

        $groupedContractors[$typeCategory][] = $contractorData;
    }

    // ===================================================================
    // FETCH LABOUR RECORDS - Grouped by labour_type
    // ===================================================================
    $labourQuery = "SELECT 
                id,
                full_name,
                labour_type
              FROM labour_records 
              WHERE full_name IS NOT NULL 
              AND full_name != ''
              AND status = 'active'
              ORDER BY 
                labour_type ASC,
                full_name ASC";

    $labourStmt = $pdo->prepare($labourQuery);
    $labourStmt->execute();
    $labours = $labourStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($labours as $labour) {
        // Convert labour_type to display name: permanent -> Permanent Labour, etc.
        $labourType = ucfirst(strtolower($labour['labour_type'])) . ' Labour';
        
        if (!isset($groupedContractors[$labourType])) {
            $groupedContractors[$labourType] = [];
        }

        $contractorData = [
            'id' => $labour['id'],
            'name' => $labour['full_name'],
            'type' => $labourType,
            'recipient_type' => 'labour'
        ];

        $groupedContractors[$labourType][] = $contractorData;
    }

    // Determine icon for groups based on type
    $getIconForType = function($typeCategory) {
        $isLabour = (stripos($typeCategory, 'labour') !== false || 
                    stripos($typeCategory, 'labor') !== false);
        return $isLabour ? '👷' : '📦';
    };

    // Sort groups alphabetically
    ksort($groupedContractors);

    // Format response with groups
    $formattedGroups = [];
    $totalContractors = 0;

    foreach ($groupedContractors as $typeCategory => $contractors) {
        $icon = $getIconForType($typeCategory);
        $displayName = str_replace('_', ' ', $typeCategory);
        $displayName = ucwords($displayName);

        $formattedGroups[] = [
            'type' => $typeCategory,
            'display_name' => $displayName,
            'icon' => $icon,
            'contractors' => $contractors,
            'count' => count($contractors)
        ];

        $totalContractors += count($contractors);
    }

    echo json_encode([
        'success' => true,
        'groups' => $formattedGroups,
        'total_contractors' => $totalContractors
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

