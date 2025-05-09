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

if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
if (!file_exists($material_images_dir)) {
    mkdir($material_images_dir, 0777, true);
}
if (!file_exists($bill_images_dir)) {
    mkdir($bill_images_dir, 0777, true);
}

// Function to handle file uploads
function uploadFile($file, $target_dir) {
    if ($file['error'] != 0) {
        return false;
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_path = $target_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return $filename;
    } else {
        return false;
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
                        
                        $filename = uploadFile($file, $material_images_dir);
                        
                        if ($filename) {
                            $image_path = 'uploads/calendar_events/material_images/' . $filename;
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
                                'error' => $file['error']
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
                        
                        $filename = uploadFile($file, $bill_images_dir);
                        
                        if ($filename) {
                            $image_path = 'uploads/calendar_events/bill_images/' . $filename;
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
                                'error' => $file['error']
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
    
    // Commit the transaction
    $conn->commit();
    debug_log('Transaction committed successfully');
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Calendar event saved successfully',
        'event_id' => $event_id
    ]);
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