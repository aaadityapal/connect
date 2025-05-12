<?php
// Enable error logging to a file
ini_set('log_errors', 1);
ini_set('error_log', '../logs/calendar_event_errors.log');

// Create logs directory if it doesn't exist
if (!file_exists('../logs')) {
    mkdir('../logs', 0777, true);
}

// Add a debug function to log messages
function debug_log($message, $data = null) {
    $log_message = date('Y-m-d H:i:s') . ' - ' . $message;
    if ($data !== null) {
        $log_message .= ' - ' . json_encode($data);
    }
    error_log($log_message);
}

// Log the start of processing
debug_log('Starting calendar event save process');

// Database connection
require_once '../config.php';

// Use the PDO connection from config.php
$conn = $pdo;

// Log successful DB connection
debug_log('Database connection included');

session_start();

// Log session state
debug_log('Session started', ['user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set']);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    debug_log('Error: User not logged in');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = '../uploads/calendar_events/';
$material_images_dir = $upload_dir . 'material_images/';
$bill_images_dir = $upload_dir . 'bill_images/';
$work_media_dir = $upload_dir . 'work_progress_media/';
$inventory_media_dir = $upload_dir . 'inventory_media/';
$inventory_bills_dir = $upload_dir . 'inventory_bills/';

if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
if (!file_exists($material_images_dir)) {
    mkdir($material_images_dir, 0777, true);
}
if (!file_exists($bill_images_dir)) {
    mkdir($bill_images_dir, 0777, true);
}
if (!file_exists($work_media_dir)) {
    mkdir($work_media_dir, 0777, true);
}
if (!file_exists($inventory_media_dir)) {
    mkdir($inventory_media_dir, 0777, true);
}
if (!file_exists($inventory_bills_dir)) {
    mkdir($inventory_bills_dir, 0777, true);
}

// Debug mode
$debug_mode = false;

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Log function for debugging
function logDebug($message, $data = null) {
    global $debug_mode;
    if (!$debug_mode) return;
    
    $log_file = '../logs/calendar_events.log';
    $log_dir = dirname($log_file);
    
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}";
    
    if ($data !== null) {
        $log_message .= ": " . print_r($data, true);
    }
    
    file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
}

// Set content type to JSON
header('Content-Type: application/json');

// Function to create upload directory if it doesn't exist
function createUploadDirectory($path) {
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
        return true;
    }
    return file_exists($path) && is_writable($path);
}

// Function to handle file uploads
function handleFileUpload($file, $target_dir) {
    // Check if file was uploaded successfully
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $error_message = isset($file['error']) ? getUploadErrorMessage($file['error']) : 'Unknown error';
        return [
            'success' => false,
            'error' => "File upload error: {$error_message}"
        ];
    }
    
    // Create upload directory if it doesn't exist
    if (!createUploadDirectory($target_dir)) {
        return [
            'success' => false,
            'error' => "Failed to create upload directory: {$target_dir}"
        ];
    }
    
    // Validate file type (allow images, videos, and PDFs)
    $allowed_types = [
        // Images
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 
        // Documents
        'application/pdf',
        // Videos
        'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-flv', 
        'video/webm', 'video/mpeg', 'video/3gpp', 'video/ogg'
    ];
    
    debug_log('File upload type check', [
        'file_type' => $file['type'],
        'allowed' => in_array($file['type'], $allowed_types)
    ]);
    
    if (!in_array($file['type'], $allowed_types)) {
        return [
            'success' => false,
            'error' => "Invalid file type: {$file['type']}. Allowed types include images, videos, and PDFs."
        ];
    }
    
    // Generate unique filename to prevent overwrites
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_path = $target_dir . $filename;
    
    // Move uploaded file to target directory
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Log successful upload
        debug_log('File uploaded successfully', [
            'original_name' => $file['name'],
            'saved_as' => $filename,
            'file_type' => $file['type'],
            'file_size' => $file['size'],
            'path' => $target_path
        ]);
        
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $target_path,
            'mime_type' => $file['type'],
            'is_video' => strpos($file['type'], 'video/') === 0
        ];
    } else {
        debug_log('File upload failed', [
            'from' => $file['tmp_name'],
            'to' => $target_path,
            'error' => error_get_last()
        ]);
        
        return [
            'success' => false,
            'error' => "Failed to move uploaded file to {$target_path}"
        ];
    }
}

// Get upload error message
function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload';
        default:
            return 'Unknown upload error';
    }
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();
    debug_log('Started database transaction');
    
    // Get event data
    $event_title = $_POST['event_title'];
    $event_date = $_POST['event_date'];
    $user_id = $_SESSION['user_id'];
    
    debug_log('Processing event data', [
        'title' => $event_title,
        'date' => $event_date,
        'user_id' => $user_id
    ]);
    
    // Insert event
    $sql = "INSERT INTO sv_calendar_events (title, event_date, created_by) 
            VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare statement failed");
    }
    $stmt->execute([$event_title, $event_date, $user_id]);
    
    $event_id = $conn->lastInsertId();
    debug_log('Event created successfully', ['event_id' => $event_id]);
    
    // Process vendors
    if (isset($_POST['vendor_count']) && $_POST['vendor_count'] > 0) {
        $vendor_count = intval($_POST['vendor_count']);
        debug_log('Processing vendors', ['count' => $vendor_count]);
        
        for ($i = 1; $i <= $vendor_count; $i++) {
            if (!isset($_POST["vendor_name_$i"])) {
                debug_log('Skipping vendor', ['index' => $i, 'reason' => 'vendor_name not set']);
                continue;
            }
            
            $vendor_type = $_POST["vendor_type_$i"];
            $vendor_name = $_POST["vendor_name_$i"];
            $contact_number = $_POST["contact_number_$i"] ?? '';
            
            debug_log('Processing vendor', [
                'index' => $i,
                'type' => $vendor_type,
                'name' => $vendor_name
            ]);
            
            // Insert vendor
            $sql = "INSERT INTO sv_event_vendors (event_id, vendor_type, vendor_name, contact_number, sequence_number) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare vendor statement failed");
            }
            
            $stmt->execute([$event_id, $vendor_type, $vendor_name, $contact_number, $i]);
            
            $vendor_id = $conn->lastInsertId();
            debug_log('Vendor created successfully', ['vendor_id' => $vendor_id]);
            
            // Process materials for this vendor
            if (isset($_POST["material_count_$i"]) && $_POST["material_count_$i"] > 0) {
                $material_count = intval($_POST["material_count_$i"]);
                debug_log('Processing materials', ['vendor_id' => $vendor_id, 'count' => $material_count]);
                
                for ($j = 1; $j <= $material_count; $j++) {
                    $material_key = "material_{$i}_{$j}";
                    
                    debug_log('Processing material', ['material_key' => $material_key]);
                    
                    if (!isset($_POST["remarks_$material_key"])) {
                        debug_log('Skipping material', ['material_key' => $material_key, 'reason' => 'remarks not set']);
                        continue;
                    }
                    
                    $remarks = $_POST["remarks_$material_key"];
                    $amount = $_POST["amount_$material_key"] ?? 0;
                    
                    debug_log('Material data', [
                        'material_key' => $material_key,
                        'remarks' => $remarks,
                        'amount' => $amount
                    ]);
                    
                    // Insert material
                    $sql = "INSERT INTO sv_vendor_materials (vendor_id, remarks, amount) 
                            VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Prepare material statement failed");
                    }
                    
                    $stmt->execute([$vendor_id, $remarks, $amount]);
                    
                    $material_id = $conn->lastInsertId();
                    debug_log('Material created successfully', ['material_id' => $material_id]);
                    
                    // Handle material images upload
                    if (isset($_FILES["material_images_$material_key"]) && $_FILES["material_images_$material_key"]['error'] == 0) {
                        $file = $_FILES["material_images_$material_key"];
                        debug_log('Processing material image', [
                            'material_key' => $material_key,
                            'file_name' => $file['name'],
                            'file_size' => $file['size']
                        ]);
                        
                        $upload_result = handleFileUpload($file, $material_images_dir);
                        
                        if ($upload_result['success']) {
                            $image_path = $upload_result['path'];
                            debug_log('Material image uploaded', ['path' => $image_path]);
                            
                            $sql = "INSERT INTO sv_material_images (material_id, image_path) VALUES (?, ?)";
                            $stmt = $conn->prepare($sql);
                            if (!$stmt) {
                                throw new Exception("Prepare material image statement failed");
                            }
                            
                            $stmt->execute([$material_id, $image_path]);
                            
                            debug_log('Material image saved to database');
                        } else {
                            debug_log('Failed to upload material image', [
                                'material_key' => $material_key,
                                'error' => $upload_result['error']
                            ]);
                        }
                    }
                    
                    // Handle bill image upload
                    if (isset($_FILES["bill_image_$material_key"]) && $_FILES["bill_image_$material_key"]['error'] == 0) {
                        $file = $_FILES["bill_image_$material_key"];
                        debug_log('Processing bill image', [
                            'material_key' => $material_key,
                            'file_name' => $file['name'],
                            'file_size' => $file['size']
                        ]);
                        
                        $upload_result = handleFileUpload($file, $bill_images_dir);
                        
                        if ($upload_result['success']) {
                            $image_path = $upload_result['path'];
                            debug_log('Bill image uploaded', ['path' => $image_path]);
                            
                            $sql = "INSERT INTO sv_bill_images (material_id, image_path) VALUES (?, ?)";
                            $stmt = $conn->prepare($sql);
                            if (!$stmt) {
                                throw new Exception("Prepare bill image statement failed");
                            }
                            
                            $stmt->execute([$material_id, $image_path]);
                            
                            debug_log('Bill image saved to database');
                        } else {
                            debug_log('Failed to upload bill image', [
                                'material_key' => $material_key,
                                'error' => $upload_result['error']
                            ]);
                        }
                    }
                }
            }
            
            // Process labours for this vendor
            if (isset($_POST["labour_count_$i"]) && $_POST["labour_count_$i"] > 0) {
                $labour_count = intval($_POST["labour_count_$i"]);
                
                for ($k = 1; $k <= $labour_count; $k++) {
                    $labour_key = "labour_{$i}_{$k}";
                    
                    if (!isset($_POST["labour_name_$labour_key"])) continue;
                    
                    $labour_name = $_POST["labour_name_$labour_key"];
                    $contact_number = $_POST["labour_number_$labour_key"] ?? '';
                    $morning_attendance = $_POST["morning_attendance_$labour_key"] ?? 'present';
                    $evening_attendance = $_POST["evening_attendance_$labour_key"] ?? 'present';
                    
                    // Insert labour
                    $sql = "INSERT INTO sv_vendor_labours (vendor_id, labour_name, contact_number, sequence_number, morning_attendance, evening_attendance) 
                            VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    
                    $stmt->execute([$vendor_id, $labour_name, $contact_number, $k, $morning_attendance, $evening_attendance]);
                    $labour_id = $conn->lastInsertId();
                    
                    // If labour wage info is included, save it
                    if (isset($_POST["daily_wage_$labour_key"])) {
                        $daily_wage = $_POST["daily_wage_$labour_key"] ?? 0;
                        $total_day_wage = $_POST["total_day_wage_$labour_key"] ?? 0;
                        $ot_hours = $_POST["ot_hours_$labour_key"] ?? 0;
                        $ot_minutes = $_POST["ot_minutes_$labour_key"] ?? 0;
                        $ot_rate = $_POST["ot_rate_$labour_key"] ?? 0;
                        $total_ot_amount = $_POST["total_ot_amount_$labour_key"] ?? 0;
                        $transport_mode = $_POST["transport_mode_$labour_key"] ?? '';
                        $travel_amount = $_POST["travel_amount_$labour_key"] ?? 0;
                        $grand_total = $_POST["grand_total_$labour_key"] ?? 0;
                        
                        $sql = "INSERT INTO sv_labour_wages (labour_id, daily_wage, total_day_wage, ot_hours, ot_minutes, ot_rate, total_ot_amount, transport_mode, travel_amount, grand_total) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            $labour_id, $daily_wage, $total_day_wage, $ot_hours, $ot_minutes, 
                            $ot_rate, $total_ot_amount, $transport_mode, $travel_amount, $grand_total
                        ]);
                    }
                }
            }
        }
    }
    
    // Process company labours if any
    if (isset($_POST['company_labour_count']) && $_POST['company_labour_count'] > 0) {
        $labour_count = intval($_POST['company_labour_count']);
        
        for ($i = 1; $i <= $labour_count; $i++) {
            if (!isset($_POST["company_labour_name_$i"])) continue;
            
            $labour_name = $_POST["company_labour_name_$i"];
            $contact_number = $_POST["company_labour_number_$i"] ?? '';
            $morning_attendance = $_POST["company_morning_attendance_$i"] ?? 'present';
            $evening_attendance = $_POST["company_evening_attendance_$i"] ?? 'present';
            
            // Insert company labour
            $sql = "INSERT INTO sv_company_labours (event_id, labour_name, contact_number, sequence_number, morning_attendance, evening_attendance) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$event_id, $labour_name, $contact_number, $i, $morning_attendance, $evening_attendance]);
            $labour_id = $conn->lastInsertId();
            
            // If labour wage info is included, save it
            if (isset($_POST["company_daily_wage_$i"])) {
                $daily_wage = $_POST["company_daily_wage_$i"] ?? 0;
                $total_day_wage = $_POST["company_total_day_wage_$i"] ?? 0;
                $ot_hours = $_POST["company_ot_hours_$i"] ?? 0;
                $ot_minutes = $_POST["company_ot_minutes_$i"] ?? 0;
                $ot_rate = $_POST["company_ot_rate_$i"] ?? 0;
                $total_ot_amount = $_POST["company_total_ot_amount_$i"] ?? 0;
                $transport_mode = $_POST["company_transport_mode_$i"] ?? '';
                $travel_amount = $_POST["company_travel_amount_$i"] ?? 0;
                $grand_total = $_POST["company_grand_total_$i"] ?? 0;
                
                $sql = "INSERT INTO sv_company_wages (company_labour_id, daily_wage, total_day_wage, ot_hours, ot_minutes, ot_rate, total_ot_amount, transport_mode, travel_amount, grand_total) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $labour_id, $daily_wage, $total_day_wage, $ot_hours, $ot_minutes, 
                    $ot_rate, $total_ot_amount, $transport_mode, $travel_amount, $grand_total
                ]);
            }
        }
    }

    // Process beverages if any
    if (isset($_POST['beverage_count']) && $_POST['beverage_count'] > 0) {
        $beverage_count = intval($_POST['beverage_count']);
        debug_log('Processing beverages', ['count' => $beverage_count]);
        
        for ($i = 1; $i <= $beverage_count; $i++) {
            $beverage_type = $_POST["beverage_type_$i"] ?? '';
            $beverage_name = $_POST["beverage_name_$i"] ?? '';
            $beverage_amount = $_POST["beverage_amount_$i"] ?? 0;
            
            debug_log('Processing beverage', [
                'number' => $i,
                'type' => $beverage_type,
                'name' => $beverage_name,
                'amount' => $beverage_amount
            ]);
            
            if (!empty($beverage_type) || !empty($beverage_name)) {
                try {
                    $stmt = $conn->prepare("INSERT INTO sv_event_beverages 
                        (event_id, beverage_type, beverage_name, amount, sequence_number) 
                        VALUES (:event_id, :beverage_type, :beverage_name, :amount, :sequence_number)");
                    
                    $stmt->execute([
                        ':event_id' => $event_id,
                        ':beverage_type' => $beverage_type,
                        ':beverage_name' => $beverage_name,
                        ':amount' => $beverage_amount,
                        ':sequence_number' => $i
                    ]);
                    
                    debug_log('Beverage saved successfully', [
                        'beverage_id' => $conn->lastInsertId(),
                        'number' => $i
                    ]);
                } catch (PDOException $e) {
                    debug_log('Error saving beverage', [
                        'number' => $i,
                        'error' => $e->getMessage()
                    ]);
                    
                    // Check if table exists, if not, try to create it
                    if (strpos($e->getMessage(), "sv_event_beverages' doesn't exist") !== false) {
                        debug_log('Attempting to create sv_event_beverages table');
                        try {
                            $conn->exec("CREATE TABLE IF NOT EXISTS `sv_event_beverages` (
                                `beverage_id` INT AUTO_INCREMENT PRIMARY KEY,
                                `event_id` INT NOT NULL,
                                `beverage_type` VARCHAR(100),
                                `beverage_name` VARCHAR(100),
                                `amount` DECIMAL(10,2),
                                `sequence_number` INT,
                                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                            
                            // Retry insertion
                            $stmt = $conn->prepare("INSERT INTO sv_event_beverages 
                                (event_id, beverage_type, beverage_name, amount, sequence_number) 
                                VALUES (:event_id, :beverage_type, :beverage_name, :amount, :sequence_number)");
                            
                            $stmt->execute([
                                ':event_id' => $event_id,
                                ':beverage_type' => $beverage_type,
                                ':beverage_name' => $beverage_name,
                                ':amount' => $beverage_amount,
                                ':sequence_number' => $i
                            ]);
                            
                            debug_log('Beverage saved after creating table', [
                                'beverage_id' => $conn->lastInsertId(),
                                'number' => $i
                            ]);
                        } catch (PDOException $innerEx) {
                            debug_log('Failed to create table or retry insertion', [
                                'error' => $innerEx->getMessage()
                            ]);
                        }
                    }
                }
            }
        }
    }
    
    // Process work progress entries if any
    if (isset($_POST['work_progress_count']) && $_POST['work_progress_count'] > 0) {
        $work_progress_count = intval($_POST['work_progress_count']);
        debug_log('Processing work progress entries', ['count' => $work_progress_count]);
        
        for ($i = 1; $i <= $work_progress_count; $i++) {
            $work_category = $_POST["work_category_$i"] ?? '';
            $work_type = $_POST["work_type_$i"] ?? '';
            $work_done = $_POST["work_done_$i"] ?? 'yes';
            $work_remarks = $_POST["work_remarks_$i"] ?? '';
            
            debug_log('Processing work progress entry', [
                'number' => $i,
                'category' => $work_category,
                'type' => $work_type,
                'done' => $work_done
            ]);
            
            if (!empty($work_category) || !empty($work_type) || !empty($work_remarks)) {
                try {
                    // Insert work progress entry
                    $stmt = $conn->prepare("INSERT INTO sv_work_progress 
                        (event_id, work_category, work_type, work_done, remarks, sequence_number) 
                        VALUES (:event_id, :work_category, :work_type, :work_done, :remarks, :sequence_number)");
                    
                    $stmt->execute([
                        ':event_id' => $event_id,
                        ':work_category' => $work_category,
                        ':work_type' => $work_type,
                        ':work_done' => $work_done,
                        ':remarks' => $work_remarks,
                        ':sequence_number' => $i
                    ]);
                    
                    $work_id = $conn->lastInsertId();
                    debug_log('Work progress entry saved successfully', [
                        'work_id' => $work_id,
                        'number' => $i
                    ]);
                    
                    // Process media files for this work entry
                    $media_count = isset($_POST["work_media_count_$i"]) ? intval($_POST["work_media_count_$i"]) : 0;
                    
                    if ($media_count > 0) {
                        debug_log('Processing work media files', [
                            'work_id' => $work_id,
                            'count' => $media_count
                        ]);
                        
                        // Create per-work directory to organize files better
                        $work_specific_dir = $work_media_dir . 'work_' . $work_id . '/';
                        if (!file_exists($work_specific_dir)) {
                            mkdir($work_specific_dir, 0777, true);
                            debug_log('Created work-specific media directory', [
                                'directory' => $work_specific_dir
                            ]);
                        }
                        
                        for ($j = 1; $j <= $media_count; $j++) {
                            $media_key = "work_media_{$i}_{$j}";
                            
                            if (isset($_FILES[$media_key]) && $_FILES[$media_key]['error'] === UPLOAD_ERR_OK) {
                                $file = $_FILES[$media_key];
                                debug_log('Processing work media file', [
                                    'work_id' => $work_id,
                                    'file_name' => $file['name'],
                                    'file_type' => $file['type'],
                                    'file_size' => $file['size']
                                ]);
                                
                                // Determine if it's an image or video
                                $media_type = 'image';
                                if (strpos($file['type'], 'video/') === 0) {
                                    $media_type = 'video';
                                }
                                
                                // Add timestamp to filename to prevent conflicts with multiple uploads
                                $file_name_parts = pathinfo($file['name']);
                                $unique_name = $file_name_parts['filename'] . '_' . time() . '_' . mt_rand(1000, 9999);
                                if (isset($file_name_parts['extension'])) {
                                    $unique_name .= '.' . $file_name_parts['extension'];
                                }
                                
                                // Use unique name in upload
                                $file['name'] = $unique_name;
                                
                                // Upload the file to the work-specific directory
                                $upload_result = handleFileUpload($file, $work_specific_dir);
                                
                                if ($upload_result['success']) {
                                    $file_path = $upload_result['path'];
                                    $orig_file_name = $_FILES[$media_key]['name']; // Store original filename
                                    
                                    debug_log('Work media file uploaded', [
                                        'path' => $file_path,
                                        'filename' => $unique_name,
                                        'original_name' => $orig_file_name
                                    ]);
                                    
                                    // Save the media record in the database
                                    $stmt = $conn->prepare("INSERT INTO sv_work_progress_media 
                                        (work_id, file_name, file_path, media_type, file_size, sequence_number) 
                                        VALUES (:work_id, :file_name, :file_path, :media_type, :file_size, :sequence_number)");
                                    
                                    $stmt->execute([
                                        ':work_id' => $work_id,
                                        ':file_name' => $orig_file_name, // Original filename for display
                                        ':file_path' => $file_path,
                                        ':media_type' => $media_type,
                                        ':file_size' => $file['size'],
                                        ':sequence_number' => $j
                                    ]);
                                    
                                    $media_id = $conn->lastInsertId();
                                    debug_log('Work media record saved to database', [
                                        'media_id' => $media_id
                                    ]);
                                } else {
                                    debug_log('Failed to upload work media file', [
                                        'error' => $upload_result['error']
                                    ]);
                                }
                            } else if (isset($_FILES[$media_key])) {
                                // Log upload errors other than 'no file'
                                if ($_FILES[$media_key]['error'] !== UPLOAD_ERR_NO_FILE) {
                                    debug_log('Work media file upload error', [
                                        'error_code' => $_FILES[$media_key]['error'],
                                        'error_message' => getUploadErrorMessage($_FILES[$media_key]['error'])
                                    ]);
                                }
                            }
                        }
                    }
                } catch (PDOException $e) {
                    debug_log('Error saving work progress entry', [
                        'number' => $i,
                        'error' => $e->getMessage()
                    ]);
                    
                    // Check if table exists, if not, try to create it
                    if (strpos($e->getMessage(), "sv_work_progress' doesn't exist") !== false) {
                        debug_log('Attempting to create work progress tables');
                        try {
                            // Create work progress table
                            $conn->exec("CREATE TABLE IF NOT EXISTS `sv_work_progress` (
                                `work_id` INT AUTO_INCREMENT PRIMARY KEY,
                                `event_id` INT NOT NULL,
                                `work_category` VARCHAR(100) NOT NULL,
                                `work_type` VARCHAR(100) NOT NULL,
                                `work_done` ENUM('yes', 'no') DEFAULT 'yes',
                                `remarks` TEXT,
                                `sequence_number` INT,
                                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                            
                            // Create work media table
                            $conn->exec("CREATE TABLE IF NOT EXISTS `sv_work_progress_media` (
                                `media_id` INT AUTO_INCREMENT PRIMARY KEY,
                                `work_id` INT NOT NULL,
                                `file_name` VARCHAR(255) NOT NULL,
                                `file_path` VARCHAR(255) NOT NULL,
                                `media_type` ENUM('image', 'video') DEFAULT 'image',
                                `file_size` INT,
                                `sequence_number` INT,
                                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                FOREIGN KEY (`work_id`) REFERENCES `sv_work_progress`(`work_id`) ON DELETE CASCADE
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                            
                            debug_log('Work progress tables created successfully');
                            
                            // Retry insertion
                            $stmt = $conn->prepare("INSERT INTO sv_work_progress 
                                (event_id, work_category, work_type, work_done, remarks, sequence_number) 
                                VALUES (:event_id, :work_category, :work_type, :work_done, :remarks, :sequence_number)");
                            
                            $stmt->execute([
                                ':event_id' => $event_id,
                                ':work_category' => $work_category,
                                ':work_type' => $work_type,
                                ':work_done' => $work_done,
                                ':remarks' => $work_remarks,
                                ':sequence_number' => $i
                            ]);
                            
                            $work_id = $conn->lastInsertId();
                            debug_log('Work progress entry saved after creating table', [
                                'work_id' => $work_id,
                                'number' => $i
                            ]);
                        } catch (PDOException $innerEx) {
                            debug_log('Failed to create tables or retry insertion', [
                                'error' => $innerEx->getMessage()
                            ]);
                        }
                    }
                }
            }
        }
    }
    
    // Process inventory entries if any
    if (isset($_POST['inventory_count']) && $_POST['inventory_count'] > 0) {
        $inventory_count = intval($_POST['inventory_count']);
        debug_log('Processing inventory entries', ['count' => $inventory_count]);
        
        for ($i = 1; $i <= $inventory_count; $i++) {
            $inventory_type = $_POST["inventory_type_$i"] ?? 'received';
            $material_type = $_POST["material_type_$i"] ?? '';
            $quantity = $_POST["quantity_$i"] ?? 0;
            $unit = $_POST["unit_$i"] ?? '';
            $remarks = $_POST["inventory_remarks_$i"] ?? '';
            
            debug_log('Processing inventory entry', [
                'number' => $i,
                'type' => $inventory_type,
                'material' => $material_type,
                'quantity' => $quantity,
                'unit' => $unit
            ]);
            
            if (!empty($material_type)) {
                try {
                    // Insert inventory entry
                    $stmt = $conn->prepare("INSERT INTO sv_inventory_items 
                        (event_id, inventory_type, material_type, quantity, unit, remarks, sequence_number) 
                        VALUES (:event_id, :inventory_type, :material_type, :quantity, :unit, :remarks, :sequence_number)");
                    
                    $stmt->execute([
                        ':event_id' => $event_id,
                        ':inventory_type' => $inventory_type,
                        ':material_type' => $material_type,
                        ':quantity' => $quantity,
                        ':unit' => $unit,
                        ':remarks' => $remarks,
                        ':sequence_number' => $i
                    ]);
                    
                    $inventory_id = $conn->lastInsertId();
                    debug_log('Inventory entry saved successfully', [
                        'inventory_id' => $inventory_id,
                        'number' => $i
                    ]);
                    
                    // Process bill image for this inventory item
                    if (isset($_FILES["inventory_bill_$i"]) && $_FILES["inventory_bill_$i"]['error'] === UPLOAD_ERR_OK) {
                        $file = $_FILES["inventory_bill_$i"];
                        debug_log('Processing inventory bill image', [
                            'inventory_id' => $inventory_id,
                            'file_name' => $file['name'],
                            'file_size' => $file['size']
                        ]);
                        
                        // Create inventory-specific directory for bills
                        $inventory_bill_specific_dir = $inventory_bills_dir . 'inventory_' . $inventory_id . '/';
                        if (!file_exists($inventory_bill_specific_dir)) {
                            mkdir($inventory_bill_specific_dir, 0777, true);
                        }
                        
                        $upload_result = handleFileUpload($file, $inventory_bill_specific_dir);
                        
                        if ($upload_result['success']) {
                            $file_path = $upload_result['path'];
                            
                            // Save bill image record
                            $stmt = $conn->prepare("INSERT INTO sv_inventory_media 
                                (inventory_id, file_name, file_path, media_type, file_size, sequence_number) 
                                VALUES (:inventory_id, :file_name, :file_path, :media_type, :file_size, :sequence_number)");
                            
                            $stmt->execute([
                                ':inventory_id' => $inventory_id,
                                ':file_name' => $file['name'],
                                ':file_path' => $file_path,
                                ':media_type' => 'bill',
                                ':file_size' => $file['size'],
                                ':sequence_number' => 1
                            ]);
                            
                            debug_log('Inventory bill image saved to database', [
                                'media_id' => $conn->lastInsertId()
                            ]);
                        }
                    }
                    
                    // Process media files for this inventory item
                    $media_count = isset($_POST["inventory_media_count_$i"]) ? intval($_POST["inventory_media_count_$i"]) : 0;
                    
                    if ($media_count > 0) {
                        debug_log('Processing inventory media files', [
                            'inventory_id' => $inventory_id,
                            'count' => $media_count
                        ]);
                        
                        // Create inventory-specific media directory if not already created for bill
                        $inventory_specific_dir = $inventory_media_dir . 'inventory_' . $inventory_id . '/';
                        if (!file_exists($inventory_specific_dir)) {
                            mkdir($inventory_specific_dir, 0777, true);
                        }
                        
                        for ($j = 1; $j <= $media_count; $j++) {
                            $media_key = "inventory_media_{$i}_{$j}";
                            
                            if (isset($_FILES[$media_key]) && $_FILES[$media_key]['error'] === UPLOAD_ERR_OK) {
                                $file = $_FILES[$media_key];
                                debug_log('Processing inventory media file', [
                                    'inventory_id' => $inventory_id,
                                    'file_name' => $file['name'],
                                    'file_type' => $file['type'],
                                    'file_size' => $file['size']
                                ]);
                                
                                // Determine if it's an image or video
                                $media_type = 'photo';
                                if (strpos($file['type'], 'video/') === 0) {
                                    $media_type = 'video';
                                }
                                
                                // Add timestamp to filename to prevent conflicts
                                $file_name_parts = pathinfo($file['name']);
                                $unique_name = $file_name_parts['filename'] . '_' . time() . '_' . mt_rand(1000, 9999);
                                if (isset($file_name_parts['extension'])) {
                                    $unique_name .= '.' . $file_name_parts['extension'];
                                }
                                
                                // Use unique name in upload
                                $file['name'] = $unique_name;
                                
                                // Upload the file
                                $upload_result = handleFileUpload($file, $inventory_specific_dir);
                                
                                if ($upload_result['success']) {
                                    $file_path = $upload_result['path'];
                                    $orig_file_name = $_FILES[$media_key]['name']; // Store original filename
                                    
                                    debug_log('Inventory media file uploaded', [
                                        'path' => $file_path,
                                        'filename' => $unique_name,
                                        'original_name' => $orig_file_name
                                    ]);
                                    
                                    // Save the media record
                                    $stmt = $conn->prepare("INSERT INTO sv_inventory_media 
                                        (inventory_id, file_name, file_path, media_type, file_size, sequence_number) 
                                        VALUES (:inventory_id, :file_name, :file_path, :media_type, :file_size, :sequence_number)");
                                    
                                    $stmt->execute([
                                        ':inventory_id' => $inventory_id,
                                        ':file_name' => $orig_file_name,
                                        ':file_path' => $file_path,
                                        ':media_type' => $media_type,
                                        ':file_size' => $file['size'],
                                        ':sequence_number' => $j + 1 // +1 because bill is sequence 1
                                    ]);
                                    
                                    debug_log('Inventory media record saved to database', [
                                        'media_id' => $conn->lastInsertId()
                                    ]);
                                }
                            }
                        }
                    }
                } catch (PDOException $e) {
                    debug_log('Error saving inventory entry', [
                        'number' => $i,
                        'error' => $e->getMessage()
                    ]);
                    
                    // Check if tables exist, if not, try to create them
                    if (strpos($e->getMessage(), "sv_inventory_items' doesn't exist") !== false) {
                        debug_log('Attempting to create inventory tables');
                        try {
                            // Create inventory items table
                            $conn->exec("CREATE TABLE IF NOT EXISTS `sv_inventory_items` (
                                `inventory_id` INT AUTO_INCREMENT PRIMARY KEY,
                                `event_id` INT NOT NULL,
                                `inventory_type` ENUM('received', 'consumed', 'other') DEFAULT 'received',
                                `material_type` VARCHAR(100) NOT NULL,
                                `quantity` DECIMAL(10,2) DEFAULT 0,
                                `unit` VARCHAR(20),
                                `remarks` TEXT,
                                `sequence_number` INT,
                                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                            
                            // Create inventory media table
                            $conn->exec("CREATE TABLE IF NOT EXISTS `sv_inventory_media` (
                                `media_id` INT AUTO_INCREMENT PRIMARY KEY,
                                `inventory_id` INT NOT NULL,
                                `file_name` VARCHAR(255) NOT NULL,
                                `file_path` VARCHAR(255) NOT NULL,
                                `media_type` ENUM('bill', 'photo', 'video') DEFAULT 'photo',
                                `file_size` INT,
                                `sequence_number` INT,
                                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                FOREIGN KEY (`inventory_id`) REFERENCES `sv_inventory_items`(`inventory_id`) ON DELETE CASCADE
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                            
                            debug_log('Inventory tables created successfully');
                            
                            // Retry insertion
                            $stmt = $conn->prepare("INSERT INTO sv_inventory_items 
                                (event_id, inventory_type, material_type, quantity, unit, remarks, sequence_number) 
                                VALUES (:event_id, :inventory_type, :material_type, :quantity, :unit, :remarks, :sequence_number)");
                            
                            $stmt->execute([
                                ':event_id' => $event_id,
                                ':inventory_type' => $inventory_type,
                                ':material_type' => $material_type,
                                ':quantity' => $quantity,
                                ':unit' => $unit,
                                ':remarks' => $remarks,
                                ':sequence_number' => $i
                            ]);
                            
                            $inventory_id = $conn->lastInsertId();
                            debug_log('Inventory entry saved after creating table', [
                                'inventory_id' => $inventory_id,
                                'number' => $i
                            ]);
                        } catch (PDOException $innerEx) {
                            debug_log('Failed to create tables or retry insertion', [
                                'error' => $innerEx->getMessage()
                            ]);
                        }
                    }
                }
            }
        }
    }
    
    // Commit the transaction
    $conn->commit();
    debug_log('Transaction committed successfully');
    
    // Process file uploads (Material and Bill Images)
    $material_images = [];
    $bill_images = [];

    // Handle material images
    $material_image_count = isset($_POST['material_count']) ? intval($_POST['material_count']) : 0;
    $upload_base_dir = '../uploads/calendar_events/';
    $material_upload_dir = $upload_base_dir . 'material_images/';
    $bill_upload_dir = $upload_base_dir . 'bill_images/';

    // Create upload directories if they don't exist
    createUploadDirectory($upload_base_dir);
    createUploadDirectory($material_upload_dir);
    createUploadDirectory($bill_upload_dir);

    for ($i = 1; $i <= $material_image_count; $i++) {
        $material_image_key = "material_image_{$i}";
        if (isset($_FILES[$material_image_key]) && $_FILES[$material_image_key]['name']) {
            $upload_result = handleFileUpload($_FILES[$material_image_key], $material_upload_dir);
            if ($upload_result['success']) {
                $material_images[$i] = $upload_result;
            } else {
                logDebug("Material image upload failed", $upload_result);
            }
        }
    }

    // Handle bill images
    $vendor_count = isset($_POST['vendor_count']) ? intval($_POST['vendor_count']) : 0;
    for ($i = 1; $i <= $vendor_count; $i++) {
        $bill_image_key = "bill_image_{$i}";
        if (isset($_FILES[$bill_image_key]) && $_FILES[$bill_image_key]['name']) {
            $upload_result = handleFileUpload($_FILES[$bill_image_key], $bill_upload_dir);
            if ($upload_result['success']) {
                $bill_images[$i] = $upload_result;
            } else {
                logDebug("Bill image upload failed", $upload_result);
            }
        }
    }

    // Return response with file upload info
    $response = [
        'status' => 'success',
        'message' => 'Calendar event saved successfully',
        'event_id' => $event_id,
        'material_images' => $material_images,
        'bill_images' => $bill_images
    ];

    echo json_encode($response);
    debug_log('Save process completed successfully', ['event_id' => $event_id]);
    
} catch (Exception $e) {
    // Roll back the transaction in case of error
    $conn->rollBack();
    debug_log('Error occurred, transaction rolled back', ['error' => $e->getMessage()]);
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while saving the event: ' . $e->getMessage()
    ]);
}

// Close connection (not needed for PDO)
?> 