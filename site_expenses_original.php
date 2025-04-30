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
        // Get company labours from previous entries
        $labour_query = "SELECT DISTINCT cl.labour_name, cl.mobile, cl.wage 
                         FROM company_labours cl
                         JOIN site_updates s ON cl.site_update_id = s.id
                         WHERE s.user_id = ? AND cl.labour_name LIKE ?
                         ORDER BY cl.labour_name
                         LIMIT 10";
        
        $stmt = $conn->prepare($labour_query);
        $search_param = "%" . $search_term . "%";
        $stmt->bind_param("is", $user_id, $search_param);
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
            'name' => $labour['labour_name'],
            'mobile' => $labour['mobile'],
            'attendance' => $labour['attendance'],
            'ot_hours' => floatval($labour['ot_hours']),
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
                        $labour_attendance = $conn->real_escape_string($labour_data['attendance']);
                        $labour_ot_hours = floatval($labour_data['ot_hours']);
                        $labour_wage = floatval($labour_data['wage']);
                        $labour_ot_amount = floatval($labour_data['ot_amount']);
                        $labour_total = floatval($labour_data['total']);
                        
                        $labour_query = "INSERT INTO vendor_labours (vendor_id, labour_name, mobile, attendance, ot_hours, wage, ot_amount, total_amount) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $labour_stmt = $conn->prepare($labour_query);
                        $labour_stmt->bind_param("isssdddd", $vendor_db_id, $labour_name, $labour_mobile, $labour_attendance, $labour_ot_hours, $labour_wage, $labour_ot_amount, $labour_total);
                        $labour_stmt->execute();
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
                $labour_attendance = $conn->real_escape_string($labour_data['attendance']);
                $labour_ot_hours = floatval($labour_data['ot_hours']);
                $labour_wage = floatval($labour_data['wage']);
                $labour_ot_amount = floatval($labour_data['ot_amount']);
                $labour_total = floatval($labour_data['total']);
                
                $labour_query = "INSERT INTO company_labours (site_update_id, labour_name, mobile, attendance, ot_hours, wage, ot_amount, total_amount) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $labour_stmt = $conn->prepare($labour_query);
                $labour_stmt->bind_param("isssdddd", $site_update_id, $labour_name, $labour_mobile, $labour_attendance, $labour_ot_hours, $labour_wage, $labour_ot_amount, $labour_total);
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
                        $labour_attendance = $conn->real_escape_string($labour_data['attendance']);
                        $labour_ot_hours = floatval($labour_data['ot_hours']);
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
                                                attendance = ?, 
                                                ot_hours = ?, 
                                                wage = ?, 
                                                ot_amount = ?, 
                                                total_amount = ? 
                                                WHERE id = ? AND vendor_id = ?";
                            $labour_update_stmt = $conn->prepare($labour_update_query);
                            $labour_update_stmt->bind_param("sssddddii", $labour_name, $labour_mobile, $labour_attendance, $labour_ot_hours, $labour_wage, $labour_ot_amount, $labour_total, $labour_db_id, $vendor_db_id);
                            $labour_update_stmt->execute();
                        } else {
                            // Insert new labour
                            $labour_insert_query = "INSERT INTO vendor_labours (vendor_id, labour_name, mobile, attendance, ot_hours, wage, ot_amount, total_amount) 
                                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                            $labour_insert_stmt = $conn->prepare($labour_insert_query);
                            $labour_insert_stmt->bind_param("isssdddd", $vendor_db_id, $labour_name, $labour_mobile, $labour_attendance, $labour_ot_hours, $labour_wage, $labour_ot_amount, $labour_total);
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
                $labour_attendance = $conn->real_escape_string($labour_data['attendance']);
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
                                         wage = ?, 
                                         ot_amount = ?, 
                                         total_amount = ? 
                                         WHERE id = ? AND site_update_id = ?";
                    $labour_update_stmt = $conn->prepare($labour_update_query);
                    $labour_update_stmt->bind_param("sssddddii", $labour_name, $labour_mobile, $labour_attendance, $labour_ot_hours, $labour_wage, $labour_ot_amount, $labour_total, $labour_db_id, $site_update_id);
                    $labour_update_stmt->execute();
                } else {
                    // Insert new company labour
                    $labour_insert_query = "INSERT INTO company_labours (site_update_id, labour_name, mobile, attendance, ot_hours, wage, ot_amount, total_amount) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $labour_insert_stmt = $conn->prepare($labour_insert_query);
                    $labour_insert_stmt->bind_param("isssdddd", $site_update_id, $labour_name, $labour_mobile, $labour_attendance, $labour_ot_hours, $labour_wage, $labour_ot_amount, $labour_total);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <style>
        /* Ensure autocomplete dropdown appears on top */
        .ui-autocomplete {
            z-index: 9999 !important;
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        /* Style autocomplete items for better visibility */
        .ui-menu-item {
            padding: 3px;
        }
        
        /* Reset and Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            overflow-x: hidden;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }
        
        /* Left Panel/Sidebar Styles */
        .left-panel {
            width: 280px;
            background: linear-gradient(180deg, #2c3e50, #34495e);
            color: #fff;
            height: 100vh;
            transition: all 0.3s ease;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }
        
        .left-panel.collapsed {
            width: 70px;
        }
        
        .left-panel.collapsed + .main-content {
            margin-left: 70px;
        }
        
        .toggle-btn {
            position: absolute;
            right: -18px;
            top: 25px;
            background: #fff;
            border: none;
            color: #2c3e50;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .toggle-btn:hover {
            transform: scale(1.15);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            background: #f8f9fa;
        }
        
        .toggle-btn i {
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .toggle-btn:hover i {
            color: #1a237e;
            transform: scale(1.2);
        }
        
        .user-section {
            padding: 20px 15px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 10px;
        }
        
        .user-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 10px;
            background-color: #fff;
            padding: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .user-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .user-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-role {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .menu-item {
            padding: 16px 25px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            margin: 5px 0;
            position: relative;
            overflow: hidden;
        }
        
        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left: 4px solid #3498db;
            padding-left: 30px;
        }
        
        .menu-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 4px solid #3498db;
        }
        
        .menu-item::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: rgba(255, 255, 255, 0.1);
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s ease;
            z-index: 0;
        }
        
        .menu-item:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }
        
        .menu-item i {
            margin-right: 15px;
            width: 20px;
            font-size: 1.2em;
            text-align: center;
            position: relative;
            z-index: 1;
            color: #3498db;
        }
        
        .menu-text {
            transition: all 0.3s ease;
            font-size: 0.95em;
            letter-spacing: 0.3px;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        
        .collapsed .menu-text {
            display: none;
        }
        
        .menu-container {
            flex: 1;
            overflow-y: auto;
            padding: 10px 15px;
        }
        
        .menu-label {
            font-size: 12px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            margin: 15px 15px 10px;
            letter-spacing: 1px;
        }
        
        .menu-item.section-start {
            margin-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
        }
        
        .logout-item {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 0, 0, 0.1);
        }
        
        .logout-item:hover {
            background: rgba(255, 0, 0, 0.2);
            border-left: 4px solid #ff4444 !important;
        }
        
        .logout-item i {
            color: #ff4444 !important;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: all 0.3s ease;
            padding: 20px;
            background-color: #f5f7fa;
            min-height: 100vh;
        }
        
        .greeting-section {
            background: linear-gradient(135deg, #3498db, #2980b9);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .greeting-content {
            flex: 1;
        }
        
        .greeting-header h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .greeting-text {
            margin-right: 8px;
        }
        
        .user-name-text {
            font-weight: 700;
        }
        
        .current-time {
            font-size: 16px;
            margin-top: 8px;
            display: flex;
            align-items: center;
        }
        
        .time-icon {
            margin-right: 8px;
            font-size: 16px;
        }
        
        /* Content Section Styles */
        .content-section {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        /* Section Title Styles */
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 20px 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: #3498db;
            font-size: 20px;
        }
        
        /* Subsection Title Styles */
        .subsection-title {
            font-size: 16px;
            font-weight: 600;
            color: #444;
            margin: 15px 0 10px;
            display: flex;
            align-items: center;
            padding-bottom: 8px;
            border-bottom: 1px dashed #eee;
        }
        
        .subsection-title i {
            margin-right: 8px;
            color: #6f42c1;
            font-size: 16px;
        }
        
        /* Item container styles */
        .work-progress-container, .inventory-container, .vendor-container, .company-labour-container, .travel-allowance-container, .beverage-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #eee;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .work-progress-container:hover, .inventory-container:hover, .vendor-container:hover, .company-labour-container:hover, .travel-allowance-container:hover, .beverage-container:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #ddd;
        }
        
        /* Button styles enhancement */
        .btn-add-item {
            border-radius: 30px;
            font-weight: 500;
            letter-spacing: 0.3px;
            border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-add-item i {
            margin-right: 8px;
            font-size: 14px;
        }
        
        .btn-add-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-add-item:active {
            transform: translateY(0);
            box-shadow: 0 2px 3px rgba(0,0,0,0.1);
        }
        
        /* Item header styles */
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .item-header h4 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .item-header h4 i {
            margin-right: 8px;
            color: #6f42c1;
        }
        
        .remove-btn {
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .remove-btn:hover {
            background-color: #ffeaea;
            color: #dc3545;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .card-title {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 12px;
            font-size: 24px;
            color: #3498db;
        }
        
        .card-body {
            padding: 15px 0;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
            display: flex;
            align-items: center;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #6c757d;
            font-size: 14px;
        }
        
        /* Improve form element styles */
        .form-control {
            width: 100%;
            padding: 12px 15px;
            font-size: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
            outline: none;
        }
        
        select.form-control {
            padding-right: 30px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 500;
            text-align: center;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        /* Table Styles */
        .table-container {
            margin-top: 20px;
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        .data-table tr:hover {
            background-color: #f5f7fa;
        }
        
        .data-table .empty-row td {
            text-align: center;
            padding: 20px;
            color: #888;
        }
        
        /* Alert Styles */
        .alert-success, .alert-error {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .left-panel {
                width: 70px;
            }
            
            .left-panel:hover {
                width: 280px;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .user-name, .user-role, .menu-text, .menu-label {
                opacity: 0;
                display: none;
            }
            
            .left-panel:hover .user-name, 
            .left-panel:hover .user-role, 
            .left-panel:hover .menu-text, 
            .left-panel:hover .menu-label {
                opacity: 1;
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .greeting-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .greeting-content {
                margin-bottom: 15px;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .card-title {
                margin-bottom: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 15px;
            }
            
            .greeting-header h2 {
                font-size: 20px;
            }
            
            .form-control, .btn {
                padding: 10px;
            }
        }
        
        /* View Receipt Link Styles */
        .view-receipt {
            color: #3498db;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: color 0.2s ease;
        }
        
        .view-receipt i {
            margin-right: 5px;
        }
        
        .view-receipt:hover {
            color: #2980b9;
            text-decoration: underline;
        }
        
        .text-muted {
            color: #999;
            font-style: italic;
        }
        
        /* View Details Button Styles */
        .btn-view-details {
            background-color: transparent;
            color: #3498db;
            border: 1px solid #3498db;
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-view-details:hover {
            background-color: #3498db;
            color: white;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 25px;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }
        
        .close-modal {
            font-size: 24px;
            color: #aaa;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .detail-item {
            margin-bottom: 15px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            display: block;
        }
        
        .detail-value {
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        /* Enhanced Site Detail Modal Styles */
        .site-update-details-modal .site-detail-modal-content {
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            margin: 5% auto;
        }
        
        .site-detail-section {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .site-detail-section:last-child {
            border-bottom: none;
        }
        
        .site-detail-section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .site-detail-section-title i {
            margin-right: 10px;
            color: #3498db;
        }
        
        .site-detail-vendors-list, 
        .site-detail-company-labours-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .site-detail-vendor-card, 
        .site-detail-labour-card {
            background-color: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
        }
        
        .site-detail-vendor-header, 
        .site-detail-labour-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #ddd;
        }
        
        .site-detail-vendor-type, 
        .site-detail-labour-name {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }
        
        .site-detail-vendor-name, 
        .site-detail-labour-type {
            color: #666;
            font-size: 14px;
        }
        
        .site-detail-vendor-contact {
            margin-top: 5px;
            display: flex;
            align-items: center;
            color: #666;
            font-size: 14px;
        }
        
        .site-detail-vendor-contact i {
            margin-right: 5px;
            color: #3498db;
        }
        
        .site-detail-labour-list {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        
        .site-detail-labour-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 8px;
            background-color: #fff;
            border-radius: 5px;
            border: 1px solid #eee;
            transition: all 0.2s ease;
        }
        
        .site-detail-labour-row:hover {
            background-color: #f0f5ff;
        }
        
        .site-detail-labour-col {
            flex: 1;
            padding: 0 5px;
        }
        
        .site-detail-labour-col-label {
            font-size: 12px;
            color: #777;
            display: block;
            margin-bottom: 2px;
        }
        
        .site-detail-labour-col-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }
        
        .site-detail-expenses-summary {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid #eee;
        }
        
        .site-detail-summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .site-detail-summary-row:last-child {
            border-bottom: none;
        }
        
        .site-detail-summary-label {
            font-weight: 600;
            color: #555;
        }
        
        .site-detail-summary-value {
            font-weight: 600;
            color: #28a745;
        }
        
        .site-detail-grand-total {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #ddd;
        }
        
        .site-detail-grand-total .site-detail-summary-label,
        .site-detail-grand-total .site-detail-summary-value {
            font-size: 18px;
            color: #dc3545;
        }
        
        .site-detail-empty-message {
            text-align: center;
            padding: 15px;
            color: #777;
            font-style: italic;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        /* Add these styles at the end of your existing CSS */
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .add-update-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        /* Site Update Modal Styles */
        .modal-site-update {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-site-update .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 25px;
            border-radius: 10px;
            max-width: 800px;
            width: 90%;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .vendor-container {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #eee;
        }
        
        .vendor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .vendor-type {
            font-weight: 600;
            color: #333;
        }
        
        .remove-btn {
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .remove-btn:hover {
            color: #bd2130;
        }
        
        .labour-container {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border: 1px solid #eee;
        }
        
        .labour-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .btn-add-item {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }
        
        .btn-add-item:hover {
            background-color: #218838;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .col-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 10px;
        }
        
        .col-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
            padding: 0 10px;
        }
        
        .col-3 {
            flex: 0 0 25%;
            max-width: 25%;
            padding: 0 10px;
        }
        
        .vendor-type-select {
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .col-6, .col-4, .col-3 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            padding-top: 20px;
            margin-top: 20px;
            border-top: 1px solid #eee;
            gap: 10px;
        }
        
        .col-12 {
            flex: 0 0 100%;
            max-width: 100%;
            padding: 0 10px;
        }
        
        /* Additional section styles */
        .subsection-title {
            font-size: 16px;
            font-weight: 600;
            color: #444;
            margin: 15px 0 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .travel-allowance-container, .beverage-container, .company-labour-container {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #eee;
            position: relative;
        }
        
        .total-section {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #ddd;
        }
        
        .total-section strong {
            margin-right: 15px;
            font-size: 15px;
            color: #333;
        }
        
        .total-section span {
            font-size: 16px;
            font-weight: 600;
            color: #28a745;
        }
        
        .summary-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border: 1px solid #eee;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .summary-label {
            font-weight: 600;
            color: #333;
        }
        
        .summary-value {
            font-weight: 600;
            color: #28a745;
        }
        
        .grand-total {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 2px solid #ddd;
            font-size: 18px;
        }
        
        .grand-total .summary-label, .grand-total .summary-value {
            font-size: 18px;
            color: #dc3545;
        }
        
        /* Remove button in the corner */
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .remove-btn-corner {
            position: absolute;
            top: 10px;
            right: 10px;
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .remove-btn-corner:hover {
            color: #bd2130;
        }
        
        /* Work Progress Styles */
        .work-progress-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #eee;
            position: relative;
        }
        
        .work-progress-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            justify-content: center;
            position: sticky;
            bottom: 0;
            background-color: #f8f9fa;
            padding-bottom: 10px;
            z-index: 10;
        }
        
        .work-progress-buttons .btn-add-item {
            padding: 10px 15px;
            font-size: 15px;
            min-width: 160px;
            transition: all 0.3s ease;
        }
        
        .btn-add-civil {
            background-color: #28a745;
            color: white;
        }
        
        .btn-add-civil:hover {
            background-color: #218838;
        }
        
        .btn-add-interior {
            background-color: #007bff;
            color: white;
        }
        
        .btn-add-interior:hover {
            background-color: #0069d9;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .item-header h4 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .total-section {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #ddd;
        }
        
        #work-progress-list {
            max-height: 500px;
            overflow-y: auto;
            margin-bottom: 10px;
        }
        
        /* Inventory Styles */
        .inventory-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #eee;
            position: relative;
        }
        
        #inventory-list {
            max-height: 500px;
            overflow-y: auto;
            margin-bottom: 10px;
        }
        
        .inventory-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            justify-content: center;
            position: sticky;
            bottom: 0;
            background-color: #f8f9fa;
            padding-bottom: 10px;
            z-index: 10;
        }
        
        .btn-add-inventory {
            background-color: #6f42c1;
            color: white;
            padding: 10px 15px;
            font-size: 15px;
            min-width: 160px;
            transition: all 0.3s ease;
        }
        
        .btn-add-inventory:hover {
            background-color: #5a32a3;
        }
        
        /* Enhance card styles */
        .card {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .card-title {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 12px;
            font-size: 24px;
            color: #3498db;
        }
        
        .card-body {
            padding: 15px 0;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
            display: flex;
            align-items: center;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #6c757d;
            font-size: 14px;
        }
        
        /* Section Title Button Styles */
        .section-title .btn-add-item {
            margin-left: auto;
            padding: 6px 12px;
            font-size: 14px;
            border-radius: 6px;
            background-color: #28a745;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        
        .section-title .btn-add-item:hover {
            background-color: #218838;
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.15);
        }
        
        .section-title .btn-add-item i {
            color: white;
            margin-right: 5px;
            font-size: 12px;
        }
        
        /* Specific vendor and labour buttons */
        .section-title button.btn-add-item {
            background: linear-gradient(to bottom, #28a745, #218838);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            margin-left: auto;
        }
        
        .section-title button.btn-add-item:hover {
            background: linear-gradient(to bottom, #218838, #1e7e34);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            transform: translateY(-1px);
        }
        
        .section-title button.btn-add-item:active {
            transform: translateY(1px);
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .section-title button.btn-add-item i {
            color: white;
            margin-right: 6px;
            font-size: 12px;
        }
        
        /* Work Progress & Inventory Styles */
        .site-detail-work-progress-list,
        .site-detail-inventory-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .site-detail-work-item,
        .site-detail-inventory-item {
            background-color: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
        }
        
        .site-detail-work-header,
        .site-detail-inventory-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #ddd;
        }
        
        .site-detail-work-type,
        .site-detail-material-type {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }
        
        .site-detail-work-category,
        .site-detail-inventory-quantity {
            color: #666;
            font-size: 14px;
            margin-top: 3px;
        }
        
        .site-detail-work-status {
            font-size: 14px;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 500;
            display: inline-block;
        }
        
        .site-detail-work-status.completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .site-detail-work-status.in-progress {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .site-detail-work-status.not-started {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .site-detail-work-remarks,
        .site-detail-inventory-notes {
            margin: 10px 0;
            padding: 10px;
            background-color: #fff;
            border-radius: 5px;
            border: 1px solid #eee;
            font-size: 14px;
            color: #555;
        }
        
        /* Media Gallery Styles */
        .site-detail-media-gallery {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .site-detail-media-title {
            font-size: 14px;
            font-weight: 600;
            color: #555;
            margin-bottom: 10px;
        }
        
        .site-detail-media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
        }
        
        .site-detail-media-item {
            position: relative;
            border-radius: 5px;
            overflow: hidden;
            height: 120px;
            background-color: #eee;
            border: 1px solid #ddd;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .site-detail-media-item:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .site-detail-media-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .site-detail-media-item.video::after {
            content: '\f144';
            font-family: 'Font Awesome 5 Free';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 24px;
            color: #fff;
            text-shadow: 0 0 5px rgba(0, 0, 0, 0.7);
            z-index: 1;
        }
        
        /* Media Modal Styles */
        .site-detail-media-modal {
            display: none;
            position: fixed;
            z-index: 3000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.9);
        }
        
        .site-detail-media-modal-content {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            position: relative;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .site-detail-media-modal-content img {
            width: 100%;
            height: auto;
            max-height: 80vh;
            object-fit: contain;
        }
        
        .site-detail-media-modal-content video {
            width: 100%;
            height: auto;
            max-height: 80vh;
        }
        
        .site-detail-media-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            z-index: 3001;
        }
        
        /* Site Detail Modal Header Actions */
        .site-detail-header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .site-detail-edit-btn {
            padding: 8px 15px;
            font-size: 14px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
            background-color: #3498db;
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .site-detail-edit-btn:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
        }
        
        .site-detail-edit-btn:active {
            transform: translateY(0);
        }
        
        .site-detail-edit-btn i {
            font-size: 13px;
        }

        /* Improve existing files display in edit form */
        .existing-files-container {
            margin-top: 15px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }

        .existing-files-header {
            font-weight: 600;
            color: #555;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .existing-files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .existing-file-item {
            position: relative;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #ddd;
            height: 100px;
            background-color: #f8f9fa;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }

        .existing-file-item:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .existing-file-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .existing-file-item.video::before {
            content: '\f144';
            font-family: 'Font Awesome 5 Free';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 20px;
            color: #fff;
            text-shadow: 0 0 5px rgba(0,0,0,0.7);
            z-index: 1;
        }

        .existing-file-item .file-delete {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 20px;
            height: 20px;
            background-color: rgba(255,255,255,0.8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc3545;
            font-size: 12px;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s ease;
            z-index: 2;
        }

        .existing-file-item:hover .file-delete {
            opacity: 1;
        }

        .existing-file-item .file-delete:hover {
            background-color: #fff;
            color: #bd2130;
        }

        .site-detail-media-item,
        .existing-file-item {
            display: inline-block;
            vertical-align: top;
        }

        /* Ensure consistent styling between view and edit modes */
        #edit-existing-files-work-container img,
        #edit-existing-files-inventory-container img {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
        }

        /* Make the file inputs more attractive */
        input[type="file"].form-control {
            padding: 8px;
            background-color: #f8f9fa;
        }

        input[type="file"].form-control::-webkit-file-upload-button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        input[type="file"].form-control::-webkit-file-upload-button:hover {
            background-color: #2980b9;
        }

        .site-detail-labour-col {
            flex: 1;
            padding: 0 5px;
        }
        
        /* Column width adjustments for labor tables */
        .site-detail-labour-row .site-detail-labour-col:nth-child(1) { /* # column */
            flex: 0 0 5%;
            max-width: 5%;
        }
        
        .site-detail-labour-row .site-detail-labour-col:nth-child(2) { /* Name column */
            flex: 0 0 20%;
            max-width: 20%;
        }
        
        .site-detail-labour-row .site-detail-labour-col:nth-child(3) { /* Mobile column */
            flex: 0 0 15%;
            max-width: 15%;
        }
        
        .site-detail-labour-col-label {
            font-size: 12px;
            color: #777;
            display: block;
            margin-bottom: 2px;
        }
    </style>
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
    
    <script>
        // Add this function to safely get DOM elements
        function safeQuerySelector(selector) {
            const element = document.querySelector(selector);
            return element;
        }

        // Function to toggle sidebar panel
        function togglePanel() {
            const leftPanel = document.getElementById('leftPanel');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (leftPanel) {
                leftPanel.classList.toggle('collapsed');
                if (leftPanel.classList.contains('collapsed')) {
                    toggleIcon.classList.remove('fa-chevron-left');
                    toggleIcon.classList.add('fa-chevron-right');
                } else {
                    toggleIcon.classList.remove('fa-chevron-right');
                    toggleIcon.classList.add('fa-chevron-left');
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize totals
            if (typeof updateTotalWages === 'function') {
                updateTotalWages();
                updateTravellingAllowancesTotal();
                updateBeveragesTotal();
                updateMiscExpensesTotal();
                updateGrandTotal();
            }

            // Initialize counters
            window.vendorCounter = 0;
            window.labourCounter = 0;
            window.companyLabourCounter = 0;
            window.travelAllowanceCounter = 0;
            window.beverageCounter = 0;
            window.workProgressCounter = 0;
            window.inventoryCounter = 0;
            
            // Initialize custom scroll function
            window.smoothScrollToElement = function(element, offset = 50) {
                if (!element) return;
                
                // Get element position
                const rect = element.getBoundingClientRect();
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                const targetTop = rect.top + scrollTop - offset;
                
                // Scroll to element
                window.scrollTo({
                    top: targetTop,
                    behavior: 'smooth'
                });
            };

            // Toggle sidebar on small screens
            const menuItems = document.querySelectorAll('.menu-item');
            if (menuItems) {
                menuItems.forEach(item => {
                    item.addEventListener('click', function() {
                        if (window.innerWidth <= 992) {
                            const leftPanel = document.querySelector('.left-panel');
                            if (leftPanel) {
                                leftPanel.classList.toggle('collapsed');
                            }
                        }
                    });
                });
            }
            
            // Highlight active menu item
            const currentPath = window.location.pathname;
            const menuLinks = document.querySelectorAll('.menu-item');
            
            if (menuLinks) {
                menuLinks.forEach(link => {
                    if (link.getAttribute('onclick') && 
                        link.getAttribute('onclick').includes(currentPath.split('/').pop())) {
                        link.classList.add('active');
                    }
                });
            }
            
            // Modal functionality
            const updateDetailsModal = safeQuerySelector('#updateDetailsModal');
            const closeBtn = safeQuerySelector('.close-modal');
            const siteUpdateModal = safeQuerySelector('#siteUpdateModal');
            
            if (closeBtn) {
                closeBtn.onclick = function() {
                    if (updateDetailsModal) {
                        updateDetailsModal.style.display = 'none';
                    }
                }
            }
            
            // Event delegation for window clicks
            window.onclick = function(event) {
                if (updateDetailsModal && event.target === updateDetailsModal) {
                    updateDetailsModal.style.display = 'none';
                } else if (siteUpdateModal && event.target === siteUpdateModal) {
                    if (typeof hideSiteUpdateModal === 'function') {
                        hideSiteUpdateModal();
                    }
                }
            }
        });
        
        // Add vendor
        function addVendor() {
            window.vendorCounter++;
            const vendorsContainer = document.getElementById('vendors-container');
            
            const vendorDiv = document.createElement('div');
            vendorDiv.className = 'vendor-container';
            vendorDiv.id = `vendor-${window.vendorCounter}`;
            
            vendorDiv.innerHTML = `
                <div class="vendor-header">
                    <div class="vendor-type-select">
                        <label for="vendor-type-${window.vendorCounter}">Vendor Service</label>
                        <select class="form-control vendor-type-select" id="vendor-type-${window.vendorCounter}" name="vendors[${window.vendorCounter}][type]" required>
                            <option value="">Select Vendor Type</option>
                            <option value="POP">POP</option>
                            <option value="Tile">Tile</option>
                            <option value="Electrical">Electrical</option>
                            <option value="Plumbing">Plumbing</option>
                            <option value="Carpentry">Carpentry</option>
                            <option value="Painting">Painting</option>
                            <option value="HVAC">HVAC</option>
                            <option value="Flooring">Flooring</option>
                            <option value="Roofing">Roofing</option>
                            <option value="Masonry">Masonry</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <button type="button" class="remove-btn" onclick="removeVendor(${window.vendorCounter})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="form-group">
                    <label for="vendor-name-${window.vendorCounter}">Vendor Name</label>
                    <input type="text" class="form-control vendor-name-input" id="vendor-name-${window.vendorCounter}" name="vendors[${window.vendorCounter}][name]" required autocomplete="off">
                    <div class="search-results" id="vendor-search-results-${window.vendorCounter}"></div>
                </div>
                <div class="form-group">
                    <label for="vendor-contact-${window.vendorCounter}">Contact Number</label>
                    <input type="text" class="form-control" id="vendor-contact-${window.vendorCounter}" name="vendors[${window.vendorCounter}][contact]">
                </div>
                
                <div class="vendor-labours" id="vendor-labours-${window.vendorCounter}">
                    <!-- Labours will be added here -->
                </div>
                <button type="button" class="btn-add-item" onclick="addLabour(${window.vendorCounter})">
                    <i class="fas fa-plus"></i> Add Labour
                </button>
            `;
            
            vendorsContainer.appendChild(vendorDiv);
            
            // Initialize autocomplete for vendor name input
            initVendorAutocomplete(window.vendorCounter);
        }
        
        // Initialize autocomplete for vendor name field
        function initVendorAutocomplete(vendorId) {
            const vendorNameInput = document.getElementById(`vendor-name-${vendorId}`);
            const vendorTypeSelect = document.getElementById(`vendor-type-${vendorId}`);
            const vendorContactInput = document.getElementById(`vendor-contact-${vendorId}`);
            
            // Setup autocomplete for vendor name field
            $(vendorNameInput).autocomplete({
                source: function(request, response) {
                    // Make AJAX call to get vendor data
                    $.ajax({
                        url: 'site_expenses.php',
                        dataType: 'json',
                        data: {
                            action: 'get_vendor_labour_data',
                            type: 'vendor',
                            term: request.term
                        },
                        success: function(data) {
                            if (data.success) {
                                response($.map(data.data, function(item) {
                                    return {
                                        label: item.name + ' (' + item.type + ')',
                                        value: item.name,
                                        vendor: item
                                    };
                                }));
                            } else {
                                response([]);
                            }
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    // Auto-fill vendor details
                    const vendor = ui.item.vendor;
                    
                    // Set vendor type
                    if (vendor.type) {
                        vendorTypeSelect.value = vendor.type;
                    }
                    
                    // Set contact
                    if (vendor.contact) {
                        vendorContactInput.value = vendor.contact;
                    }
                    
                    return true;
                }
            });
            
            // Also trigger search when vendor type changes
            vendorTypeSelect.addEventListener('change', function() {
                if (vendorNameInput.value.length > 0) {
                    $(vendorNameInput).autocomplete('search', vendorNameInput.value);
                }
            });
        }
        
        // Remove vendor
        function removeVendor(id) {
            const vendorDiv = document.getElementById(`vendor-${id}`);
            vendorDiv.remove();
            // Update totals
            updateVendorTotals();
            updateGrandTotal();
        }

        // Function to update vendor totals
        function updateVendorTotals() {
            // This function triggers the calculation of total wages
            updateTotalWages();
        }
        
        // Add labour to vendor
        function addLabour(vendorId) {
            window.labourCounter++;
            const labourContainer = document.getElementById(`vendor-labours-${vendorId}`);
            
            // Count existing labor items for this vendor to determine number
            const existingLabors = labourContainer.querySelectorAll('.labour-container').length;
            const laborNumber = existingLabors + 1;
            
            const labourDiv = document.createElement('div');
            labourDiv.className = 'labour-container';
            labourDiv.id = `labour-${window.labourCounter}`;
            
            labourDiv.innerHTML = `
                <div class="labour-header">
                    <strong>Labour #${laborNumber}</strong>
                    <button type="button" class="remove-btn" onclick="removeLabour(${window.labourCounter}, ${vendorId})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="labour-name-${window.labourCounter}">Labour Name</label>
                            <input type="text" class="form-control labour-name-input" id="labour-name-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][name]" required autocomplete="off">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="labour-mobile-${window.labourCounter}">Mobile Number</label>
                            <input type="text" class="form-control" id="labour-mobile-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][mobile]">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-3">
                        <div class="form-group">
                            <label for="labour-attendance-${window.labourCounter}">Attendance</label>
                            <select class="form-control" id="labour-attendance-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][attendance]" required onchange="calculateLabourTotal(${window.labourCounter})">
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Half-day">Half-day</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="labour-ot-hours-${window.labourCounter}">OT Hours</label>
                            <input type="number" class="form-control" id="labour-ot-hours-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][ot_hours]" value="0" min="0" step="0.5" onchange="calculateLabourTotal(${window.labourCounter})">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="labour-wage-${window.labourCounter}">Wage (₹)</label>
                            <input type="number" class="form-control" id="labour-wage-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][wage]" value="0" min="0" required onchange="calculateLabourTotal(${window.labourCounter})">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="labour-ot-amount-${window.labourCounter}">OT Amount (₹)</label>
                            <input type="number" class="form-control" id="labour-ot-amount-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][ot_amount]" value="0" min="0" readonly>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="labour-total-${window.labourCounter}">Total Amount (₹)</label>
                            <input type="number" class="form-control vendor-labour-total" id="labour-total-${window.labourCounter}" name="vendors[${vendorId}][labours][${window.labourCounter}][total]" value="0" min="0" readonly>
                        </div>
                    </div>
                </div>
            `;
            
            labourContainer.appendChild(labourDiv);
            
            // Initialize autocomplete for labour name
            initLabourAutocomplete(window.labourCounter, vendorId);
        }
        
        // Initialize autocomplete for labour name field
        function initLabourAutocomplete(labourId, vendorId) {
            const labourNameInput = document.getElementById(`labour-name-${labourId}`);
            const labourMobileInput = document.getElementById(`labour-mobile-${labourId}`);
            const labourWageInput = document.getElementById(`labour-wage-${labourId}`);
            const vendorTypeSelect = document.getElementById(`vendor-type-${vendorId}`);
            const vendorNameInput = document.getElementById(`vendor-name-${vendorId}`);
            
            // Get vendor type and name for better search results
            const vendorType = vendorTypeSelect ? vendorTypeSelect.value : '';
            const vendorName = vendorNameInput ? vendorNameInput.value : '';
            
            // Setup autocomplete for labour name field
            $(labourNameInput).autocomplete({
                source: function(request, response) {
                    // Make AJAX call to get labour data
                    $.ajax({
                        url: 'site_expenses.php',
                        dataType: 'json',
                        data: {
                            action: 'get_vendor_labour_data',
                            type: 'vendor_labour',
                            term: request.term,
                            vendor_type: vendorType,
                            vendor_name: vendorName
                        },
                        success: function(data) {
                            if (data.success) {
                                response($.map(data.data, function(item) {
                                    return {
                                        label: item.name,
                                        value: item.name,
                                        labour: item
                                    };
                                }));
                            } else {
                                response([]);
                            }
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    // Auto-fill labour details
                    const labour = ui.item.labour;
                    
                    // Set mobile number
                    if (labour.mobile) {
                        labourMobileInput.value = labour.mobile;
                    }
                    
                    // Set wage
                    if (labour.wage) {
                        labourWageInput.value = labour.wage;
                        // Recalculate total
                        calculateLabourTotal(labourId);
                    }
                    
                    return true;
                }
            });
        }
        
        // Remove labour
        function removeLabour(id, vendorId) {
            const labourDiv = document.getElementById(`labour-${id}`);
            labourDiv.remove();
            
            // Update numbers for remaining labor items
            if (vendorId) {
                const labourContainer = document.getElementById(`vendor-labours-${vendorId}`);
                if (labourContainer) {
                    const laborItems = labourContainer.querySelectorAll('.labour-container');
                    laborItems.forEach((item, index) => {
                        const headerEl = item.querySelector('.labour-header strong');
                        if (headerEl) {
                            headerEl.textContent = `Labour #${index + 1}`;
                        }
                    });
                }
            }
            
            // Update totals
            updateTotalWages();
            updateGrandTotal();
        }
        
        // Calculate labour totals
        function calculateLabourTotal(labourId) {
            const attendanceSelect = document.getElementById(`labour-attendance-${labourId}`);
            const otHoursInput = document.getElementById(`labour-ot-hours-${labourId}`);
            const wageInput = document.getElementById(`labour-wage-${labourId}`);
            const otAmountInput = document.getElementById(`labour-ot-amount-${labourId}`);
            const totalInput = document.getElementById(`labour-total-${labourId}`);
            
            const attendance = attendanceSelect.value;
            const otHours = parseFloat(otHoursInput.value) || 0;
            const wage = parseFloat(wageInput.value) || 0;
            
            // Calculate attendance factor
            let attendanceFactor = 1;
            if (attendance === 'Absent') {
                attendanceFactor = 0;
            } else if (attendance === 'Half-day') {
                attendanceFactor = 0.5;
            }
            
            // Calculate OT amount (1.5x regular wage)
            const otRate = wage / 8 * 1.5; // Assuming 8-hour workday
            const otAmount = otHours * otRate;
            
            // Calculate total
            const total = (wage * attendanceFactor) + otAmount;
            
            // Update fields
            otAmountInput.value = otAmount.toFixed(2);
            totalInput.value = total.toFixed(2);
            
            // Update total wages and grand total
            updateTotalWages();
        }
        
        // Modal functionality
        const modal = document.getElementById('updateDetailsModal');
        const closeBtn = document.querySelector('.close-modal');
        
        function viewUpdateDetails(siteName, date, details) {
            const modalSiteName = document.getElementById('modalSiteName');
            const modalDate = document.getElementById('modalDate');
            const modalDetails = document.getElementById('modalDetails');
            const modal = document.getElementById('updateDetailsModal');
            
            if (modalSiteName) modalSiteName.textContent = siteName;
            if (modalDate) modalDate.textContent = date;
            if (modalDetails) modalDetails.textContent = details;
            if (modal) modal.style.display = 'block';
            
            // Fetch and populate the additional site update details
            fetchSiteUpdateDetails(siteName, date);
        }
        
        function fetchSiteUpdateDetails(siteName, date) {
            // Make an AJAX call to get the site update details
            fetch(`site_expenses.php?action=get_site_update_details&site_name=${encodeURIComponent(siteName)}&date=${encodeURIComponent(date)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(responseData => {
                    if (responseData.success) {
                        populateSiteUpdateDetails(responseData.data);
                    } else {
                        console.error('Error fetching details:', responseData.message);
                        // Show a message in the modal
                        const vendorsList = document.getElementById('modalVendorsList');
                        const companyLaboursList = document.getElementById('modalCompanyLaboursList');
                        
                        if (vendorsList) vendorsList.innerHTML = '<div class="site-detail-empty-message">No vendor details available.</div>';
                        if (companyLaboursList) companyLaboursList.innerHTML = '<div class="site-detail-empty-message">No company labour details available.</div>';
                        
                        // Hide expenses section
                        const expensesSection = document.getElementById('modalExpensesSection');
                        if (expensesSection) expensesSection.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error fetching site update details:', error);
                    // Show a fallback message using demo data
                    console.log('Using demo data for display...');
                    
                    // Fallback to demo data for display purposes
                    const demoData = {
                        vendors: [
                            {
                                type: "POP",
                                name: "Mahaveer Enterprises",
                                contact: "9876543210",
                                labours: [
                                    {
                                        name: "Ramesh Kumar",
                                        mobile: "8765432109",
                                        attendance: "Present",
                                        ot_hours: 2,
                                        wage: 500,
                                        ot_amount: 125,
                                        total: 625
                                    },
                                    {
                                        name: "Suresh Singh",
                                        mobile: "7654321098",
                                        attendance: "Half-day",
                                        ot_hours: 0,
                                        wage: 450,
                                        ot_amount: 0,
                                        total: 225
                                    }
                                ]
                            },
                            {
                                type: "Electrical",
                                name: "Power Solutions",
                                contact: "9876543211",
                                labours: [
                                    {
                                        name: "Vijay Kapoor",
                                        mobile: "8765432108",
                                        attendance: "Present",
                                        ot_hours: 1.5,
                                        wage: 600,
                                        ot_amount: 112.5,
                                        total: 712.5
                                    }
                                ]
                            }
                        ],
                        company_labours: [
                            {
                                name: "Rahul Sharma",
                                mobile: "9876543212",
                                attendance: "Present",
                                ot_hours: 2,
                                wage: 700,
                                ot_amount: 175,
                                total: 875
                            },
                            {
                                name: "Amit Patel",
                                mobile: "9876543213",
                                attendance: "Present",
                                ot_hours: 0,
                                wage: 650,
                                ot_amount: 0,
                                total: 650
                            }
                        ],
                        expenses: {
                            total_wages: 3087.5,
                            total_misc_expenses: 1250,
                            grand_total: 4337.5
                        }
                    };
                    
                    populateSiteUpdateDetails(demoData);
                });
        }
        
        function populateSiteUpdateDetails(data) {
            // Populate vendors section
            const vendorsList = document.getElementById('modalVendorsList');
            const vendorsSection = document.getElementById('modalVendorsSection');
            
            if (vendorsList && data.vendors && data.vendors.length > 0) {
                vendorsList.innerHTML = '';
                
                data.vendors.forEach(vendor => {
                    const vendorCard = document.createElement('div');
                    vendorCard.className = 'site-detail-vendor-card';
                    
                    let vendorHTML = `
                        <div class="site-detail-vendor-header">
                            <div style="display: flex; align-items: center;">
                                <div style="margin-right: 10px; font-weight: bold; min-width: 25px; height: 25px; background-color: #3498db; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    ${data.vendors.indexOf(vendor) + 1}
                                </div>
                                <div>
                                    <div class="site-detail-vendor-type">${vendor.type}</div>
                                    <div class="site-detail-vendor-name">${vendor.name}</div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    if (vendor.contact) {
                        vendorHTML += `
                            <div class="site-detail-vendor-contact">
                                <i class="fas fa-phone"></i> ${vendor.contact}
                            </div>
                        `;
                    }
                    
                    // Add vendor labours if any
                    if (vendor.labours && vendor.labours.length > 0) {
                        vendorHTML += `<div class="site-detail-labour-list">`;
                        
                        // Header row
                        vendorHTML += `
                            <div class="site-detail-labour-row" style="background-color: #f0f0f0; font-weight: 600;">
                                <div class="site-detail-labour-col" style="width: 5%;">
                                    <span class="site-detail-labour-col-label">#</span>
                                </div>
                                <div class="site-detail-labour-col">
                                    <span class="site-detail-labour-col-label">Name</span>
                                </div>
                                <div class="site-detail-labour-col">
                                    <span class="site-detail-labour-col-label">Mobile</span>
                                </div>
                                <div class="site-detail-labour-col">
                                    <span class="site-detail-labour-col-label">Attendance</span>
                                </div>
                                <div class="site-detail-labour-col">
                                    <span class="site-detail-labour-col-label">OT Hours</span>
                                </div>
                                <div class="site-detail-labour-col">
                                    <span class="site-detail-labour-col-label">Wage (₹)</span>
                                </div>
                                <div class="site-detail-labour-col">
                                    <span class="site-detail-labour-col-label">Total (₹)</span>
                                </div>
                            </div>
                        `;
                        
                        // Data rows
                        vendor.labours.forEach((labour, laborIndex) => {
                            vendorHTML += `
                                <div class="site-detail-labour-row">
                                    <div class="site-detail-labour-col" style="width: 5%;">
                                        <span class="site-detail-labour-col-value">${laborIndex + 1}</span>
                                    </div>
                                    <div class="site-detail-labour-col">
                                        <span class="site-detail-labour-col-value">${labour.name}</span>
                                    </div>
                                    <div class="site-detail-labour-col">
                                        <span class="site-detail-labour-col-value">${labour.mobile ? labour.mobile : '-'}</span>
                                    </div>
                                    <div class="site-detail-labour-col">
                                        <span class="site-detail-labour-col-value">${labour.attendance}</span>
                                    </div>
                                    <div class="site-detail-labour-col">
                                        <span class="site-detail-labour-col-value">${labour.ot_hours}</span>
                                    </div>
                                    <div class="site-detail-labour-col">
                                        <span class="site-detail-labour-col-value">${labour.wage}</span>
                                    </div>
                                    <div class="site-detail-labour-col">
                                        <span class="site-detail-labour-col-value">${labour.total}</span>
                                    </div>
                                </div>
                            `;
                        });
                        
                        vendorHTML += `</div>`;
                    }
                    
                    vendorCard.innerHTML = vendorHTML;
                    vendorsList.appendChild(vendorCard);
                });
                
                vendorsSection.style.display = 'block';
            } else if (vendorsSection) {
                vendorsList.innerHTML = '<div class="site-detail-empty-message">No vendors found for this update.</div>';
                vendorsSection.style.display = 'block';
            }
            
            // Populate company labours section
            const companyLaboursList = document.getElementById('modalCompanyLaboursList');
            const companyLaboursSection = document.getElementById('modalCompanyLaboursSection');
            
            if (companyLaboursList && data.company_labours && data.company_labours.length > 0) {
                companyLaboursList.innerHTML = '';
                
                let companyLaboursHTML = `
                    <div class="site-detail-labour-card">
                        <div class="site-detail-labour-list">
                            <div class="site-detail-labour-row" style="background-color: #f0f0f0; font-weight: 600;">
                                <div class="site-detail-labour-col" style="width: 5%;">
                                    <span class="site-detail-labour-col-label">#</span>
                                </div>
                                <div class="site-detail-labour-col">
                                    <span class="site-detail-labour-col-label">Name</span>
                                </div>
                                <div class="site-detail-labour-col">
                                    <span class="site-detail-labour-col-label">Mobile</span>
                                </div>
                                <div class="site-detail-labour-col">
                                    <span class="site-detail-labour-col-label">Attendance</span>
                                </div>
                                <div class="site-detail-labour-col">
                                    <span class="site-detail-labour-col-label">OT Hours</span>
                                </div>
                                <div class="site-detail-labour-col">
                                    <span class="site-detail-labour-col-label">Wage (₹)</span>
                                </div>
                                <div class="site-detail-labour-col">
                                    <span class="site-detail-labour-col-label">Total (₹)</span>
                                </div>
                            </div>
                `;
                
                data.company_labours.forEach((labour, labourIndex) => {
                    companyLaboursHTML += `
                        <div class="site-detail-labour-row">
                            <div class="site-detail-labour-col" style="width: 5%;">
                                <span class="site-detail-labour-col-value">${labourIndex + 1}</span>
                            </div>
                            <div class="site-detail-labour-col">
                                <span class="site-detail-labour-col-value">${labour.name}</span>
                            </div>
                            <div class="site-detail-labour-col">
                                <span class="site-detail-labour-col-value">${labour.mobile ? labour.mobile : '-'}</span>
                            </div>
                            <div class="site-detail-labour-col">
                                <span class="site-detail-labour-col-value">${labour.attendance}</span>
                            </div>
                            <div class="site-detail-labour-col">
                                <span class="site-detail-labour-col-value">${labour.ot_hours}</span>
                            </div>
                            <div class="site-detail-labour-col">
                                <span class="site-detail-labour-col-value">${labour.wage}</span>
                            </div>
                            <div class="site-detail-labour-col">
                                <span class="site-detail-labour-col-value">${labour.total}</span>
                            </div>
                        </div>
                    `;
                });
                
                companyLaboursHTML += `</div></div>`;
                companyLaboursList.innerHTML = companyLaboursHTML;
                companyLaboursSection.style.display = 'block';
            } else if (companyLaboursSection) {
                companyLaboursList.innerHTML = '<div class="site-detail-empty-message">No company labours found for this update.</div>';
                companyLaboursSection.style.display = 'block';
            }
            
            // Populate work progress section
            const workProgressList = document.getElementById('modalWorkProgressList');
            const workProgressSection = document.getElementById('modalWorkProgressSection');
            
            if (workProgressList && data.work_progress && data.work_progress.length > 0) {
                workProgressList.innerHTML = '';
                
                data.work_progress.forEach((item, index) => {
                    const workItem = document.createElement('div');
                    workItem.className = 'site-detail-work-item';
                    
                    // Determine status class
                    let statusClass = 'not-started';
                    if (item.status === 'Yes') {
                        statusClass = 'completed';
                    } else if (item.status === 'In Progress') {
                        statusClass = 'in-progress';
                    }
                    
                    let workHTML = `
                        <div class="site-detail-work-header">
                            <div style="display: flex; align-items: center;">
                                <div style="margin-right: 10px; font-weight: bold; min-width: 25px; height: 25px; background-color: #3498db; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    ${index + 1}
                                </div>
                                <div>
                                    <div class="site-detail-work-type">${item.work_type}</div>
                                    <div class="site-detail-work-category">${item.category}</div>
                                </div>
                            </div>
                            <div class="site-detail-work-status ${statusClass}">
                                ${item.status}
                            </div>
                        </div>
                    `;
                    
                    if (item.remarks && item.remarks.trim() !== '') {
                        workHTML += `
                            <div class="site-detail-work-remarks">
                                <strong>Remarks:</strong> ${item.remarks}
                            </div>
                        `;
                    }
                    
                    // Add media files if any
                    if (item.files && item.files.length > 0) {
                        workHTML += `
                            <div class="site-detail-media-gallery">
                                <div class="site-detail-media-title">Photos/Videos</div>
                                <div class="site-detail-media-grid">
                        `;
                        
                        item.files.forEach((file, fileIndex) => {
                            const isVideo = file.type === 'video';
                            const mediaClass = isVideo ? 'video' : 'image';
                            const mediaId = `work-media-${index}-${fileIndex}`;
                            
                            workHTML += `
                                <div class="site-detail-media-item ${mediaClass}" id="${mediaId}" onclick="openMediaModal('${file.path}', '${file.type}')">
                                    ${isVideo ? 
                                        `<img src="images/video-thumbnail.jpg" alt="Video thumbnail">` : 
                                        `<img src="${file.path}" alt="Work progress image">`}
                                </div>
                            `;
                        });
                        
                        workHTML += `
                                </div>
                            </div>
                        `;
                    }
                    
                    workItem.innerHTML = workHTML;
                    workProgressList.appendChild(workItem);
                });
                
                workProgressSection.style.display = 'block';
            } else if (workProgressSection) {
                workProgressList.innerHTML = '<div class="site-detail-empty-message">No work progress data found for this update.</div>';
                workProgressSection.style.display = 'block';
            }
            
            // Populate inventory section
            const inventoryList = document.getElementById('modalInventoryList');
            const inventorySection = document.getElementById('modalInventorySection');
            
            if (inventoryList && data.inventory && data.inventory.length > 0) {
                inventoryList.innerHTML = '';
                
                data.inventory.forEach((item, index) => {
                    const inventoryItem = document.createElement('div');
                    inventoryItem.className = 'site-detail-inventory-item';
                    
                    let inventoryHTML = `
                        <div class="site-detail-inventory-header">
                            <div style="display: flex; align-items: center;">
                                <div style="margin-right: 10px; font-weight: bold; min-width: 25px; height: 25px; background-color: #3498db; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    ${index + 1}
                                </div>
                                <div>
                                    <div class="site-detail-material-type">${item.material}</div>
                                    <div class="site-detail-inventory-quantity">${item.quantity} ${item.unit}</div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    if (item.standard_values && item.standard_values.trim() !== '') {
                        inventoryHTML += `
                            <div class="site-detail-inventory-notes">
                                <strong>Notes:</strong> ${item.standard_values}
                            </div>
                        `;
                    }
                    
                    // Add media files if any
                    if (item.files && item.files.length > 0) {
                        inventoryHTML += `
                            <div class="site-detail-media-gallery">
                                <div class="site-detail-media-title">Photos/Videos</div>
                                <div class="site-detail-media-grid">
                        `;
                        
                        item.files.forEach((file, fileIndex) => {
                            const isVideo = file.type === 'video';
                            const mediaClass = isVideo ? 'video' : 'image';
                            const mediaId = `inventory-media-${index}-${fileIndex}`;
                            
                            inventoryHTML += `
                                <div class="site-detail-media-item ${mediaClass}" id="${mediaId}" onclick="openMediaModal('${file.path}', '${file.type}')">
                                    ${isVideo ? 
                                        `<img src="images/video-thumbnail.jpg" alt="Video thumbnail">` : 
                                        `<img src="${file.path}" alt="Inventory image">`}
                                </div>
                            `;
                        });
                        
                        inventoryHTML += `
                                </div>
                            </div>
                        `;
                    }
                    
                    inventoryItem.innerHTML = inventoryHTML;
                    inventoryList.appendChild(inventoryItem);
                });
                
                inventorySection.style.display = 'block';
            } else if (inventorySection) {
                inventoryList.innerHTML = '<div class="site-detail-empty-message">No inventory data found for this update.</div>';
                inventorySection.style.display = 'block';
            }
            
            // Populate expenses summary
            if (data.expenses) {
                const totalWages = document.getElementById('modalTotalWages');
                const totalMiscExpenses = document.getElementById('modalTotalMiscExpenses');
                const grandTotal = document.getElementById('modalGrandTotal');
                
                if (totalWages) totalWages.textContent = '₹' + data.expenses.total_wages.toFixed(2);
                if (totalMiscExpenses) totalMiscExpenses.textContent = '₹' + data.expenses.total_misc_expenses.toFixed(2);
                if (grandTotal) grandTotal.textContent = '₹' + data.expenses.grand_total.toFixed(2);
                
                document.getElementById('modalExpensesSection').style.display = 'block';
            } else {
                document.getElementById('modalExpensesSection').style.display = 'none';
            }
        }
        
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            } else if (event.target === document.getElementById('siteUpdateModal')) {
                hideSiteUpdateModal();
            }
        }

        // Company Labour counter
        // Remove duplicate declarations
        
        // Add company labour
        function addCompanyLabour() {
            window.companyLabourCounter++;
            const container = document.getElementById('company-labours-container');
            
            // Count existing labor items to determine number
            const existingLabors = container.querySelectorAll('.company-labour-container').length;
            const laborNumber = existingLabors + 1;
            
            const labourDiv = document.createElement('div');
            labourDiv.className = 'company-labour-container';
            labourDiv.id = `company-labour-${window.companyLabourCounter}`;
            
            labourDiv.innerHTML = `
                <button type="button" class="remove-btn-corner" onclick="removeCompanyLabour(${window.companyLabourCounter})">
                    <i class="fas fa-times"></i>
                </button>
                <h4 style="margin-bottom: 15px; font-size: 16px; color: #333;">Company Labour #${laborNumber}</h4>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="company-labour-name-${window.companyLabourCounter}">Labour Name</label>
                            <input type="text" class="form-control company-labour-name-input" id="company-labour-name-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][name]" required autocomplete="off">
                            </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="company-labour-mobile-${window.companyLabourCounter}">Mobile Number</label>
                            <input type="text" class="form-control" id="company-labour-mobile-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][mobile]">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-3">
                        <div class="form-group">
                            <label for="company-labour-attendance-${window.companyLabourCounter}">Attendance</label>
                            <select class="form-control" id="company-labour-attendance-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][attendance]" required onchange="calculateCompanyLabourTotal(${window.companyLabourCounter})">
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Half-day">Half-day</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="company-labour-ot-hours-${window.companyLabourCounter}">OT Hours</label>
                            <input type="number" class="form-control" id="company-labour-ot-hours-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][ot_hours]" value="0" min="0" step="0.5" onchange="calculateCompanyLabourTotal(${window.companyLabourCounter})">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="company-labour-wage-${window.companyLabourCounter}">Wage (₹)</label>
                            <input type="number" class="form-control" id="company-labour-wage-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][wage]" value="0" min="0" required onchange="calculateCompanyLabourTotal(${window.companyLabourCounter})">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="company-labour-ot-amount-${window.companyLabourCounter}">OT Amount (₹)</label>
                            <input type="number" class="form-control" id="company-labour-ot-amount-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][ot_amount]" value="0" min="0" readonly>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="company-labour-total-${window.companyLabourCounter}">Total Amount (₹)</label>
                            <input type="number" class="form-control company-labour-total" id="company-labour-total-${window.companyLabourCounter}" name="company_labours[${window.companyLabourCounter}][total]" value="0" min="0" readonly data-id="${window.companyLabourCounter}">
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(labourDiv);
            
            // Initialize autocomplete for company labour name
            initCompanyLabourAutocomplete(window.companyLabourCounter);
        }
        
        // Initialize autocomplete for company labour name field
        function initCompanyLabourAutocomplete(labourId) {
            const labourNameInput = document.getElementById(`company-labour-name-${labourId}`);
            const labourMobileInput = document.getElementById(`company-labour-mobile-${labourId}`);
            const labourWageInput = document.getElementById(`company-labour-wage-${labourId}`);
            
            // Setup autocomplete for company labour name field
            $(labourNameInput).autocomplete({
                source: function(request, response) {
                    // Make AJAX call to get company labour data
                    $.ajax({
                        url: 'site_expenses.php',
                        dataType: 'json',
                        data: {
                            action: 'get_vendor_labour_data',
                            type: 'company_labour',
                            term: request.term
                        },
                        success: function(data) {
                            if (data.success) {
                                response($.map(data.data, function(item) {
                                    return {
                                        label: item.name,
                                        value: item.name,
                                        labour: item
                                    };
                                }));
                            } else {
                                response([]);
                            }
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    // Auto-fill company labour details
                    const labour = ui.item.labour;
                    
                    // Set mobile number
                    if (labour.mobile) {
                        labourMobileInput.value = labour.mobile;
                    }
                    
                    // Set wage
                    if (labour.wage) {
                        labourWageInput.value = labour.wage;
                        // Recalculate total
                        calculateCompanyLabourTotal(labourId);
                    }
                    
                    return true;
                }
            });
        }

        // Remove company labour
        function removeCompanyLabour(id) {
            const labourDiv = document.getElementById(`company-labour-${id}`);
            labourDiv.remove();
            
            // Update numbers for remaining labor items
            const container = document.getElementById('company-labours-container');
            if (container) {
                const laborItems = container.querySelectorAll('.company-labour-container');
                laborItems.forEach((item, index) => {
                    const headerEl = item.querySelector('h4');
                    if (headerEl) {
                        headerEl.textContent = `Company Labour #${index + 1}`;
                    }
                });
            }
            
            updateTotalWages();
            updateGrandTotal();
        }

        // Calculate company labour totals
        function calculateCompanyLabourTotal(labourId) {
            const attendanceSelect = document.getElementById(`company-labour-attendance-${labourId}`);
            const otHoursInput = document.getElementById(`company-labour-ot-hours-${labourId}`);
            const wageInput = document.getElementById(`company-labour-wage-${labourId}`);
            const otAmountInput = document.getElementById(`company-labour-ot-amount-${labourId}`);
            const totalInput = document.getElementById(`company-labour-total-${labourId}`);
            
            const attendance = attendanceSelect.value;
            const otHours = parseFloat(otHoursInput.value) || 0;
            const wage = parseFloat(wageInput.value) || 0;
            
            // Calculate attendance factor
            let attendanceFactor = 1;
            if (attendance === 'Absent') {
                attendanceFactor = 0;
            } else if (attendance === 'Half-day') {
                attendanceFactor = 0.5;
            }
            
            // Calculate OT amount (1.5x regular wage)
            const otRate = wage / 8 * 1.5; // Assuming 8-hour workday
            const otAmount = otHours * otRate;
            
            // Calculate total
            const total = (wage * attendanceFactor) + otAmount;
            
            // Update fields
            otAmountInput.value = otAmount.toFixed(2);
            totalInput.value = total.toFixed(2);
            
            // Update total wages
            updateTotalWages();
            updateGrandTotal();
        }

        // Add travelling allowance
        function addTravellingAllowance() {
            window.travelAllowanceCounter++;
            const container = document.getElementById('travel-allowances-list');
            
            const allowanceDiv = document.createElement('div');
            allowanceDiv.className = 'travel-allowance-container';
            allowanceDiv.id = `travel-allowance-${window.travelAllowanceCounter}`;
            
            allowanceDiv.innerHTML = `
                <button type="button" class="remove-btn-corner" onclick="removeTravellingAllowance(${window.travelAllowanceCounter})">
                    <i class="fas fa-times"></i>
                </button>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="travel-from-${window.travelAllowanceCounter}">From</label>
                            <input type="text" class="form-control" id="travel-from-${window.travelAllowanceCounter}" name="travel_allowances[${window.travelAllowanceCounter}][from]" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="travel-to-${window.travelAllowanceCounter}">To</label>
                            <input type="text" class="form-control" id="travel-to-${window.travelAllowanceCounter}" name="travel_allowances[${window.travelAllowanceCounter}][to]" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label for="travel-mode-${window.travelAllowanceCounter}">Mode of Transport</label>
                            <select class="form-control" id="travel-mode-${window.travelAllowanceCounter}" name="travel_allowances[${window.travelAllowanceCounter}][mode]" required>
                                <option value="">Select Mode</option>
                                <option value="Car">Car</option>
                                <option value="Bike">Bike</option>
                                <option value="Bus">Bus</option>
                                <option value="Train">Train</option>
                                <option value="Auto">Auto</option>
                                <option value="Taxi">Taxi</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label for="travel-kilometers-${window.travelAllowanceCounter}">Total Kilometers</label>
                            <input type="number" class="form-control" id="travel-kilometers-${window.travelAllowanceCounter}" name="travel_allowances[${window.travelAllowanceCounter}][kilometers]" value="0" min="0" step="0.1">
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label for="travel-amount-${window.travelAllowanceCounter}">Amount (₹)</label>
                            <input type="number" class="form-control travel-amount" id="travel-amount-${window.travelAllowanceCounter}" name="travel_allowances[${window.travelAllowanceCounter}][amount]" value="0" min="0" required onchange="updateTravellingAllowancesTotal()">
                        </div>
                    </div>
                            </div>
                        `;
            
            container.appendChild(allowanceDiv);
        }

        // Remove travelling allowance
        function removeTravellingAllowance(id) {
            const allowanceDiv = document.getElementById(`travel-allowance-${id}`);
            allowanceDiv.remove();
            updateTravellingAllowancesTotal();
            updateMiscExpensesTotal();
            updateGrandTotal();
        }

        // Update travelling allowances total
        function updateTravellingAllowancesTotal() {
            const amountInputs = document.querySelectorAll('.travel-amount');
            let total = 0;
            
            amountInputs.forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            
            const totalSpan = document.getElementById('total-travel-allowances');
            const totalInput = document.getElementById('total-travel-allowances-input');
            
            if (totalSpan) totalSpan.textContent = total.toFixed(2);
            if (totalInput) totalInput.value = total.toFixed(2);
            
            updateMiscExpensesTotal();
            updateGrandTotal();
        }

        // Add beverage
        function addBeverage() {
            window.beverageCounter++;
            const container = document.getElementById('beverages-list');
            
            const beverageDiv = document.createElement('div');
            beverageDiv.className = 'beverage-container';
            beverageDiv.id = `beverage-${window.beverageCounter}`;
            
            beverageDiv.innerHTML = `
                <button type="button" class="remove-btn-corner" onclick="removeBeverage(${window.beverageCounter})">
                    <i class="fas fa-times"></i>
                </button>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="beverage-name-${window.beverageCounter}">Beverage/Food Item</label>
                            <input type="text" class="form-control" id="beverage-name-${window.beverageCounter}" name="beverages[${window.beverageCounter}][name]" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="beverage-amount-${window.beverageCounter}">Amount (₹)</label>
                            <input type="number" class="form-control beverage-amount" id="beverage-amount-${window.beverageCounter}" name="beverages[${window.beverageCounter}][amount]" value="0" min="0" required onchange="updateBeveragesTotal()">
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(beverageDiv);
        }

        // Remove beverage
        function removeBeverage(id) {
            const beverageDiv = document.getElementById(`beverage-${id}`);
            beverageDiv.remove();
            updateBeveragesTotal();
            updateMiscExpensesTotal();
            updateGrandTotal();
        }

        // Update beverages total
        function updateBeveragesTotal() {
            const amountInputs = document.querySelectorAll('.beverage-amount');
            let total = 0;
            
            amountInputs.forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            
            const totalSpan = document.getElementById('total-beverages');
            const totalInput = document.getElementById('total-beverages-input');
            
            if (totalSpan) totalSpan.textContent = total.toFixed(2);
            if (totalInput) totalInput.value = total.toFixed(2);
            
            updateMiscExpensesTotal();
            updateGrandTotal();
        }

        // Update total wages (vendor labours + company labours)
        function updateTotalWages() {
            // Sum vendor labour totals
            const vendorLabourTotals = document.querySelectorAll('.vendor-labour-total');
            let vendorLaboursTotal = 0;
            
            vendorLabourTotals.forEach(input => {
                vendorLaboursTotal += parseFloat(input.value) || 0;
            });
            
            // Sum company labour totals
            const companyLabourTotals = document.querySelectorAll('.company-labour-total');
            let companyLaboursTotal = 0;
            
            companyLabourTotals.forEach(input => {
                companyLaboursTotal += parseFloat(input.value) || 0;
            });
            
            // Combined total
            const totalWages = vendorLaboursTotal + companyLaboursTotal;
            
            const totalWagesSpan = document.getElementById('total-wages');
            const totalWagesInput = document.getElementById('total-wages-input');
            
            if (totalWagesSpan) totalWagesSpan.textContent = totalWages.toFixed(2);
            if (totalWagesInput) totalWagesInput.value = totalWages.toFixed(2);
            
            updateGrandTotal();
        }

        // Update miscellaneous expenses total (travel allowances + beverages)
        function updateMiscExpensesTotal() {
            const travelInput = document.getElementById('total-travel-allowances-input');
            const beveragesInput = document.getElementById('total-beverages-input');
            
            const travelAllowances = travelInput ? (parseFloat(travelInput.value) || 0) : 0;
            const beverages = beveragesInput ? (parseFloat(beveragesInput.value) || 0) : 0;
            
            const totalMiscExpenses = travelAllowances + beverages;
            
            const totalSpan = document.getElementById('total-misc-expenses');
            const totalInput = document.getElementById('total-misc-expenses-input');
            
            if (totalSpan) totalSpan.textContent = totalMiscExpenses.toFixed(2);
            if (totalInput) totalInput.value = totalMiscExpenses.toFixed(2);
            
            updateGrandTotal();
        }

        // Update grand total (wages + misc expenses)
        function updateGrandTotal() {
            const wagesInput = document.getElementById('total-wages-input');
            const miscExpensesInput = document.getElementById('total-misc-expenses-input');
            
            const wages = wagesInput ? (parseFloat(wagesInput.value) || 0) : 0;
            const miscExpenses = miscExpensesInput ? (parseFloat(miscExpensesInput.value) || 0) : 0;
            
            const grandTotal = wages + miscExpenses;
            
            const grandTotalSpan = document.getElementById('grand-total');
            const grandTotalInput = document.getElementById('grand-total-input');
            
            if (grandTotalSpan) grandTotalSpan.textContent = grandTotal.toFixed(2);
            if (grandTotalInput) grandTotalInput.value = grandTotal.toFixed(2);
        }

        // Work progress counter
        let workProgressCounter = 0;

        // Add work progress item
        function addWorkProgress(type) {
            window.workProgressCounter++;
            const container = document.getElementById('work-progress-list');
            
            const workProgressDiv = document.createElement('div');
            workProgressDiv.className = 'work-progress-container';
            workProgressDiv.id = `work-progress-${window.workProgressCounter}`;
            
            let workOptions = '';
            
            if (type === 'civil') {
                workOptions = `
                    <option value="">Select Civil Work</option>
                    <option value="Foundation">Foundation</option>
                    <option value="Excavation">Excavation</option>
                    <option value="RCC">RCC Work</option>
                    <option value="Brickwork">Brickwork</option>
                    <option value="Plastering">Plastering</option>
                    <option value="Flooring Base">Flooring Base Preparation</option>
                    <option value="Waterproofing">Waterproofing</option>
                    <option value="External Plastering">External Plastering</option>
                    <option value="Concrete Work">Concrete Work</option>
                    <option value="Drainage">Drainage System</option>
                    <option value="Other Civil Work">Other Civil Work</option>
                `;
            } else if (type === 'interior') {
                workOptions = `
                    <option value="">Select Interior Work</option>
                    <option value="Painting">Painting</option>
                    <option value="Flooring">Flooring</option>
                    <option value="Wall Cladding">Wall Cladding</option>
                    <option value="Ceiling Work">Ceiling Work</option>
                    <option value="Furniture">Furniture Installation</option>
                    <option value="Electrical">Electrical Fittings</option>
                    <option value="Plumbing">Plumbing Fixtures</option>
                    <option value="Tiling">Tiling</option>
                    <option value="Carpentry">Carpentry</option>
                    <option value="Lighting">Lighting Installation</option>
                    <option value="HVAC">HVAC Installation</option>
                    <option value="Other Interior Work">Other Interior Work</option>
                `;
            }
            
            workProgressDiv.innerHTML = `
                <div class="item-header">
                    <h4>${type === 'civil' ? '<i class="fas fa-hammer"></i> Civil Work' : '<i class="fas fa-couch"></i> Interior Work'}</h4>
                    <button type="button" class="remove-btn" onclick="removeWorkProgress(${window.workProgressCounter})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="work-type-${window.workProgressCounter}"><i class="fas fa-clipboard-check"></i> Type of Work</label>
                            <select class="form-control" id="work-type-${window.workProgressCounter}" name="work_progress[${window.workProgressCounter}][work_type]" required>
                                ${workOptions}
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="work-status-${window.workProgressCounter}"><i class="fas fa-check-circle"></i> Is Work Completed?</label>
                            <select class="form-control" id="work-status-${window.workProgressCounter}" name="work_progress[${window.workProgressCounter}][status]" required>
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                                <option value="In Progress">In Progress</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="work-category-${window.workProgressCounter}"><i class="fas fa-tag"></i> Category</label>
                            <input type="hidden" name="work_progress[${window.workProgressCounter}][category]" value="${type}">
                            <input type="text" class="form-control" value="${type === 'civil' ? 'Civil Work' : 'Interior Work'}" readonly>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="work-remarks-${window.workProgressCounter}"><i class="fas fa-comment-alt"></i> Remarks</label>
                            <textarea class="form-control" id="work-remarks-${window.workProgressCounter}" name="work_progress[${window.workProgressCounter}][remarks]" placeholder="Add any remarks about the work progress..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="work-files-${window.workProgressCounter}"><i class="fas fa-camera"></i> Upload Pictures/Videos</label>
                            <input type="file" class="form-control" id="work-files-${window.workProgressCounter}" name="work_progress_files_${window.workProgressCounter}[]" multiple accept="image/*,video/*">
                            <small class="text-muted">You can select multiple files. Accepted formats: images and videos.</small>
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(workProgressDiv);
            
            // Improved scrolling to the newly created work progress container
            setTimeout(function() {
                // Use our custom scroll function
                window.smoothScrollToElement(workProgressDiv, 80);
                
                // Add focus to the first input in the new container
                const firstSelect = document.getElementById(`work-type-${window.workProgressCounter}`);
                if (firstSelect) {
                    firstSelect.focus();
                }
            }, 300);
        }

        // Remove work progress item
        function removeWorkProgress(id) {
            const workProgressDiv = document.getElementById(`work-progress-${id}`);
            workProgressDiv.remove();
        }

        // Open/close site update modal
        function openSiteUpdateModal() {
            const modal = document.getElementById('siteUpdateModal');
            if (modal) {
                modal.style.display = 'block';
                // Initialize totals when modal opens
                setTimeout(() => {
                    if (typeof updateTotalWages === 'function') {
                        updateTotalWages();
                        updateTravellingAllowancesTotal();
                        updateBeveragesTotal();
                        updateMiscExpensesTotal();
                        updateGrandTotal();
                    }
                }, 100);
            }
        }
        
        function hideSiteUpdateModal() {
            const modal = document.getElementById('siteUpdateModal');
            if (modal) modal.style.display = 'none';
        }
        
        // Inventory counter
        window.inventoryCounter = 0;
        
        // Add inventory item
        function addInventoryItem() {
            window.inventoryCounter++;
            const container = document.getElementById('inventory-list');
            
            // Count existing inventory items to determine number
            const existingItems = container.querySelectorAll('.inventory-container').length;
            const itemNumber = existingItems + 1;
            
            const inventoryDiv = document.createElement('div');
            inventoryDiv.className = 'inventory-container';
            inventoryDiv.id = `inventory-${window.inventoryCounter}`;
            
            inventoryDiv.innerHTML = `
                <div class="item-header">
                    <h4>
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background-color: #3498db; color: white; border-radius: 50%; margin-right: 10px; font-size: 14px;">${itemNumber}</span>
                        <i class="fas fa-box"></i> Inventory Item
                    </h4>
                    <button type="button" class="remove-btn" onclick="removeInventoryItem(${window.inventoryCounter})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="material-type-${window.inventoryCounter}"><i class="fas fa-cubes"></i> Material</label>
                            <select class="form-control" id="material-type-${window.inventoryCounter}" name="inventory[${window.inventoryCounter}][material]" required>
                                <option value="">Select Material</option>
                                <option value="Cement">Cement</option>
                                <option value="Sand">Sand</option>
                                <option value="Aggregate">Aggregate</option>
                                <option value="Bricks">Bricks</option>
                                <option value="Steel">Steel</option>
                                <option value="Timber">Timber</option>
                                <option value="Paint">Paint</option>
                                <option value="Tiles">Tiles</option>
                                <option value="Glass">Glass</option>
                                <option value="Electrical Wires">Electrical Wires</option>
                                <option value="Pipes">Pipes</option>
                                <option value="Sanitary Fixtures">Sanitary Fixtures</option>
                                <option value="Concrete">Concrete</option>
                                <option value="Plaster">Plaster</option>
                                <option value="Gravel">Gravel</option>
                                <option value="Stone Dust">Stone Dust</option>
                                <option value="Water Proofing Materials">Water Proofing Materials</option>
                                <option value="Plywood">Plywood</option>
                                <option value="Adhesives">Adhesives</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="quantity-${window.inventoryCounter}"><i class="fas fa-balance-scale"></i> Quantity</label>
                            <input type="number" class="form-control" id="quantity-${window.inventoryCounter}" name="inventory[${window.inventoryCounter}][quantity]" min="0" step="any" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="unit-${window.inventoryCounter}"><i class="fas fa-ruler"></i> Unit</label>
                            <select class="form-control" id="unit-${window.inventoryCounter}" name="inventory[${window.inventoryCounter}][unit]" required>
                                <option value="">Select Unit</option>
                                <option value="Kg">Kg</option>
                                <option value="Bag">Bag</option>
                                <option value="Ton">Ton</option>
                                <option value="Cubic Meter">Cubic Meter</option>
                                <option value="Square Meter">Square Meter</option>
                                <option value="Meter">Meter</option>
                                <option value="Piece">Piece</option>
                                <option value="Number">Number</option>
                                <option value="Litre">Litre</option>
                                <option value="Quintal">Quintal</option>
                                <option value="Bundle">Bundle</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="standard-values-${window.inventoryCounter}"><i class="fas fa-clipboard"></i> Standard Values/Notes</label>
                            <textarea class="form-control" id="standard-values-${window.inventoryCounter}" name="inventory[${window.inventoryCounter}][standard_values]" placeholder="Add any standard values or notes about the material..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="inventory-files-${window.inventoryCounter}"><i class="fas fa-camera"></i> Upload Pictures/Videos</label>
                            <input type="file" class="form-control" id="inventory-files-${window.inventoryCounter}" name="inventory_files_${window.inventoryCounter}[]" multiple accept="image/*,video/*">
                            <small class="text-muted">You can select multiple files. Accepted formats: images and videos.</small>
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(inventoryDiv);
            
            // Improved scrolling to the newly created inventory container
            setTimeout(function() {
                // Use our custom scroll function
                window.smoothScrollToElement(inventoryDiv, 80);
                
                // Add focus to the first input in the new container
                const firstSelect = document.getElementById(`material-type-${window.inventoryCounter}`);
                if (firstSelect) {
                    try {
                        firstSelect.focus();
                    } catch (e) {
                        console.error('Focus failed:', e);
                    }
                }
            }, 300); // Increased timeout for better reliability
        }
        
        // Remove inventory item
        function removeInventoryItem(id) {
            const inventoryDiv = document.getElementById(`inventory-${id}`);
            inventoryDiv.remove();
            
            // Update numbers for remaining inventory items
            const container = document.getElementById('inventory-list');
            if (container) {
                const items = container.querySelectorAll('.inventory-container');
                items.forEach((item, index) => {
                    const numberEl = item.querySelector('.item-header h4 span');
                    if (numberEl) {
                        numberEl.textContent = index + 1;
                    }
                });
            }
        }

        // Function to open media modal
        function openMediaModal(path, type) {
            const modal = document.getElementById('siteDetailMediaModal');
            const modalContent = document.getElementById('siteDetailMediaContent');
            
            if (!modal || !modalContent) return;
            
            // Clear previous content
            modalContent.innerHTML = '';
            
            // Create new content based on type
            if (type === 'image') {
                const img = document.createElement('img');
                img.src = path;
                img.alt = 'Site image';
                modalContent.appendChild(img);
            } else if (type === 'video') {
                const video = document.createElement('video');
                video.controls = true;
                video.autoplay = true;
                
                const source = document.createElement('source');
                source.src = path;
                
                // Determine video type from path
                const extension = path.split('.').pop().toLowerCase();
                let mimeType = 'video/mp4'; // Default
                
                if (extension === 'mov') {
                    mimeType = 'video/quicktime';
                } else if (extension === 'avi') {
                    mimeType = 'video/x-msvideo';
                } else if (extension === 'wmv') {
                    mimeType = 'video/x-ms-wmv';
                }
                
                source.type = mimeType;
                video.appendChild(source);
                modalContent.appendChild(video);
            }
            
            // Show the modal
            modal.style.display = 'block';
        }
        
        // Set up media modal close handlers
        document.addEventListener('DOMContentLoaded', function() {
            // Close the media modal when clicking the close button
            const mediaCloseBtn = document.querySelector('.site-detail-media-close');
            if (mediaCloseBtn) {
                mediaCloseBtn.addEventListener('click', function() {
                    const mediaModal = document.getElementById('siteDetailMediaModal');
                    if (mediaModal) {
                        mediaModal.style.display = 'none';
                        
                        // Pause any playing videos
                        const videos = document.querySelectorAll('#siteDetailMediaContent video');
                        if (videos.length > 0) {
                            videos.forEach(video => {
                                video.pause();
                            });
                        }
                    }
                });
            }
            
            // Close media modal when clicking outside the content
            window.addEventListener('click', function(event) {
                const mediaModal = document.getElementById('siteDetailMediaModal');
                if (event.target === mediaModal) {
                    mediaModal.style.display = 'none';
                    
                    // Pause any playing videos
                    const videos = document.querySelectorAll('#siteDetailMediaContent video');
                    if (videos.length > 0) {
                        videos.forEach(video => {
                            video.pause();
                        });
                    }
                }
            });
        });
        
        // Function to open the edit site details modal
        function editSiteDetails() {
            // Get the site name and date from the view modal
            const siteName = document.getElementById('modalSiteName').textContent;
            const siteDate = document.getElementById('modalDate').textContent;
            
            // Show loading state
            document.getElementById('siteDetailEditBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            
            // Fetch the site update details for editing
            fetch(`site_expenses.php?action=get_site_update_for_edit&site_name=${encodeURIComponent(siteName)}&date=${encodeURIComponent(siteDate)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(responseData => {
                    if (responseData.success) {
                        // Populate the edit form with the data
                        populateEditForm(responseData.data);
                        
                        // Hide the view modal and show the edit modal
                        document.getElementById('updateDetailsModal').style.display = 'none';
                        document.getElementById('editSiteUpdateModal').style.display = 'block';
                    } else {
                        console.error('Error fetching site update for edit:', responseData.message);
                        alert('Error loading site update data for editing: ' + responseData.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching site update for edit:', error);
                    alert('Error loading site update data for editing. Please try again later.');
                })
                .finally(() => {
                    // Reset button state
                    document.getElementById('siteDetailEditBtn').innerHTML = '<i class="fas fa-edit"></i> Edit Details';
                });
        }
        
        // Function to hide the edit site update modal
        function hideEditSiteUpdateModal() {
            document.getElementById('editSiteUpdateModal').style.display = 'none';
        }
        
        // Function to populate the edit form with data
        function populateEditForm(data) {
            // Set site update ID
            document.getElementById('edit_site_update_id').value = data.id;
            
            // Set basic details
            document.getElementById('edit_site_name').value = data.site_name;
            
            // Convert date to YYYY-MM-DD format for the date input
            if (data.update_date) {
                const dateObj = new Date(data.update_date);
                const formattedDate = dateObj.toISOString().split('T')[0];
                document.getElementById('edit_update_date').value = formattedDate;
            }
            
            // Clear existing data containers
            document.getElementById('edit-vendors-container').innerHTML = '';
            document.getElementById('edit-company-labours-container').innerHTML = '';
            document.getElementById('edit-work-progress-list').innerHTML = '';
            document.getElementById('edit-inventory-list').innerHTML = '';
            
            // Populate vendors and their labours
            if (data.vendors && data.vendors.length > 0) {
                data.vendors.forEach(vendor => {
                    // Add vendor and populate it with data
                    const vendorId = addVendorToEdit(true);
                    populateVendorEdit(vendorId, vendor);
                });
            }
            
            // Populate company labours
            if (data.company_labours && data.company_labours.length > 0) {
                data.company_labours.forEach(labour => {
                    // Add company labour and populate it with data
                    const labourId = addCompanyLabourToEdit(true);
                    populateCompanyLabourEdit(labourId, labour);
                });
            }
            
            // Populate work progress items
            if (data.work_progress && data.work_progress.length > 0) {
                data.work_progress.forEach(item => {
                    // Add work progress item and populate it with data
                    const itemId = addWorkProgressToEdit(item.category.toLowerCase(), true);
                    populateWorkProgressEdit(itemId, item);
                });
            }
            
            // Populate inventory items
            if (data.inventory && data.inventory.length > 0) {
                data.inventory.forEach(item => {
                    // Add inventory item and populate it with data
                    const itemId = addInventoryItemToEdit(true);
                    populateInventoryEdit(itemId, item);
                });
            }
            
            // Update totals
            if (data.expenses) {
                document.getElementById('edit-total-wages').textContent = parseFloat(data.expenses.total_wages).toFixed(2);
                document.getElementById('edit-total-wages-input').value = data.expenses.total_wages;
                
                document.getElementById('edit-total-misc-expenses').textContent = parseFloat(data.expenses.total_misc_expenses).toFixed(2);
                document.getElementById('edit-total-misc-expenses-input').value = data.expenses.total_misc_expenses;
                
                document.getElementById('edit-grand-total').textContent = parseFloat(data.expenses.grand_total).toFixed(2);
                document.getElementById('edit-grand-total-input').value = data.expenses.grand_total;
            }
        }
        
        // Add vendor to the edit form
        function addVendorToEdit(silent = false) {
            window.vendorEditCounter = window.vendorEditCounter || 0;
            window.vendorEditCounter++;
            
            const vendorsContainer = document.getElementById('edit-vendors-container');
            
            const vendorDiv = document.createElement('div');
            vendorDiv.className = 'vendor-container';
            vendorDiv.id = `edit-vendor-${window.vendorEditCounter}`;
            
            vendorDiv.innerHTML = `
                <div class="vendor-header">
                    <div class="vendor-type-select">
                        <label for="edit-vendor-type-${window.vendorEditCounter}">Vendor Service</label>
                        <select class="form-control" id="edit-vendor-type-${window.vendorEditCounter}" name="edit_vendors[${window.vendorEditCounter}][type]" required>
                            <option value="">Select Vendor Type</option>
                            <option value="POP">POP</option>
                            <option value="Tile">Tile</option>
                            <option value="Electrical">Electrical</option>
                            <option value="Plumbing">Plumbing</option>
                            <option value="Carpentry">Carpentry</option>
                            <option value="Painting">Painting</option>
                            <option value="HVAC">HVAC</option>
                            <option value="Flooring">Flooring</option>
                            <option value="Roofing">Roofing</option>
                            <option value="Masonry">Masonry</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <button type="button" class="remove-btn" onclick="removeVendorFromEdit(${window.vendorEditCounter})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <input type="hidden" name="edit_vendors[${window.vendorEditCounter}][db_id]" id="edit-vendor-db-id-${window.vendorEditCounter}" value="">
                <div class="form-group">
                    <label for="edit-vendor-name-${window.vendorEditCounter}">Vendor Name</label>
                    <input type="text" class="form-control" id="edit-vendor-name-${window.vendorEditCounter}" name="edit_vendors[${window.vendorEditCounter}][name]" required>
                </div>
                <div class="form-group">
                    <label for="edit-vendor-contact-${window.vendorEditCounter}">Contact Number</label>
                    <input type="text" class="form-control" id="edit-vendor-contact-${window.vendorEditCounter}" name="edit_vendors[${window.vendorEditCounter}][contact]">
                </div>
                
                <div class="vendor-labours" id="edit-vendor-labours-${window.vendorEditCounter}">
                    <!-- Labours will be added here -->
                </div>
                <button type="button" class="btn-add-item" onclick="addLabourToEdit(${window.vendorEditCounter})">
                    <i class="fas fa-plus"></i> Add Labour
                </button>
            `;
            
            vendorsContainer.appendChild(vendorDiv);
            
            if (!silent) {
                // Scroll to the newly added vendor
                setTimeout(() => {
                    vendorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
            
            return window.vendorEditCounter;
        }
        
        // Remove vendor from the edit form
        function removeVendorFromEdit(id) {
            const vendorDiv = document.getElementById(`edit-vendor-${id}`);
            vendorDiv.remove();
            // Update totals
            updateEditTotals();
        }
        
        // Populate vendor in the edit form
        function populateVendorEdit(vendorId, vendorData) {
            if (vendorData.id) {
                document.getElementById(`edit-vendor-db-id-${vendorId}`).value = vendorData.id;
            }
            
            document.getElementById(`edit-vendor-type-${vendorId}`).value = vendorData.type;
            document.getElementById(`edit-vendor-name-${vendorId}`).value = vendorData.name;
            
            if (vendorData.contact) {
                document.getElementById(`edit-vendor-contact-${vendorId}`).value = vendorData.contact;
            }
            
            // Add labours if any
            if (vendorData.labours && vendorData.labours.length > 0) {
                vendorData.labours.forEach(labour => {
                    const labourId = addLabourToEdit(vendorId, true);
                    populateLabourEdit(vendorId, labourId, labour);
                });
            }
        }
        
        // Add labour to a vendor in the edit form
        function addLabourToEdit(vendorId, silent = false) {
            window.labourEditCounter = window.labourEditCounter || 0;
            window.labourEditCounter++;
            
            const labourContainer = document.getElementById(`edit-vendor-labours-${vendorId}`);
            
            // Count existing labor items for this vendor to determine number
            const existingLabors = labourContainer.querySelectorAll('.labour-container').length;
            const laborNumber = existingLabors + 1;
            
            const labourDiv = document.createElement('div');
            labourDiv.className = 'labour-container';
            labourDiv.id = `edit-labour-${window.labourEditCounter}`;
            
            labourDiv.innerHTML = `
                <input type="hidden" id="edit-labour-db-id-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][db_id]" value="">
                <div class="labour-header">
                    <strong>Labour #${laborNumber}</strong>
                    <button type="button" class="remove-btn" onclick="removeLabourFromEdit(${window.labourEditCounter}, ${vendorId})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="edit-labour-name-${window.labourEditCounter}">Labour Name</label>
                            <input type="text" class="form-control" id="edit-labour-name-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][name]" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="edit-labour-mobile-${window.labourEditCounter}">Mobile Number</label>
                            <input type="text" class="form-control" id="edit-labour-mobile-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][mobile]">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-3">
                        <div class="form-group">
                            <label for="edit-labour-attendance-${window.labourEditCounter}">Attendance</label>
                            <select class="form-control" id="edit-labour-attendance-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][attendance]" required onchange="calculateEditLabourTotal(${window.labourEditCounter})">
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Half-day">Half-day</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="edit-labour-ot-hours-${window.labourEditCounter}">OT Hours</label>
                            <input type="number" class="form-control" id="edit-labour-ot-hours-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][ot_hours]" value="0" min="0" step="0.5" onchange="calculateEditLabourTotal(${window.labourEditCounter})">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="edit-labour-wage-${window.labourEditCounter}">Wage (₹)</label>
                            <input type="number" class="form-control" id="edit-labour-wage-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][wage]" value="0" min="0" required onchange="calculateEditLabourTotal(${window.labourEditCounter})">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="edit-labour-ot-amount-${window.labourEditCounter}">OT Amount (₹)</label>
                            <input type="number" class="form-control" id="edit-labour-ot-amount-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][ot_amount]" value="0" min="0" readonly>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="edit-labour-total-${window.labourEditCounter}">Total Amount (₹)</label>
                            <input type="number" class="form-control edit-vendor-labour-total" id="edit-labour-total-${window.labourEditCounter}" name="edit_vendors[${vendorId}][labours][${window.labourEditCounter}][total]" value="0" min="0" readonly>
                        </div>
                    </div>
                </div>
            `;
            
            labourContainer.appendChild(labourDiv);
            
            if (!silent) {
                // Scroll to the newly added labour
                setTimeout(() => {
                    labourDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
            
            return window.labourEditCounter;
        }
        
        // Remove labour from the edit form
        function removeLabourFromEdit(id, vendorId) {
            const labourDiv = document.getElementById(`edit-labour-${id}`);
            labourDiv.remove();
            
            // Update numbers for remaining labor items
            if (vendorId) {
                const labourContainer = document.getElementById(`edit-vendor-labours-${vendorId}`);
                if (labourContainer) {
                    const laborItems = labourContainer.querySelectorAll('.labour-container');
                    laborItems.forEach((item, index) => {
                        const headerEl = item.querySelector('.labour-header strong');
                        if (headerEl) {
                            headerEl.textContent = `Labour #${index + 1}`;
                        }
                    });
                }
            }
            
            // Update totals
            updateEditTotals();
        }
        
        // Populate labour in the edit form
        function populateLabourEdit(vendorId, labourId, labourData) {
            if (labourData.id) {
                document.getElementById(`edit-labour-db-id-${labourId}`).value = labourData.id;
            }
            
            document.getElementById(`edit-labour-name-${labourId}`).value = labourData.name;
            
            if (labourData.mobile) {
                document.getElementById(`edit-labour-mobile-${labourId}`).value = labourData.mobile;
            }
            
            document.getElementById(`edit-labour-attendance-${labourId}`).value = labourData.attendance;
            document.getElementById(`edit-labour-ot-hours-${labourId}`).value = labourData.ot_hours;
            document.getElementById(`edit-labour-wage-${labourId}`).value = labourData.wage;
            document.getElementById(`edit-labour-ot-amount-${labourId}`).value = labourData.ot_amount;
            document.getElementById(`edit-labour-total-${labourId}`).value = labourData.total;
        }
        
        // Calculate labour totals in the edit form
        function calculateEditLabourTotal(labourId) {
            const attendanceSelect = document.getElementById(`edit-labour-attendance-${labourId}`);
            const otHoursInput = document.getElementById(`edit-labour-ot-hours-${labourId}`);
            const wageInput = document.getElementById(`edit-labour-wage-${labourId}`);
            const otAmountInput = document.getElementById(`edit-labour-ot-amount-${labourId}`);
            const totalInput = document.getElementById(`edit-labour-total-${labourId}`);
            
            const attendance = attendanceSelect.value;
            const otHours = parseFloat(otHoursInput.value) || 0;
            const wage = parseFloat(wageInput.value) || 0;
            
            // Calculate attendance factor
            let attendanceFactor = 1;
            if (attendance === 'Absent') {
                attendanceFactor = 0;
            } else if (attendance === 'Half-day') {
                attendanceFactor = 0.5;
            }
            
            // Calculate OT amount (1.5x regular wage)
            const otRate = wage / 8 * 1.5; // Assuming 8-hour workday
            const otAmount = otHours * otRate;
            
            // Calculate total
            const total = (wage * attendanceFactor) + otAmount;
            
            // Update fields
            otAmountInput.value = otAmount.toFixed(2);
            totalInput.value = total.toFixed(2);
            
            // Update total wages and grand total
            updateEditTotals();
        }
        
        // Add company labour to the edit form
        function addCompanyLabourToEdit(silent = false) {
            window.companyLabourEditCounter = window.companyLabourEditCounter || 0;
            window.companyLabourEditCounter++;
            
            const container = document.getElementById('edit-company-labours-container');
            
            // Count existing labor items to determine number
            const existingLabors = container.querySelectorAll('.company-labour-container').length;
            const laborNumber = existingLabors + 1;
            
            const labourDiv = document.createElement('div');
            labourDiv.className = 'company-labour-container';
            labourDiv.id = `edit-company-labour-${window.companyLabourEditCounter}`;
            
            labourDiv.innerHTML = `
                <button type="button" class="remove-btn-corner" onclick="removeCompanyLabourFromEdit(${window.companyLabourEditCounter})">
                    <i class="fas fa-times"></i>
                </button>
                <h4 style="margin-bottom: 15px; font-size: 16px; color: #333;">Company Labour #${laborNumber}</h4>
                <input type="hidden" id="edit-company-labour-db-id-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][db_id]" value="">
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="edit-company-labour-name-${window.companyLabourEditCounter}">Labour Name</label>
                            <input type="text" class="form-control" id="edit-company-labour-name-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][name]" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="edit-company-labour-mobile-${window.companyLabourEditCounter}">Mobile Number</label>
                            <input type="text" class="form-control" id="edit-company-labour-mobile-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][mobile]">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-3">
                        <div class="form-group">
                            <label for="edit-company-labour-attendance-${window.companyLabourEditCounter}">Attendance</label>
                            <select class="form-control" id="edit-company-labour-attendance-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][attendance]" required onchange="calculateEditCompanyLabourTotal(${window.companyLabourEditCounter})">
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Half-day">Half-day</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="edit-company-labour-ot-hours-${window.companyLabourEditCounter}">OT Hours</label>
                            <input type="number" class="form-control" id="edit-company-labour-ot-hours-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][ot_hours]" value="0" min="0" step="0.5" onchange="calculateEditCompanyLabourTotal(${window.companyLabourEditCounter})">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="edit-company-labour-wage-${window.companyLabourEditCounter}">Wage (₹)</label>
                            <input type="number" class="form-control" id="edit-company-labour-wage-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][wage]" value="0" min="0" required onchange="calculateEditCompanyLabourTotal(${window.companyLabourEditCounter})">
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="form-group">
                            <label for="edit-company-labour-ot-amount-${window.companyLabourEditCounter}">OT Amount (₹)</label>
                            <input type="number" class="form-control" id="edit-company-labour-ot-amount-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][ot_amount]" value="0" min="0" readonly>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="edit-company-labour-total-${window.companyLabourEditCounter}">Total Amount (₹)</label>
                            <input type="number" class="form-control edit-company-labour-total" id="edit-company-labour-total-${window.companyLabourEditCounter}" name="edit_company_labours[${window.companyLabourEditCounter}][total]" value="0" min="0" readonly>
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(labourDiv);
            
            if (!silent) {
                // Scroll to the newly added labour
                setTimeout(() => {
                    labourDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
            
            return window.companyLabourEditCounter;
        }
        
        // Remove company labour from the edit form
        function removeCompanyLabourFromEdit(id) {
            const labourDiv = document.getElementById(`edit-company-labour-${id}`);
            labourDiv.remove();
            
            // Update numbers for remaining labor items
            const container = document.getElementById('edit-company-labours-container');
            if (container) {
                const laborItems = container.querySelectorAll('.company-labour-container');
                laborItems.forEach((item, index) => {
                    const headerEl = item.querySelector('h4');
                    if (headerEl) {
                        headerEl.textContent = `Company Labour #${index + 1}`;
                    }
                });
            }
            
            // Update totals
            updateEditTotals();
        }
        
        // Populate company labour in the edit form
        function populateCompanyLabourEdit(labourId, labourData) {
            if (labourData.id) {
                document.getElementById(`edit-company-labour-db-id-${labourId}`).value = labourData.id;
            }
            
            document.getElementById(`edit-company-labour-name-${labourId}`).value = labourData.name;
            
            if (labourData.mobile) {
                document.getElementById(`edit-company-labour-mobile-${labourId}`).value = labourData.mobile;
            }
            
            document.getElementById(`edit-company-labour-attendance-${labourId}`).value = labourData.attendance;
            document.getElementById(`edit-company-labour-ot-hours-${labourId}`).value = labourData.ot_hours;
            document.getElementById(`edit-company-labour-wage-${labourId}`).value = labourData.wage;
            document.getElementById(`edit-company-labour-ot-amount-${labourId}`).value = labourData.ot_amount;
            document.getElementById(`edit-company-labour-total-${labourId}`).value = labourData.total;
        }
        
        // Calculate company labour totals in the edit form
        function calculateEditCompanyLabourTotal(labourId) {
            const attendanceSelect = document.getElementById(`edit-company-labour-attendance-${labourId}`);
            const otHoursInput = document.getElementById(`edit-company-labour-ot-hours-${labourId}`);
            const wageInput = document.getElementById(`edit-company-labour-wage-${labourId}`);
            const otAmountInput = document.getElementById(`edit-company-labour-ot-amount-${labourId}`);
            const totalInput = document.getElementById(`edit-company-labour-total-${labourId}`);
            
            const attendance = attendanceSelect.value;
            const otHours = parseFloat(otHoursInput.value) || 0;
            const wage = parseFloat(wageInput.value) || 0;
            
            // Calculate attendance factor
            let attendanceFactor = 1;
            if (attendance === 'Absent') {
                attendanceFactor = 0;
            } else if (attendance === 'Half-day') {
                attendanceFactor = 0.5;
            }
            
            // Calculate OT amount (1.5x regular wage)
            const otRate = wage / 8 * 1.5; // Assuming 8-hour workday
            const otAmount = otHours * otRate;
            
            // Calculate total
            const total = (wage * attendanceFactor) + otAmount;
            
            // Update fields
            otAmountInput.value = otAmount.toFixed(2);
            totalInput.value = total.toFixed(2);
            
            // Update totals
            updateEditTotals();
        }
        
        // Update totals in the edit form
        function updateEditTotals() {
            // Sum vendor labour totals
            const vendorLabourTotals = document.querySelectorAll('.edit-vendor-labour-total');
            let vendorLaboursTotal = 0;
            
            vendorLabourTotals.forEach(input => {
                vendorLaboursTotal += parseFloat(input.value) || 0;
            });
            
            // Sum company labour totals
            const companyLabourTotals = document.querySelectorAll('.edit-company-labour-total');
            let companyLaboursTotal = 0;
            
            companyLabourTotals.forEach(input => {
                companyLaboursTotal += parseFloat(input.value) || 0;
            });
            
            // Combined total
            const totalWages = vendorLaboursTotal + companyLaboursTotal;
            
            const totalWagesSpan = document.getElementById('edit-total-wages');
            const totalWagesInput = document.getElementById('edit-total-wages-input');
            
            if (totalWagesSpan) totalWagesSpan.textContent = totalWages.toFixed(2);
            if (totalWagesInput) totalWagesInput.value = totalWages.toFixed(2);
            
            // Update grand total
            updateEditGrandTotal();
        }
        
        // Update grand total in the edit form
        function updateEditGrandTotal() {
            const wagesInput = document.getElementById('edit-total-wages-input');
            const miscExpensesInput = document.getElementById('edit-total-misc-expenses-input');
            
            const wages = wagesInput ? (parseFloat(wagesInput.value) || 0) : 0;
            const miscExpenses = miscExpensesInput ? (parseFloat(miscExpensesInput.value) || 0) : 0;
            
            const grandTotal = wages + miscExpenses;
            
            const grandTotalSpan = document.getElementById('edit-grand-total');
            const grandTotalInput = document.getElementById('edit-grand-total-input');
            
            if (grandTotalSpan) grandTotalSpan.textContent = grandTotal.toFixed(2);
            if (grandTotalInput) grandTotalInput.value = grandTotal.toFixed(2);
        }

        // Add work progress item to the edit form
        function addWorkProgressToEdit(category, silent = false) {
            window.workProgressEditCounter = window.workProgressEditCounter || 0;
            window.workProgressEditCounter++;
            
            const container = document.getElementById('edit-work-progress-list');
            
            const workItem = document.createElement('div');
            workItem.className = 'work-progress-item';
            workItem.id = `edit-work-progress-${window.workProgressEditCounter}`;
            
            // Convert category to title case for display
            const categoryDisplay = category.charAt(0).toUpperCase() + category.slice(1);
            
            // Create options based on category
            let workTypeOptions = '';
            
            if (category === 'civil') {
                workTypeOptions = `
                    <option value="">Select Civil Work</option>
                    <option value="Foundation">Foundation</option>
                    <option value="Excavation">Excavation</option>
                    <option value="RCC Work">RCC Work</option>
                    <option value="Brickwork">Brickwork</option>
                    <option value="Plastering">Plastering</option>
                    <option value="Flooring Base Preparation">Flooring Base Preparation</option>
                    <option value="Waterproofing">Waterproofing</option>
                    <option value="External Plastering">External Plastering</option>
                    <option value="Concrete Work">Concrete Work</option>
                    <option value="Drainage System">Drainage System</option>
                    <option value="Other Civil Work">Other Civil Work</option>
                `;
            } else if (category === 'interior') {
                workTypeOptions = `
                    <option value="">Select Interior Work</option>
                    <option value="Painting">Painting</option>
                    <option value="Flooring">Flooring</option>
                    <option value="Wall Cladding">Wall Cladding</option>
                    <option value="Ceiling Work">Ceiling Work</option>
                    <option value="Furniture Installation">Furniture Installation</option>
                    <option value="Electrical Fittings">Electrical Fittings</option>
                    <option value="Plumbing Fixtures">Plumbing Fixtures</option>
                    <option value="Tiling">Tiling</option>
                    <option value="Carpentry">Carpentry</option>
                    <option value="Lighting Installation">Lighting Installation</option>
                    <option value="HVAC Installation">HVAC Installation</option>
                    <option value="Other Interior Work">Other Interior Work</option>
                `;
            }
            
            workItem.innerHTML = `
                <button type="button" class="remove-btn-corner" onclick="removeWorkProgressFromEdit(${window.workProgressEditCounter})">
                    <i class="fas fa-times"></i>
                </button>
                <input type="hidden" name="edit_work_progress[${window.workProgressEditCounter}][db_id]" id="edit-work-progress-db-id-${window.workProgressEditCounter}" value="">
                <input type="hidden" name="edit_work_progress[${window.workProgressEditCounter}][category]" value="${category}">
                <div class="work-progress-header">
                    <div class="header-left">
                        <i class="${category === 'civil' ? 'fas fa-hammer' : 'fas fa-couch'}"></i>
                        <span class="header-title">${categoryDisplay} Work</span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit-work-type-${window.workProgressEditCounter}">${categoryDisplay} Work Type</label>
                    <select class="form-control" id="edit-work-type-${window.workProgressEditCounter}" name="edit_work_progress[${window.workProgressEditCounter}][work_type]" required>
                        ${workTypeOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-work-status-${window.workProgressEditCounter}">Is Work Completed?</label>
                    <select class="form-control" id="edit-work-status-${window.workProgressEditCounter}" name="edit_work_progress[${window.workProgressEditCounter}][status]" required>
                        <option value="Yes">Yes</option>
                        <option value="In Progress">In Progress</option>
                        <option value="No">Not Started</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-work-remarks-${window.workProgressEditCounter}">Remarks</label>
                    <textarea class="form-control" id="edit-work-remarks-${window.workProgressEditCounter}" name="edit_work_progress[${window.workProgressEditCounter}][remarks]" placeholder="Add any remarks or notes about the work..."></textarea>
                </div>
                <div class="form-group">
                    <label for="edit-work-files-${window.workProgressEditCounter}"><i class="fas fa-camera"></i> Upload Pictures/Videos</label>
                    <input type="file" class="form-control" id="edit-work-files-${window.workProgressEditCounter}" name="edit_work_progress_files_${window.workProgressEditCounter}[]" multiple accept="image/*,video/*">
                    <small class="text-muted">You can select multiple files. Accepted formats: images and videos.</small>
                </div>
                <div id="edit-existing-files-work-${window.workProgressEditCounter}" class="existing-files-container">
                    <!-- Existing files will be loaded here dynamically -->
                </div>
            `;
            
            container.appendChild(workItem);
            
            if (!silent) {
                // Scroll to the newly added work item
                setTimeout(() => {
                    workItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
            
            return window.workProgressEditCounter;
        }

        // Remove work progress from edit form
        function removeWorkProgressFromEdit(id) {
            const workItem = document.getElementById(`edit-work-progress-${id}`);
            workItem.remove();
        }

        // Populate work progress in edit form
        function populateWorkProgressEdit(itemId, itemData) {
            if (itemData.id) {
                document.getElementById(`edit-work-progress-db-id-${itemId}`).value = itemData.id;
            }
            
            document.getElementById(`edit-work-type-${itemId}`).value = itemData.work_type;
            document.getElementById(`edit-work-status-${itemId}`).value = itemData.status;
            
            if (itemData.remarks) {
                document.getElementById(`edit-work-remarks-${itemId}`).value = itemData.remarks;
            }
            
            // Add existing files if any
            if (itemData.files && itemData.files.length > 0) {
                const filesContainer = document.getElementById(`edit-existing-files-work-${itemId}`);
                filesContainer.innerHTML = '<div class="existing-files-header"><i class="fas fa-paperclip"></i> Existing Files</div>';
                
                const filesGrid = document.createElement('div');
                filesGrid.className = 'existing-files-grid';
                
                itemData.files.forEach(file => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'existing-file-item';
                    if (file.type === 'video') {
                        fileItem.classList.add('video');
                    }
                    fileItem.onclick = function(e) {
                        // Prevent triggering if clicking on delete button
                        if (e.target.closest('.file-delete')) return;
                        openMediaModal(file.path, file.type);
                    };
                    fileItem.style.cursor = 'pointer';
                    
                    if (file.type === 'image') {
                        fileItem.innerHTML = `
                            <img src="${file.path}" alt="Work progress image">
                            <div class="file-delete" onclick="removeExistingFile(this, ${file.id})">
                                <i class="fas fa-times"></i>
                            </div>
                            <input type="hidden" name="edit_work_progress[${itemId}][existing_files][]" value="${file.id}">
                        `;
                    } else {
                        fileItem.innerHTML = `
                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: #343a40; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-film" style="font-size: 24px; color: white;"></i>
                            </div>
                            <div class="file-delete" onclick="removeExistingFile(this, ${file.id})">
                                <i class="fas fa-times"></i>
                            </div>
                            <input type="hidden" name="edit_work_progress[${itemId}][existing_files][]" value="${file.id}">
                        `;
                    }
                    
                    filesGrid.appendChild(fileItem);
                });
                
                filesContainer.appendChild(filesGrid);
            }
        }

        // Add inventory item to edit form
        function addInventoryItemToEdit(silent = false) {
            window.inventoryEditCounter = window.inventoryEditCounter || 0;
            window.inventoryEditCounter++;
            
            const container = document.getElementById('edit-inventory-list');
            
            // Count existing inventory items to determine number
            const existingItems = container.querySelectorAll('.inventory-container').length;
            const itemNumber = existingItems + 1;
            
            const inventoryDiv = document.createElement('div');
            inventoryDiv.className = 'inventory-container';
            inventoryDiv.id = `edit-inventory-${window.inventoryEditCounter}`;
            
            inventoryDiv.innerHTML = `
                <div class="item-header">
                    <h4>
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background-color: #3498db; color: white; border-radius: 50%; margin-right: 10px; font-size: 14px;">${itemNumber}</span>
                        <i class="fas fa-box"></i> Inventory Item
                    </h4>
                    <button type="button" class="remove-btn" onclick="removeInventoryItemFromEdit(${window.inventoryEditCounter})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <input type="hidden" name="edit_inventory[${window.inventoryEditCounter}][db_id]" id="edit-inventory-db-id-${window.inventoryEditCounter}" value="">
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="edit-material-type-${window.inventoryEditCounter}"><i class="fas fa-cubes"></i> Material</label>
                            <select class="form-control" id="edit-material-type-${window.inventoryEditCounter}" name="edit_inventory[${window.inventoryEditCounter}][material]" required>
                                <option value="">Select Material</option>
                                <option value="Cement">Cement</option>
                                <option value="Sand">Sand</option>
                                <option value="Aggregate">Aggregate</option>
                                <option value="Bricks">Bricks</option>
                                <option value="Steel">Steel</option>
                                <option value="Timber">Timber</option>
                                <option value="Paint">Paint</option>
                                <option value="Tiles">Tiles</option>
                                <option value="Glass">Glass</option>
                                <option value="Electrical Wires">Electrical Wires</option>
                                <option value="Pipes">Pipes</option>
                                <option value="Sanitary Fixtures">Sanitary Fixtures</option>
                                <option value="Concrete">Concrete</option>
                                <option value="Plaster">Plaster</option>
                                <option value="Gravel">Gravel</option>
                                <option value="Stone Dust">Stone Dust</option>
                                <option value="Water Proofing Materials">Water Proofing Materials</option>
                                <option value="Plywood">Plywood</option>
                                <option value="Adhesives">Adhesives</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="edit-quantity-${window.inventoryEditCounter}"><i class="fas fa-balance-scale"></i> Quantity</label>
                            <input type="number" class="form-control" id="edit-quantity-${window.inventoryEditCounter}" name="edit_inventory[${window.inventoryEditCounter}][quantity]" min="0" step="any" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="edit-unit-${window.inventoryEditCounter}"><i class="fas fa-ruler"></i> Unit</label>
                            <select class="form-control" id="edit-unit-${window.inventoryEditCounter}" name="edit_inventory[${window.inventoryEditCounter}][unit]" required>
                                <option value="">Select Unit</option>
                                <option value="Kg">Kg</option>
                                <option value="Bag">Bag</option>
                                <option value="Ton">Ton</option>
                                <option value="Cubic Meter">Cubic Meter</option>
                                <option value="Square Meter">Square Meter</option>
                                <option value="Meter">Meter</option>
                                <option value="Piece">Piece</option>
                                <option value="Number">Number</option>
                                <option value="Litre">Litre</option>
                                <option value="Quintal">Quintal</option>
                                <option value="Bundle">Bundle</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="edit-standard-values-${window.inventoryEditCounter}"><i class="fas fa-clipboard"></i> Standard Values/Notes</label>
                            <textarea class="form-control" id="edit-standard-values-${window.inventoryEditCounter}" name="edit_inventory[${window.inventoryEditCounter}][standard_values]" placeholder="Add any standard values or notes about the material..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="edit-inventory-files-${window.inventoryEditCounter}"><i class="fas fa-camera"></i> Upload Pictures/Videos</label>
                            <input type="file" class="form-control" id="edit-inventory-files-${window.inventoryEditCounter}" name="edit_inventory_files_${window.inventoryEditCounter}[]" multiple accept="image/*,video/*">
                            <small class="text-muted">You can select multiple files. Accepted formats: images and videos.</small>
                        </div>
                    </div>
                </div>
                <div id="edit-existing-files-inventory-${window.inventoryEditCounter}" class="existing-files-container">
                    <!-- Existing files will be loaded here dynamically -->
                </div>
            `;
            
            container.appendChild(inventoryDiv);
            
            if (!silent) {
                // Scroll to the newly added inventory
                setTimeout(() => {
                    inventoryDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
            
            return window.inventoryEditCounter;
        }

        // Remove inventory item from edit form
        function removeInventoryItemFromEdit(id) {
            const inventoryDiv = document.getElementById(`edit-inventory-${id}`);
            inventoryDiv.remove();
            
            // Update numbers for remaining inventory items
            const container = document.getElementById('edit-inventory-list');
            if (container) {
                const items = container.querySelectorAll('.inventory-container');
                items.forEach((item, index) => {
                    const numberEl = item.querySelector('.item-header h4 span');
                    if (numberEl) {
                        numberEl.textContent = index + 1;
                    }
                });
            }
        }

        // Populate inventory item in edit form
        function populateInventoryEdit(itemId, itemData) {
            if (itemData.id) {
                document.getElementById(`edit-inventory-db-id-${itemId}`).value = itemData.id;
            }
            
            document.getElementById(`edit-material-type-${itemId}`).value = itemData.material;
            document.getElementById(`edit-quantity-${itemId}`).value = itemData.quantity;
            document.getElementById(`edit-unit-${itemId}`).value = itemData.unit;
            
            if (itemData.standard_values) {
                document.getElementById(`edit-standard-values-${itemId}`).value = itemData.standard_values;
            }
            
            // Add existing files if any
            if (itemData.files && itemData.files.length > 0) {
                const filesContainer = document.getElementById(`edit-existing-files-inventory-${itemId}`);
                filesContainer.innerHTML = '<div class="existing-files-header"><i class="fas fa-paperclip"></i> Existing Files</div>';
                
                const filesGrid = document.createElement('div');
                filesGrid.className = 'existing-files-grid';
                
                itemData.files.forEach(file => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'existing-file-item';
                    if (file.type === 'video') {
                        fileItem.classList.add('video');
                    }
                    fileItem.onclick = function(e) {
                        // Prevent triggering if clicking on delete button
                        if (e.target.closest('.file-delete')) return;
                        openMediaModal(file.path, file.type);
                    };
                    fileItem.style.cursor = 'pointer';
                    
                    if (file.type === 'image') {
                        fileItem.innerHTML = `
                            <img src="${file.path}" alt="Inventory image">
                            <div class="file-delete" onclick="removeExistingFile(this, ${file.id})">
                                <i class="fas fa-times"></i>
                            </div>
                            <input type="hidden" name="edit_inventory[${itemId}][existing_files][]" value="${file.id}">
                        `;
                    } else {
                        fileItem.innerHTML = `
                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: #343a40; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-film" style="font-size: 24px; color: white;"></i>
                            </div>
                            <div class="file-delete" onclick="removeExistingFile(this, ${file.id})">
                                <i class="fas fa-times"></i>
                            </div>
                            <input type="hidden" name="edit_inventory[${itemId}][existing_files][]" value="${file.id}">
                        `;
                    }
                    
                    filesGrid.appendChild(fileItem);
                });
                
                filesContainer.appendChild(filesGrid);
            }
        }

        // Function to update all totals in the edit form
        function updateEditTotals() {
            // Calculate total wages
            let totalWages = 0;
            
            // Calculate vendor labour wages
            const vendorLabourTotals = document.querySelectorAll('.edit-vendor-labour-total');
            vendorLabourTotals.forEach(totalInput => {
                totalWages += parseFloat(totalInput.value) || 0;
            });
            
            // Calculate company labour wages
            const companyLabourTotals = document.querySelectorAll('.edit-company-labour-total');
            companyLabourTotals.forEach(totalInput => {
                totalWages += parseFloat(totalInput.value) || 0;
            });
            
            // Update total wages display and input
            document.getElementById('edit-total-wages').textContent = totalWages.toFixed(2);
            document.getElementById('edit-total-wages-input').value = totalWages.toFixed(2);
            
            // Calculate grand total (just total wages for now, can add more components later)
            const grandTotal = totalWages;
            
            // Update grand total display and input
            document.getElementById('edit-grand-total').textContent = grandTotal.toFixed(2);
            document.getElementById('edit-grand-total-input').value = grandTotal.toFixed(2);
        }

        // Handle removing existing files
        function removeExistingFile(element, fileId) {
            // Remove the file item from the UI
            const fileItem = element.closest('.existing-file-item');
            if (fileItem) {
                // Add a hidden input to track deleted files
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'deleted_files[]';
                hiddenInput.value = fileId;
                
                // Add to the form
                const form = document.getElementById('edit-site-update-form');
                if (form) {
                    form.appendChild(hiddenInput);
                }
                
                // Remove the element with animation
                fileItem.style.transition = 'all 0.3s ease';
                fileItem.style.transform = 'scale(0.8)';
                fileItem.style.opacity = '0';
                
                setTimeout(() => {
                    fileItem.remove();
                }, 300);
            }
        }
        
        // Function to open media in modal for preview
        function openMediaModal(filePath, fileType) {
            const modal = document.getElementById('siteDetailMediaModal');
            const modalContent = document.getElementById('siteDetailMediaContent');
            
            if (fileType === 'image') {
                modalContent.innerHTML = `<img src="${filePath}" alt="Media">`;
            } else if (fileType === 'video') {
                modalContent.innerHTML = `<video controls><source src="${filePath}" type="video/mp4">Your browser does not support the video tag.</video>`;
            }
            
            modal.style.display = 'block';
        }
        
        // Close media modal
        function closeMediaModal() {
            const modal = document.getElementById('siteDetailMediaModal');
            modal.style.display = 'none';
        }
    </script>
    
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
</body>
</html> 