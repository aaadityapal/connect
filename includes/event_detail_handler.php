<?php
/**
 * Event Detail Handler
 * This file handles retrieving event details from the database for the enhanced event view modal
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

// Check if this is an AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'get_event_details':
            if (isset($_POST['event_id'])) {
                $eventId = intval($_POST['event_id']);
                $response = getEventDetails($eventId);
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
            break;
            
        default:
            $response = [
                'status' => 'error',
                'message' => 'Invalid action'
            ];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
    }
}

/**
 * Get complete event details from the database
 * @param int $eventId The event ID
 * @return array Response with status, message, and event data
 */
function getEventDetails($eventId) {
    global $conn;
    
    // Initialize response
    $response = [
        'status' => 'error',
        'message' => 'Failed to retrieve event details',
        'event' => null
    ];
    
    try {
        // Get basic event information
        $eventQuery = "SELECT e.event_id, e.title, e.event_date, e.created_by, e.created_at, 
                        e.updated_at, u.username AS created_by_name, s.site_name
                    FROM sv_calendar_events e
                    LEFT JOIN users u ON e.created_by = u.id
                    LEFT JOIN sites s ON e.site_id = s.site_id
                    WHERE e.event_id = ?";
        
        $stmt = $conn->prepare($eventQuery);
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $response['message'] = 'Event not found';
            return $response;
        }
        
        $event = $result->fetch_assoc();
        
        // Get vendors
        $event['vendors'] = getVendorsByEventId($eventId);
        
        // Get company labours
        $event['company_labours'] = getCompanyLaboursByEventId($eventId);
        
        // Get beverages
        $event['beverages'] = getBeveragesByEventId($eventId);
        
        // Get work progress
        $event['work_progress'] = getWorkProgressByEventId($eventId);
        
        // Get inventory items
        $event['inventory'] = getInventoryByEventId($eventId);
        
        $response['status'] = 'success';
        $response['message'] = 'Event details retrieved successfully';
        $response['event'] = $event;
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    return $response;
}

/**
 * Get vendors for an event
 * @param int $eventId The event ID
 * @return array Vendors data
 */
function getVendorsByEventId($eventId) {
    global $conn;
    
    $vendors = [];
    
    // Query to get vendors
    $query = "SELECT vendor_id, vendor_type, vendor_name, contact_number, sequence_number
              FROM sv_event_vendors
              WHERE event_id = ?
              ORDER BY sequence_number";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($vendor = $result->fetch_assoc()) {
        // Get vendor materials
        $vendor['material'] = getVendorMaterial($vendor['vendor_id']);
        
        // Get vendor labourers
        $vendor['labourers'] = getVendorLabourers($vendor['vendor_id']);
        
        $vendors[] = $vendor;
    }
    
    return $vendors;
}

/**
 * Get material data for a vendor
 * @param int $vendorId The vendor ID
 * @return array|null Material data or null if not found
 */
function getVendorMaterial($vendorId) {
    global $conn;
    
    $query = "SELECT material_id, remarks, amount
              FROM sv_vendor_materials
              WHERE vendor_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $vendorId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $material = $result->fetch_assoc();
    
    // Get material images
    $material['materialPictures'] = getMaterialImages($material['material_id']);
    
    // Get bill images
    $material['billPictures'] = getBillImages($material['material_id']);
    
    return $material;
}

/**
 * Get material images
 * @param int $materialId The material ID
 * @return array Material images
 */
function getMaterialImages($materialId) {
    global $conn;
    
    $images = [];
    
    $query = "SELECT image_id, image_path, upload_date
              FROM sv_material_images
              WHERE material_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $materialId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($image = $result->fetch_assoc()) {
        // Extract filename from path
        $image['name'] = basename($image['image_path']);
        $images[] = $image;
    }
    
    return $images;
}

/**
 * Get bill images
 * @param int $materialId The material ID
 * @return array Bill images
 */
function getBillImages($materialId) {
    global $conn;
    
    $images = [];
    
    $query = "SELECT bill_id, image_path, upload_date
              FROM sv_bill_images
              WHERE material_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $materialId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($image = $result->fetch_assoc()) {
        // Extract filename from path
        $image['name'] = basename($image['image_path']);
        $images[] = $image;
    }
    
    return $images;
}

/**
 * Get labourers for a vendor
 * @param int $vendorId The vendor ID
 * @return array Labourers data
 */
function getVendorLabourers($vendorId) {
    global $conn;
    
    $labourers = [];
    
    $query = "SELECT labour_id, labour_name, contact_number, sequence_number, 
                     morning_attendance, evening_attendance
              FROM sv_vendor_labours
              WHERE vendor_id = ?
              ORDER BY sequence_number";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $vendorId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($labourer = $result->fetch_assoc()) {
        // Get labour wages
        $labourer['wages'] = getLabourWages($labourer['labour_id']);
        
        // Add attendance status in a better format
        $labourer['attendance'] = [
            'morning' => $labourer['morning_attendance'],
            'evening' => $labourer['evening_attendance']
        ];
        
        // Add OT information if wages exist
        if ($labourer['wages']) {
            $labourer['overtime'] = [
                'hours' => $labourer['wages']['ot_hours'],
                'minutes' => $labourer['wages']['ot_minutes'],
                'rate' => $labourer['wages']['ot_rate'],
                'total' => $labourer['wages']['total_ot_amount']
            ];
            
            $labourer['travel'] = [
                'mode' => $labourer['wages']['transport_mode'],
                'amount' => $labourer['wages']['travel_amount']
            ];
        }
        
        $labourers[] = $labourer;
    }
    
    return $labourers;
}

/**
 * Get labour wages
 * @param int $labourId The labour ID
 * @return array|null Wages data or null if not found
 */
function getLabourWages($labourId) {
    global $conn;
    
    $query = "SELECT daily_wage, total_day_wage, ot_hours, ot_minutes, ot_rate, 
                     total_ot_amount, transport_mode, travel_amount, grand_total
              FROM sv_labour_wages
              WHERE labour_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $labourId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $wages = $result->fetch_assoc();
    $wages['perDay'] = $wages['daily_wage'];
    $wages['totalDay'] = $wages['total_day_wage'];
    
    return $wages;
}

/**
 * Get company labours for an event
 * @param int $eventId The event ID
 * @return array Company labours data
 */
function getCompanyLaboursByEventId($eventId) {
    global $conn;
    
    $labourers = [];
    
    $query = "SELECT company_labour_id, labour_name, contact_number, sequence_number, 
                     morning_attendance, evening_attendance
              FROM sv_company_labours
              WHERE event_id = ?
              ORDER BY sequence_number";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($labourer = $result->fetch_assoc()) {
        // Get labour wages
        $labourer['wages'] = getCompanyLabourWages($labourer['company_labour_id']);
        
        // Add attendance status in a better format
        $labourer['attendance'] = [
            'morning' => $labourer['morning_attendance'],
            'evening' => $labourer['evening_attendance']
        ];
        
        // Add OT information if wages exist
        if ($labourer['wages']) {
            $labourer['overtime'] = [
                'hours' => $labourer['wages']['ot_hours'],
                'minutes' => $labourer['wages']['ot_minutes'],
                'rate' => $labourer['wages']['ot_rate'],
                'total' => $labourer['wages']['total_ot_amount']
            ];
            
            $labourer['travel'] = [
                'mode' => $labourer['wages']['transport_mode'],
                'amount' => $labourer['wages']['travel_amount']
            ];
        }
        
        $labourers[] = $labourer;
    }
    
    return $labourers;
}

/**
 * Get company labour wages
 * @param int $companyLabourId The company labour ID
 * @return array|null Wages data or null if not found
 */
function getCompanyLabourWages($companyLabourId) {
    global $conn;
    
    $query = "SELECT daily_wage, total_day_wage, ot_hours, ot_minutes, ot_rate, 
                     total_ot_amount, transport_mode, travel_amount, grand_total
              FROM sv_company_wages
              WHERE company_labour_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $companyLabourId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $wages = $result->fetch_assoc();
    $wages['perDay'] = $wages['daily_wage'];
    $wages['totalDay'] = $wages['total_day_wage'];
    
    return $wages;
}

/**
 * Get beverages for an event
 * @param int $eventId The event ID
 * @return array Beverages data
 */
function getBeveragesByEventId($eventId) {
    global $conn;
    
    $beverages = [];
    
    $query = "SELECT beverage_id, beverage_type, beverage_name, amount, sequence_number, created_at
              FROM sv_event_beverages
              WHERE event_id = ?
              ORDER BY sequence_number";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($beverage = $result->fetch_assoc()) {
        $beverages[] = $beverage;
    }
    
    return $beverages;
}

/**
 * Get work progress items for an event
 * @param int $eventId The event ID
 * @return array Work progress data
 */
function getWorkProgressByEventId($eventId) {
    global $conn;
    
    $workProgress = [];
    
    $query = "SELECT work_id, work_category, work_type, work_done, remarks, sequence_number, created_at
              FROM sv_work_progress
              WHERE event_id = ?
              ORDER BY sequence_number";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($work = $result->fetch_assoc()) {
        // Get work media files
        $work['media'] = getWorkProgressMedia($work['work_id']);
        
        $workProgress[] = $work;
    }
    
    return $workProgress;
}

/**
 * Get work progress media
 * @param int $workId The work ID
 * @return array Media files
 */
function getWorkProgressMedia($workId) {
    global $conn;
    
    $media = [];
    
    $query = "SELECT media_id, file_name, file_path, media_type, file_size, sequence_number, created_at
              FROM sv_work_progress_media
              WHERE work_id = ?
              ORDER BY sequence_number";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $workId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($file = $result->fetch_assoc()) {
        $media[] = $file;
    }
    
    return $media;
}

/**
 * Get inventory items for an event
 * @param int $eventId The event ID
 * @return array Inventory data
 */
function getInventoryByEventId($eventId) {
    global $conn;
    
    $inventory = [];
    
    $query = "SELECT inventory_id, inventory_type, material_type, quantity, unit, remarks, sequence_number, created_at
              FROM sv_inventory_items
              WHERE event_id = ?
              ORDER BY sequence_number";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($item = $result->fetch_assoc()) {
        // Get inventory media files
        $item['media'] = getInventoryMedia($item['inventory_id']);
        
        $inventory[] = $item;
    }
    
    return $inventory;
}

/**
 * Get inventory media
 * @param int $inventoryId The inventory ID
 * @return array Media files
 */
function getInventoryMedia($inventoryId) {
    global $conn;
    
    $media = [];
    
    $query = "SELECT media_id, file_name, file_path, media_type, file_size, sequence_number, created_at
              FROM sv_inventory_media
              WHERE inventory_id = ?
              ORDER BY sequence_number";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $inventoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($file = $result->fetch_assoc()) {
        $media[] = $file;
    }
    
    return $media;
} 