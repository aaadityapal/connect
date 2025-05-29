<?php
// Include database connection using a more reliable path
require_once(__DIR__ . '/../includes/db_connect.php');

// Set content type to JSON
header('Content-Type: application/json');

// Get current date in Y-m-d format
$today = date('Y-m-d');

// Initialize response array
$response = [
    'status' => 'success',
    'last_updated' => date('h:i A'),
    'vendor_labors' => [
        'present' => 0,
        'absent' => 0
    ],
    'company_labors' => [
        'present' => 0,
        'absent' => 0
    ],
    'vendor_laborers' => [],
    'company_laborers' => [],
    'vendors' => [], // Added to store vendor details
    'trend' => 0 // Will be calculated based on yesterday's data
];

try {
    // Get the most recent/active event from sv_calendar_events
    $stmt = $pdo->prepare("SELECT event_id FROM sv_calendar_events WHERE event_date = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$today]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        // If no event for today, try to get the most recent event
        $stmt = $pdo->prepare("SELECT event_id FROM sv_calendar_events ORDER BY event_date DESC, created_at DESC LIMIT 1");
        $stmt->execute();
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            throw new Exception("No events found in database");
        }
    }
    
    $eventId = $event['event_id'];
    
    // Get vendor details first
    $stmt = $pdo->prepare("
        SELECT 
            vendor_id,
            event_id,
            vendor_type,
            vendor_name,
            contact_number,
            sequence_number
        FROM 
            sv_event_vendors
        WHERE 
            event_id = ?
        ORDER BY 
            sequence_number ASC, vendor_name ASC
    ");
    
    $stmt->execute([$eventId]);
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a lookup array for vendors and store in response
    $vendorLookup = [];
    foreach ($vendors as $vendor) {
        $vendorId = $vendor['vendor_id'];
        $vendorLookup[$vendorId] = $vendor;
        $vendorLookup[$vendorId]['laborers'] = []; // Initialize laborers array for each vendor
        $vendorLookup[$vendorId]['present_count'] = 0;
        $vendorLookup[$vendorId]['absent_count'] = 0;
        
        // Add to response
        $response['vendors'][] = $vendor;
    }
    
    // Get vendor laborers
    $stmt = $pdo->prepare("
        SELECT 
            l.labour_id, 
            l.vendor_id, 
            l.labour_name, 
            l.contact_number, 
            l.sequence_number,
            l.morning_attendance,
            l.evening_attendance,
            v.vendor_name,
            v.vendor_type,
            v.contact_number as vendor_contact_number
        FROM 
            sv_vendor_labours l
        JOIN 
            sv_event_vendors v ON l.vendor_id = v.vendor_id
        WHERE 
            v.event_id = ? AND
            l.attendance_date = ?
        ORDER BY
            v.sequence_number ASC, v.vendor_name ASC, l.sequence_number ASC
    ");
    
    $stmt->execute([$eventId, $today]);
    $vendorLaborers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process vendor laborers
    foreach ($vendorLaborers as $laborer) {
        // A laborer is present if either morning or evening attendance is 'Y' or 'present'
        if ($laborer['morning_attendance'] === 'Y' || $laborer['morning_attendance'] === 'present' || 
            $laborer['evening_attendance'] === 'Y' || $laborer['evening_attendance'] === 'present') {
            $response['vendor_labors']['present']++;
            
            // Increment present count for this vendor
            if (isset($vendorLookup[$laborer['vendor_id']])) {
                $vendorLookup[$laborer['vendor_id']]['present_count']++;
            }
        } else {
            $response['vendor_labors']['absent']++;
            
            // Increment absent count for this vendor
            if (isset($vendorLookup[$laborer['vendor_id']])) {
                $vendorLookup[$laborer['vendor_id']]['absent_count']++;
            }
        }
        
        // Add to the list of vendor laborers
        $response['vendor_laborers'][] = $laborer;
        
        // Also add to the vendor's laborers list
        if (isset($vendorLookup[$laborer['vendor_id']])) {
            $vendorLookup[$laborer['vendor_id']]['laborers'][] = $laborer;
        }
    }
    
    // Update vendors in response with the laborer counts
    $response['vendors_with_laborers'] = array_values($vendorLookup);
    
    // Get company laborers
    $stmt = $pdo->prepare("
        SELECT 
            company_labour_id,
            event_id,
            labour_name,
            contact_number,
            sequence_number,
            morning_attendance,
            evening_attendance
        FROM 
            sv_company_labours
        WHERE 
            event_id = ? AND
            attendance_date = ?
        ORDER BY
            sequence_number ASC, labour_name ASC
    ");
    
    $stmt->execute([$eventId, $today]);
    $companyLaborers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process company laborers
    foreach ($companyLaborers as $laborer) {
        // A laborer is present if either morning or evening attendance is 'Y' or 'present'
        if ($laborer['morning_attendance'] === 'Y' || $laborer['morning_attendance'] === 'present' || 
            $laborer['evening_attendance'] === 'Y' || $laborer['evening_attendance'] === 'present') {
            $response['company_labors']['present']++;
        } else {
            $response['company_labors']['absent']++;
        }
        
        // Add to the list of company laborers
        $response['company_laborers'][] = $laborer;
    }
    
    // Calculate trend by comparing with yesterday's data
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    // Get yesterday's event ID
    $stmt = $pdo->prepare("SELECT event_id FROM sv_calendar_events WHERE event_date = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$yesterday]);
    $yesterdayEvent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($yesterdayEvent) {
        $yesterdayEventId = $yesterdayEvent['event_id'];
        
        // Get yesterday's vendor laborers count
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN morning_attendance = 'Y' OR morning_attendance = 'present' OR 
                         evening_attendance = 'Y' OR evening_attendance = 'present' 
                    THEN 1 ELSE 0 END) as present
            FROM 
                sv_vendor_labours l
            JOIN 
                sv_event_vendors v ON l.vendor_id = v.vendor_id
            WHERE 
                v.event_id = ? AND
                l.attendance_date = ?
        ");
        
        $stmt->execute([$yesterdayEventId, $yesterday]);
        $yesterdayVendor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get yesterday's company laborers count
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN morning_attendance = 'Y' OR morning_attendance = 'present' OR 
                         evening_attendance = 'Y' OR evening_attendance = 'present' 
                    THEN 1 ELSE 0 END) as present
            FROM 
                sv_company_labours
            WHERE 
                event_id = ? AND
                attendance_date = ?
        ");
        
        $stmt->execute([$yesterdayEventId, $yesterday]);
        $yesterdayCompany = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate total present yesterday
        $yesterdayPresent = intval($yesterdayVendor['present']) + intval($yesterdayCompany['present']);
        $todayPresent = $response['vendor_labors']['present'] + $response['company_labors']['present'];
        
        // Calculate trend percentage
        if ($yesterdayPresent > 0) {
            $trend = round((($todayPresent - $yesterdayPresent) / $yesterdayPresent) * 100);
            $response['trend'] = $trend;
        }
    }
    
    // If no data is found for today, check if there are records from previous days
    if (empty($vendorLaborers) && empty($companyLaborers)) {
        // Find the most recent date with attendance data - using a simpler query
        try {
            // First check vendor laborers
            $stmt = $pdo->prepare("SELECT MAX(attendance_date) as latest_date FROM sv_vendor_labours");
            $stmt->execute();
            $vendorLatestDate = $stmt->fetchColumn();
            
            // Then check company laborers
            $stmt = $pdo->prepare("SELECT MAX(attendance_date) as latest_date FROM sv_company_labours");
            $stmt->execute();
            $companyLatestDate = $stmt->fetchColumn();
            
            // Use the most recent date from either table
            $latestDate = $vendorLatestDate;
            if ($companyLatestDate && (!$latestDate || $companyLatestDate > $latestDate)) {
                $latestDate = $companyLatestDate;
            }
        } catch (PDOException $e) {
            error_log("SQL Error in get_labor_attendance.php: " . $e->getMessage());
            throw new Exception("Database error: " . $e->getMessage());
        }
        
        if ($latestDate) {
            // Get event ID for that date
            $stmt = $pdo->prepare("SELECT event_id FROM sv_calendar_events WHERE event_date = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$latestDate]);
            $latestEvent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($latestEvent) {
                $latestEventId = $latestEvent['event_id'];
                
                // Get vendor details first
                $stmt = $pdo->prepare("
                    SELECT 
                        vendor_id,
                        event_id,
                        vendor_type,
                        vendor_name,
                        contact_number,
                        sequence_number
                    FROM 
                        sv_event_vendors
                    WHERE 
                        event_id = ?
                    ORDER BY 
                        sequence_number ASC, vendor_name ASC
                ");
                
                $stmt->execute([$latestEventId]);
                $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Create a lookup array for vendors and store in response
                $vendorLookup = [];
                foreach ($vendors as $vendor) {
                    $vendorId = $vendor['vendor_id'];
                    $vendorLookup[$vendorId] = $vendor;
                    $vendorLookup[$vendorId]['laborers'] = []; // Initialize laborers array for each vendor
                    $vendorLookup[$vendorId]['present_count'] = 0;
                    $vendorLookup[$vendorId]['absent_count'] = 0;
                    
                    // Add to response
                    $response['vendors'][] = $vendor;
                }
                
                // Get vendor laborers for the latest date
                $stmt = $pdo->prepare("
                    SELECT 
                        l.labour_id, 
                        l.vendor_id, 
                        l.labour_name, 
                        l.contact_number, 
                        l.sequence_number,
                        l.morning_attendance,
                        l.evening_attendance,
                        v.vendor_name,
                        v.vendor_type,
                        v.contact_number as vendor_contact_number
                    FROM 
                        sv_vendor_labours l
                    JOIN 
                        sv_event_vendors v ON l.vendor_id = v.vendor_id
                    WHERE 
                        v.event_id = ? AND
                        l.attendance_date = ?
                    ORDER BY
                        v.sequence_number ASC, v.vendor_name ASC, l.sequence_number ASC
                ");
                
                $stmt->execute([$latestEventId, $latestDate]);
                $vendorLaborers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Process vendor laborers
                $response['vendor_labors']['present'] = 0;
                $response['vendor_labors']['absent'] = 0;
                $response['vendor_laborers'] = [];
                
                foreach ($vendorLaborers as $laborer) {
                    if ($laborer['morning_attendance'] === 'Y' || $laborer['morning_attendance'] === 'present' || 
                        $laborer['evening_attendance'] === 'Y' || $laborer['evening_attendance'] === 'present') {
                        $response['vendor_labors']['present']++;
                        
                        // Increment present count for this vendor
                        if (isset($vendorLookup[$laborer['vendor_id']])) {
                            $vendorLookup[$laborer['vendor_id']]['present_count']++;
                        }
                    } else {
                        $response['vendor_labors']['absent']++;
                        
                        // Increment absent count for this vendor
                        if (isset($vendorLookup[$laborer['vendor_id']])) {
                            $vendorLookup[$laborer['vendor_id']]['absent_count']++;
                        }
                    }
                    
                    $response['vendor_laborers'][] = $laborer;
                    
                    // Also add to the vendor's laborers list
                    if (isset($vendorLookup[$laborer['vendor_id']])) {
                        $vendorLookup[$laborer['vendor_id']]['laborers'][] = $laborer;
                    }
                }
                
                // Update vendors in response with the laborer counts
                $response['vendors_with_laborers'] = array_values($vendorLookup);
                
                // Get company laborers for the latest date
                $stmt = $pdo->prepare("
                    SELECT 
                        company_labour_id,
                        event_id,
                        labour_name,
                        contact_number,
                        sequence_number,
                        morning_attendance,
                        evening_attendance
                    FROM 
                        sv_company_labours
                    WHERE 
                        event_id = ? AND
                        attendance_date = ?
                    ORDER BY
                        sequence_number ASC, labour_name ASC
                ");
                
                $stmt->execute([$latestEventId, $latestDate]);
                $companyLaborers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Process company laborers
                $response['company_labors']['present'] = 0;
                $response['company_labors']['absent'] = 0;
                $response['company_laborers'] = [];
                
                foreach ($companyLaborers as $laborer) {
                    if ($laborer['morning_attendance'] === 'Y' || $laborer['morning_attendance'] === 'present' || 
                        $laborer['evening_attendance'] === 'Y' || $laborer['evening_attendance'] === 'present') {
                        $response['company_labors']['present']++;
                    } else {
                        $response['company_labors']['absent']++;
                    }
                    
                    $response['company_laborers'][] = $laborer;
                }
                
                // Update the response with a note
                $response['note'] = "Showing data from {$latestDate} (latest available)";
            }
        }
    }
    
    // Return success response
    echo json_encode($response);
    
} catch (Exception $e) {
    // Return error response with proper status code
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'details' => 'Error in get_labor_attendance.php: ' . $e->getMessage()
    ];
    
    // Log the error
    error_log('Error in get_labor_attendance.php: ' . $e->getMessage());
    
    echo json_encode($response);
} 