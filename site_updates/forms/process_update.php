<?php
/**
 * Site Update Form Processor
 * 
 * This file processes the site update form submission and saves all data to the database.
 * It handles all form sections: vendors, laborers, company labor, travel expenses, 
 * beverages, work progress, and inventory.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
session_start();
}

// Debug: Log the $_FILES array to check if files are being received
error_log("FILES array: " . print_r($_FILES, true));

// Include database connection
require_once '../../config/db_connect.php';

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to handle file upload
function handle_file_upload($file, $upload_dir) {
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        error_log("Creating directory: " . $upload_dir);
        $dir_created = mkdir($upload_dir, 0777, true);
        error_log("Directory creation " . ($dir_created ? "successful" : "failed"));
        
        if (!$dir_created) {
            error_log("Failed to create directory. Error: " . error_get_last()['message']);
            return false;
        }
    }
    
    $file_name = basename($file["name"]);
    $target_file = $upload_dir . '/' . time() . '_' . $file_name;
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if file is an actual image or video
    $allowed_image_types = ["jpg", "jpeg", "png", "gif"];
    $allowed_video_types = ["mp4", "avi", "mov", "wmv"];
    
    if (in_array($file_type, $allowed_image_types)) {
        $file_category = 'image';
    } else if (in_array($file_type, $allowed_video_types)) {
        $file_category = 'video';
    } else {
        error_log("Invalid file type: " . $file_type);
        return false; // Invalid file type
    }
    
    // Log the attempt to upload the file
    error_log("Attempting to move uploaded file from " . $file["tmp_name"] . " to " . $target_file);
    
    // Upload file
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        error_log("File moved successfully");
        return [
            'file_path' => $target_file,
            'file_name' => $file_name,
            'file_type' => $file_category
        ];
    } else {
        error_log("Failed to move uploaded file. Error: " . error_get_last()['message']);
        error_log("Upload error code: " . $file["error"]);
        return false;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_update'])) {
    // Initialize response array
    $response = [
        'status' => 'error',
        'message' => '',
        'errors' => []
    ];
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // 1. Process main site update data
        $site_name = sanitize_input($_POST['site_name'] ?? '');
        $update_date = sanitize_input($_POST['update_date'] ?? '');
        $user_id = $_SESSION['user_id'] ?? 0; // Assuming user ID is stored in session
        
        // Validate required fields
    if (empty($site_name)) {
            $response['errors'][] = "Site name is required";
    }
    
    if (empty($update_date)) {
            $response['errors'][] = "Update date is required";
        }
        
        // If validation errors, return early
        if (!empty($response['errors'])) {
            $response['message'] = "Validation errors occurred";
            echo json_encode($response);
            exit;
        }
        
        // Insert main site update record
        $stmt = $conn->prepare("INSERT INTO site_updates (site_name, update_date, created_by) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $site_name, $update_date, $user_id);
        $stmt->execute();
        
        // Get the inserted site update ID
        $site_update_id = $conn->insert_id;
        
        // 2. Process vendors and laborers
        if (isset($_POST['vendors']) && is_array($_POST['vendors'])) {
            foreach ($_POST['vendors'] as $vendor_data) {
                // Insert vendor record
                $vendor_type = sanitize_input($vendor_data['type'] ?? '');
                $vendor_name = sanitize_input($vendor_data['name'] ?? '');
                $vendor_contact = sanitize_input($vendor_data['contact'] ?? '');
                
                if (!empty($vendor_name)) {
                    $stmt = $conn->prepare("INSERT INTO vendors (site_update_id, vendor_type, name, contact) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isss", $site_update_id, $vendor_type, $vendor_name, $vendor_contact);
                    $stmt->execute();
                    
                    $vendor_id = $conn->insert_id;
                    
                    // Process laborers for this vendor
            if (isset($vendor_data['laborers']) && is_array($vendor_data['laborers'])) {
                        foreach ($vendor_data['laborers'] as $laborer_data) {
                            $laborer_name = sanitize_input($laborer_data['name'] ?? '');
                            $laborer_contact = sanitize_input($laborer_data['contact'] ?? '');
                            $morning = sanitize_input($laborer_data['morning'] ?? 'P');
                            $evening = sanitize_input($laborer_data['evening'] ?? 'P');
                            $wages = floatval($laborer_data['wages'] ?? 0);
                            $ot_hours = floatval($laborer_data['ot_hours'] ?? 0);
                            $ot_minutes = intval($laborer_data['ot_minutes'] ?? 0);
                            $ot_rate = floatval($laborer_data['ot_rate'] ?? 0);
                            
                            if (!empty($laborer_name)) {
                                $stmt = $conn->prepare("INSERT INTO laborers (vendor_id, name, contact, morning_attendance, evening_attendance, wages_per_day, ot_hours, ot_minutes, ot_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt->bind_param("issssddid", $vendor_id, $laborer_name, $laborer_contact, $morning, $evening, $wages, $ot_hours, $ot_minutes, $ot_rate);
                                $stmt->execute();
                            }
                        }
                    }
                }
            }
        }
        
        // 3. Process company labours
        if (isset($_POST['company_labours']) && is_array($_POST['company_labours'])) {
            foreach ($_POST['company_labours'] as $labour_data) {
                $labour_name = sanitize_input($labour_data['name'] ?? '');
                $labour_contact = sanitize_input($labour_data['contact'] ?? '');
                $morning = sanitize_input($labour_data['morning'] ?? 'P');
                $evening = sanitize_input($labour_data['evening'] ?? 'P');
                $wages = floatval($labour_data['wages'] ?? 0);
                $ot_hours = floatval($labour_data['ot_hours'] ?? 0);
                $ot_minutes = intval($labour_data['ot_minutes'] ?? 0);
                $ot_rate = floatval($labour_data['ot_rate'] ?? 0);
                
                if (!empty($labour_name)) {
                    $stmt = $conn->prepare("INSERT INTO company_labours (site_update_id, name, contact, morning_attendance, evening_attendance, wages_per_day, ot_hours, ot_minutes, ot_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issssddid", $site_update_id, $labour_name, $labour_contact, $morning, $evening, $wages, $ot_hours, $ot_minutes, $ot_rate);
                    $stmt->execute();
                }
            }
        }
        
        // 4. Process travel expenses
        if (isset($_POST['travel_expenses']) && is_array($_POST['travel_expenses'])) {
            foreach ($_POST['travel_expenses'] as $travel_data) {
                $travel_from = sanitize_input($travel_data['from'] ?? '');
                $travel_to = sanitize_input($travel_data['to'] ?? '');
                $mode = sanitize_input($travel_data['mode'] ?? '');
                $km = floatval($travel_data['km'] ?? 0);
                $amount = floatval($travel_data['amount'] ?? 0);
                
                if (!empty($travel_from) && !empty($travel_to)) {
                    $stmt = $conn->prepare("INSERT INTO travel_expenses (site_update_id, travel_from, travel_to, transport_mode, km_travelled, amount) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssdd", $site_update_id, $travel_from, $travel_to, $mode, $km, $amount);
                    $stmt->execute();
                }
            }
        }
        
        // 5. Process beverages
        if (isset($_POST['beverages']) && is_array($_POST['beverages'])) {
            foreach ($_POST['beverages'] as $beverage_data) {
                $beverage_type = sanitize_input($beverage_data['type'] ?? '');
                $beverage_name = sanitize_input($beverage_data['name'] ?? '');
                $amount = floatval($beverage_data['amount'] ?? 0);
                
                if (!empty($beverage_name)) {
                    $stmt = $conn->prepare("INSERT INTO beverages (site_update_id, beverage_type, name, amount) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("issd", $site_update_id, $beverage_type, $beverage_name, $amount);
                    $stmt->execute();
                }
            }
        }
        
        // 6. Process work progress
        if (isset($_POST['work_progress']) && is_array($_POST['work_progress'])) {
            error_log("Processing work progress data");
            foreach ($_POST['work_progress'] as $progress_id => $progress_data) {
                $work_category = sanitize_input($progress_data['category'] ?? '');
                $work_type = sanitize_input($progress_data['type'] ?? '');
                $work_done = sanitize_input($progress_data['done'] ?? 'No');
                $remarks = sanitize_input($progress_data['remarks'] ?? '');
                
                if (!empty($work_category) && !empty($work_type)) {
                    $stmt = $conn->prepare("INSERT INTO work_progress (site_update_id, work_category, work_type, work_done, remarks) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("issss", $site_update_id, $work_category, $work_type, $work_done, $remarks);
                    $stmt->execute();
                    
                    $work_progress_id = $conn->insert_id;
                    error_log("Inserted work progress with ID: " . $work_progress_id);
                    
                    // Process media files for this work progress
                    $media_field_name = "work_progress_media_" . $progress_id;
                    error_log("Checking for media field: " . $media_field_name);
                    error_log("Media field exists: " . (isset($_FILES[$media_field_name]) ? 'Yes' : 'No'));
                    if (isset($_FILES[$media_field_name])) {
                        error_log("Media field has name: " . (isset($_FILES[$media_field_name]['name']) ? 'Yes' : 'No'));
                        error_log("Media field name is not empty: " . (!empty($_FILES[$media_field_name]['name'][0]) ? 'Yes' : 'No'));
                    }
                    
                    if (isset($_FILES[$media_field_name]) && !empty($_FILES[$media_field_name]['name'][0])) {
                        $upload_dir = "../../uploads/work_progress/" . $site_update_id . "/" . $work_progress_id;
                        error_log("Work progress media found. Upload directory: " . $upload_dir);
                        
                        // Count of files
                        $file_count = count($_FILES[$media_field_name]['name']);
                        error_log("Number of files to upload: " . $file_count);
                        
                        for ($i = 0; $i < $file_count; $i++) {
                            if (!empty($_FILES[$media_field_name]['name'][$i])) {
                                error_log("Processing file " . ($i+1) . ": " . $_FILES[$media_field_name]['name'][$i]);
                                
                                $file = [
                                    "name" => $_FILES[$media_field_name]['name'][$i],
                                    "type" => $_FILES[$media_field_name]['type'][$i],
                                    "tmp_name" => $_FILES[$media_field_name]['tmp_name'][$i],
                                    "error" => $_FILES[$media_field_name]['error'][$i],
                                    "size" => $_FILES[$media_field_name]['size'][$i]
                                ];
                                
                                $upload_result = handle_file_upload($file, $upload_dir);
                                
                                if ($upload_result !== false) {
                                    error_log("File uploaded successfully, inserting into DB");
                                    $stmt = $conn->prepare("INSERT INTO work_progress_media (work_progress_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?)");
                                    $stmt->bind_param("isss", $work_progress_id, $upload_result['file_name'], $upload_result['file_path'], $upload_result['file_type']);
                                    $stmt->execute();
                                    error_log("Media record inserted with ID: " . $conn->insert_id);
                                } else {
                                    error_log("Failed to upload work progress media file");
                                }
                            }
                        }
                    } else {
                        error_log("No media files found for work progress ID: " . $progress_id);
                    }
                }
            }
        }
        
        // 7. Process inventory
        if (isset($_POST['inventory']) && is_array($_POST['inventory'])) {
            error_log("Processing inventory data");
            foreach ($_POST['inventory'] as $inventory_id => $inventory_data) {
                $inventory_type = sanitize_input($inventory_data['type'] ?? '');
                $material = sanitize_input($inventory_data['material'] ?? '');
                $quantity = floatval($inventory_data['quantity'] ?? 0);
                $unit = sanitize_input($inventory_data['unit'] ?? '');
                $notes = sanitize_input($inventory_data['notes'] ?? '');
                
                if (!empty($inventory_type) && !empty($material)) {
                    // Handle bill picture first
                    $bill_picture_path = '';
                    $bill_field_name = "inventory_bill_" . $inventory_id;
                    error_log("Checking for bill picture field: " . $bill_field_name);
                    error_log("Bill field exists: " . (isset($_FILES[$bill_field_name]) ? 'Yes' : 'No'));
                    if (isset($_FILES[$bill_field_name])) {
                        error_log("Bill field has name: " . (isset($_FILES[$bill_field_name]['name']) ? 'Yes' : 'No'));
                        error_log("Bill field name is not empty: " . (!empty($_FILES[$bill_field_name]['name']) ? 'Yes' : 'No'));
                    }
                    
                    if (isset($_FILES[$bill_field_name]) && !empty($_FILES[$bill_field_name]['name'])) {
                        $upload_dir = "../../uploads/inventory/bills/" . $site_update_id;
                        error_log("Inventory bill found. Upload directory: " . $upload_dir);
                        $upload_result = handle_file_upload($_FILES[$bill_field_name], $upload_dir);
                        
                        if ($upload_result !== false) {
                            error_log("Bill uploaded successfully");
                            $bill_picture_path = $upload_result['file_path'];
                        } else {
                            error_log("Failed to upload inventory bill");
                        }
                    } else {
                        error_log("No bill picture found for inventory ID: " . $inventory_id);
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO inventory (site_update_id, inventory_type, material, quantity, unit, notes, bill_picture) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issdsss", $site_update_id, $inventory_type, $material, $quantity, $unit, $notes, $bill_picture_path);
                    $stmt->execute();
                    
                    $inventory_item_id = $conn->insert_id;
                    error_log("Inserted inventory item with ID: " . $inventory_item_id);
                    
                    // Process media files for this inventory item
                    $media_field_name = "inventory_media_" . $inventory_id;
                    error_log("Checking for inventory media field: " . $media_field_name);
                    error_log("Media field exists: " . (isset($_FILES[$media_field_name]) ? 'Yes' : 'No'));
                    if (isset($_FILES[$media_field_name])) {
                        error_log("Media field has name: " . (isset($_FILES[$media_field_name]['name']) ? 'Yes' : 'No'));
                        error_log("Media field name is not empty: " . (!empty($_FILES[$media_field_name]['name'][0]) ? 'Yes' : 'No'));
                    }
                    
                    if (isset($_FILES[$media_field_name]) && !empty($_FILES[$media_field_name]['name'][0])) {
                        $upload_dir = "../../uploads/inventory/" . $site_update_id . "/" . $inventory_item_id;
                        error_log("Inventory media found. Upload directory: " . $upload_dir);
                        
                        // Count of files
                        $file_count = count($_FILES[$media_field_name]['name']);
                        error_log("Number of inventory media files to upload: " . $file_count);
                        
                        for ($i = 0; $i < $file_count; $i++) {
                            if (!empty($_FILES[$media_field_name]['name'][$i])) {
                                error_log("Processing inventory media file " . ($i+1) . ": " . $_FILES[$media_field_name]['name'][$i]);
                                
                                $file = [
                                    "name" => $_FILES[$media_field_name]['name'][$i],
                                    "type" => $_FILES[$media_field_name]['type'][$i],
                                    "tmp_name" => $_FILES[$media_field_name]['tmp_name'][$i],
                                    "error" => $_FILES[$media_field_name]['error'][$i],
                                    "size" => $_FILES[$media_field_name]['size'][$i]
                                ];
                                
                                $upload_result = handle_file_upload($file, $upload_dir);
                                
                                if ($upload_result !== false) {
                                    error_log("Inventory media file uploaded successfully, inserting into DB");
                                    $stmt = $conn->prepare("INSERT INTO inventory_media (inventory_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?)");
                                    $stmt->bind_param("isss", $inventory_item_id, $upload_result['file_name'], $upload_result['file_path'], $upload_result['file_type']);
                                    $stmt->execute();
                                    error_log("Inventory media record inserted with ID: " . $conn->insert_id);
                                } else {
                                    error_log("Failed to upload inventory media file");
                                }
                            }
                        }
                    } else {
                        error_log("No media files found for inventory ID: " . $inventory_id);
                    }
                }
            }
        }
        
        // Commit the transaction
        $conn->commit();
        error_log("Transaction committed successfully");
        
        // Set success response
        $response['status'] = 'success';
        $response['message'] = 'Site update saved successfully!';
        $response['update_id'] = $site_update_id;
        
        // Set session success message
        $_SESSION['update_success'] = 'Site update saved successfully!';
        
        // Redirect back or to a success page
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            // If AJAX request, return JSON response
            echo json_encode($response);
        } else {
            // If regular form submission, redirect
            header('Location: ../../site_updates.php?success=update_added');
        }
            
        } catch (Exception $e) {
        // Rollback the transaction in case of error
            $conn->rollback();
        
        // Set error response
        $response['status'] = 'error';
        $response['message'] = 'An error occurred: ' . $e->getMessage();
        
        // Log the error
        error_log('Site update form error: ' . $e->getMessage());
        
        // Return error response
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            // If AJAX request, return JSON response
            echo json_encode($response);
        } else {
            // If regular form submission, redirect with error
            $_SESSION['update_errors'] = [$response['message']];
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        }
    }
    
    exit;
}

// If accessed directly without form submission
$_SESSION['update_errors'] = ['Invalid form submission'];
    header('Location: ../../site_updates.php');
exit;
?>