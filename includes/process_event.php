<?php
/**
 * Site Event Form Processing
 * 
 * Handles the processing of the site event form submissions
 */

// Include necessary files
require_once 'activity_logger.php';
require_once 'file_upload.php';
require_once 'media_upload_handler.php';

/**
 * Process the site event form submission
 * 
 * @param array $postData The $_POST data
 * @param array $fileData The $_FILES data
 * @param int $userId The current user ID
 * @return array Response with status and message
 */
function processSiteEventForm($postData, $fileData, $userId) {
    global $pdo;
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // 1. Insert the main site event
        $eventId = insertSiteEvent($postData, $userId);
        
        // 2. Process vendors if any
        if (isset($postData['vendor_name']) && is_array($postData['vendor_name'])) {
            processVendors($eventId, $postData, $fileData, $userId);
        }
        
        // 3. Process company labours if any
        if (isset($postData['company_labour_name']) && is_array($postData['company_labour_name'])) {
            processCompanyLabours($eventId, $postData, $userId);
        }
        
        // 4. Process travel expenses if any
        if (isset($postData['travel_from']) && is_array($postData['travel_from'])) {
            processTravelExpenses($eventId, $postData, $fileData, $userId);
        }
        
        // 5. Process beverages if any
        if (isset($postData['beverage_type']) && is_array($postData['beverage_type'])) {
            processBeverages($eventId, $postData, $fileData, $userId);
        }
        
        // 6. Process work progress if any
        if (isset($postData['work_category']) && is_array($postData['work_category'])) {
            processWorkProgress($eventId, $postData, $fileData, $userId);
        }
        
        // 7. Process inventory items if any
        if (isset($postData['inventory_type']) && is_array($postData['inventory_type'])) {
            processInventoryItems($eventId, $postData, $fileData, $userId);
        }
        
        // Commit transaction
        $pdo->commit();
        
        return [
            'status' => 'success',
            'message' => 'Site event data has been saved successfully!',
            'event_id' => $eventId
        ];
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        error_log("Site Event Processing Error: " . $e->getMessage());
        
        return [
            'status' => 'error',
            'message' => 'An error occurred while saving the site event data: ' . $e->getMessage()
        ];
    }
}

/**
 * Insert the main site event record
 * 
 * @param array $postData Form data
 * @param int $userId Current user ID
 * @return int The newly created event ID
 */
function insertSiteEvent($postData, $userId) {
    global $pdo;
    
    $sql = "INSERT INTO site_events (site_name, event_date, created_by) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $postData['site_name'],
        $postData['event_date'],
        $userId
    ]);
    
    $eventId = $pdo->lastInsertId();
    
    // Log the event creation
    logSiteEventCreation($userId, $eventId, $postData['site_name'], $postData['event_date']);
    
    return $eventId;
}

/**
 * Process vendors and their laborers
 * 
 * @param int $eventId Site event ID
 * @param array $postData Form data
 * @param array $fileData Files data
 * @param int $userId Current user ID
 */
function processVendors($eventId, $postData, $fileData, $userId) {
    global $pdo;
    
    $vendorCount = count($postData['vendor_name']);
    
    for ($i = 0; $i < $vendorCount; $i++) {
        // Skip empty vendor entries
        if (empty($postData['vendor_name'][$i])) {
            continue;
        }
        
        // Handle vendor type (custom or selected)
        $vendorType = $postData['vendor_type'][$i];
        if ($vendorType === 'custom' && !empty($postData['custom_vendor_type'][$i])) {
            $vendorType = $postData['custom_vendor_type'][$i];
        }
        
        // Process material pictures if any
        $materialPicture = null;
        if (isset($fileData['vendor_material_picture']['name'][$i]) && !empty($fileData['vendor_material_picture']['name'][$i])) {
            $file = [
                'name' => $fileData['vendor_material_picture']['name'][$i],
                'type' => $fileData['vendor_material_picture']['type'][$i],
                'tmp_name' => $fileData['vendor_material_picture']['tmp_name'][$i],
                'error' => $fileData['vendor_material_picture']['error'][$i],
                'size' => $fileData['vendor_material_picture']['size'][$i]
            ];
            
            $uploadResult = uploadImage($file, 'vendor_materials');
            if (!isset($uploadResult['error'])) {
                $materialPicture = $uploadResult['path'];
            }
        }
        
        // Process bill pictures if any
        $billPicture = null;
        if (isset($fileData['vendor_bill_picture']['name'][$i]) && !empty($fileData['vendor_bill_picture']['name'][$i])) {
            $file = [
                'name' => $fileData['vendor_bill_picture']['name'][$i],
                'type' => $fileData['vendor_bill_picture']['type'][$i],
                'tmp_name' => $fileData['vendor_bill_picture']['tmp_name'][$i],
                'error' => $fileData['vendor_bill_picture']['error'][$i],
                'size' => $fileData['vendor_bill_picture']['size'][$i]
            ];
            
            $uploadResult = uploadImage($file, 'vendor_bills');
            if (!isset($uploadResult['error'])) {
                $billPicture = $uploadResult['path'];
            }
        }
        
        // Insert vendor
        $sql = "INSERT INTO event_vendors (
                    event_id, vendor_type, vendor_name, vendor_contact, 
                    material_remark, material_amount, material_picture, bill_picture
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $eventId,
            $vendorType,
            $postData['vendor_name'][$i],
            $postData['vendor_contact'][$i],
            $postData['vendor_material_remark'][$i] ?? null,
            $postData['vendor_material_amount'][$i] ?? null,
            $materialPicture,
            $billPicture
        ]);
        
        $vendorId = $pdo->lastInsertId();
        
        // Log vendor addition
        logVendorAddition($userId, $vendorId, $eventId, $postData['vendor_name'][$i]);
        
        // Process vendor laborers if any
        if (isset($postData['labor_name']) && is_array($postData['labor_name'])) {
            processVendorLaborers($vendorId, $postData, $userId);
        }
    }
}

/**
 * Process vendor laborers
 * 
 * @param int $vendorId Vendor ID
 * @param array $postData Form data
 * @param int $userId Current user ID
 */
function processVendorLaborers($vendorId, $postData, $userId) {
    global $pdo;
    
    // We need to identify which laborers belong to this vendor
    // For this implementation, we'll assume that labor data is matched by index
    if (empty($postData['labor_name'])) {
        return;
    }
    
    $laborCount = count($postData['labor_name']);
    
    for ($i = 0; $i < $laborCount; $i++) {
        // Skip empty labor entries
        if (empty($postData['labor_name'][$i])) {
            continue;
        }
        
        // Calculate total day wages based on attendance
        $morningAttendance = $postData['morning_attendance'][$i] ?? 'A';
        $eveningAttendance = $postData['evening_attendance'][$i] ?? 'A';
        $wagesPerDay = floatval($postData['wages_per_day'][$i] ?? 0);
        
        $totalDayWages = 0;
        if ($morningAttendance === 'P' && $eveningAttendance === 'P') {
            $totalDayWages = $wagesPerDay;
        } elseif ($morningAttendance === 'P' || $eveningAttendance === 'P') {
            $totalDayWages = $wagesPerDay * 0.5;
        }
        
        // Calculate overtime amount
        $otHours = intval($postData['ot_hours'][$i] ?? 0);
        $otMinutes = intval($postData['ot_minutes'][$i] ?? 0);
        $otRate = floatval($postData['ot_rate'][$i] ?? 0);
        
        $totalOtAmount = ($otHours + ($otMinutes / 60)) * $otRate;
        
        // Calculate travel amount
        $travelAmount = floatval($postData['labor_travel_amount'][$i] ?? 0);
        
        // Calculate grand total
        $grandTotal = $totalDayWages + $totalOtAmount + $travelAmount;
        
        // Insert labor record
        $sql = "INSERT INTO vendor_laborers (
                    vendor_id, labor_name, labor_contact, 
                    morning_attendance, evening_attendance, 
                    wages_per_day, total_day_wages, 
                    ot_hours, ot_minutes, ot_rate, total_ot_amount,
                    transport_mode, travel_amount, 
                    grand_total
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $vendorId,
            $postData['labor_name'][$i],
            $postData['labor_contact'][$i],
            $morningAttendance,
            $eveningAttendance,
            $wagesPerDay,
            $totalDayWages,
            $otHours,
            $otMinutes,
            $otRate,
            $totalOtAmount,
            $postData['labor_transport_mode'][$i] ?? null,
            $travelAmount,
            $grandTotal
        ]);
        
        $laborId = $pdo->lastInsertId();
        
        // Log labor addition
        logVendorLabourAddition($userId, $laborId, $vendorId, $postData['labor_name'][$i]);
    }
}

/**
 * Process company labours
 * 
 * @param int $eventId Site event ID
 * @param array $postData Form data
 * @param int $userId Current user ID
 */
function processCompanyLabours($eventId, $postData, $userId) {
    global $pdo;
    
    $labourCount = count($postData['company_labour_name']);
    
    for ($i = 0; $i < $labourCount; $i++) {
        // Skip empty labour entries
        if (empty($postData['company_labour_name'][$i])) {
            continue;
        }
        
        // Calculate total day wages based on attendance
        $morningAttendance = $postData['company_labour_morning_attendance'][$i] ?? 'A';
        $eveningAttendance = $postData['company_labour_evening_attendance'][$i] ?? 'A';
        $wagesPerDay = floatval($postData['company_labour_wages_per_day'][$i] ?? 0);
        
        $totalDayWages = 0;
        if ($morningAttendance === 'P' && $eveningAttendance === 'P') {
            $totalDayWages = $wagesPerDay;
        } elseif ($morningAttendance === 'P' || $eveningAttendance === 'P') {
            $totalDayWages = $wagesPerDay * 0.5;
        }
        
        // Calculate overtime amount
        $otHours = intval($postData['company_labour_ot_hours'][$i] ?? 0);
        $otMinutes = intval($postData['company_labour_ot_minutes'][$i] ?? 0);
        $otRate = floatval($postData['company_labour_ot_rate'][$i] ?? 0);
        
        $totalOtAmount = ($otHours + ($otMinutes / 60)) * $otRate;
        
        // Calculate travel amount
        $travelAmount = floatval($postData['company_labour_travel_amount'][$i] ?? 0);
        
        // Calculate grand total
        $grandTotal = $totalDayWages + $totalOtAmount + $travelAmount;
        
        // Insert labour record
        $sql = "INSERT INTO event_company_labours (
                    event_id, labour_name, labour_contact, 
                    morning_attendance, evening_attendance, 
                    wages_per_day, total_day_wages, 
                    ot_hours, ot_minutes, ot_rate, total_ot_amount,
                    transport_mode, travel_amount, 
                    grand_total
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $eventId,
            $postData['company_labour_name'][$i],
            $postData['company_labour_contact'][$i],
            $morningAttendance,
            $eveningAttendance,
            $wagesPerDay,
            $totalDayWages,
            $otHours,
            $otMinutes,
            $otRate,
            $totalOtAmount,
            $postData['company_labour_transport_mode'][$i] ?? null,
            $travelAmount,
            $grandTotal
        ]);
        
        $labourId = $pdo->lastInsertId();
        
        // Log labour addition
        logCompanyLabourAddition($userId, $labourId, $eventId, $postData['company_labour_name'][$i]);
    }
}

/**
 * Process travel expenses
 * 
 * @param int $eventId Site event ID
 * @param array $postData Form data
 * @param array $fileData Files data
 * @param int $userId Current user ID
 */
function processTravelExpenses($eventId, $postData, $fileData, $userId) {
    global $pdo;
    
    $travelCount = count($postData['travel_from']);
    
    for ($i = 0; $i < $travelCount; $i++) {
        // Skip empty travel entries
        if (empty($postData['travel_from'][$i]) || empty($postData['travel_to'][$i])) {
            continue;
        }
        
        // Process travel picture if any
        $travelPicture = null;
        if (isset($fileData['travel_picture']['name'][$i]) && !empty($fileData['travel_picture']['name'][$i])) {
            $file = [
                'name' => $fileData['travel_picture']['name'][$i],
                'type' => $fileData['travel_picture']['type'][$i],
                'tmp_name' => $fileData['travel_picture']['tmp_name'][$i],
                'error' => $fileData['travel_picture']['error'][$i],
                'size' => $fileData['travel_picture']['size'][$i]
            ];
            
            $uploadResult = uploadImage($file, 'travel_expenses');
            if (!isset($uploadResult['error'])) {
                $travelPicture = $uploadResult['path'];
            }
        }
        
        // Insert travel expense
        $sql = "INSERT INTO event_travel_expenses (
                    event_id, from_location, to_location, transport_mode,
                    distance_km, amount, remarks, travel_picture
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $eventId,
            $postData['travel_from'][$i],
            $postData['travel_to'][$i],
            $postData['transport_mode'][$i],
            $postData['distance_km'][$i] ?? null,
            $postData['amount'][$i] ?? 0,
            $postData['travel_remarks'][$i] ?? null,
            $travelPicture
        ]);
        
        $expenseId = $pdo->lastInsertId();
        
        // Log travel expense addition
        logTravelExpenseAddition($userId, $expenseId, $eventId, $postData['travel_from'][$i], $postData['travel_to'][$i]);
    }
}

/**
 * Process beverages
 * 
 * @param int $eventId Site event ID
 * @param array $postData Form data
 * @param array $fileData Files data
 * @param int $userId Current user ID
 */
function processBeverages($eventId, $postData, $fileData, $userId) {
    global $pdo;
    
    $beverageCount = count($postData['beverage_type']);
    
    for ($i = 0; $i < $beverageCount; $i++) {
        // Skip empty beverage entries
        if (empty($postData['beverage_type'][$i])) {
            continue;
        }
        
        // Calculate total amount
        $quantity = intval($postData['beverage_quantity'][$i] ?? 0);
        $unitPrice = floatval($postData['beverage_unit_price'][$i] ?? 0);
        $totalAmount = $quantity * $unitPrice;
        
        // Process bill picture if any
        $billPicture = null;
        if (isset($fileData['beverage_bill_picture']['name'][$i]) && !empty($fileData['beverage_bill_picture']['name'][$i])) {
            $file = [
                'name' => $fileData['beverage_bill_picture']['name'][$i],
                'type' => $fileData['beverage_bill_picture']['type'][$i],
                'tmp_name' => $fileData['beverage_bill_picture']['tmp_name'][$i],
                'error' => $fileData['beverage_bill_picture']['error'][$i],
                'size' => $fileData['beverage_bill_picture']['size'][$i]
            ];
            
            $uploadResult = uploadImage($file, 'beverage_bills');
            if (!isset($uploadResult['error'])) {
                $billPicture = $uploadResult['path'];
            }
        }
        
        // Insert beverage
        $sql = "INSERT INTO event_beverages (
                    event_id, beverage_type, quantity, unit_price,
                    total_amount, remarks, bill_picture
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $eventId,
            $postData['beverage_type'][$i],
            $quantity,
            $unitPrice,
            $totalAmount,
            $postData['beverage_remarks'][$i] ?? null,
            $billPicture
        ]);
        
        $beverageId = $pdo->lastInsertId();
        
        // Log beverage addition
        logBeverageAddition($userId, $beverageId, $eventId, $postData['beverage_type'][$i], $quantity);
    }
}

/**
 * Process work progress
 * 
 * @param int $eventId Site event ID
 * @param array $postData Form data
 * @param array $fileData Files data
 * @param int $userId Current user ID
 */
function processWorkProgress($eventId, $postData, $fileData, $userId) {
    global $pdo;
    
    $workCount = count($postData['work_category']);
    
    for ($i = 0; $i < $workCount; $i++) {
        // Skip empty work entries
        if (empty($postData['work_category'][$i])) {
            continue;
        }
        
        // Insert work progress
        $sql = "INSERT INTO event_work_progress (
                    event_id, work_category, work_type, description, status,
                    completion_percentage, remarks
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $eventId,
            $postData['work_category'][$i],
            $postData['work_type'][$i] ?? null,
            $postData['work_description'][$i] ?? '',
            $postData['work_status'][$i] ?? 'In Progress',
            $postData['completion_percentage'][$i] ?? 0,
            $postData['work_remarks'][$i] ?? null
        ]);
        
        $workId = $pdo->lastInsertId();
        
        // Log work progress addition
        logWorkProgressAddition($userId, $workId, $eventId, $postData['work_category'][$i], $postData['completion_percentage'][$i] ?? 0);
        
        // Process work media if any
        if (isset($fileData['work_media_file']['name'][$i]) && is_array($fileData['work_media_file']['name'][$i])) {
            // Use the new media handler
            $mediaResults = handleWorkProgressMedia($workId, $fileData['work_media_file'], $i);
            
            // Log any errors
            if (!empty($mediaResults['errors'])) {
                foreach ($mediaResults['errors'] as $error) {
                    error_log("Work Progress Media Error: " . $error);
                }
            }
        }
    }
}

/**
 * Process inventory items
 * 
 * @param int $eventId Site event ID
 * @param array $postData Form data
 * @param array $fileData Files data
 * @param int $userId Current user ID
 */
function processInventoryItems($eventId, $postData, $fileData, $userId) {
    global $pdo;
    
    $inventoryCount = count($postData['inventory_type']);
    
    for ($i = 0; $i < $inventoryCount; $i++) {
        // Skip empty inventory entries
        if (empty($postData['inventory_type'][$i])) {
            continue;
        }
        
        // Get material field - might be 'material' or 'item_name' depending on form
        $material = $postData['material'][$i] ?? $postData['item_name'][$i] ?? '';
        
        if (empty($material)) {
            continue;
        }
        
        // Calculate total price
        $quantity = floatval($postData['quantity'][$i] ?? 0);
        $unitPrice = floatval($postData['unit_price'][$i] ?? 0);
        $totalPrice = $quantity * $unitPrice;
        
        // Process bill picture if any
        $billPicture = null;
        if (isset($fileData['bill_picture']['name'][$i]) && !empty($fileData['bill_picture']['name'][$i])) {
            $file = [
                'name' => $fileData['bill_picture']['name'][$i],
                'type' => $fileData['bill_picture']['type'][$i],
                'tmp_name' => $fileData['bill_picture']['tmp_name'][$i],
                'error' => $fileData['bill_picture']['error'][$i],
                'size' => $fileData['bill_picture']['size'][$i]
            ];
            
            // Use the new single media upload handler
            $uploadResult = handleSingleMediaUpload($file, 'inventory_bills');
            if (!isset($uploadResult['error'])) {
                $billPicture = $uploadResult['path'];
            } else {
                error_log("Inventory Bill Upload Error: " . $uploadResult['error']);
            }
        }
        
        // Insert inventory item
        $sql = "INSERT INTO event_inventory_items (
                    event_id, inventory_type, material, quantity, units,
                    unit_price, total_price, remaining_quantity, supplier_name,
                    bill_number, bill_date, bill_picture, remarks
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $eventId,
            $postData['inventory_type'][$i],
            $material,
            $quantity,
            $postData['units'][$i] ?? '',
            $unitPrice,
            $totalPrice,
            $quantity, // Initially, remaining quantity equals the total quantity
            $postData['supplier_name'][$i] ?? '',
            $postData['bill_number'][$i] ?? '',
            $postData['bill_date'][$i] ?? null,
            $billPicture,
            $postData['inventory_remarks'][$i] ?? ''
        ]);
        
        // Get the inventory item ID
        $inventoryId = $pdo->lastInsertId();
        
        // Process inventory media files if any
        if (isset($fileData['inventory_media_file']['name'])) {
            $mediaFiles = $fileData['inventory_media_file'];
            $mediaCaptions = $postData['inventory_media_caption'] ?? [];
            
            // Count how many files were uploaded
            $mediaCount = count($mediaFiles['name']);
            
            for ($j = 0; $j < $mediaCount; $j++) {
                // Skip empty files
                if (empty($mediaFiles['name'][$j])) {
                    continue;
                }
                
                $file = [
                    'name' => $mediaFiles['name'][$j],
                    'type' => $mediaFiles['type'][$j],
                    'tmp_name' => $mediaFiles['tmp_name'][$j],
                    'error' => $mediaFiles['error'][$j],
                    'size' => $mediaFiles['size'][$j]
                ];
                
                // Skip files with errors
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                // Determine media type
                $mediaType = '';
                if (strpos($file['type'], 'image/') === 0) {
                    $mediaType = 'image';
                } elseif (strpos($file['type'], 'video/') === 0) {
                    $mediaType = 'video';
                } else {
                    continue; // Skip unsupported file types
                }
                
                // Create upload directory if not exists
                $uploadDir = dirname(dirname(__FILE__)) . '/uploads/inventory/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Generate unique filename
                $uniqueFileName = time() . '_' . uniqid() . '_' . $file['name'];
                $filePath = 'uploads/inventory/' . $uniqueFileName;
                $uploadFilePath = dirname(dirname(__FILE__)) . '/' . $filePath;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $uploadFilePath)) {
                    // Get caption if available
                    $description = isset($mediaCaptions[$j]) ? $mediaCaptions[$j] : '';
                    
                    // Save to database
                    $mediaSql = "INSERT INTO inventory_media (inventory_id, media_type, file_path, description) 
                              VALUES (?, ?, ?, ?)";
                    
                    $mediaStmt = $pdo->prepare($mediaSql);
                    $mediaStmt->execute([
                        $inventoryId,
                        $mediaType,
                        $filePath,
                        $description
                    ]);
                }
            }
        }
    }
}