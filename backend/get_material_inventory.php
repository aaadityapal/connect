<?php
// get_material_inventory.php - API to get remaining material inventory for a specific site

// Set error reporting to log errors
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Ensure we output proper JSON even if there's an error
header('Content-Type: application/json');

// Include database connection
require_once('../includes/db_connect.php');

// Function to check if a table exists
function tableExists($tableName, $conn) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Check if required tables exist
if (!tableExists('sv_inventory_items', $conn) || !tableExists('sv_calendar_events', $conn)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Required database tables do not exist'
    ]);
    exit;
}

// Check if site is provided
if (!isset($_POST['site']) || empty($_POST['site'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Site name is required'
    ]);
    exit;
}

// Get site name from POST data
$site = $_POST['site'];
$material = isset($_POST['material']) ? $_POST['material'] : null;

try {
    // Use mysqli for the database query
    $sitePattern = "%$site%";
    
    // First, check the actual column names in these tables
    $eventColumns = [];
    $inventoryColumns = [];
    
    // Get column names for sv_calendar_events
    $colResult = $conn->query("SHOW COLUMNS FROM sv_calendar_events");
    while ($col = $colResult->fetch_assoc()) {
        $eventColumns[] = $col['Field'];
    }
    
    // Get column names for sv_inventory_items
    $colResult = $conn->query("SHOW COLUMNS FROM sv_inventory_items");
    while ($col = $colResult->fetch_assoc()) {
        $inventoryColumns[] = $col['Field'];
    }
    
    // Determine the correct column names
    $eventTitleCol = in_array('event_title', $eventColumns) ? 'event_title' : 'title';
    $eventIdCol = in_array('event_id', $eventColumns) ? 'event_id' : 'id';
    
    $invTypeCol = in_array('inventory_type', $inventoryColumns) ? 'inventory_type' : 'type';
    $invMaterialCol = in_array('material_type', $inventoryColumns) ? 'material_type' : 'material';
    $invQuantityCol = in_array('quantity', $inventoryColumns) ? 'quantity' : 'amount';
    $invUnitCol = in_array('unit', $inventoryColumns) ? 'unit' : 'units';
    $invEventIdCol = in_array('event_id', $inventoryColumns) ? 'event_id' : 'calendar_event_id';
    
    // Query to get received and consumed materials for the site
    $sql = "SELECT 
                i.{$invTypeCol} as inventory_type, 
                i.{$invMaterialCol} as material_type, 
                i.{$invQuantityCol} as quantity, 
                i.{$invUnitCol} as unit 
            FROM 
                sv_inventory_items i
            INNER JOIN 
                sv_calendar_events e ON i.{$invEventIdCol} = e.{$eventIdCol} 
            WHERE 
                e.{$eventTitleCol} LIKE ?";
    
    $params = [$sitePattern];
    $types = "s";
    
    // Add material filter if provided
    if ($material) {
        $sql .= " AND i.{$invMaterialCol} = ?";
        $params[] = $material;
        $types .= "s";
    }
    
    // Prepare and execute the statement
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    // Bind parameters dynamically
    $stmt->bind_param($types, ...$params);
    
    // Execute the query
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }
    
    // Get results
    $result = $stmt->get_result();
    $items = [];
    
    // Fetch all results
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    // Close the statement
    $stmt->close();
    
    // Process results to calculate remaining inventory
    $inventory = [];
    
    foreach ($items as $item) {
        $materialType = $item['material_type'];
        $unit = $item['unit'];
        $quantity = (float)$item['quantity'];
        $type = $item['inventory_type'];
        
        if (!isset($inventory[$materialType])) {
            $inventory[$materialType] = [
                'material' => $materialType,
                'received' => 0,
                'consumed' => 0,
                'remaining' => 0,
                'unit' => $unit
            ];
        }
        
        // Add to received or consumed based on inventory type
        if ($type === 'received') {
            $inventory[$materialType]['received'] += $quantity;
        } else if ($type === 'consumed') {
            $inventory[$materialType]['consumed'] += $quantity;
        }
        
        // Calculate remaining
        $inventory[$materialType]['remaining'] = $inventory[$materialType]['received'] - $inventory[$materialType]['consumed'];
    }
    
    // Convert to indexed array
    $inventoryArray = array_values($inventory);
    
    // Return success response with inventory data
    echo json_encode([
        'status' => 'success',
        'inventory' => $inventoryArray
    ]);
    
} catch (Exception $e) {
    // Log any errors
    error_log("Error in get_material_inventory.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?> 