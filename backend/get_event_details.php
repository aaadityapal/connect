<?php
/**
 * Get Event Details API
 * Fetches detailed information for a specific event
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once dirname(__FILE__) . '/../config/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User not authenticated'
    ]);
    exit;
}

// Check for required parameters
if (!isset($_GET['event_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required parameter (event_id)'
    ]);
    exit;
}

$eventId = intval($_GET['event_id']);

// Check if event ID is valid
if ($eventId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid event ID'
    ]);
    exit;
}

try {
    // Prepare and execute the query to fetch detailed event information
    $query = "SELECT e.event_id, e.title, e.event_date, e.created_by, u.username as created_by_name, e.created_at,
              (SELECT COUNT(*) FROM sv_event_vendors WHERE event_id = e.event_id) AS vendors_count,
              (SELECT COUNT(*) FROM sv_company_labours WHERE event_id = e.event_id) AS company_labours_count,
              (SELECT COUNT(*) FROM sv_event_beverages WHERE event_id = e.event_id) AS beverages_count,
              (SELECT COUNT(*) FROM sv_work_progress WHERE event_id = e.event_id) AS work_progress_count,
              (SELECT COUNT(*) FROM sv_inventory_items WHERE event_id = e.event_id) AS inventory_count
        FROM sv_calendar_events e
        LEFT JOIN users u ON e.created_by = u.id
              WHERE e.event_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        // Determine event type based on counts
        $eventType = determineEventType($row);
    
        // Get site name (if applicable)
        $siteName = "Main Site"; // Default value
        if (isset($row['site_id'])) {
            $siteQuery = "SELECT site_name FROM sites WHERE site_id = ?";
            $siteStmt = $conn->prepare($siteQuery);
            $siteStmt->bind_param("i", $row['site_id']);
            $siteStmt->execute();
            $siteResult = $siteStmt->get_result();
            if ($siteResult && $siteRow = $siteResult->fetch_assoc()) {
                $siteName = $siteRow['site_name'];
            }
        }
        
        // Fetch vendors if any
    $vendors = [];
        if ($row['vendors_count'] > 0) {
            $vendorQuery = "SELECT v.vendor_id, v.vendor_name, v.vendor_type, v.contact_number, v.sequence_number
        FROM sv_event_vendors v
                          WHERE v.event_id = ?";
            $vendorStmt = $conn->prepare($vendorQuery);
            $vendorStmt->bind_param("i", $eventId);
            $vendorStmt->execute();
            $vendorResult = $vendorStmt->get_result();
    
            while ($vendorResult && $vendorRow = $vendorResult->fetch_assoc()) {
                // Fetch laborers for this vendor
                $vendorId = $vendorRow['vendor_id'];
                $labourers = [];
                
                $labourQuery = "SELECT labour_id, labour_name, contact_number, sequence_number, 
                               morning_attendance, evening_attendance
                        FROM sv_vendor_labours
                        WHERE vendor_id = ?
                        ORDER BY sequence_number";
        
                $labourStmt = $conn->prepare($labourQuery);
                $labourStmt->bind_param("i", $vendorId);
                $labourStmt->execute();
                $labourResult = $labourStmt->get_result();
        
                while ($labourResult && $labourRow = $labourResult->fetch_assoc()) {
                    // Get labor wages
                    $labourId = $labourRow['labour_id'];
                    $wagesQuery = "SELECT daily_wage as perDay, total_day_wage as totalDay, 
                                  ot_hours, ot_minutes, ot_rate, total_ot_amount,
                                  transport_mode, travel_amount, grand_total
                            FROM sv_labour_wages
                            WHERE labour_id = ?";
                    
                    $wagesStmt = $conn->prepare($wagesQuery);
                    $wagesStmt->bind_param("i", $labourId);
                    $wagesStmt->execute();
                    $wagesResult = $wagesStmt->get_result();
            
                    if ($wagesResult && $wagesRow = $wagesResult->fetch_assoc()) {
                        $labourRow['wages'] = $wagesRow;
                        
                        // Add OT information
                        $labourRow['overtime'] = [
                            'hours' => $wagesRow['ot_hours'],
                            'minutes' => $wagesRow['ot_minutes'],
                            'rate' => $wagesRow['ot_rate'],
                            'total' => $wagesRow['total_ot_amount']
                        ];
                        
                        // Add travel information
                        $labourRow['travel'] = [
                            'mode' => $wagesRow['transport_mode'],
                            'amount' => $wagesRow['travel_amount']
                        ];
                    }
                    
                    $labourers[] = $labourRow;
        }
        
                // Add laborers to the vendor data
                $vendorRow['labourers'] = $labourers;
                
                $vendors[] = $vendorRow;
            }
    }
    
        // Fetch company labours if any
        $companyLabours = [];
        if ($row['company_labours_count'] > 0) {
            $labourQuery = "SELECT cl.company_labour_id, cl.labour_name, cl.contact_number, cl.sequence_number, 
                            cl.morning_attendance, cl.evening_attendance
        FROM sv_company_labours cl
                          WHERE cl.event_id = ?";
            $labourStmt = $conn->prepare($labourQuery);
            $labourStmt->bind_param("i", $eventId);
            $labourStmt->execute();
            $labourResult = $labourStmt->get_result();
    
            while ($labourResult && $labourRow = $labourResult->fetch_assoc()) {
                // Get wages for this company labor
                $companyLabourId = $labourRow['company_labour_id'];
                $wagesQuery = "SELECT daily_wage as perDay, total_day_wage as totalDay, 
                              ot_hours, ot_minutes, ot_rate, total_ot_amount,
                              transport_mode, travel_amount, grand_total
                        FROM sv_company_wages
                        WHERE company_labour_id = ?";
    
                $wagesStmt = $conn->prepare($wagesQuery);
                $wagesStmt->bind_param("i", $companyLabourId);
                $wagesStmt->execute();
                $wagesResult = $wagesStmt->get_result();
    
                if ($wagesResult && $wagesRow = $wagesResult->fetch_assoc()) {
                    $labourRow['wages'] = $wagesRow;
                    
                    // Add OT information
                    $labourRow['overtime'] = [
                        'hours' => $wagesRow['ot_hours'],
                        'minutes' => $wagesRow['ot_minutes'],
                        'rate' => $wagesRow['ot_rate'],
                        'total' => $wagesRow['total_ot_amount']
                    ];
                    
                    // Add travel information
                    $labourRow['travel'] = [
                        'mode' => $wagesRow['transport_mode'],
                        'amount' => $wagesRow['travel_amount']
                    ];
                }
                
                $companyLabours[] = $labourRow;
            }
    }
    
        // Fetch beverages if any
    $beverages = [];
        if ($row['beverages_count'] > 0) {
            $beverageQuery = "SELECT b.beverage_id, b.beverage_type, b.beverage_name, b.amount, b.sequence_number
                            FROM sv_event_beverages b
                            WHERE b.event_id = ?";
            $beverageStmt = $conn->prepare($beverageQuery);
            $beverageStmt->bind_param("i", $eventId);
            $beverageStmt->execute();
            $beverageResult = $beverageStmt->get_result();
    
            while ($beverageResult && $beverageRow = $beverageResult->fetch_assoc()) {
                $beverages[] = $beverageRow;
            }
    }
    
        // Fetch work progress if any
        $workProgress = [];
        if ($row['work_progress_count'] > 0) {
            $progressQuery = "SELECT wp.work_id, wp.work_category, wp.work_type, wp.work_done, wp.remarks, wp.sequence_number
        FROM sv_work_progress wp
                             WHERE wp.event_id = ?";
            $progressStmt = $conn->prepare($progressQuery);
            $progressStmt->bind_param("i", $eventId);
            $progressStmt->execute();
            $progressResult = $progressStmt->get_result();
    
            while ($progressResult && $progressRow = $progressResult->fetch_assoc()) {
                // Get media files for this work progress item
                $workId = $progressRow['work_id'];
                $mediaQuery = "SELECT media_id, file_name, file_path, media_type 
            FROM sv_work_progress_media
                              WHERE work_id = ?";
                $mediaStmt = $conn->prepare($mediaQuery);
                $mediaStmt->bind_param("i", $workId);
                $mediaStmt->execute();
                $mediaResult = $mediaStmt->get_result();
        
                $mediaFiles = [];
                while ($mediaResult && $mediaRow = $mediaResult->fetch_assoc()) {
                    $mediaFiles[] = $mediaRow;
        }
        
                // Add media files to the work progress data
                $progressRow['media'] = $mediaFiles;
                
                $workProgress[] = $progressRow;
            }
    }
    
        // Fetch inventory items if any
        $inventory = [];
        if ($row['inventory_count'] > 0) {
            $inventoryQuery = "SELECT ii.inventory_id, ii.inventory_type, ii.material_type, ii.quantity, ii.unit, ii.remarks, ii.sequence_number
        FROM sv_inventory_items ii
                             WHERE ii.event_id = ?";
            $inventoryStmt = $conn->prepare($inventoryQuery);
            $inventoryStmt->bind_param("i", $eventId);
            $inventoryStmt->execute();
            $inventoryResult = $inventoryStmt->get_result();
    
            while ($inventoryResult && $inventoryRow = $inventoryResult->fetch_assoc()) {
                // Get media files for this inventory item
                $inventoryId = $inventoryRow['inventory_id'];
                $mediaQuery = "SELECT media_id, file_name, file_path, media_type 
            FROM sv_inventory_media
                              WHERE inventory_id = ?";
                $mediaStmt = $conn->prepare($mediaQuery);
                $mediaStmt->bind_param("i", $inventoryId);
                $mediaStmt->execute();
                $mediaResult = $mediaStmt->get_result();
        
                $mediaFiles = [];
                while ($mediaResult && $mediaRow = $mediaResult->fetch_assoc()) {
                    $mediaFiles[] = $mediaRow;
        }
        
                // Add media files to the inventory data
                $inventoryRow['media'] = $mediaFiles;
                
                $inventory[] = $inventoryRow;
            }
    }
    
        // Format event data for output
        $event = [
            'event_id' => $row['event_id'],
            'title' => $row['title'],
            'event_date' => $row['event_date'],
            'event_type' => $eventType,
            'site_name' => $siteName,
            'created_by' => $row['created_by'],
            'created_by_name' => $row['created_by_name'] ?? 'Unknown',
            'created_at' => $row['created_at'],
            'vendors' => $vendors,
            'company_labours' => $companyLabours,
            'beverages' => $beverages,
            'work_progress' => $workProgress,
            'inventory' => $inventory,
            'counts' => [
                'vendors' => (int)$row['vendors_count'],
                'company_labours' => (int)$row['company_labours_count'],
                'beverages' => (int)$row['beverages_count'],
                'work_progress' => (int)$row['work_progress_count'],
                'inventory' => (int)$row['inventory_count']
            ]
        ];
        
        // Return success response with event data
        echo json_encode([
            'status' => 'success',
            'message' => 'Event details fetched successfully',
            'event' => $event
        ]);
    } else {
        // Event not found
        echo json_encode([
            'status' => 'error',
            'message' => 'Event not found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch event details: ' . $e->getMessage()
    ]);
    }
    
/**
 * Determine the event type based on associated data
 */
function determineEventType($row) {
    // First check if counts suggest a specific type
    if ((int)$row['inventory_count'] > 0) {
        return 'delivery';
    } elseif ((int)$row['work_progress_count'] > 0) {
        return 'inspection';
    } elseif ((int)$row['vendors_count'] > 0) {
        return 'report';
    }
    
    // If no counts suggest a type, check title keywords
    $title = strtolower($row['title']);
    
    if (strpos($title, 'inspect') !== false || strpos($title, 'safety') !== false || strpos($title, 'check') !== false) {
        return 'inspection';
    } elseif (strpos($title, 'delivery') !== false || strpos($title, 'material') !== false || strpos($title, 'supply') !== false) {
        return 'delivery';
    } elseif (strpos($title, 'meeting') !== false || strpos($title, 'review') !== false || strpos($title, 'planning') !== false) {
        return 'meeting';
    } elseif (strpos($title, 'report') !== false || strpos($title, 'document') !== false) {
        return 'report';
    } elseif (strpos($title, 'issue') !== false || strpos($title, 'problem') !== false || strpos($title, 'fix') !== false) {
        return 'issue';
    }
    
    // Default to meeting if no other type matches
    return 'meeting';
} 