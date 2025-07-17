<?php
// update_calendar_event.php - Backend handler for updating calendar events

session_start();
require_once('../includes/db_connect.php');
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit;
}

// Check for POST request and action
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'update_event') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Get event ID
if (!isset($_POST['event_id']) || empty($_POST['event_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Event ID is required']);
    exit;
}

$event_id = intval($_POST['event_id']);
$title = $_POST['title'] ?? '';
$event_date = $_POST['event_date'] ?? '';
$user_id = $_SESSION['user_id'];

// Start transaction
$conn->begin_transaction();

try {
    // Update main event
    $update_event_sql = "UPDATE sv_calendar_events SET 
                         title = ?, 
                         event_date = ?, 
                         updated_at = CURRENT_TIMESTAMP 
                         WHERE event_id = ?";
    
    $stmt = $conn->prepare($update_event_sql);
    $stmt->bind_param("ssi", $title, $event_date, $event_id);
    $stmt->execute();
    
    // Process vendors and their related data
    if (isset($_POST['vendors']) && is_array($_POST['vendors'])) {
        foreach ($_POST['vendors'] as $vendor) {
            // Handle vendor details
            if (!empty($vendor['vendor_id'])) {
                // Update existing vendor
                $update_vendor_sql = "UPDATE sv_event_vendors SET 
                                     vendor_name = ?, 
                                     vendor_type = ?, 
                                     contact_number = ? 
                                     WHERE vendor_id = ? AND event_id = ?";
                
                $stmt = $conn->prepare($update_vendor_sql);
                $stmt->bind_param("sssii", $vendor['vendor_name'], $vendor['vendor_type'], 
                                $vendor['contact_number'], $vendor['vendor_id'], $event_id);
                $stmt->execute();
                
                $vendor_id = $vendor['vendor_id'];
            } else {
                // Insert new vendor
                $insert_vendor_sql = "INSERT INTO sv_event_vendors 
                                    (event_id, vendor_type, vendor_name, contact_number, sequence_number) 
                                    VALUES (?, ?, ?, ?, 
                                    (SELECT COALESCE(MAX(sequence_number), 0) + 1 FROM sv_event_vendors WHERE event_id = ?))";
                
                $stmt = $conn->prepare($insert_vendor_sql);
                $stmt->bind_param("isssi", $event_id, $vendor['vendor_type'], 
                                $vendor['vendor_name'], $vendor['contact_number'], $event_id);
                $stmt->execute();
                
                $vendor_id = $conn->insert_id;
            }
            
            // Handle material details for this vendor
            if ($vendor_id) {
                // Check if material already exists for this vendor
                $check_material_sql = "SELECT material_id FROM sv_vendor_materials WHERE vendor_id = ?";
                $stmt = $conn->prepare($check_material_sql);
                $stmt->bind_param("i", $vendor_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Update existing material
                    $material_row = $result->fetch_assoc();
                    $material_id = $material_row['material_id'];
                    
                    $update_material_sql = "UPDATE sv_vendor_materials SET 
                                          remarks = ?, 
                                          amount = ? 
                                          WHERE material_id = ?";
                    
                    $stmt = $conn->prepare($update_material_sql);
                    $stmt->bind_param("sdi", $vendor['material_remarks'], $vendor['material_amount'], $material_id);
                    $stmt->execute();
                } else {
                    // Insert new material
                    $insert_material_sql = "INSERT INTO sv_vendor_materials 
                                         (vendor_id, remarks, amount) 
                                         VALUES (?, ?, ?)";
                    
                    $stmt = $conn->prepare($insert_material_sql);
                    $stmt->bind_param("isd", $vendor_id, $vendor['material_remarks'], $vendor['material_amount']);
                    $stmt->execute();
                    
                    $material_id = $conn->insert_id;
                }
                
                // Process material images if uploaded
                if ($material_id && isset($_FILES['vendors'])) {
                    processMediaFiles($conn, $_FILES['vendors'], 'material_images', $vendor_id, $material_id, 'sv_material_images');
                }
                
                // Process bill images if uploaded
                if ($material_id && isset($_FILES['vendors'])) {
                    processMediaFiles($conn, $_FILES['vendors'], 'bill_images', $vendor_id, $material_id, 'sv_bill_images');
                }
            }
            
            // Handle labourers for this vendor
            if (isset($vendor['labourers']) && is_array($vendor['labourers'])) {
                foreach ($vendor['labourers'] as $labourer) {
                    if (!empty($labourer['labour_id'])) {
                        // Update existing labourer
                        $update_labour_sql = "UPDATE sv_vendor_labours SET 
                                           labour_name = ?, 
                                           contact_number = ?, 
                                           morning_attendance = ?, 
                                           evening_attendance = ? 
                                           WHERE labour_id = ? AND vendor_id = ?";
                        
                        $stmt = $conn->prepare($update_labour_sql);
                        $stmt->bind_param("ssssii", $labourer['labour_name'], $labourer['contact_number'], 
                                        $labourer['morning_attendance'], $labourer['evening_attendance'], 
                                        $labourer['labour_id'], $vendor_id);
                        $stmt->execute();
                        
                        // Update labour wages
                        $update_wages_sql = "UPDATE sv_labour_wages SET 
                                          daily_wage = ?, 
                                          total_day_wage = ?, 
                                          ot_hours = ?, 
                                          ot_minutes = ?, 
                                          ot_rate = ?, 
                                          total_ot_amount = ?, 
                                          transport_mode = ?, 
                                          travel_amount = ?, 
                                          grand_total = ? 
                                          WHERE labour_id = ?";
                        
                        $stmt = $conn->prepare($update_wages_sql);
                        $stmt->bind_param("ddiiidsddi", $labourer['daily_wage'], $labourer['total_day_wage'], 
                                      $labourer['ot_hours'], $labourer['ot_minutes'], $labourer['ot_rate'], 
                                      $labourer['ot_amount'], $labourer['travel_mode'], $labourer['travel_amount'], 
                                      $labourer['grand_total'], $labourer['labour_id']);
                        $stmt->execute();
                    } else {
                        // Insert new labourer
                        $insert_labour_sql = "INSERT INTO sv_vendor_labours 
                                           (vendor_id, labour_name, contact_number, morning_attendance, evening_attendance, sequence_number) 
                                           VALUES (?, ?, ?, ?, ?, 
                                           (SELECT COALESCE(MAX(sequence_number), 0) + 1 FROM sv_vendor_labours WHERE vendor_id = ?))";
                        
                        $stmt = $conn->prepare($insert_labour_sql);
                        $stmt->bind_param("issssi", $vendor_id, $labourer['labour_name'], $labourer['contact_number'], 
                                        $labourer['morning_attendance'], $labourer['evening_attendance'], $vendor_id);
                        $stmt->execute();
                        
                        $labour_id = $conn->insert_id;
                        
                        // Insert labour wages
                        $insert_wages_sql = "INSERT INTO sv_labour_wages 
                                          (labour_id, daily_wage, total_day_wage, ot_hours, ot_minutes, ot_rate, 
                                          total_ot_amount, transport_mode, travel_amount, grand_total) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $stmt = $conn->prepare($insert_wages_sql);
                        $stmt->bind_param("iddiiidsdd", $labour_id, $labourer['daily_wage'], $labourer['total_day_wage'], 
                                       $labourer['ot_hours'], $labourer['ot_minutes'], $labourer['ot_rate'], 
                                       $labourer['ot_amount'], $labourer['travel_mode'], $labourer['travel_amount'], 
                                       $labourer['grand_total']);
                        $stmt->execute();
                    }
                }
            }
        }
    }
    
    // Process company labourers
    if (isset($_POST['company_labours']) && is_array($_POST['company_labours'])) {
        foreach ($_POST['company_labours'] as $labour) {
            if (!empty($labour['company_labour_id'])) {
                // Update existing company labour
                $update_labour_sql = "UPDATE sv_company_labours SET 
                                    labour_name = ?, 
                                    contact_number = ?, 
                                    morning_attendance = ?, 
                                    evening_attendance = ?,
                                    daily_wage = ?,
                                    updated_by = ?,
                                    updated_at = CURRENT_TIMESTAMP 
                                    WHERE company_labour_id = ? AND event_id = ?";
                
                $stmt = $conn->prepare($update_labour_sql);
                $stmt->bind_param("ssssdiid", $labour['labour_name'], $labour['contact_number'], 
                                $labour['morning_attendance'], $labour['evening_attendance'], 
                                $labour['daily_wage'], $user_id, $labour['company_labour_id'], $event_id);
                $stmt->execute();
            } else {
                // Insert new company labour
                $insert_labour_sql = "INSERT INTO sv_company_labours 
                                    (event_id, labour_name, contact_number, morning_attendance, evening_attendance, 
                                    sequence_number, daily_wage, created_by, attendance_date) 
                                    VALUES (?, ?, ?, ?, ?, 
                                    (SELECT COALESCE(MAX(sequence_number), 0) + 1 FROM sv_company_labours WHERE event_id = ?), 
                                    ?, ?, ?)";
                
                $stmt = $conn->prepare($insert_labour_sql);
                $stmt->bind_param("issssidis", $event_id, $labour['labour_name'], $labour['contact_number'], 
                                $labour['morning_attendance'], $labour['evening_attendance'], $event_id, 
                                $labour['daily_wage'], $user_id, $event_date);
                $stmt->execute();
            }
        }
    }
    
    // Process beverages
    if (isset($_POST['beverages']) && is_array($_POST['beverages'])) {
        foreach ($_POST['beverages'] as $beverage) {
            if (!empty($beverage['beverage_id'])) {
                // Update existing beverage
                $update_beverage_sql = "UPDATE sv_event_beverages SET 
                                      beverage_type = ?, 
                                      beverage_name = ?, 
                                      amount = ?,
                                      updated_by = ?,
                                      updated_at = CURRENT_TIMESTAMP 
                                      WHERE beverage_id = ? AND event_id = ?";
                
                $stmt = $conn->prepare($update_beverage_sql);
                $stmt->bind_param("sssdii", $beverage['beverage_type'], $beverage['beverage_name'], 
                                $beverage['amount'], $user_id, $beverage['beverage_id'], $event_id);
                $stmt->execute();
            } else {
                // Insert new beverage
                $insert_beverage_sql = "INSERT INTO sv_event_beverages 
                                     (event_id, beverage_type, beverage_name, amount, sequence_number, created_by) 
                                     VALUES (?, ?, ?, ?, 
                                     (SELECT COALESCE(MAX(sequence_number), 0) + 1 FROM sv_event_beverages WHERE event_id = ?), 
                                     ?)";
                
                $stmt = $conn->prepare($insert_beverage_sql);
                $stmt->bind_param("issdii", $event_id, $beverage['beverage_type'], 
                               $beverage['beverage_name'], $beverage['amount'], $event_id, $user_id);
                $stmt->execute();
            }
        }
    }
    
    // Process work progress items
    if (isset($_POST['work_progress']) && is_array($_POST['work_progress'])) {
        foreach ($_POST['work_progress'] as $work) {
            if (!empty($work['work_id'])) {
                // Update existing work progress
                $update_work_sql = "UPDATE sv_work_progress SET 
                                  work_category = ?, 
                                  work_type = ?, 
                                  work_done = ?, 
                                  remarks = ?,
                                  updated_by = ?,
                                  updated_at = CURRENT_TIMESTAMP 
                                  WHERE work_id = ? AND event_id = ?";
                
                $stmt = $conn->prepare($update_work_sql);
                $stmt->bind_param("ssssiis", $work['work_category'], $work['work_type'], 
                               $work['work_done'], $work['remarks'], $user_id, $work['work_id'], $event_id);
                $stmt->execute();
                
                $work_id = $work['work_id'];
            } else {
                // Insert new work progress
                $insert_work_sql = "INSERT INTO sv_work_progress 
                                 (event_id, work_category, work_type, work_done, remarks, sequence_number, created_by) 
                                 VALUES (?, ?, ?, ?, ?, 
                                 (SELECT COALESCE(MAX(sequence_number), 0) + 1 FROM sv_work_progress WHERE event_id = ?), 
                                 ?)";
                
                $stmt = $conn->prepare($insert_work_sql);
                $stmt->bind_param("issssii", $event_id, $work['work_category'], $work['work_type'], 
                               $work['work_done'], $work['remarks'], $event_id, $user_id);
                $stmt->execute();
                
                $work_id = $conn->insert_id;
            }
            
            // Process work progress media if uploaded
            if ($work_id && isset($_FILES['work_progress'])) {
                processWorkMedia($conn, $_FILES['work_progress'], $work_id);
            }
        }
    }
    
    // Process inventory items
    if (isset($_POST['inventory']) && is_array($_POST['inventory'])) {
        foreach ($_POST['inventory'] as $inventory) {
            if (!empty($inventory['inventory_id'])) {
                // Update existing inventory
                $update_inventory_sql = "UPDATE sv_inventory_items SET 
                                       inventory_type = ?, 
                                       material_type = ?, 
                                       quantity = ?, 
                                       unit = ?, 
                                       remarks = ?,
                                       updated_by = ?,
                                       updated_at = CURRENT_TIMESTAMP 
                                       WHERE inventory_id = ? AND event_id = ?";
                
                $stmt = $conn->prepare($update_inventory_sql);
                $stmt->bind_param("ssdssiis", $inventory['inventory_type'], $inventory['material_type'], 
                                $inventory['quantity'], $inventory['unit'], $inventory['remarks'], 
                                $user_id, $inventory['inventory_id'], $event_id);
                $stmt->execute();
                
                $inventory_id = $inventory['inventory_id'];
            } else {
                // Insert new inventory
                $insert_inventory_sql = "INSERT INTO sv_inventory_items 
                                      (event_id, inventory_type, material_type, quantity, unit, remarks, 
                                      sequence_number, created_by) 
                                      VALUES (?, ?, ?, ?, ?, ?, 
                                      (SELECT COALESCE(MAX(sequence_number), 0) + 1 FROM sv_inventory_items WHERE event_id = ?), 
                                      ?)";
                
                $stmt = $conn->prepare($insert_inventory_sql);
                $stmt->bind_param("issdssiis", $event_id, $inventory['inventory_type'], $inventory['material_type'], 
                                $inventory['quantity'], $inventory['unit'], $inventory['remarks'], 
                                $event_id, $user_id);
                $stmt->execute();
                
                $inventory_id = $conn->insert_id;
            }
            
            // Process inventory media if uploaded
            if ($inventory_id && isset($_FILES['inventory'])) {
                processInventoryMedia($conn, $_FILES['inventory'], $inventory_id);
            }
        }
    }
    
    // Log the event update in event logs
    $details = json_encode([
        'event_id' => $event_id,
        'title' => $title,
        'event_date' => $event_date
    ]);
    
    $log_sql = "INSERT INTO sv_event_logs 
              (event_id, action_type, performed_by, details) 
              VALUES (?, 'update', ?, ?)";
    
    $stmt = $conn->prepare($log_sql);
    $stmt->bind_param("iis", $event_id, $user_id, $details);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode(['status' => 'success', 'message' => 'Event updated successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log error
    error_log('Error updating event: ' . $e->getMessage());
    
    // Return error response
    echo json_encode(['status' => 'error', 'message' => 'Failed to update event: ' . $e->getMessage()]);
}

// Helper function to process media files
function processMediaFiles($conn, $files, $fieldName, $vendorId, $materialId, $tableType) {
    if (isset($files['name'][$vendorId][$fieldName])) {
        // Create upload directory if it doesn't exist
        $uploadDir = '../uploads/' . ($fieldName === 'material_images' ? 'material_images' : 'bill_images') . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Get the files
        $fileCount = count($files['name'][$vendorId][$fieldName]);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$vendorId][$fieldName][$i] === 0) {
                // Generate unique filename
                $extension = pathinfo($files['name'][$vendorId][$fieldName][$i], PATHINFO_EXTENSION);
                $filename = uniqid('', true) . '_' . time() . '.' . $extension;
                $filepath = $uploadDir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($files['tmp_name'][$vendorId][$fieldName][$i], $filepath)) {
                    // Insert into database
                    if ($tableType === 'sv_material_images') {
                        $sql = "INSERT INTO sv_material_images (material_id, image_path, upload_date) VALUES (?, ?, NOW())";
                    } else {
                        $sql = "INSERT INTO sv_bill_images (material_id, image_path, upload_date) VALUES (?, ?, NOW())";
                    }
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("is", $materialId, $filename);
                    $stmt->execute();
                }
            }
        }
    }
}

// Helper function to process work media files
function processWorkMedia($conn, $files, $workId) {
    if (isset($files['name'][$workId]['media'])) {
        // Create upload directory if it doesn't exist
        $uploadDir = '../uploads/work_progress/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Get the files
        $fileCount = count($files['name'][$workId]['media']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$workId]['media'][$i] === 0) {
                // Generate unique filename
                $extension = pathinfo($files['name'][$workId]['media'][$i], PATHINFO_EXTENSION);
                $filename = uniqid('', true) . '_' . time() . '.' . $extension;
                $filepath = $uploadDir . $filename;
                
                // Determine media type
                $mediaType = 'image';
                if (in_array(strtolower($extension), ['mp4', 'mov', 'avi', 'mkv'])) {
                    $mediaType = 'video';
                }
                
                // Move uploaded file
                if (move_uploaded_file($files['tmp_name'][$workId]['media'][$i], $filepath)) {
                    // Insert into database
                    $sql = "INSERT INTO sv_work_progress_media 
                          (work_id, file_name, file_path, media_type, file_size, sequence_number) 
                          VALUES (?, ?, ?, ?, ?, 
                          (SELECT COALESCE(MAX(sequence_number), 0) + 1 FROM sv_work_progress_media WHERE work_id = ?))";
                    
                    $stmt = $conn->prepare($sql);
                    $fileSize = $files['size'][$workId]['media'][$i];
                    $stmt->bind_param("isssii", $workId, $filename, $filepath, $mediaType, $fileSize, $workId);
                    $stmt->execute();
                }
            }
        }
    }
}

// Helper function to process inventory media files
function processInventoryMedia($conn, $files, $inventoryId) {
    if (isset($files['name'][$inventoryId]['media'])) {
        // Create upload directories if they don't exist
        $uploadDirPhoto = '../uploads/inventory_images/';
        $uploadDirBill = '../uploads/inventory_bills/';
        $uploadDirVideo = '../uploads/inventory_videos/';
        
        if (!file_exists($uploadDirPhoto)) mkdir($uploadDirPhoto, 0755, true);
        if (!file_exists($uploadDirBill)) mkdir($uploadDirBill, 0755, true);
        if (!file_exists($uploadDirVideo)) mkdir($uploadDirVideo, 0755, true);
        
        // Get the files
        $fileCount = count($files['name'][$inventoryId]['media']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$inventoryId]['media'][$i] === 0) {
                // Generate unique filename
                $extension = pathinfo($files['name'][$inventoryId]['media'][$i], PATHINFO_EXTENSION);
                $filename = uniqid('', true) . '_' . time() . '.' . $extension;
                
                // Determine media type and upload directory
                $mediaType = 'photo';
                $uploadDir = $uploadDirPhoto;
                
                if (strtolower($extension) === 'pdf') {
                    $mediaType = 'bill';
                    $uploadDir = $uploadDirBill;
                } elseif (in_array(strtolower($extension), ['mp4', 'mov', 'avi', 'mkv'])) {
                    $mediaType = 'video';
                    $uploadDir = $uploadDirVideo;
                }
                
                $filepath = $uploadDir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($files['tmp_name'][$inventoryId]['media'][$i], $filepath)) {
                    // Insert into database
                    $sql = "INSERT INTO sv_inventory_media 
                          (inventory_id, file_name, file_path, media_type, file_size, sequence_number) 
                          VALUES (?, ?, ?, ?, ?, 
                          (SELECT COALESCE(MAX(sequence_number), 0) + 1 FROM sv_inventory_media WHERE inventory_id = ?))";
                    
                    $stmt = $conn->prepare($sql);
                    $fileSize = $files['size'][$inventoryId]['media'][$i];
                    $stmt->bind_param("isssii", $inventoryId, $filename, $filepath, $mediaType, $fileSize, $inventoryId);
                    $stmt->execute();
                }
            }
        }
    }
}
?>
