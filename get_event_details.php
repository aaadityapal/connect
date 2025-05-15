<?php
// Start session and check for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Check if user has the 'Site Supervisor' role
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Site Supervisor') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Include database connection
include_once('includes/db_connect.php');

// Check if event_id is provided
if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid event ID']);
    exit();
}

$event_id = intval($_GET['event_id']);
$response = ['success' => false];

try {
    // Fetch the main event details
    $event = fetchEventDetails($conn, $event_id);
    
    if (!$event) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Event not found']);
        exit();
    }
    
    // Fetch vendors data
    $event['vendors'] = fetchVendorDetails($conn, $event_id);
    
    // Fetch vendor labor data
    $event['vendor_labor'] = fetchVendorLabor($conn, $event_id);
    
    // Fetch company labor data
    $event['company_labor'] = fetchCompanyLabor($conn, $event_id);
    
    // Fetch materials data
    $event['materials'] = fetchMaterials($conn, $event_id);
    
    // Fetch work progress data
    $event['work_progress'] = fetchWorkProgress($conn, $event_id);
    
    // Fetch inventory data
    $event['inventory'] = fetchInventory($conn, $event_id);
    
    // Fetch beverages data
    $event['beverages'] = fetchBeverages($conn, $event_id);
    
    // Calculate total expenses
    $event['total_expenses'] = calculateTotalExpenses($event);
    
    $response = [
        'success' => true,
        'event' => $event
    ];
    
} catch (Exception $e) {
    error_log("Error fetching event details: " . $e->getMessage());
    $response = [
        'success' => false,
        'error' => 'An error occurred while fetching event details'
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();

/**
 * Function to fetch main event details
 */
function fetchEventDetails($conn, $event_id) {
    $query = "SELECT e.*, u.username as created_by_name 
              FROM sv_calendar_events e 
              LEFT JOIN users u ON e.created_by = u.id 
              WHERE e.event_id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $event = $result->fetch_assoc();
    $stmt->close();
    
    return $event;
}

/**
 * Function to fetch vendor details
 */
function fetchVendorDetails($conn, $event_id) {
    $vendors = [];
    
    $query = "SELECT * FROM sv_event_vendors WHERE event_id = ? ORDER BY sequence_number ASC";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($vendor = $result->fetch_assoc()) {
        // Fetch materials for this vendor
        $vendor['materials'] = fetchVendorMaterials($conn, $vendor['vendor_id']);
        $vendors[] = $vendor;
    }
    
    $stmt->close();
    return $vendors;
}

/**
 * Function to fetch vendor materials
 */
function fetchVendorMaterials($conn, $vendor_id) {
    $materials = [];
    
    $query = "SELECT * FROM sv_vendor_materials WHERE vendor_id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($material = $result->fetch_assoc()) {
        // Fetch material images
        $material['images'] = fetchMaterialImages($conn, $material['material_id']);
        
        // Fetch bill images
        $material['bills'] = fetchBillImages($conn, $material['material_id']);
        
        $materials[] = $material;
    }
    
    $stmt->close();
    return $materials;
}

/**
 * Function to fetch material images
 */
function fetchMaterialImages($conn, $material_id) {
    $images = [];
    
    $query = "SELECT * FROM sv_material_images WHERE material_id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($image = $result->fetch_assoc()) {
        $images[] = $image;
    }
    
    $stmt->close();
    return $images;
}

/**
 * Function to fetch bill images
 */
function fetchBillImages($conn, $material_id) {
    $bills = [];
    
    $query = "SELECT * FROM sv_bill_images WHERE material_id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($bill = $result->fetch_assoc()) {
        $bills[] = $bill;
    }
    
    $stmt->close();
    return $bills;
}

/**
 * Function to fetch vendor labor
 */
function fetchVendorLabor($conn, $event_id) {
    $labor = [];
    
    // First get all vendors for this event
    $vendors = [];
    $vendorQuery = "SELECT vendor_id FROM sv_event_vendors WHERE event_id = ?";
    $stmt = $conn->prepare($vendorQuery);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $vendors[] = $row['vendor_id'];
    }
    
    $stmt->close();
    
    // Now fetch labor for each vendor
    foreach ($vendors as $vendor_id) {
        $query = "SELECT l.*, v.vendor_name 
                  FROM sv_vendor_labours l 
                  JOIN sv_event_vendors v ON l.vendor_id = v.vendor_id 
                  WHERE l.vendor_id = ? 
                  ORDER BY l.sequence_number ASC";
        
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($laborer = $result->fetch_assoc()) {
            // Fetch wages for this laborer
            $laborer['wages'] = fetchLaborWages($conn, $laborer['labour_id']);
            $labor[] = $laborer;
        }
        
        $stmt->close();
    }
    
    return $labor;
}

/**
 * Function to fetch labor wages
 */
function fetchLaborWages($conn, $labor_id) {
    $query = "SELECT * FROM sv_labour_wages WHERE labour_id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $labor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $wages = $result->fetch_assoc();
    $stmt->close();
    
    return $wages;
}

/**
 * Function to fetch company labor
 */
function fetchCompanyLabor($conn, $event_id) {
    $labor = [];
    
    $query = "SELECT * FROM sv_company_labours WHERE event_id = ? ORDER BY sequence_number ASC";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($laborer = $result->fetch_assoc()) {
        // Fetch wages for this company laborer
        $laborer['wages'] = fetchCompanyWages($conn, $laborer['company_labour_id']);
        $labor[] = $laborer;
    }
    
    $stmt->close();
    return $labor;
}

/**
 * Function to fetch company labor wages
 */
function fetchCompanyWages($conn, $company_labor_id) {
    $query = "SELECT * FROM sv_company_wages WHERE company_labour_id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $company_labor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $wages = $result->fetch_assoc();
    $stmt->close();
    
    return $wages;
}

/**
 * Function to fetch materials directly associated with the event
 */
function fetchMaterials($conn, $event_id) {
    // This function combines vendor materials that were already fetched
    // In a real implementation, you might have additional materials directly associated with the event
    return [];
}

/**
 * Function to fetch work progress data
 */
function fetchWorkProgress($conn, $event_id) {
    $progress = [];
    
    $query = "SELECT * FROM sv_work_progress WHERE event_id = ? ORDER BY sequence_number ASC";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($work = $result->fetch_assoc()) {
        // Fetch media files for this work progress
        $work['media'] = fetchWorkProgressMedia($conn, $work['work_id']);
        $progress[] = $work;
    }
    
    $stmt->close();
    return $progress;
}

/**
 * Function to fetch work progress media
 */
function fetchWorkProgressMedia($conn, $work_id) {
    $media = [];
    
    $query = "SELECT * FROM sv_work_progress_media WHERE work_id = ? ORDER BY sequence_number ASC";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $work_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($file = $result->fetch_assoc()) {
        // Make sure we have the correct file path for each media file
        if (!empty($file['file_name'])) {
            if (empty($file['file_path'])) {
                // First check if the file exists in the calendar events folder
                $calendarDir = 'uploads/calendar_events/work_progress_media/work_' . $work_id . '/';
                $calendarPath = $calendarDir . $file['file_name'];
                
                if (file_exists($calendarPath)) {
                    $file['file_path'] = $calendarPath;
                } else {
                    // If not in calendar folder, try the normal work_progress folder
                    $baseDir = 'uploads/work_progress/';
                    
                    // Check if file is in a subdirectory
                    if (!empty($file['work_id'])) {
                        // If organized by work_id
                        if (is_dir($baseDir . $file['work_id'])) {
                            $file['file_path'] = $baseDir . $file['work_id'] . '/' . $file['file_name'];
                        } else {
                            $file['file_path'] = $baseDir . $file['file_name'];
                        }
                    } else {
                        $file['file_path'] = $baseDir . $file['file_name'];
                    }
                }
            }
        }
        
        $media[] = $file;
    }
    
    $stmt->close();
    return $media;
}

/**
 * Function to fetch inventory data
 */
function fetchInventory($conn, $event_id) {
    $inventory = [];
    
    $query = "SELECT * FROM sv_inventory_items WHERE event_id = ? ORDER BY sequence_number ASC";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($item = $result->fetch_assoc()) {
        // Fetch media files for this inventory item
        $item['media'] = fetchInventoryMedia($conn, $item['inventory_id']);
        $inventory[] = $item;
    }
    
    $stmt->close();
    return $inventory;
}

/**
 * Function to fetch inventory media
 */
function fetchInventoryMedia($conn, $inventory_id) {
    $media = [];
    
    $query = "SELECT * FROM sv_inventory_media WHERE inventory_id = ? ORDER BY sequence_number ASC";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $inventory_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($file = $result->fetch_assoc()) {
        // Make sure we have the correct file path for each media file
        if (!empty($file['file_name'])) {
            if (empty($file['file_path'])) {
                // Construct the full path if it's not already stored
                $baseDir = 'uploads/';
                
                // Choose the correct directory based on media type
                if ($file['media_type'] === 'bill') {
                    $baseDir .= 'inventory_bills/';
                } elseif ($file['media_type'] === 'video') {
                    $baseDir .= 'inventory_videos/';
                } else {
                    $baseDir .= 'inventory_images/';
                }
                
                // Check if file is in a subdirectory
                if (!empty($file['inventory_id'])) {
                    // If organized by inventory_id
                    if (is_dir($baseDir . $file['inventory_id'])) {
                        $file['file_path'] = $baseDir . $file['inventory_id'] . '/' . $file['file_name'];
                    } else {
                        $file['file_path'] = $baseDir . $file['file_name'];
                    }
                } else {
                    $file['file_path'] = $baseDir . $file['file_name'];
                }
            }
        }
        
        $media[] = $file;
    }
    
    $stmt->close();
    return $media;
}

/**
 * Function to fetch beverages data
 */
function fetchBeverages($conn, $event_id) {
    $beverages = [];
    
    $query = "SELECT * FROM sv_event_beverages WHERE event_id = ? ORDER BY sequence_number ASC";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($beverage = $result->fetch_assoc()) {
        $beverages[] = $beverage;
    }
    
    $stmt->close();
    return $beverages;
}

/**
 * Calculate total expenses from all sources
 */
function calculateTotalExpenses($event) {
    $total = 0;
    
    // Add up vendor materials costs
    if (isset($event['vendors'])) {
        foreach ($event['vendors'] as $vendor) {
            if (isset($vendor['materials'])) {
                foreach ($vendor['materials'] as $material) {
                    $total += (float)$material['amount'];
                }
            }
        }
    }
    
    // Add up vendor labor costs
    if (isset($event['vendor_labor'])) {
        foreach ($event['vendor_labor'] as $laborer) {
            if (isset($laborer['wages'])) {
                $total += (float)$laborer['wages']['grand_total'];
            }
        }
    }
    
    // Add up company labor costs
    if (isset($event['company_labor'])) {
        foreach ($event['company_labor'] as $laborer) {
            if (isset($laborer['wages'])) {
                $total += (float)$laborer['wages']['grand_total'];
            }
        }
    }
    
    // Add beverage costs
    if (isset($event['beverages'])) {
        foreach ($event['beverages'] as $beverage) {
            $total += (float)$beverage['amount'];
        }
    }
    
    return $total;
} 