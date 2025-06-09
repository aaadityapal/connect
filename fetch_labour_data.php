<?php
// Include database connection
require_once 'config/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get request data
$request_data = json_decode(file_get_contents('php://input'), true);
$selected_site = isset($request_data['site']) ? $request_data['site'] : 'all';

// Get today's date
$today = date('Y-m-d');

try {
    // Prepare the queries
    if ($selected_site === 'all') {
        // Query for all company labours present today
        $company_labour_query = "
            SELECT 
                cl.*,
                ce.title as event_title
            FROM sv_company_labours cl
            LEFT JOIN sv_calendar_events ce ON cl.event_id = ce.event_id
            WHERE (cl.morning_attendance = 1 OR cl.evening_attendance = 1)
            AND cl.attendance_date = ?
            AND cl.is_deleted = 0
            ORDER BY cl.labour_name ASC
        ";
        $company_params = [$today];
        
        // Query for all vendor labours present today
        $vendor_labour_query = "
            SELECT 
                vl.*,
                ev.vendor_name,
                ce.title as event_title
            FROM sv_vendor_labours vl
            LEFT JOIN sv_event_vendors ev ON vl.vendor_id = ev.vendor_id
            LEFT JOIN sv_calendar_events ce ON ev.event_id = ce.event_id
            WHERE (vl.morning_attendance = 1 OR vl.evening_attendance = 1)
            AND vl.attendance_date = ?
            AND vl.is_deleted = 0
            ORDER BY vl.labour_name ASC
        ";
        $vendor_params = [$today];
    } else {
        // Extract site number from the site ID (e.g., 'site1' becomes '1')
        $site_number = filter_var($selected_site, FILTER_SANITIZE_NUMBER_INT);
        
        // Query for company labours present today for the specific site
        $company_labour_query = "
            SELECT 
                cl.*,
                ce.title as event_title
            FROM sv_company_labours cl
            LEFT JOIN sv_calendar_events ce ON cl.event_id = ce.event_id
            WHERE (cl.morning_attendance = 1 OR cl.evening_attendance = 1)
            AND cl.attendance_date = ?
            AND cl.is_deleted = 0
            AND ce.title LIKE ?
            ORDER BY cl.labour_name ASC
        ";
        $company_params = [$today, "%Site $site_number%"];
        
        // Query for vendor labours present today for the specific site
        $vendor_labour_query = "
            SELECT 
                vl.*,
                ev.vendor_name,
                ce.title as event_title
            FROM sv_vendor_labours vl
            LEFT JOIN sv_event_vendors ev ON vl.vendor_id = ev.vendor_id
            LEFT JOIN sv_calendar_events ce ON ev.event_id = ce.event_id
            WHERE (vl.morning_attendance = 1 OR vl.evening_attendance = 1)
            AND vl.attendance_date = ?
            AND vl.is_deleted = 0
            AND ce.title LIKE ?
            ORDER BY vl.labour_name ASC
        ";
        $vendor_params = [$today, "%Site $site_number%"];
    }
    
    // Execute company labour query
    $stmt = $pdo->prepare($company_labour_query);
    $stmt->execute($company_params);
    $company_labours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Execute vendor labour query
    $stmt = $pdo->prepare($vendor_labour_query);
    $stmt->execute($vendor_params);
    $vendor_labours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return the data as JSON
    echo json_encode([
        'success' => true,
        'company_labours' => $company_labours,
        'vendor_labours' => $vendor_labours
    ]);
    
} catch (PDOException $e) {
    // Log the error
    error_log("Error fetching labour data: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching labour data',
        'error' => $e->getMessage(),
        'company_labours' => [],
        'vendor_labours' => []
    ]);
}
?> 