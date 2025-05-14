<?php
/**
 * This file fetches detailed information about a specific calendar event
 * Used for displaying event details in modals or detailed views
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
include_once('../includes/db_connect.php');

// Set the content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not authenticated'
    ]);
    exit;
}

// Get parameters from request
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$event_date = isset($_GET['event_date']) ? $_GET['event_date'] : '';

// Validate parameters - we need either event_id or event_date
if ($event_id <= 0 && empty($event_date)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Must provide either event_id or event_date parameter'
    ]);
    exit;
}

try {
    // Case 1: If event_date is provided, fetch all events for that date
    if (!empty($event_date)) {
        // Validate date format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid date format. Use YYYY-MM-DD'
            ]);
            exit;
        }
        
        // Get all events for the specified date
        $stmt = $conn->prepare("
            SELECT 
                e.event_id
            FROM sv_calendar_events e
            WHERE e.event_date = ?
            ORDER BY e.created_at ASC
        ");
        
        $stmt->bind_param("s", $event_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'date' => $event_date,
                    'events' => []
                ]
            ]);
            exit;
        }
        
        // Process all events for this date
        $all_events = [];
        while ($row = $result->fetch_assoc()) {
            $event_id = $row['event_id'];
            
            // For each event_id, fetch its complete details (reuse existing code below)
            // The event data structure will be built inside the individual event processing code
            $event_data = getEventDetailsById($conn, $event_id);
            $all_events[] = $event_data;
        }
        
        // Return all events for this date
        echo json_encode([
            'status' => 'success',
            'data' => [
                'date' => $event_date,
                'events' => $all_events
            ]
        ]);
        exit;
    }
    
    // Case 2: Fetch a specific event by ID
    $event_data = getEventDetailsById($conn, $event_id);
    
    // Return success response with event data
    echo json_encode([
        'status' => 'success',
        'data' => $event_data
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log('Error in get_event_details.php: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

/**
 * Function to get complete event details by ID
 * Extracts the event data fetching logic for reuse
 */
function getEventDetailsById($conn, $event_id) {
    // 1. Get basic event information
    $stmt = $conn->prepare("
        SELECT 
            e.event_id, 
            e.title, 
            e.event_date, 
            e.created_by,
            e.created_at,
            e.updated_at,
            u.username as creator_name
        FROM sv_calendar_events e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.event_id = ?
    ");
    
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Handle the case where event ID is invalid
        return null;
    }
    
    $event = $result->fetch_assoc();
    
    // Format dates
    $event['event_date_formatted'] = date('F j, Y', strtotime($event['event_date']));
    $event['created_at_formatted'] = date('F j, Y h:i A', strtotime($event['created_at']));
    $event['updated_at_formatted'] = date('F j, Y h:i A', strtotime($event['updated_at']));
    
    // 2. Get vendors information
    $vendors = [];
    $stmt = $conn->prepare("
        SELECT 
            v.vendor_id,
            v.vendor_type,
            v.vendor_name,
            v.contact_number,
            v.sequence_number
        FROM sv_event_vendors v
        WHERE v.event_id = ?
        ORDER BY v.sequence_number ASC
    ");
    
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $vendor_id = $row['vendor_id'];
        $vendor = $row;
        
        // 2.1 Get vendor materials
        $materials = [];
        $stmt_materials = $conn->prepare("
            SELECT 
                m.material_id,
                m.remarks,
                m.amount
            FROM sv_vendor_materials m
            WHERE m.vendor_id = ?
        ");
        
        $stmt_materials->bind_param("i", $vendor_id);
        $stmt_materials->execute();
        $result_materials = $stmt_materials->get_result();
        
        while ($material_row = $result_materials->fetch_assoc()) {
            $material_id = $material_row['material_id'];
            $material = $material_row;
            
            // Get material images
            $material_images = [];
            $stmt_images = $conn->prepare("
                SELECT image_id, image_path, upload_date
                FROM sv_material_images
                WHERE material_id = ?
            ");
            
            $stmt_images->bind_param("i", $material_id);
            $stmt_images->execute();
            $result_images = $stmt_images->get_result();
            
            while ($image_row = $result_images->fetch_assoc()) {
                $material_images[] = $image_row;
            }
            
            // Get bill images
            $bill_images = [];
            $stmt_bills = $conn->prepare("
                SELECT bill_id, image_path, upload_date
                FROM sv_bill_images
                WHERE material_id = ?
            ");
            
            $stmt_bills->bind_param("i", $material_id);
            $stmt_bills->execute();
            $result_bills = $stmt_bills->get_result();
            
            while ($bill_row = $result_bills->fetch_assoc()) {
                $bill_images[] = $bill_row;
            }
            
            $material['material_images'] = $material_images;
            $material['bill_images'] = $bill_images;
            $materials[] = $material;
        }
        
        // 2.2 Get vendor labours
        $labours = [];
        $stmt_labours = $conn->prepare("
            SELECT 
                l.labour_id,
                l.labour_name,
                l.contact_number,
                l.sequence_number,
                l.morning_attendance,
                l.evening_attendance,
                w.daily_wage,
                w.total_day_wage,
                w.ot_hours,
                w.ot_minutes,
                w.ot_rate,
                w.total_ot_amount,
                w.transport_mode,
                w.travel_amount,
                w.grand_total
            FROM sv_vendor_labours l
            LEFT JOIN sv_labour_wages w ON l.labour_id = w.labour_id
            WHERE l.vendor_id = ?
            ORDER BY l.sequence_number ASC
        ");
        
        $stmt_labours->bind_param("i", $vendor_id);
        $stmt_labours->execute();
        $result_labours = $stmt_labours->get_result();
        
        while ($labour_row = $result_labours->fetch_assoc()) {
            $labours[] = $labour_row;
        }
        
        $vendor['materials'] = $materials;
        $vendor['labours'] = $labours;
        $vendors[] = $vendor;
    }
    
    // 3. Get company labours
    $company_labours = [];
    $stmt = $conn->prepare("
        SELECT 
            cl.company_labour_id,
            cl.labour_name,
            cl.contact_number,
            cl.sequence_number,
            cl.morning_attendance,
            cl.evening_attendance,
            cw.daily_wage,
            cw.total_day_wage,
            cw.ot_hours,
            cw.ot_minutes,
            cw.ot_rate,
            cw.total_ot_amount,
            cw.transport_mode,
            cw.travel_amount,
            cw.grand_total
        FROM sv_company_labours cl
        LEFT JOIN sv_company_wages cw ON cl.company_labour_id = cw.company_labour_id
        WHERE cl.event_id = ?
        ORDER BY cl.sequence_number ASC
    ");
    
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $company_labours[] = $row;
    }
    
    // 4. Get beverages
    $beverages = [];
    $stmt = $conn->prepare("
        SELECT 
            beverage_id,
            beverage_type,
            beverage_name,
            amount,
            sequence_number,
            created_at
        FROM sv_event_beverages
        WHERE event_id = ?
        ORDER BY sequence_number ASC
    ");
    
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $beverages[] = $row;
    }
    
    // 5. Get work progress
    $work_progress = [];
    $stmt = $conn->prepare("
        SELECT 
            wp.work_id,
            wp.work_category,
            wp.work_type,
            wp.work_done,
            wp.remarks,
            wp.sequence_number,
            wp.created_at
        FROM sv_work_progress wp
        WHERE wp.event_id = ?
        ORDER BY wp.sequence_number ASC
    ");
    
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $work_id = $row['work_id'];
        $work = $row;
        
        // Get work media files
        $media_files = [];
        $stmt_media = $conn->prepare("
            SELECT 
                media_id,
                file_name,
                file_path,
                media_type,
                file_size,
                sequence_number,
                created_at
            FROM sv_work_progress_media
            WHERE work_id = ?
            ORDER BY sequence_number ASC
        ");
        
        $stmt_media->bind_param("i", $work_id);
        $stmt_media->execute();
        $result_media = $stmt_media->get_result();
        
        while ($media_row = $result_media->fetch_assoc()) {
            $media_files[] = $media_row;
        }
        
        $work['media_files'] = $media_files;
        $work_progress[] = $work;
    }
    
    // 6. Get inventory items
    $inventory_items = [];
    $stmt = $conn->prepare("
        SELECT 
            ii.inventory_id,
            ii.inventory_type,
            ii.material_type,
            ii.quantity,
            ii.unit,
            ii.remarks,
            ii.sequence_number,
            ii.created_at
        FROM sv_inventory_items ii
        WHERE ii.event_id = ?
        ORDER BY ii.sequence_number ASC
    ");
    
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $inventory_id = $row['inventory_id'];
        $inventory_item = $row;
        
        // Get inventory media files
        $media_files = [];
        $stmt_media = $conn->prepare("
            SELECT 
                media_id,
                file_name,
                file_path,
                media_type,
                file_size,
                sequence_number,
                created_at
            FROM sv_inventory_media
            WHERE inventory_id = ?
            ORDER BY sequence_number ASC
        ");
        
        $stmt_media->bind_param("i", $inventory_id);
        $stmt_media->execute();
        $result_media = $stmt_media->get_result();
        
        while ($media_row = $result_media->fetch_assoc()) {
            $media_files[] = $media_row;
        }
        
        $inventory_item['media_files'] = $media_files;
        $inventory_items[] = $inventory_item;
    }
    
    // 7. Calculate wages summary
    $wages_summary = [
        'vendor_labour_base_wages' => 0,
        'vendor_labour_ot' => 0,
        'company_labour_base_wages' => 0,
        'company_labour_ot' => 0,
        'travel_expenses' => 0,
        'beverage_expenses' => 0,
        'total_wages' => 0
    ];
    
    // Calculate vendor labour wages
    foreach ($vendors as $vendor) {
        foreach ($vendor['labours'] as $labour) {
            $wages_summary['vendor_labour_base_wages'] += floatval($labour['total_day_wage'] ?? 0);
            $wages_summary['vendor_labour_ot'] += floatval($labour['total_ot_amount'] ?? 0);
            $wages_summary['travel_expenses'] += floatval($labour['travel_amount'] ?? 0);
        }
    }
    
    // Calculate company labour wages
    foreach ($company_labours as $labour) {
        $wages_summary['company_labour_base_wages'] += floatval($labour['total_day_wage'] ?? 0);
        $wages_summary['company_labour_ot'] += floatval($labour['total_ot_amount'] ?? 0);
        $wages_summary['travel_expenses'] += floatval($labour['travel_amount'] ?? 0);
    }
    
    // Calculate beverage expenses
    foreach ($beverages as $beverage) {
        $wages_summary['beverage_expenses'] += floatval($beverage['amount'] ?? 0);
    }
    
    // Calculate total wages
    $wages_summary['total_wages'] = 
        $wages_summary['vendor_labour_base_wages'] + 
        $wages_summary['vendor_labour_ot'] + 
        $wages_summary['company_labour_base_wages'] + 
        $wages_summary['company_labour_ot'] + 
        $wages_summary['travel_expenses'] + 
        $wages_summary['beverage_expenses'];
    
    // 8. Assemble the complete event data
    return [
        'event' => $event,
        'vendors' => $vendors,
        'company_labours' => $company_labours,
        'beverages' => $beverages,
        'work_progress' => $work_progress,
        'inventory_items' => $inventory_items,
        'wages_summary' => $wages_summary
    ];
} 