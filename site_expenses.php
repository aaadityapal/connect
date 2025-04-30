<?php
session_start();
require_once 'config/db_connect.php';
date_default_timezone_set('Asia/Kolkata');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user details
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

// Get current time and date in IST
$current_time = date("h:i:s A"); // 12-hour format with seconds and AM/PM
$current_date = date("l, F j, Y");

// Get greeting based on IST hour
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour >= 12 && $hour < 16) {
    $greeting = "Good Afternoon";
} elseif ($hour >= 16 && $hour < 20) {
    $greeting = "Good Evening";
} else {
    $greeting = "Good Night";
}

// AJAX endpoint to fetch vendor and labour data for autocomplete
if (isset($_GET['action']) && $_GET['action'] === 'get_vendor_labour_data') {
    $search_term = isset($_GET['term']) ? $conn->real_escape_string($_GET['term']) : '';
    $data_type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';
    
    // Prepare response array
    $response = [
        'success' => false,
        'data' => []
    ];
    
    if ($data_type === 'vendor') {
        // Get unique vendors with their details from previous entries
        $vendor_query = "SELECT DISTINCT v.vendor_type, v.vendor_name, v.contact, v.work_description 
                         FROM site_vendors v 
                         JOIN site_updates s ON v.site_update_id = s.id 
                         WHERE s.user_id = ? AND 
                         (v.vendor_name LIKE ? OR v.vendor_type LIKE ?)
                         ORDER BY v.vendor_name
                         LIMIT 10";
        
        $stmt = $conn->prepare($vendor_query);
        $search_param = "%" . $search_term . "%";
        $stmt->bind_param("iss", $user_id, $search_param, $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $response['data'][] = [
                'type' => $row['vendor_type'],
                'name' => $row['vendor_name'],
                'contact' => $row['contact'],
                'work_description' => $row['work_description']
            ];
        }
        
        $response['success'] = true;
    } 
    elseif ($data_type === 'vendor_labour') {
        $vendor_id = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
        $vendor_type = isset($_GET['vendor_type']) ? $conn->real_escape_string($_GET['vendor_type']) : '';
        $vendor_name = isset($_GET['vendor_name']) ? $conn->real_escape_string($_GET['vendor_name']) : '';
        
        // Get labours based on vendor type/name or direct labour name search
        $labour_query = "SELECT DISTINCT vl.labour_name, vl.mobile, vl.wage 
                         FROM vendor_labours vl
                         JOIN site_vendors v ON vl.vendor_id = v.id
                         JOIN site_updates s ON v.site_update_id = s.id
                         WHERE s.user_id = ? AND 
                         ((v.vendor_type = ? OR v.vendor_name = ?) OR vl.labour_name LIKE ?)
                         ORDER BY vl.labour_name
                         LIMIT 10";
        
        $stmt = $conn->prepare($labour_query);
        $search_param = "%" . $search_term . "%";
        $stmt->bind_param("isss", $user_id, $vendor_type, $vendor_name, $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $response['data'][] = [
                'name' => $row['labour_name'],
                'mobile' => $row['mobile'],
                'wage' => floatval($row['wage'])
            ];
        }
        
        $response['success'] = true;
    }
    elseif ($data_type === 'company_labour') {
        // Get combined list of all labours (both company and vendor)
        
        // First get company labours
        $combined_labours = [];
        $labour_query = "SELECT DISTINCT cl.labour_name, cl.mobile, cl.wage, 'company' as source
                         FROM company_labours cl
                         JOIN site_updates s ON cl.site_update_id = s.id
                         WHERE s.user_id = ? AND cl.labour_name LIKE ?";
        
        // Then get vendor labours with UNION
        $labour_query .= " UNION 
                         SELECT DISTINCT vl.labour_name, vl.mobile, vl.wage, 'vendor' as source
                         FROM vendor_labours vl
                         JOIN site_vendors v ON vl.vendor_id = v.id
                         JOIN site_updates s ON v.site_update_id = s.id
                         WHERE s.user_id = ? AND vl.labour_name LIKE ?
                         ORDER BY labour_name
                         LIMIT 20";
        
        $stmt = $conn->prepare($labour_query);
        $search_param = "%" . $search_term . "%";
        $stmt->bind_param("isis", $user_id, $search_param, $user_id, $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $response['data'][] = [
                'name' => $row['labour_name'],
                'mobile' => $row['mobile'],
                'wage' => floatval($row['wage']),
                'source' => $row['source']
            ];
        }
        
        $response['success'] = true;
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// AJAX endpoint to get site update details
if (isset($_GET['action']) && $_GET['action'] === 'get_site_update_details') {
    // Validate and sanitize inputs
    $site_name = isset($_GET['site_name']) ? $conn->real_escape_string($_GET['site_name']) : '';
    $update_date = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : '';
    
    // Convert display date format (d M Y) to database format (Y-m-d)
    $date_obj = DateTime::createFromFormat('d M Y', $update_date);
    $db_date = $date_obj ? $date_obj->format('Y-m-d') : '';
    
    // Prepare response array
    $response = [
        'success' => false,
        'message' => '',
        'data' => []
    ];
    
    if (empty($site_name) || empty($db_date)) {
        $response['message'] = 'Invalid site name or date.';
        echo json_encode($response);
        exit();
    }
    
    // Get the site update ID
    $update_query = "SELECT * FROM site_updates WHERE user_id = ? AND site_name = ? AND update_date = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("iss", $user_id, $site_name, $db_date);
    $stmt->execute();
    $update_result = $stmt->get_result();
    
    if ($update_result->num_rows === 0) {
        $response['message'] = 'Site update not found.';
        echo json_encode($response);
        exit();
    }
    
    $update_data = $update_result->fetch_assoc();
    $site_update_id = $update_data['id'];
    
    // Get vendors and their labours
    $vendors = [];
    $vendors_query = "SELECT * FROM site_vendors WHERE site_update_id = ?";
    $stmt = $conn->prepare($vendors_query);
    $stmt->bind_param("i", $site_update_id);
    $stmt->execute();
    $vendors_result = $stmt->get_result();
    
    while ($vendor = $vendors_result->fetch_assoc()) {
        $vendor_id = $vendor['id'];
        
        // Get labours for this vendor
        $labours = [];
        $labours_query = "SELECT * FROM vendor_labours WHERE vendor_id = ?";
        $stmt_labours = $conn->prepare($labours_query);
        $stmt_labours->bind_param("i", $vendor_id);
        $stmt_labours->execute();
        $labours_result = $stmt_labours->get_result();
        
        while ($labour = $labours_result->fetch_assoc()) {
            $labours[] = [
                'name' => $labour['labour_name'],
                'mobile' => $labour['mobile'],
                'attendance' => $labour['attendance'],
                'ot_hours' => floatval($labour['ot_hours']),
                'wage' => floatval($labour['wage']),
                'ot_amount' => floatval($labour['ot_amount']),
                'total' => floatval($labour['total_amount'])
            ];
        }
        
        $vendors[] = [
            'type' => $vendor['vendor_type'],
            'name' => $vendor['vendor_name'],
            'contact' => $vendor['contact'],
            'work_description' => $vendor['work_description'],
            'labours' => $labours
        ];
    }
    
    // Get company labours
    $company_labours = [];
    $company_labours_query = "SELECT * FROM company_labours WHERE site_update_id = ?";
    $stmt = $conn->prepare($company_labours_query);
    $stmt->bind_param("i", $site_update_id);
    $stmt->execute();
    $company_labours_result = $stmt->get_result();
    
    while ($labour = $company_labours_result->fetch_assoc()) {
        $company_labours[] = [
            'id' => $labour['id'],
            'name' => $labour['labour_name'],
            'mobile' => $labour['mobile'],
            'attendance' => $labour['attendance'],
            'ot_hours' => floatval($labour['ot_hours']),
            'ot_wages' => floatval($labour['ot_wages']),
            'wage' => floatval($labour['wage']),
            'ot_amount' => floatval($labour['ot_amount']),
            'total' => floatval($labour['total_amount'])
        ];
    }
    
    // Get work progress with files
    $work_progress = [];
    $work_progress_query = "SELECT * FROM work_progress WHERE site_update_id = ?";
    $stmt = $conn->prepare($work_progress_query);
    $stmt->bind_param("i", $site_update_id);
    $stmt->execute();
    $work_progress_result = $stmt->get_result();
    
    while ($progress = $work_progress_result->fetch_assoc()) {
        $progress_id = $progress['id'];
        
        // Get media files for this work progress item
        $files = [];
        $files_query = "SELECT * FROM work_progress_files WHERE work_progress_id = ?";
        $stmt_files = $conn->prepare($files_query);
        $stmt_files->bind_param("i", $progress_id);
        $stmt_files->execute();
        $files_result = $stmt_files->get_result();
        
        while ($file = $files_result->fetch_assoc()) {
            $files[] = [
                'path' => $file['file_path'],
                'type' => $file['file_type'] // 'image' or 'video'
            ];
        }
        
        $work_progress[] = [
            'work_type' => $progress['work_type'],
            'status' => $progress['status'],
            'category' => $progress['category'],
            'remarks' => $progress['remarks'],
            'files' => $files
        ];
    }
    
    // Get inventory items with files
    $inventory = [];
    $inventory_query = "SELECT * FROM inventory WHERE site_update_id = ?";
    $stmt = $conn->prepare($inventory_query);
    $stmt->bind_param("i", $site_update_id);
    $stmt->execute();
    $inventory_result = $stmt->get_result();
    
    while ($item = $inventory_result->fetch_assoc()) {
        $inventory_id = $item['id'];
        
        // Get media files for this inventory item
        $files = [];
        $files_query = "SELECT * FROM inventory_files WHERE inventory_id = ?";
        $stmt_files = $conn->prepare($files_query);
        $stmt_files->bind_param("i", $inventory_id);
        $stmt_files->execute();
        $files_result = $stmt_files->get_result();
        
        while ($file = $files_result->fetch_assoc()) {
            $files[] = [
                'path' => $file['file_path'],
                'type' => $file['file_type'] // 'image' or 'video'
            ];
        }
        
        $inventory[] = [
            'material' => $item['material'],
            'quantity' => floatval($item['quantity']),
            'unit' => $item['unit'],
            'standard_values' => $item['standard_values'],
            'files' => $files
        ];
    }
    
    // Get expenses
    $expenses = [
        'total_wages' => floatval($update_data['total_wages']),
        'total_misc_expenses' => floatval($update_data['total_misc_expenses']),
        'grand_total' => floatval($update_data['grand_total'])
    ];
    
    // Prepare final response
    $response = [
        'success' => true,
        'data' => [
            'site_name' => $site_name,
            'update_date' => $update_date,
            'update_details' => $update_data['update_details'],
            'vendors' => $vendors,
            'company_labours' => $company_labours,
            'work_progress' => $work_progress,
            'inventory' => $inventory,
            'expenses' => $expenses
        ]
    ];
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// AJAX endpoint to get site update for editing
if (isset($_GET['action']) && $_GET['action'] === 'get_site_update_for_edit') {
    // Validate and sanitize inputs
    $site_name = isset($_GET['site_name']) ? $conn->real_escape_string($_GET['site_name']) : '';
    $update_date = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : '';
    
    // Convert display date format (d M Y) to database format (Y-m-d)
    $date_obj = DateTime::createFromFormat('d M Y', $update_date);
    $db_date = $date_obj ? $date_obj->format('Y-m-d') : '';
    
    // Prepare response array
    $response = [
        'success' => false,
        'message' => '',
        'data' => []
    ];
    
    if (empty($site_name) || empty($db_date)) {
        $response['message'] = 'Invalid site name or date.';
        echo json_encode($response);
        exit();
    }
    
    // Get the site update ID
    $update_query = "SELECT * FROM site_updates WHERE user_id = ? AND site_name = ? AND update_date = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("iss", $user_id, $site_name, $db_date);
    $stmt->execute();
    $update_result = $stmt->get_result();
    
    if ($update_result->num_rows === 0) {
        $response['message'] = 'Site update not found.';
        echo json_encode($response);
        exit();
    }
    
    $update_data = $update_result->fetch_assoc();
    $site_update_id = $update_data['id'];
    
    // Use the same logic as the view endpoint but include DB IDs for editing
    // Get vendors and their labours with DB IDs
    $vendors = [];
    $vendors_query = "SELECT * FROM site_vendors WHERE site_update_id = ?";
    $stmt = $conn->prepare($vendors_query);
    $stmt->bind_param("i", $site_update_id);
    $stmt->execute();
    $vendors_result = $stmt->get_result();
    
    while ($vendor = $vendors_result->fetch_assoc()) {
        $vendor_id = $vendor['id'];
        
        // Get labours for this vendor
        $labours = [];
        $labours_query = "SELECT * FROM vendor_labours WHERE vendor_id = ?";
        $stmt_labours = $conn->prepare($labours_query);
        $stmt_labours->bind_param("i", $vendor_id);
        $stmt_labours->execute();
        $labours_result = $stmt_labours->get_result();
        
        while ($labour = $labours_result->fetch_assoc()) {
            $labours[] = [
                'id' => $labour['id'],
                'name' => $labour['labour_name'],
                'mobile' => $labour['mobile'],
                'attendance' => $labour['attendance'],
                'ot_hours' => floatval($labour['ot_hours']),
                'wage' => floatval($labour['wage']),
                'ot_amount' => floatval($labour['ot_amount']),
                'total' => floatval($labour['total_amount'])
            ];
        }
        
        $vendors[] = [
            'id' => $vendor_id,
            'type' => $vendor['vendor_type'],
            'name' => $vendor['vendor_name'],
            'contact' => $vendor['contact'],
            'work_description' => $vendor['work_description'],
            'labours' => $labours
        ];
    }
    
    // Get company labours with DB IDs
    $company_labours = [];
    $company_labours_query = "SELECT * FROM company_labours WHERE site_update_id = ?";
    $stmt = $conn->prepare($company_labours_query);
    $stmt->bind_param("i", $site_update_id);
    $stmt->execute();
    $company_labours_result = $stmt->get_result();
    
    while ($labour = $company_labours_result->fetch_assoc()) {
        $company_labours[] = [
            'id' => $labour['id'],
            'name' => $labour['labour_name'],
            'mobile' => $labour['mobile'],
            'attendance' => $labour['attendance'],
            'ot_hours' => floatval($labour['ot_hours']),
            'ot_wages' => floatval($labour['ot_wages']),
            'wage' => floatval($labour['wage']),
            'ot_amount' => floatval($labour['ot_amount']),
            'total' => floatval($labour['total_amount'])
        ];
    }
    
    // Get work progress with files and DB IDs
    $work_progress = [];
    $work_progress_query = "SELECT * FROM work_progress WHERE site_update_id = ?";
    $stmt = $conn->prepare($work_progress_query);
    $stmt->bind_param("i", $site_update_id);
    $stmt->execute();
    $work_progress_result = $stmt->get_result();
    
    while ($progress = $work_progress_result->fetch_assoc()) {
        $progress_id = $progress['id'];
        
        // Get media files for this work progress item
        $files = [];
        $files_query = "SELECT * FROM work_progress_files WHERE work_progress_id = ?";
        $stmt_files = $conn->prepare($files_query);
        $stmt_files->bind_param("i", $progress_id);
        $stmt_files->execute();
        $files_result = $stmt_files->get_result();
        
        while ($file = $files_result->fetch_assoc()) {
            $files[] = [
                'id' => $file['id'],
                'path' => $file['file_path'],
                'type' => $file['file_type'] // 'image' or 'video'
            ];
        }
        
        $work_progress[] = [
            'id' => $progress_id,
            'work_type' => $progress['work_type'],
            'status' => $progress['status'],
            'category' => $progress['category'],
            'remarks' => $progress['remarks'],
            'files' => $files
        ];
    }
    
    // Get inventory items with files and DB IDs
    $inventory = [];
    $inventory_query = "SELECT * FROM inventory WHERE site_update_id = ?";
    $stmt = $conn->prepare($inventory_query);
    $stmt->bind_param("i", $site_update_id);
    $stmt->execute();
    $inventory_result = $stmt->get_result();
    
    while ($item = $inventory_result->fetch_assoc()) {
        $inventory_id = $item['id'];
        
        // Get media files for this inventory item
        $files = [];
        $files_query = "SELECT * FROM inventory_files WHERE inventory_id = ?";
        $stmt_files = $conn->prepare($files_query);
        $stmt_files->bind_param("i", $inventory_id);
        $stmt_files->execute();
        $files_result = $stmt_files->get_result();
        
        while ($file = $files_result->fetch_assoc()) {
            $files[] = [
                'id' => $file['id'],
                'path' => $file['file_path'],
                'type' => $file['file_type'] // 'image' or 'video'
            ];
        }
        
        $inventory[] = [
            'id' => $inventory_id,
            'material' => $item['material'],
            'quantity' => floatval($item['quantity']),
            'unit' => $item['unit'],
            'standard_values' => $item['standard_values'],
            'files' => $files
        ];
    }
    
    // Get expenses
    $expenses = [
        'total_wages' => floatval($update_data['total_wages']),
        'total_misc_expenses' => floatval($update_data['total_misc_expenses']),
        'grand_total' => floatval($update_data['grand_total'])
    ];
    
    // Prepare final response
    $response = [
        'success' => true,
        'data' => [
            'id' => $site_update_id,
            'site_name' => $site_name,
            'update_date' => $db_date,
            'update_details' => $update_data['update_details'],
            'vendors' => $vendors,
            'company_labours' => $company_labours,
            'work_progress' => $work_progress,
            'inventory' => $inventory,
            'expenses' => $expenses
        ]
    ];
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Check if a form was submitted for site update
$site_update_message = '';
if (isset($_POST['submit_site_update'])) {
    $site_name = $conn->real_escape_string($_POST['site_name']);
    $update_date = $conn->real_escape_string($_POST['update_date']);
    
    // Get totals
    $total_wages = floatval($_POST['total_wages'] ?? 0);
    $total_misc_expenses = floatval($_POST['total_misc_expenses'] ?? 0);
    $grand_total = floatval($_POST['grand_total'] ?? 0);
    
    // Start a transaction
    $conn->begin_transaction();
    
    try {
        // Insert site update
        $query = "INSERT INTO site_updates (user_id, site_name, update_details, update_date, total_wages, total_misc_expenses, grand_total) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isssddd", $user_id, $site_name, $update_details, $update_date, $total_wages, $total_misc_expenses, $grand_total);
        $stmt->execute();
        
        $site_update_id = $conn->insert_id;
        
        // Process vendors if any
        if (isset($_POST['vendors']) && is_array($_POST['vendors'])) {
            foreach ($_POST['vendors'] as $vendor_id => $vendor_data) {
                // Insert vendor
                $vendor_type = $conn->real_escape_string($vendor_data['type']);
                $vendor_name = $conn->real_escape_string($vendor_data['name']);
                $vendor_contact = isset($vendor_data['contact']) ? $conn->real_escape_string($vendor_data['contact']) : '';
                $vendor_work = isset($vendor_data['work_description']) ? $conn->real_escape_string($vendor_data['work_description']) : '';
                
                $vendor_query = "INSERT INTO site_vendors (site_update_id, vendor_type, vendor_name, contact, work_description) 
                                VALUES (?, ?, ?, ?, ?)";
                $vendor_stmt = $conn->prepare($vendor_query);
                $vendor_stmt->bind_param("issss", $site_update_id, $vendor_type, $vendor_name, $vendor_contact, $vendor_work);
                $vendor_stmt->execute();
                
                $vendor_db_id = $conn->insert_id;
                
                // Process labours if any
                if (isset($vendor_data['labours']) && is_array($vendor_data['labours'])) {
                    foreach ($vendor_data['labours'] as $labour_id => $labour_data) {
                        // Insert labour
                        $labour_name = $conn->real_escape_string($labour_data['name']);
                        $labour_mobile = isset($labour_data['mobile']) ? $conn->real_escape_string($labour_data['mobile']) : '';
                        $morning_attendance = isset($labour_data['morning_attendance']) ? $conn->real_escape_string($labour_data['morning_attendance']) : $conn->real_escape_string($labour_data['attendance'] ?? 'Present');
                        $afternoon_attendance = isset($labour_data['afternoon_attendance']) ? $conn->real_escape_string($labour_data['afternoon_attendance']) : $conn->real_escape_string($labour_data['attendance'] ?? 'Present');
                        $labour_ot_hours = floatval($labour_data['ot_hours']);
                        $labour_ot_wages = isset($labour_data['ot_wages']) ? floatval($labour_data['ot_wages']) : 0.00;
                        $labour_wage = floatval($labour_data['wage']);
                        $labour_ot_amount = floatval($labour_data['ot_amount']);
                        $labour_total = floatval($labour_data['total']);
                        
                        $labour_insert_query = "INSERT INTO vendor_labours (vendor_id, labour_name, mobile, morning_attendance, afternoon_attendance, ot_hours, ot_wages, wage, ot_amount, total_amount) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $labour_insert_stmt = $conn->prepare($labour_insert_query);
                        $labour_insert_stmt->bind_param("issssddddd", $vendor_db_id, $labour_name, $labour_mobile, $morning_attendance, $afternoon_attendance, $labour_ot_hours, $labour_ot_wages, $labour_wage, $labour_ot_amount, $labour_total);
                        $labour_insert_stmt->execute();
                    }
                }
            }
        }
        
        // Process company labours if any
        if (isset($_POST['company_labours']) && is_array($_POST['company_labours'])) {
            foreach ($_POST['company_labours'] as $labour_id => $labour_data) {
                // Insert company labour
                $labour_name = $conn->real_escape_string($labour_data['name']);
                $labour_mobile = isset($labour_data['mobile']) ? $conn->real_escape_string($labour_data['mobile']) : '';
                $morning_attendance = isset($labour_data['morning_attendance']) ? $conn->real_escape_string($labour_data['morning_attendance']) : $conn->real_escape_string($labour_data['attendance'] ?? 'Present');
                $afternoon_attendance = isset($labour_data['afternoon_attendance']) ? $conn->real_escape_string($labour_data['afternoon_attendance']) : $conn->real_escape_string($labour_data['attendance'] ?? 'Present');
                $labour_ot_hours = floatval($labour_data['ot_hours']);
                $labour_wage = floatval($labour_data['wage']);
                $labour_ot_amount = floatval($labour_data['ot_amount']);
                $labour_total = floatval($labour_data['total']);
                
                $labour_query = "INSERT INTO company_labours (site_update_id, labour_name, mobile, morning_attendance, afternoon_attendance, ot_hours, ot_wages, wage, ot_amount, total_amount) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $labour_stmt = $conn->prepare($labour_query);
                $labour_stmt->bind_param("issssddddd", $site_update_id, $labour_name, $labour_mobile, $morning_attendance, $afternoon_attendance, $labour_ot_hours, $labour_ot_wages, $labour_wage, $labour_ot_amount, $labour_total);
                $labour_stmt->execute();
            }
        }
        
        // Process travel allowances if any
        if (isset($_POST['travel_allowances']) && is_array($_POST['travel_allowances'])) {
            foreach ($_POST['travel_allowances'] as $allowance_id => $allowance_data) {
                // Insert travel allowance
                $from_location = $conn->real_escape_string($allowance_data['from']);
                $to_location = $conn->real_escape_string($allowance_data['to']);
                $mode = $conn->real_escape_string($allowance_data['mode']);
                $kilometers = floatval($allowance_data['kilometers']);
                $amount = floatval($allowance_data['amount']);
                
                $allowance_query = "INSERT INTO travel_allowances (site_update_id, from_location, to_location, mode, kilometers, amount) 
                                    VALUES (?, ?, ?, ?, ?, ?)";
                $allowance_stmt = $conn->prepare($allowance_query);
                $allowance_stmt->bind_param("isssdd", $site_update_id, $from_location, $to_location, $mode, $kilometers, $amount);
                $allowance_stmt->execute();
            }
        }
        
        // Process beverages if any
        if (isset($_POST['beverages']) && is_array($_POST['beverages'])) {
            foreach ($_POST['beverages'] as $beverage_id => $beverage_data) {
                // Insert beverage
                $beverage_name = $conn->real_escape_string($beverage_data['name']);
                $amount = floatval($beverage_data['amount']);
                
                $beverage_query = "INSERT INTO beverages (site_update_id, name, amount) 
                                  VALUES (?, ?, ?)";
                $beverage_stmt = $conn->prepare($beverage_query);
                $beverage_stmt->bind_param("isd", $site_update_id, $beverage_name, $amount);
                $beverage_stmt->execute();
            }
        }
        
        // Process work progress items if any
        if (isset($_POST['work_progress']) && is_array($_POST['work_progress'])) {
            foreach ($_POST['work_progress'] as $progress_id => $progress_data) {
                // Insert work progress data
                $work_type = $conn->real_escape_string($progress_data['work_type']);
                $work_status = $conn->real_escape_string($progress_data['status']);
                $work_category = $conn->real_escape_string($progress_data['category']);
                $work_remarks = $conn->real_escape_string($progress_data['remarks'] ?? '');
                
                $progress_query = "INSERT INTO work_progress (site_update_id, work_type, status, category, remarks) 
                                  VALUES (?, ?, ?, ?, ?)";
                $progress_stmt = $conn->prepare($progress_query);
                $progress_stmt->bind_param("issss", $site_update_id, $work_type, $work_status, $work_category, $work_remarks);
                $progress_stmt->execute();
                
                $work_progress_id = $conn->insert_id;
                
                // Process uploaded files if any
                if (isset($_FILES["work_progress_files_" . $progress_id]) && !empty($_FILES["work_progress_files_" . $progress_id]['name'][0])) {
                    // Create directory if not exists
                    $uploadDir = 'uploads/work_progress/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // Process each file
                    $fileCount = count($_FILES["work_progress_files_" . $progress_id]['name']);
                    
                    for ($i = 0; $i < $fileCount; $i++) {
                        $fileName = $_FILES["work_progress_files_" . $progress_id]['name'][$i];
                        $fileTmpName = $_FILES["work_progress_files_" . $progress_id]['tmp_name'][$i];
                        $fileSize = $_FILES["work_progress_files_" . $progress_id]['size'][$i];
                        $fileError = $_FILES["work_progress_files_" . $progress_id]['error'][$i];
                        
                        // Get file extension
                        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        
                        // Allowed extensions
                        $allowedImageExts = ['jpg', 'jpeg', 'png', 'gif'];
                        $allowedVideoExts = ['mp4', 'mov', 'avi', 'wmv'];
                        $allowedExts = array_merge($allowedImageExts, $allowedVideoExts);
                        
                        // Check if file extension is allowed
                        if (in_array($fileExt, $allowedExts)) {
                            // Check file size (max 20MB)
                            if ($fileSize <= 20971520) {
                                // Create a unique file name to prevent overwriting
                                $newFileName = uniqid('work_progress_') . '.' . $fileExt;
                                $fileDestination = $uploadDir . $newFileName;
                                
                                // Move uploaded file to destination
                                if (move_uploaded_file($fileTmpName, $fileDestination)) {
                                    // Determine file type
                                    $file_type = in_array($fileExt, $allowedImageExts) ? 'image' : 'video';
                                    
                                    // Insert file info to database
                                    $file_query = "INSERT INTO work_progress_files (work_progress_id, file_path, file_type) 
                                                 VALUES (?, ?, ?)";
                                    $file_stmt = $conn->prepare($file_query);
                                    $file_stmt->bind_param("iss", $work_progress_id, $fileDestination, $file_type);
                                    $file_stmt->execute();
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Process inventory items if any
        if (isset($_POST['inventory']) && is_array($_POST['inventory'])) {
            foreach ($_POST['inventory'] as $inventory_id => $inventory_data) {
                // Insert inventory data
                $material = $conn->real_escape_string($inventory_data['material']);
                $quantity = floatval($inventory_data['quantity']);
                $unit = $conn->real_escape_string($inventory_data['unit']);
                $standard_values = $conn->real_escape_string($inventory_data['standard_values'] ?? '');
                
                $inventory_query = "INSERT INTO inventory (site_update_id, material, quantity, unit, standard_values) 
                                   VALUES (?, ?, ?, ?, ?)";
                $inventory_stmt = $conn->prepare($inventory_query);
                $inventory_stmt->bind_param("isdss", $site_update_id, $material, $quantity, $unit, $standard_values);
                $inventory_stmt->execute();
                
                $inventory_id_db = $conn->insert_id;
                
                // Process uploaded files if any
                if (isset($_FILES["inventory_files_" . $inventory_id]) && !empty($_FILES["inventory_files_" . $inventory_id]['name'][0])) {
                    // Create directory if not exists
                    $uploadDir = 'uploads/inventory/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // Process each file
                    $fileCount = count($_FILES["inventory_files_" . $inventory_id]['name']);
                    
                    for ($i = 0; $i < $fileCount; $i++) {
                        $fileName = $_FILES["inventory_files_" . $inventory_id]['name'][$i];
                        $fileTmpName = $_FILES["inventory_files_" . $inventory_id]['tmp_name'][$i];
                        $fileSize = $_FILES["inventory_files_" . $inventory_id]['size'][$i];
                        $fileError = $_FILES["inventory_files_" . $inventory_id]['error'][$i];
                        
                        // Get file extension
                        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        
                        // Allowed extensions
                        $allowedImageExts = ['jpg', 'jpeg', 'png', 'gif'];
                        $allowedVideoExts = ['mp4', 'mov', 'avi', 'wmv'];
                        $allowedExts = array_merge($allowedImageExts, $allowedVideoExts);
                        
                        // Check if file extension is allowed
                        if (in_array($fileExt, $allowedExts)) {
                            // Check file size (max 20MB)
                            if ($fileSize <= 20971520) {
                                // Create a unique file name to prevent overwriting
                                $newFileName = uniqid('inventory_') . '.' . $fileExt;
                                $fileDestination = $uploadDir . $newFileName;
                                
                                // Move uploaded file to destination
                                if (move_uploaded_file($fileTmpName, $fileDestination)) {
                                    // Determine file type
                                    $file_type = in_array($fileExt, $allowedImageExts) ? 'image' : 'video';
                                    
                                    // Insert file info to database
                                    $file_query = "INSERT INTO inventory_files (inventory_id, file_path, file_type) 
                                                 VALUES (?, ?, ?)";
                                    $file_stmt = $conn->prepare($file_query);
                                    $file_stmt->bind_param("iss", $inventory_id_db, $fileDestination, $file_type);
                                    $file_stmt->execute();
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Commit the transaction
        $conn->commit();
        $site_update_message = '<div class="alert-success">Site update submitted successfully!</div>';
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $conn->rollback();
        $site_update_message = '<div class="alert-error">Error submitting site update: ' . $e->getMessage() . '</div>';
    }
}

// Handle editing of site update
if (isset($_POST['submit_edit_site_update'])) {
    $site_update_id = intval($_POST['edit_site_update_id']);
    $site_name = $conn->real_escape_string($_POST['edit_site_name']);
    $update_date = $conn->real_escape_string($_POST['edit_update_date']);
    
    // Get totals
    $total_wages = floatval($_POST['edit_total_wages'] ?? 0);
    $total_misc_expenses = floatval($_POST['edit_total_misc_expenses'] ?? 0);
    $grand_total = floatval($_POST['edit_grand_total'] ?? 0);
    
    // Start a transaction
    $conn->begin_transaction();
    
    try {
        // First, verify that the site update belongs to this user
        $check_query = "SELECT id FROM site_updates WHERE id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $site_update_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            throw new Exception("You don't have permission to edit this site update.");
        }
        
        // Update site update record
        $update_query = "UPDATE site_updates SET 
                        site_name = ?, 
                        update_date = ?, 
                        total_wages = ?, 
                        total_misc_expenses = ?, 
                        grand_total = ? 
                        WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ssdddi", $site_name, $update_date, $total_wages, $total_misc_expenses, $grand_total, $site_update_id);
        $update_stmt->execute();
        
        // Process vendors
        // First, collect all vendor IDs to determine which ones to delete
        $existing_vendors_query = "SELECT id FROM site_vendors WHERE site_update_id = ?";
        $existing_vendors_stmt = $conn->prepare($existing_vendors_query);
        $existing_vendors_stmt->bind_param("i", $site_update_id);
        $existing_vendors_stmt->execute();
        $existing_vendors_result = $existing_vendors_stmt->get_result();
        
        $existing_vendor_ids = [];
        while ($row = $existing_vendors_result->fetch_assoc()) {
            $existing_vendor_ids[] = $row['id'];
        }
        
        $updated_vendor_ids = [];
        
        // Process vendors from the form
        if (isset($_POST['edit_vendors']) && is_array($_POST['edit_vendors'])) {
            foreach ($_POST['edit_vendors'] as $vendor_id => $vendor_data) {
                $vendor_type = $conn->real_escape_string($vendor_data['type']);
                $vendor_name = $conn->real_escape_string($vendor_data['name']);
                $vendor_contact = isset($vendor_data['contact']) ? $conn->real_escape_string($vendor_data['contact']) : '';
                $vendor_work = isset($vendor_data['work_description']) ? $conn->real_escape_string($vendor_data['work_description']) : '';
                
                // Check if this is an existing vendor or a new one
                if (isset($vendor_data['db_id']) && !empty($vendor_data['db_id'])) {
                    $vendor_db_id = intval($vendor_data['db_id']);
                    $updated_vendor_ids[] = $vendor_db_id;
                    
                    // Update existing vendor
                    $vendor_update_query = "UPDATE site_vendors SET 
                                          vendor_type = ?, 
                                          vendor_name = ?, 
                                          contact = ?, 
                                          work_description = ? 
                                          WHERE id = ? AND site_update_id = ?";
                    $vendor_update_stmt = $conn->prepare($vendor_update_query);
                    $vendor_update_stmt->bind_param("ssssii", $vendor_type, $vendor_name, $vendor_contact, $vendor_work, $vendor_db_id, $site_update_id);
                    $vendor_update_stmt->execute();
                } else {
                    // Insert new vendor
                    $vendor_insert_query = "INSERT INTO site_vendors (site_update_id, vendor_type, vendor_name, contact, work_description) 
                                          VALUES (?, ?, ?, ?, ?)";
                    $vendor_insert_stmt = $conn->prepare($vendor_insert_query);
                    $vendor_insert_stmt->bind_param("issss", $site_update_id, $vendor_type, $vendor_name, $vendor_contact, $vendor_work);
                    $vendor_insert_stmt->execute();
                    
                    $vendor_db_id = $conn->insert_id;
                    $updated_vendor_ids[] = $vendor_db_id;
                }
                
                // Process labours for this vendor
                if (isset($vendor_data['labours']) && is_array($vendor_data['labours'])) {
                    // First, collect all labour IDs to determine which ones to delete
                    $existing_labours_query = "SELECT id FROM vendor_labours WHERE vendor_id = ?";
                    $existing_labours_stmt = $conn->prepare($existing_labours_query);
                    $existing_labours_stmt->bind_param("i", $vendor_db_id);
                    $existing_labours_stmt->execute();
                    $existing_labours_result = $existing_labours_stmt->get_result();
                    
                    $existing_labour_ids = [];
                    while ($row = $existing_labours_result->fetch_assoc()) {
                        $existing_labour_ids[] = $row['id'];
                    }
                    
                    $updated_labour_ids = [];
                    
                    foreach ($vendor_data['labours'] as $labour_id => $labour_data) {
                        $labour_name = $conn->real_escape_string($labour_data['name']);
                        $labour_mobile = isset($labour_data['mobile']) ? $conn->real_escape_string($labour_data['mobile']) : '';
                        $morning_attendance = isset($labour_data['morning_attendance']) ? $conn->real_escape_string($labour_data['morning_attendance']) : $conn->real_escape_string($labour_data['attendance'] ?? 'Present');
                        $afternoon_attendance = isset($labour_data['afternoon_attendance']) ? $conn->real_escape_string($labour_data['afternoon_attendance']) : $conn->real_escape_string($labour_data['attendance'] ?? 'Present');
                        $labour_ot_hours = floatval($labour_data['ot_hours']);
                        $labour_ot_wages = isset($labour_data['ot_wages']) ? floatval($labour_data['ot_wages']) : 0.00;
                        $labour_wage = floatval($labour_data['wage']);
                        $labour_ot_amount = floatval($labour_data['ot_amount']);
                        $labour_total = floatval($labour_data['total']);
                        
                        // Check if this is an existing labour or a new one
                        if (isset($labour_data['db_id']) && !empty($labour_data['db_id'])) {
                            $labour_db_id = intval($labour_data['db_id']);
                            $updated_labour_ids[] = $labour_db_id;
                            
                            // Update existing labour
                            $labour_update_query = "UPDATE vendor_labours SET 
                                                labour_name = ?, 
                                                mobile = ?, 
                                                morning_attendance = ?, 
                                                afternoon_attendance = ?, 
                                                ot_hours = ?, 
                                                ot_wages = ?, 
                                                wage = ?, 
                                                ot_amount = ?, 
                                                total_amount = ? 
                                                WHERE id = ? AND vendor_id = ?";
                            $labour_update_stmt = $conn->prepare($labour_update_query);
                            $labour_update_stmt->bind_param("ssssssddddii", $labour_name, $labour_mobile, $morning_attendance, $afternoon_attendance, $labour_ot_hours, $labour_ot_wages, $labour_wage, $labour_ot_amount, $labour_total, $labour_db_id, $vendor_db_id);
                            $labour_update_stmt->execute();
                        } else {
                            // Insert new labour
                            $labour_insert_query = "INSERT INTO vendor_labours (vendor_id, labour_name, mobile, morning_attendance, afternoon_attendance, ot_hours, ot_wages, wage, ot_amount, total_amount) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            $labour_insert_stmt = $conn->prepare($labour_insert_query);
                            $labour_insert_stmt->bind_param("issssddddd", $vendor_db_id, $labour_name, $labour_mobile, $morning_attendance, $afternoon_attendance, $labour_ot_hours, $labour_ot_wages, $labour_wage, $labour_ot_amount, $labour_total);
                            $labour_insert_stmt->execute();
                            
                            $updated_labour_ids[] = $conn->insert_id;
                        }
                    }
                    
                    // Delete labours that no longer exist in the form
                    $labours_to_delete = array_diff($existing_labour_ids, $updated_labour_ids);
                    foreach ($labours_to_delete as $labour_id_to_delete) {
                        $labour_delete_query = "DELETE FROM vendor_labours WHERE id = ?";
                        $labour_delete_stmt = $conn->prepare($labour_delete_query);
                        $labour_delete_stmt->bind_param("i", $labour_id_to_delete);
                        $labour_delete_stmt->execute();
                    }
                } else {
                    // If no labours were sent, delete all labours for this vendor
                    $delete_all_labours_query = "DELETE FROM vendor_labours WHERE vendor_id = ?";
                    $delete_all_labours_stmt = $conn->prepare($delete_all_labours_query);
                    $delete_all_labours_stmt->bind_param("i", $vendor_db_id);
                    $delete_all_labours_stmt->execute();
                }
            }
        }
        
        // Delete vendors that no longer exist in the form
        $vendors_to_delete = array_diff($existing_vendor_ids, $updated_vendor_ids);
        foreach ($vendors_to_delete as $vendor_id_to_delete) {
            // First delete all labours for this vendor
            $delete_vendor_labours_query = "DELETE FROM vendor_labours WHERE vendor_id = ?";
            $delete_vendor_labours_stmt = $conn->prepare($delete_vendor_labours_query);
            $delete_vendor_labours_stmt->bind_param("i", $vendor_id_to_delete);
            $delete_vendor_labours_stmt->execute();
            
            // Then delete the vendor
            $delete_vendor_query = "DELETE FROM site_vendors WHERE id = ?";
            $delete_vendor_stmt = $conn->prepare($delete_vendor_query);
            $delete_vendor_stmt->bind_param("i", $vendor_id_to_delete);
            $delete_vendor_stmt->execute();
        }
        
        // Process company labours similarly
        // First, collect all company labour IDs to determine which ones to delete
        $existing_company_labours_query = "SELECT id FROM company_labours WHERE site_update_id = ?";
        $existing_company_labours_stmt = $conn->prepare($existing_company_labours_query);
        $existing_company_labours_stmt->bind_param("i", $site_update_id);
        $existing_company_labours_stmt->execute();
        $existing_company_labours_result = $existing_company_labours_stmt->get_result();
        
        $existing_company_labour_ids = [];
        while ($row = $existing_company_labours_result->fetch_assoc()) {
            $existing_company_labour_ids[] = $row['id'];
        }
        
        $updated_company_labour_ids = [];
        
        // Process company labours from the form
        if (isset($_POST['edit_company_labours']) && is_array($_POST['edit_company_labours'])) {
            foreach ($_POST['edit_company_labours'] as $labour_id => $labour_data) {
                $labour_name = $conn->real_escape_string($labour_data['name']);
                $labour_mobile = isset($labour_data['mobile']) ? $conn->real_escape_string($labour_data['mobile']) : '';
                $morning_attendance = isset($labour_data['morning_attendance']) ? $conn->real_escape_string($labour_data['morning_attendance']) : $conn->real_escape_string($labour_data['attendance'] ?? 'Present');
                $afternoon_attendance = isset($labour_data['afternoon_attendance']) ? $conn->real_escape_string($labour_data['afternoon_attendance']) : $conn->real_escape_string($labour_data['attendance'] ?? 'Present');
                $labour_ot_hours = floatval($labour_data['ot_hours']);
                $labour_wage = floatval($labour_data['wage']);
                $labour_ot_amount = floatval($labour_data['ot_amount']);
                $labour_total = floatval($labour_data['total']);
                
                // Check if this is an existing labour or a new one
                if (isset($labour_data['db_id']) && !empty($labour_data['db_id'])) {
                    $labour_db_id = intval($labour_data['db_id']);
                    $updated_company_labour_ids[] = $labour_db_id;
                    
                    // Update existing company labour
                    $labour_update_query = "UPDATE company_labours SET 
                                         labour_name = ?, 
                                         mobile = ?, 
                                         attendance = ?, 
                                         ot_hours = ?, 
                                         ot_wages = ?, 
                                         wage = ?, 
                                         ot_amount = ?, 
                                         total_amount = ? 
                                         WHERE id = ? AND site_update_id = ?";
                    $labour_update_stmt = $conn->prepare($labour_update_query);
                    $labour_update_stmt->bind_param("sssddddii", $labour_name, $labour_mobile, $morning_attendance, $afternoon_attendance, $labour_ot_hours, $labour_ot_wages, $labour_wage, $labour_ot_amount, $labour_total, $labour_db_id, $site_update_id);
                    $labour_update_stmt->execute();
                } else {
                    // Insert new company labour
                    $labour_insert_query = "INSERT INTO company_labours (site_update_id, labour_name, mobile, morning_attendance, afternoon_attendance, ot_hours, ot_wages, wage, ot_amount, total_amount) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $labour_insert_stmt = $conn->prepare($labour_insert_query);
                    $labour_insert_stmt->bind_param("issssddddd", $site_update_id, $labour_name, $labour_mobile, $morning_attendance, $afternoon_attendance, $labour_ot_hours, $labour_ot_wages, $labour_wage, $labour_ot_amount, $labour_total);
                    $labour_insert_stmt->execute();
                    
                    $updated_company_labour_ids[] = $conn->insert_id;
                }
            }
        }
        
        // Delete company labours that no longer exist in the form
        $company_labours_to_delete = array_diff($existing_company_labour_ids, $updated_company_labour_ids);
        foreach ($company_labours_to_delete as $labour_id_to_delete) {
            $labour_delete_query = "DELETE FROM company_labours WHERE id = ?";
            $labour_delete_stmt = $conn->prepare($labour_delete_query);
            $labour_delete_stmt->bind_param("i", $labour_id_to_delete);
            $labour_delete_stmt->execute();
        }
        
        // Process work progress items
        // First, collect all work progress IDs to determine which ones to delete
        $existing_work_progress_query = "SELECT id FROM work_progress WHERE site_update_id = ?";
        $existing_work_progress_stmt = $conn->prepare($existing_work_progress_query);
        $existing_work_progress_stmt->bind_param("i", $site_update_id);
        $existing_work_progress_stmt->execute();
        $existing_work_progress_result = $existing_work_progress_stmt->get_result();
        
        $existing_work_progress_ids = [];
        while ($row = $existing_work_progress_result->fetch_assoc()) {
            $existing_work_progress_ids[] = $row['id'];
        }
        
        $updated_work_progress_ids = [];
        
        // Process work progress items from the form
        if (isset($_POST['edit_work_progress']) && is_array($_POST['edit_work_progress'])) {
            foreach ($_POST['edit_work_progress'] as $progress_id => $progress_data) {
                $work_type = $conn->real_escape_string($progress_data['work_type']);
                $work_status = $conn->real_escape_string($progress_data['status']);
                $work_category = $conn->real_escape_string($progress_data['category']);
                $work_remarks = isset($progress_data['remarks']) ? $conn->real_escape_string($progress_data['remarks']) : '';
                
                // Check if this is an existing work progress item or a new one
                if (isset($progress_data['db_id']) && !empty($progress_data['db_id'])) {
                    $work_progress_db_id = intval($progress_data['db_id']);
                    $updated_work_progress_ids[] = $work_progress_db_id;
                    
                    // Update existing work progress
                    $progress_update_query = "UPDATE work_progress SET 
                                          work_type = ?, 
                                          status = ?, 
                                          category = ?, 
                                          remarks = ? 
                                          WHERE id = ? AND site_update_id = ?";
                    $progress_update_stmt = $conn->prepare($progress_update_query);
                    $progress_update_stmt->bind_param("ssssii", $work_type, $work_status, $work_category, $work_remarks, $work_progress_db_id, $site_update_id);
                    $progress_update_stmt->execute();
                } else {
                    // Insert new work progress
                    $progress_insert_query = "INSERT INTO work_progress (site_update_id, work_type, status, category, remarks) 
                                          VALUES (?, ?, ?, ?, ?)";
                    $progress_insert_stmt = $conn->prepare($progress_insert_query);
                    $progress_insert_stmt->bind_param("issss", $site_update_id, $work_type, $work_status, $work_category, $work_remarks);
                    $progress_insert_stmt->execute();
                    
                    $work_progress_db_id = $conn->insert_id;
                    $updated_work_progress_ids[] = $work_progress_db_id;
                }
                
                // Process uploaded files if any
                if (isset($_FILES["edit_work_progress_files_{$progress_id}"]) && $_FILES["edit_work_progress_files_{$progress_id}"]['error'][0] != 4) {
                    $files = $_FILES["edit_work_progress_files_{$progress_id}"];
                    
                    // Create directory if it doesn't exist
                    $uploadDir = 'uploads/work_progress/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // Allowed file extensions
                    $allowedImageExts = ['jpg', 'jpeg', 'png', 'gif'];
                    $allowedVideoExts = ['mp4', 'mov', 'avi', 'wmv'];
                    $allowedExts = array_merge($allowedImageExts, $allowedVideoExts);
                    
                    // Process each file
                    for ($i = 0; $i < count($files['name']); $i++) {
                        if ($files['error'][$i] == 0) {
                            $fileName = $files['name'][$i];
                            $fileTmpName = $files['tmp_name'][$i];
                            $fileSize = $files['size'][$i];
                            
                            // Get file extension
                            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                            
                            // Check if file extension is allowed
                            if (in_array($fileExt, $allowedExts)) {
                                // Check file size (max 20MB)
                                if ($fileSize <= 20971520) {
                                    // Create a unique file name to prevent overwriting
                                    $newFileName = uniqid('work_progress_') . '.' . $fileExt;
                                    $fileDestination = $uploadDir . $newFileName;
                                    
                                    // Move uploaded file to destination
                                    if (move_uploaded_file($fileTmpName, $fileDestination)) {
                                        // Determine file type
                                        $file_type = in_array($fileExt, $allowedImageExts) ? 'image' : 'video';
                                        
                                        // Insert file info to database
                                        $file_query = "INSERT INTO work_progress_files (work_progress_id, file_path, file_type) 
                                                     VALUES (?, ?, ?)";
                                        $file_stmt = $conn->prepare($file_query);
                                        $file_stmt->bind_param("iss", $work_progress_db_id, $fileDestination, $file_type);
                                        $file_stmt->execute();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Delete work progress items that no longer exist in the form
        $work_progress_to_delete = array_diff($existing_work_progress_ids, $updated_work_progress_ids);
        foreach ($work_progress_to_delete as $progress_id_to_delete) {
            // First delete all files for this work progress
            $delete_files_query = "DELETE FROM work_progress_files WHERE work_progress_id = ?";
            $delete_files_stmt = $conn->prepare($delete_files_query);
            $delete_files_stmt->bind_param("i", $progress_id_to_delete);
            $delete_files_stmt->execute();
            
            // Then delete the work progress
            $delete_progress_query = "DELETE FROM work_progress WHERE id = ?";
            $delete_progress_stmt = $conn->prepare($delete_progress_query);
            $delete_progress_stmt->bind_param("i", $progress_id_to_delete);
            $delete_progress_stmt->execute();
        }
        
        // Process inventory items
        // First, collect all inventory IDs to determine which ones to delete
        $existing_inventory_query = "SELECT id FROM inventory WHERE site_update_id = ?";
        $existing_inventory_stmt = $conn->prepare($existing_inventory_query);
        $existing_inventory_stmt->bind_param("i", $site_update_id);
        $existing_inventory_stmt->execute();
        $existing_inventory_result = $existing_inventory_stmt->get_result();
        
        $existing_inventory_ids = [];
        while ($row = $existing_inventory_result->fetch_assoc()) {
            $existing_inventory_ids[] = $row['id'];
        }
        
        $updated_inventory_ids = [];
        
        // Process inventory items from the form
        if (isset($_POST['edit_inventory']) && is_array($_POST['edit_inventory'])) {
            foreach ($_POST['edit_inventory'] as $inventory_id => $inventory_data) {
                $material = $conn->real_escape_string($inventory_data['material']);
                $quantity = floatval($inventory_data['quantity']);
                $unit = $conn->real_escape_string($inventory_data['unit']);
                $standard_values = isset($inventory_data['standard_values']) ? $conn->real_escape_string($inventory_data['standard_values']) : '';
                
                // Check if this is an existing inventory item or a new one
                if (isset($inventory_data['db_id']) && !empty($inventory_data['db_id'])) {
                    $inventory_db_id = intval($inventory_data['db_id']);
                    $updated_inventory_ids[] = $inventory_db_id;
                    
                    // Update existing inventory
                    $inventory_update_query = "UPDATE inventory SET 
                                          material = ?, 
                                          quantity = ?, 
                                          unit = ?, 
                                          standard_values = ? 
                                          WHERE id = ? AND site_update_id = ?";
                    $inventory_update_stmt = $conn->prepare($inventory_update_query);
                    $inventory_update_stmt->bind_param("sdssii", $material, $quantity, $unit, $standard_values, $inventory_db_id, $site_update_id);
                    $inventory_update_stmt->execute();
                } else {
                    // Insert new inventory
                    $inventory_insert_query = "INSERT INTO inventory (site_update_id, material, quantity, unit, standard_values) 
                                          VALUES (?, ?, ?, ?, ?)";
                    $inventory_insert_stmt = $conn->prepare($inventory_insert_query);
                    $inventory_insert_stmt->bind_param("isdss", $site_update_id, $material, $quantity, $unit, $standard_values);
                    $inventory_insert_stmt->execute();
                    
                    $inventory_db_id = $conn->insert_id;
                    $updated_inventory_ids[] = $inventory_db_id;
                }
                
                // Process uploaded files if any
                if (isset($_FILES["edit_inventory_files_{$inventory_id}"]) && $_FILES["edit_inventory_files_{$inventory_id}"]['error'][0] != 4) {
                    $files = $_FILES["edit_inventory_files_{$inventory_id}"];
                    
                    // Create directory if it doesn't exist
                    $uploadDir = 'uploads/inventory/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // Allowed file extensions
                    $allowedImageExts = ['jpg', 'jpeg', 'png', 'gif'];
                    $allowedVideoExts = ['mp4', 'mov', 'avi', 'wmv'];
                    $allowedExts = array_merge($allowedImageExts, $allowedVideoExts);
                    
                    // Process each file
                    for ($i = 0; $i < count($files['name']); $i++) {
                        if ($files['error'][$i] == 0) {
                            $fileName = $files['name'][$i];
                            $fileTmpName = $files['tmp_name'][$i];
                            $fileSize = $files['size'][$i];
                            
                            // Get file extension
                            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                            
                            // Check if file extension is allowed
                            if (in_array($fileExt, $allowedExts)) {
                                // Check file size (max 20MB)
                                if ($fileSize <= 20971520) {
                                    // Create a unique file name to prevent overwriting
                                    $newFileName = uniqid('inventory_') . '.' . $fileExt;
                                    $fileDestination = $uploadDir . $newFileName;
                                    
                                    // Move uploaded file to destination
                                    if (move_uploaded_file($fileTmpName, $fileDestination)) {
                                        // Determine file type
                                        $file_type = in_array($fileExt, $allowedImageExts) ? 'image' : 'video';
                                        
                                        // Insert file info to database
                                        $file_query = "INSERT INTO inventory_files (inventory_id, file_path, file_type) 
                                                     VALUES (?, ?, ?)";
                                        $file_stmt = $conn->prepare($file_query);
                                        $file_stmt->bind_param("iss", $inventory_db_id, $fileDestination, $file_type);
                                        $file_stmt->execute();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Delete inventory items that no longer exist in the form
        $inventory_to_delete = array_diff($existing_inventory_ids, $updated_inventory_ids);
        foreach ($inventory_to_delete as $inventory_id_to_delete) {
            // First delete all files for this inventory
            $delete_files_query = "DELETE FROM inventory_files WHERE inventory_id = ?";
            $delete_files_stmt = $conn->prepare($delete_files_query);
            $delete_files_stmt->bind_param("i", $inventory_id_to_delete);
            $delete_files_stmt->execute();
            
            // Then delete the inventory
            $delete_inventory_query = "DELETE FROM inventory WHERE id = ?";
            $delete_inventory_stmt = $conn->prepare($delete_inventory_query);
            $delete_inventory_stmt->bind_param("i", $inventory_id_to_delete);
            $delete_inventory_stmt->execute();
        }
        
        // Commit the transaction
        $conn->commit();
        $site_update_message = '<div class="alert-success">Site update updated successfully!</div>';
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $conn->rollback();
        $site_update_message = '<div class="alert-error">Error updating site update: ' . $e->getMessage() . '</div>';
    }
}

// Check if a form was submitted for travel expense
$expense_message = '';
if (isset($_POST['submit_expense'])) {
    $expense_date = $conn->real_escape_string($_POST['expense_date']);
    $site_visited = $conn->real_escape_string($_POST['site_visited']);
    $amount = floatval($_POST['amount']);
    $expense_details = $conn->real_escape_string($_POST['expense_details']);
    
    // Handle file upload if present
    $receipt_path = NULL;
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
        // Check if the upload directory exists, if not create it
        $uploadDir = 'uploads/receipts/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Get file info
        $fileName = $_FILES['receipt']['name'];
        $fileTmpName = $_FILES['receipt']['tmp_name'];
        $fileSize = $_FILES['receipt']['size'];
        $fileError = $_FILES['receipt']['error'];
        
        // Get file extension
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Allowed extensions
        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
        
        // Check if file extension is allowed
        if (in_array($fileExt, $allowedExts)) {
            // Check file size (max 2MB)
            if ($fileSize <= 2097152) {
                // Create a unique file name to prevent overwriting
                $newFileName = uniqid('receipt_') . '.' . $fileExt;
                $fileDestination = $uploadDir . $newFileName;
                
                // Move uploaded file to destination
                if (move_uploaded_file($fileTmpName, $fileDestination)) {
                    $receipt_path = $fileDestination;
                } else {
                    $expense_message = '<div class="alert-error">Failed to upload receipt file.</div>';
                }
            } else {
                $expense_message = '<div class="alert-error">File size too large. Maximum file size is 2MB.</div>';
            }
        } else {
            $expense_message = '<div class="alert-error">Invalid file type. Allowed types: JPG, JPEG, PNG, PDF.</div>';
        }
    }
    
    // Only proceed with database insertion if there was no file upload error
    if (empty($expense_message)) {
        $query = "INSERT INTO travel_expenses (user_id, expense_date, site_visited, amount, expense_details, receipt_path) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("issdss", $user_id, $expense_date, $site_visited, $amount, $expense_details, $receipt_path);
        
        if ($stmt->execute()) {
            $expense_message = '<div class="alert-success">Expense submitted successfully!</div>';
        } else {
            $expense_message = '<div class="alert-error">Error submitting expense: ' . $conn->error . '</div>';
        }
    }
}

// Fetch recent site updates for the current user
$recent_updates_query = "SELECT * FROM site_updates WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($recent_updates_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_updates_result = $stmt->get_result();

// Fetch recent expenses for the current user
$recent_expenses_query = "SELECT * FROM travel_expenses WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($recent_expenses_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_expenses_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Updates & Expenses</title>
    <link rel="icon" href="images/logo.png" type="image/x-icon">
    <link rel="shortcut icon" href="images/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="css/site_expenses.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <div class="left-panel" id="leftPanel">
            <div class="brand-logo" style="padding: 20px 25px; margin-bottom: 20px;">
                <img src="" alt="Logo" style="max-width: 150px; height: auto;">
            </div>
            <button class="toggle-btn" onclick="togglePanel()">
                <i class="fas fa-chevron-left" id="toggleIcon"></i>
            </button>
            
            <!-- Main Navigation -->
            <div class="menu-item" onclick="window.location.href='similar_dashboard.php'">
                <i class="fas fa-home"></i>
                <span class="menu-text">Dashboard</span>
            </div>
            
            <!-- Personal Section -->
            <div class="menu-item" onclick="window.location.href='profile.php'">
                <i class="fas fa-user-circle"></i>
                <span class="menu-text">My Profile</span>
            </div>
            <div class="menu-item" onclick="window.location.href='leave.php'">
                <i class="fas fa-calendar-alt"></i>
                <span class="menu-text">Apply Leave</span>
            </div>
            <div class="menu-item active" onclick="window.location.href='site_expenses.php'">
                <i class="fas fa-file-excel"></i>
                <span class="menu-text">Site Excel</span>
            </div>
            
            <!-- Work Section -->
            <div class="menu-item">
                <i class="fas fa-tasks"></i>
                <span class="menu-text">My Tasks</span>
            </div>
            <div class="menu-item" onclick="window.location.href='work_sheet.php'">
                <i class="fas fa-file-alt"></i>
                <span class="menu-text">Work Sheet & Attendance</span>
            </div>
            <div class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span class="menu-text">Performance</span>
            </div>
            
            <!-- Settings & Support -->
            <div class="menu-item">
                <i class="fas fa-cog"></i>
                <span class="menu-text">Settings</span>
            </div>
            <div class="menu-item">
                <i class="fas fa-question-circle"></i>
                <span class="menu-text">Help & Support</span>
            </div>
            
            <!-- Logout at the bottom -->
            <div class="menu-item logout-item" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">Logout</span>
            </div>
        </div>
        
        <div class="main-content">
            <!-- Content Section -->
            <div class="content-section">
                <!-- Daily Site Updates Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-building"></i>
                            Daily Site Updates
                        </h3>
                        <button type="button" class="btn btn-primary add-update-btn" onclick="openSiteUpdateModal()">
                            <i class="fas fa-plus"></i> Add Update
                        </button>
                    </div>
                    <div class="card-body">
                        <?php echo $site_update_message; ?>
                        
                        <div class="table-container">
                            <h4 style="margin: 20px 0 10px;">Recent Site Updates</h4>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Site Name</th>
                                        <th>Details</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_updates_result->num_rows > 0): ?>
                                        <?php while ($update = $recent_updates_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('d M Y', strtotime($update['update_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($update['site_name']); ?></td>
                                                <td><?php echo substr(htmlspecialchars($update['update_details']), 0, 50) . (strlen($update['update_details']) > 50 ? '...' : ''); ?></td>
                                                <td>
                                                    <button class="btn-view-details" onclick="viewUpdateDetails('<?php echo addslashes($update['site_name']); ?>', '<?php echo date('d M Y', strtotime($update['update_date'])); ?>', '<?php echo addslashes($update['update_details']); ?>')">
                                                        View Details
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr class="empty-row">
                                            <td colspan="4">No recent updates found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Travelling Expenses Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-car"></i>
                            Travelling Expenses
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php echo $expense_message; ?>
                        
                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="expense_date">Date</label>
                                <input type="date" class="form-control" id="expense_date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_visited">Site Visited</label>
                                <input type="text" class="form-control" id="site_visited" name="site_visited" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="amount">Amount (₹)</label>
                                <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="expense_details">Expense Details</label>
                                <textarea class="form-control" id="expense_details" name="expense_details" placeholder="Provide details about the travelling expense (e.g. fuel, public transportation, etc.)..." required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="receipt">Receipt (Optional)</label>
                                <input type="file" class="form-control" id="receipt" name="receipt">
                                <small style="color: #666; margin-top: 5px; display: block;">
                                    Upload receipt or supporting document (Max size: 2MB, Formats: PDF, JPG, PNG)
                                </small>
                            </div>
                            
                            <button type="submit" name="submit_expense" class="btn btn-primary">Submit Expense</button>
                        </form>
                        
                        <div class="table-container">
                            <h4 style="margin: 20px 0 10px;">Recent Expenses</h4>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Site Visited</th>
                                        <th>Amount</th>
                                        <th>Details</th>
                                        <th>Receipt</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_expenses_result->num_rows > 0): ?>
                                        <?php while ($expense = $recent_expenses_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('d M Y', strtotime($expense['expense_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($expense['site_visited']); ?></td>
                                                <td>₹<?php echo number_format($expense['amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($expense['expense_details']); ?></td>
                                                <td>
                                                    <?php if (!empty($expense['receipt_path'])): ?>
                                                        <a href="<?php echo htmlspecialchars($expense['receipt_path']); ?>" target="_blank" class="view-receipt">
                                                            <i class="fas fa-file-alt"></i> View
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">No receipt</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status = $expense['status'] ?? 'Pending';
                                                    $status_color = 'gray';
                                                    
                                                    if ($status === 'Approved') {
                                                        $status_color = 'green';
                                                    } elseif ($status === 'Rejected') {
                                                        $status_color = 'red';
                                                    } elseif ($status === 'Pending') {
                                                        $status_color = 'orange';
                                                    }
                                                    ?>
                                                    <span style="color: <?php echo $status_color; ?>; font-weight: 500;">
                                                        <?php echo $status; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr class="empty-row">
                                            <td colspan="6">No recent expenses found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    
    <!-- Update Details Modal -->
    <div id="updateDetailsModal" class="modal site-update-details-modal">
        <div class="modal-content site-detail-modal-content">
            <div class="modal-header site-detail-modal-header">
                <h3 class="modal-title site-detail-modal-title">Site Update Details</h3>
                <div class="site-detail-header-actions">
                    <button type="button" class="btn btn-primary site-detail-edit-btn" id="siteDetailEditBtn" onclick="editSiteDetails()">
                        <i class="fas fa-edit"></i> Edit Details
                    </button>
                    <span class="close-modal site-detail-close-btn">&times;</span>
                </div>
            </div>
            <div class="modal-body site-detail-modal-body">
                <!-- Basic Details Section -->
                <div class="site-detail-section">
                    <h4 class="site-detail-section-title"><i class="fas fa-info-circle"></i> Basic Information</h4>
                    <div class="detail-item site-detail-item">
                        <span class="detail-label site-detail-label">Site Name</span>
                        <div id="modalSiteName" class="detail-value site-detail-value"></div>
                    </div>
                    <div class="detail-item site-detail-item">
                        <span class="detail-label site-detail-label">Date</span>
                        <div id="modalDate" class="detail-value site-detail-value"></div>
                    </div>
                    <div class="detail-item site-detail-item">
                        <span class="detail-label site-detail-label">Update Details</span>
                        <div id="modalDetails" class="detail-value site-detail-value"></div>
                    </div>
                </div>
                
                <!-- Vendors Section -->
                <div class="site-detail-section" id="modalVendorsSection">
                    <h4 class="site-detail-section-title"><i class="fas fa-users"></i> Vendors</h4>
                    <div id="modalVendorsList" class="site-detail-vendors-list">
                        <!-- Vendors will be populated here dynamically -->
                    </div>
                </div>
                
                <!-- Company Labours Section -->
                <div class="site-detail-section" id="modalCompanyLaboursSection">
                    <h4 class="site-detail-section-title"><i class="fas fa-hard-hat"></i> Company Labours</h4>
                    <div id="modalCompanyLaboursList" class="site-detail-company-labours-list">
                        <!-- Company labours will be populated here dynamically -->
                    </div>
                </div>
                
                <!-- Work Progress Section -->
                <div class="site-detail-section" id="modalWorkProgressSection">
                    <h4 class="site-detail-section-title"><i class="fas fa-tasks"></i> Work Progress</h4>
                    <div id="modalWorkProgressList" class="site-detail-work-progress-list">
                        <!-- Work progress items will be populated here dynamically -->
                    </div>
                </div>
                
                <!-- Inventory Section -->
                <div class="site-detail-section" id="modalInventorySection">
                    <h4 class="site-detail-section-title"><i class="fas fa-boxes"></i> Inventory</h4>
                    <div id="modalInventoryList" class="site-detail-inventory-list">
                        <!-- Inventory items will be populated here dynamically -->
                    </div>
                </div>
                
                <!-- Expenses Summary Section -->
                <div class="site-detail-section" id="modalExpensesSection">
                    <h4 class="site-detail-section-title"><i class="fas fa-money-bill-wave"></i> Expenses Summary</h4>
                    <div class="site-detail-expenses-summary">
                        <div class="site-detail-summary-row">
                            <span class="site-detail-summary-label">Total Wages:</span>
                            <span class="site-detail-summary-value" id="modalTotalWages">₹0.00</span>
                        </div>
                        <div class="site-detail-summary-row">
                            <span class="site-detail-summary-label">Total Misc Expenses:</span>
                            <span class="site-detail-summary-value" id="modalTotalMiscExpenses">₹0.00</span>
                        </div>
                        <div class="site-detail-summary-row site-detail-grand-total">
                            <span class="site-detail-summary-label">Grand Total:</span>
                            <span class="site-detail-summary-value" id="modalGrandTotal">₹0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Site Update Modal -->
    <div id="siteUpdateModal" class="modal-site-update">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Site Update</h3>
                <span class="close-modal" onclick="hideSiteUpdateModal()">&times;</span>
            </div>
            <form id="siteUpdateForm" action="" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- Site Details Section -->
                    <div class="section-title">
                        <i class="fas fa-building"></i> Site Details
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="site_name"><i class="fas fa-map-marker-alt"></i> Site Name</label>
                                <input type="text" class="form-control" id="site_name" name="site_name" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="update_date"><i class="fas fa-calendar-alt"></i> Date</label>
                                <input type="date" class="form-control" id="update_date" name="update_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    
                    <!-- Vendor Section -->
                    <div class="section-title">
                        <i class="fas fa-users"></i> Vendor Section
                        <button type="button" class="btn-add-item" onclick="addVendor()">
                            <i class="fas fa-plus"></i> Add Vendor
                        </button>
                    </div>
                    <div id="vendors-container">
                        <!-- Vendors will be added here dynamically -->
                    </div>
                    
                    <!-- Company Labour Section -->
                    <div class="section-title">
                        <i class="fas fa-hard-hat"></i> Company Labour
                        <button type="button" class="btn-add-item" onclick="addCompanyLabour()">
                            <i class="fas fa-plus"></i> Add Company Labour
                        </button>
                    </div>
                    <div id="company-labours-container">
                        <!-- Company labours will be added here dynamically -->
                    </div>
                    
                    <!-- Expenses Section -->
                    <div class="section-title">
                        <i class="fas fa-money-bill-wave"></i> Expenses
                    </div>
                    
                    <!-- Travelling Allowances -->
                    <div class="subsection-title">
                        <i class="fas fa-car"></i> Travelling Allowances
                    </div>
                    <div id="travelling-allowances-container">
                        <button type="button" class="btn-add-item" onclick="addTravellingAllowance()">
                            <i class="fas fa-plus"></i> Add Travelling Allowance
                        </button>
                        <div id="travel-allowances-list">
                            <!-- Travel allowances will be added here dynamically -->
                        </div>
                        <div class="total-section">
                            <strong>Total Travelling Allowances:</strong>
                            <span id="total-travel-allowances">₹0.00</span>
                            <input type="hidden" name="total_travel_allowances" id="total-travel-allowances-input" value="0">
                        </div>
                    </div>
                    
                    <!-- Beverages -->
                    <div class="subsection-title">
                        <i class="fas fa-coffee"></i> Beverages
                    </div>
                    <div id="beverages-container">
                        <button type="button" class="btn-add-item" onclick="addBeverage()">
                            <i class="fas fa-plus"></i> Add Beverage
                        </button>
                        <div id="beverages-list">
                            <!-- Beverages will be added here dynamically -->
                        </div>
                        <div class="total-section">
                            <strong>Total Beverages:</strong>
                            <span id="total-beverages">₹0.00</span>
                            <input type="hidden" name="total_beverages" id="total-beverages-input" value="0">
                        </div>
                    </div>
                    
                    <!-- Work Progress Section -->
                    <div class="section-title">
                        <i class="fas fa-tasks"></i> Work Progress
                    </div>
                    <div id="work-progress-container">
                        <div id="work-progress-list">
                            <!-- Work progress items will be added here dynamically -->
                        </div>
                        <div class="work-progress-buttons">
                            <button type="button" class="btn-add-item btn-add-civil" onclick="addWorkProgress('civil')">
                                <i class="fas fa-hammer"></i> Add Civil Work
                            </button>
                            <button type="button" class="btn-add-item btn-add-interior" style="margin-left: 10px;" onclick="addWorkProgress('interior')">
                                <i class="fas fa-couch"></i> Add Interior Work
                            </button>
                        </div>
                    </div>
                    
                    <!-- Inventory Section -->
                    <div class="section-title">
                        <i class="fas fa-boxes"></i> Inventory
                    </div>
                    <div id="inventory-container">
                        <div id="inventory-list">
                            <!-- Inventory items will be added here dynamically -->
                        </div>
                        <div class="inventory-buttons">
                            <button type="button" class="btn-add-item btn-add-inventory" onclick="addInventoryItem()">
                                <i class="fas fa-plus"></i> Add Inventory Item
                            </button>
                        </div>
                    </div>
                    
                    <!-- Summary Section -->
                    <div class="section-title">
                        <i class="fas fa-chart-pie"></i> Summary
                    </div>
                    <div class="summary-container">
                        <div class="summary-row">
                            <div class="summary-label">Total Wages:</div>
                            <div class="summary-value">₹<span id="total-wages">0.00</span></div>
                            <input type="hidden" name="total_wages" id="total-wages-input" value="0">
                        </div>
                        <div class="summary-row">
                            <div class="summary-label">Total Miscellaneous Expenses:</div>
                            <div class="summary-value">₹<span id="total-misc-expenses">0.00</span></div>
                            <input type="hidden" name="total_misc_expenses" id="total-misc-expenses-input" value="0">
                        </div>
                        <div class="summary-row grand-total">
                            <div class="summary-label">Grand Total:</div>
                            <div class="summary-value">₹<span id="grand-total">0.00</span></div>
                            <input type="hidden" name="grand_total" id="grand-total-input" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideSiteUpdateModal()">Cancel</button>
                    <button type="submit" name="submit_site_update" class="btn btn-primary">Submit Update</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Media Modal for viewing images and videos -->
    <div id="siteDetailMediaModal" class="site-detail-media-modal">
        <span class="site-detail-media-close" onclick="closeMediaModal()">&times;</span>
        <div class="site-detail-media-modal-content" id="siteDetailMediaContent">
            <!-- Media content will be loaded here dynamically -->
        </div>
    </div>
    
    <!-- Edit Site Update Modal -->
    <div id="editSiteUpdateModal" class="modal-site-update">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Site Update</h3>
                <span class="close-modal" onclick="hideEditSiteUpdateModal()">&times;</span>
            </div>
            <form id="editSiteUpdateForm" action="" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- Hidden input for site update ID -->
                    <input type="hidden" id="edit_site_update_id" name="edit_site_update_id">
                    <input type="hidden" name="action" value="edit_site_update">
                    
                    <!-- Site Details Section -->
                    <div class="section-title">
                        <i class="fas fa-building"></i> Site Details
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label for="edit_site_name"><i class="fas fa-map-marker-alt"></i> Site Name</label>
                                <input type="text" class="form-control" id="edit_site_name" name="edit_site_name" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label for="edit_update_date"><i class="fas fa-calendar-alt"></i> Date</label>
                                <input type="date" class="form-control" id="edit_update_date" name="edit_update_date" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vendor Section -->
                    <div class="section-title">
                        <i class="fas fa-users"></i> Vendor Section
                        <button type="button" class="btn-add-item" onclick="addVendorToEdit()">
                            <i class="fas fa-plus"></i> Add Vendor
                        </button>
                    </div>
                    <div id="edit-vendors-container">
                        <!-- Vendors will be added here dynamically -->
                    </div>
                    
                    <!-- Company Labour Section -->
                    <div class="section-title">
                        <i class="fas fa-hard-hat"></i> Company Labour
                        <button type="button" class="btn-add-item" onclick="addCompanyLabourToEdit()">
                            <i class="fas fa-plus"></i> Add Company Labour
                        </button>
                    </div>
                    <div id="edit-company-labours-container">
                        <!-- Company labours will be added here dynamically -->
                    </div>
                    
                    <!-- Work Progress Section -->
                    <div class="section-title">
                        <i class="fas fa-tasks"></i> Work Progress
                    </div>
                    <div id="edit-work-progress-container">
                        <div id="edit-work-progress-list">
                            <!-- Work progress items will be added here dynamically -->
                        </div>
                        <div class="work-progress-buttons">
                            <button type="button" class="btn-add-item btn-add-civil" onclick="addWorkProgressToEdit('civil')">
                                <i class="fas fa-hammer"></i> Add Civil Work
                            </button>
                            <button type="button" class="btn-add-item btn-add-interior" style="margin-left: 10px;" onclick="addWorkProgressToEdit('interior')">
                                <i class="fas fa-couch"></i> Add Interior Work
                            </button>
                        </div>
                    </div>
                    
                    <!-- Inventory Section -->
                    <div class="section-title">
                        <i class="fas fa-boxes"></i> Inventory
                    </div>
                    <div id="edit-inventory-container">
                        <div id="edit-inventory-list">
                            <!-- Inventory items will be added here dynamically -->
                        </div>
                        <div class="inventory-buttons">
                            <button type="button" class="btn-add-item btn-add-inventory" onclick="addInventoryItemToEdit()">
                                <i class="fas fa-plus"></i> Add Inventory Item
                            </button>
                        </div>
                    </div>
                    
                    <!-- Summary Section -->
                    <div class="section-title">
                        <i class="fas fa-chart-pie"></i> Summary
                    </div>
                    <div class="summary-container">
                        <div class="summary-row">
                            <div class="summary-label">Total Wages:</div>
                            <div class="summary-value">₹<span id="edit-total-wages">0.00</span></div>
                            <input type="hidden" name="edit_total_wages" id="edit-total-wages-input" value="0">
                        </div>
                        <div class="summary-row">
                            <div class="summary-label">Total Miscellaneous Expenses:</div>
                            <div class="summary-value">₹<span id="edit-total-misc-expenses">0.00</span></div>
                            <input type="hidden" name="edit_total_misc_expenses" id="edit-total-misc-expenses-input" value="0">
                        </div>
                        <div class="summary-row grand-total">
                            <div class="summary-label">Grand Total:</div>
                            <div class="summary-value">₹<span id="edit-grand-total">0.00</span></div>
                            <input type="hidden" name="edit_grand_total" id="edit-grand-total-input" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideEditSiteUpdateModal()">Cancel</button>
                    <button type="submit" name="submit_edit_site_update" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Include the autocomplete fix script -->
     <script src="js/site_expenses.js"></script>
    <script src="autocomplete_fix.js"></script>
    <script src="export_excel.js"></script>
</body>
</html> 
