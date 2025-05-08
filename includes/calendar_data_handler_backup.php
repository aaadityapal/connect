<?php
/**
 * Calendar Data Handler
 * This file handles the processing and saving of calendar data from the site supervisor dashboard
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once 'config/db_connect.php';

/**
 * Log activity to the activity log table
 * @param string $actionType The type of action (create, update, delete, view, etc.)
 * @param string $entityType The type of entity (site, event, vendor, laborer, etc.)
 * @param int|null $entityId The ID of the entity (if applicable)
 * @param int|null $eventId The event ID (if applicable)
 * @param string|null $eventDate The event date (if applicable)
 * @param string $description Description of the activity
 * @param array|null $oldValues Old values for update operations (if applicable)
 * @param array|null $newValues New values for create/update operations (if applicable)
 * @return bool Success flag
 */
function logActivity($actionType, $entityType, $entityId = null, $eventId = null, $eventDate = null, $description = '', $oldValues = null, $newValues = null) {
    global $conn;
    
    // Get user info from session
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;
    
    // Get client info
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Convert arrays to JSON strings
    $oldValuesJson = $oldValues ? json_encode($oldValues) : null;
    $newValuesJson = $newValues ? json_encode($newValues) : null;
    
    // Prepare statement
    $stmt = $conn->prepare("
        INSERT INTO hr_supervisor_activity_log (
            user_id, user_name, action_type, entity_type, entity_id, 
            event_id, event_date, description, old_values, new_values,
            ip_address, user_agent
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?,
            ?, ?
        )
    ");
    
    $stmt->bind_param(
        "isssiiissss",
        $userId,
        $userName,
        $actionType,
        $entityType,
        $entityId,
        $eventId,
        $eventDate,
        $description,
        $oldValuesJson,
        $newValuesJson,
        $ipAddress,
        $userAgent
    );
    
    return $stmt->execute();
}

/**
 * Process and save calendar data
 * @param array $data The submitted form data
 * @return array Response with status and message
 */
function saveCalendarData($data) {
    global $conn;
    
    // Initialize response
    $response = [
        'status' => 'error',
        'message' => 'Failed to save data',
        'data' => null
    ];
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Step 1: Get or create site
        $siteId = getSiteIdFromData($data['siteName']);
        
        if (!$siteId) {
            throw new Exception('Failed to get or create site');
        }
        
        // Step 2: Create event
        $eventDate = sprintf('%04d-%02d-%02d', $data['year'], $data['month'], $data['day']);
        
        $eventId = createEventRecord($siteId, $eventDate, $data['day'], $data['month'], $data['year']);
        
        if (!$eventId) {
            throw new Exception('Failed to create event record');
        }
        
        // Log event creation/update
        logActivity(
            'create',
            'event',
            $eventId,
            $eventId,
            $eventDate,
            "Calendar event created/updated for site ID $siteId on $eventDate",
            null,
            ['site_id' => $siteId, 'event_date' => $eventDate]
        );
        
        // Step 3: Process vendors
        if (isset($data['vendors']) && is_array($data['vendors'])) {
            foreach ($data['vendors'] as $vendorPosition => $vendor) {
                // Skip empty vendors
                if (empty($vendor['name']) || empty($vendor['type'])) {
                    continue;
                }
                
                $vendorId = createVendorRecord($eventId, $vendor, $vendorPosition);
                
                if (!$vendorId) {
                    throw new Exception('Failed to create vendor record');
                }
                
                // Log vendor creation
                logActivity(
                    'create',
                    'vendor',
                    $vendorId,
                    $eventId,
                    $eventDate,
                    "Vendor {$vendor['name']} added to event",
                    null,
                    [
                        'type' => $vendor['type'],
                        'name' => $vendor['name'],
                        'contact' => $vendor['contact'] ?? null
                    ]
                );
                
                // Process material data if available
                if (isset($vendor['material'])) {
                    $materialId = processMaterialData($vendorId, $vendor['material']);
                    
                    if ($materialId) {
                        // Log material creation
                        logActivity(
                            'create',
                            'material',
                            $materialId,
                            $eventId,
                            $eventDate,
                            "Material added for vendor ID $vendorId",
                            null,
                            [
                                'amount' => $vendor['material']['amount'] ?? 0,
                                'remark' => $vendor['material']['remark'] ?? ''
                            ]
                        );
                    }
                }
                
                // Process laborers if available
                if (isset($vendor['labourers']) && is_array($vendor['labourers'])) {
                    foreach ($vendor['labourers'] as $labourerPosition => $labourer) {
                        // Skip empty laborers
                        if (empty($labourer['name'])) {
                            continue;
                        }
                        
                        $laborerId = processLabourerData($vendorId, $labourer, $eventDate, $labourerPosition);
                        
                        if ($laborerId) {
                            // Log laborer creation with attendance, wages, overtime, travel
                            logActivity(
                                'create',
                                'laborer',
                                $laborerId,
                                $eventId,
                                $eventDate,
                                "Laborer {$labourer['name']} added for vendor ID $vendorId",
                                null,
                                [
                                    'name' => $labourer['name'],
                                    'contact' => $labourer['contact'] ?? '',
                                    'attendance' => $labourer['attendance'] ?? [],
                                    'wages' => $labourer['wages'] ?? [],
                                    'overtime' => $labourer['overtime'] ?? [],
                                    'travel' => $labourer['travel'] ?? []
                                ]
                            );
                        }
                    }
                }
                
                // Process single laborer if present (backward compatibility)
                if (isset($vendor['labour']) && !empty($vendor['labour']['name'])) {
                    $laborerId = processLabourerData($vendorId, $vendor['labour'], $eventDate, 0);
                    
                    if ($laborerId) {
                        // Log laborer creation
                        logActivity(
                            'create',
                            'laborer',
                            $laborerId,
                            $eventId,
                            $eventDate,
                            "Laborer {$vendor['labour']['name']} added for vendor ID $vendorId",
                            null,
                            [
                                'name' => $vendor['labour']['name'],
                                'contact' => $vendor['labour']['contact'] ?? '',
                                'attendance' => $vendor['labour']['attendance'] ?? []
                            ]
                        );
                    }
                }
            }
        }
        
        // If all is successful, commit the transaction
        $conn->commit();
        
        // Log the overall form submission
        logActivity(
            'create',
            'system',
            null,
            $eventId,
            $eventDate,
            "Calendar form submitted successfully for $eventDate",
            null,
            ['full_data' => json_encode($data)]
        );
        
        $response = [
            'status' => 'success',
            'message' => 'Calendar data saved successfully',
            'data' => [
                'eventId' => $eventId,
                'eventDate' => $eventDate
            ]
        ];
    } catch (Exception $e) {
        // An error occurred, rollback the transaction
        $conn->rollback();
        
        // Log the error
        logActivity(
            'create',
            'system',
            null,
            null,
            $eventDate ?? null,
            "Error saving calendar data: " . $e->getMessage(),
            null,
            ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]
        );
        
        $response = [
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage(),
            'data' => null
        ];
    }
    
    return $response;
}

/**
 * Get site ID from name or create new site
 * @param string $siteName The site name or code
 * @return int|bool The site ID or false on failure
 */
function getSiteIdFromData($siteName) {
    global $conn;
    
    // Check if it's a predefined site code
    $stmt = $conn->prepare("SELECT site_id FROM hr_supervisor_construction_sites WHERE site_code = ?");
    $stmt->bind_param("s", $siteName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['site_id'];
    }
    
    // Check if it's a custom site that already exists
    $stmt = $conn->prepare("SELECT site_id FROM hr_supervisor_construction_sites WHERE site_name = ? AND is_custom = 1");
    $stmt->bind_param("s", $siteName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['site_id'];
    }
    
    // Create new custom site
    $stmt = $conn->prepare("INSERT INTO hr_supervisor_construction_sites (site_code, site_name, is_custom, created_by) VALUES (?, ?, 1, ?)");
    
    // Generate a unique site code
    $siteCode = 'custom-' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $siteName)) . '-' . substr(md5(uniqid()), 0, 6);
    
    // Get current user ID from session
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    $stmt->bind_param("ssi", $siteCode, $siteName, $userId);
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    
    return false;
}

/**
 * Create event record in the calendar
 * @param int $siteId The site ID
 * @param string $eventDate The event date (YYYY-MM-DD)
 * @param int $day The day
 * @param int $month The month
 * @param int $year The year
 * @return int|bool The event ID or false on failure
 */
function createEventRecord($siteId, $eventDate, $day, $month, $year) {
    global $conn;
    
    // Check if event already exists for this site and date
    $stmt = $conn->prepare("SELECT event_id FROM hr_supervisor_calendar_site_events WHERE site_id = ? AND event_date = ?");
    $stmt->bind_param("is", $siteId, $eventDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Event exists, return its ID
        $row = $result->fetch_assoc();
        return $row['event_id'];
    }
    
    // Create new event
    $stmt = $conn->prepare("INSERT INTO hr_supervisor_calendar_site_events (site_id, event_date, event_day, event_month, event_year, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    
    // Get current user ID from session
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    $stmt->bind_param("isiiii", $siteId, $eventDate, $day, $month, $year, $userId);
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    
    return false;
}

/**
 * Create vendor record
 * @param int $eventId The event ID
 * @param array $vendorData The vendor data
 * @param int $position The position/order of the vendor
 * @return int|bool The vendor ID or false on failure
 */
function createVendorRecord($eventId, $vendorData, $position = 0) {
    global $conn;
    
    $vendorType = $vendorData['type'];
    $vendorName = $vendorData['name'];
    $vendorContact = isset($vendorData['contact']) ? $vendorData['contact'] : '';
    $isCustomType = substr($vendorType, 0, 7) === 'custom-' || !in_array($vendorType, ['supplier', 'contractor', 'consultant', 'laborer']);
    
    $stmt = $conn->prepare("INSERT INTO hr_supervisor_vendor_registry (event_id, vendor_type, vendor_name, vendor_contact, is_custom_type, vendor_position) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssii", $eventId, $vendorType, $vendorName, $vendorContact, $isCustomType, $position);
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    
    return false;
}

/**
 * Process material data for a vendor
 * @param int $vendorId The vendor ID
 * @param array $materialData The material data
 * @return int|bool The material ID or false on failure
 */
function processMaterialData($vendorId, $materialData) {
    global $conn;
    
    $materialRemark = isset($materialData['remark']) ? $materialData['remark'] : '';
    $materialAmount = isset($materialData['amount']) ? floatval($materialData['amount']) : 0.00;
    $hasMaterialPictures = isset($materialData['materialPictures']) && !empty($materialData['materialPictures']);
    $hasBillPictures = isset($materialData['billPictures']) && !empty($materialData['billPictures']);
    
    $stmt = $conn->prepare("INSERT INTO hr_supervisor_material_transaction_records (vendor_id, material_remark, material_amount, has_material_photo, has_bill_photo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isdii", $vendorId, $materialRemark, $materialAmount, $hasMaterialPictures, $hasBillPictures);
    
    if ($stmt->execute()) {
        $materialId = $conn->insert_id;
        
        // Process material pictures if available
        if ($hasMaterialPictures) {
            processMaterialPhotos($materialId, $materialData['materialPictures'], 'material');
        }
        
        // Process bill pictures if available
        if ($hasBillPictures) {
            processMaterialPhotos($materialId, $materialData['billPictures'], 'bill');
        }
        
        return $materialId;
    }
    
    return false;
}

/**
 * Process photos for materials
 * @param int $materialId The material ID
 * @param array $photos The photo data
 * @param string $type The photo type (material/bill)
 * @return bool Success flag
 */
function processMaterialPhotos($materialId, $photos, $type) {
    global $conn;
    
    if (!is_array($photos) || empty($photos)) {
        return false;
    }
    
    $success = true;
    
    foreach ($photos as $photo) {
        // In a real implementation, you would handle file uploads and storage
        // This is a placeholder for the actual photo handling logic
        $photoFilename = $photo;
        $photoPath = "uploads/materials/" . date('Y/m/d/') . $photoFilename;
        
        // Mock location data - in a real implementation, this would come from the frontend
        $latitude = isset($photo['latitude']) ? $photo['latitude'] : null;
        $longitude = isset($photo['longitude']) ? $photo['longitude'] : null;
        $accuracy = isset($photo['accuracy']) ? $photo['accuracy'] : null;
        $address = isset($photo['address']) ? $photo['address'] : null;
        $timestamp = isset($photo['timestamp']) ? date('Y-m-d H:i:s', $photo['timestamp']) : null;
        
        $stmt = $conn->prepare("INSERT INTO hr_supervisor_material_photo_records (material_id, photo_type, photo_filename, photo_path, latitude, longitude, location_accuracy, location_address, location_timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssdddss", $materialId, $type, $photoFilename, $photoPath, $latitude, $longitude, $accuracy, $address, $timestamp);
        
        if (!$stmt->execute()) {
            $success = false;
        }
    }
    
    return $success;
}

/**
 * Process laborer data
 * @param int $vendorId The vendor ID
 * @param array $labourerData The laborer data
 * @param string $eventDate The event date (YYYY-MM-DD)
 * @param int $position The position/order of the laborer
 * @return int|bool The laborer ID or false on failure
 */
function processLabourerData($vendorId, $labourerData, $eventDate, $position = 0) {
    global $conn;
    
    $labourerName = $labourerData['name'];
    $labourerContact = isset($labourerData['contact']) ? $labourerData['contact'] : '';
    
    // Insert laborer record
    $stmt = $conn->prepare("INSERT INTO hr_supervisor_laborer_registry (vendor_id, laborer_name, laborer_contact, laborer_position) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issi", $vendorId, $labourerName, $labourerContact, $position);
    
    if (!$stmt->execute()) {
        return false;
    }
    
    $labourerId = $conn->insert_id;
    
    // Process attendance data
    $morningStatus = isset($labourerData['attendance']['morning']) ? $labourerData['attendance']['morning'] : 'not_recorded';
    $eveningStatus = isset($labourerData['attendance']['evening']) ? $labourerData['attendance']['evening'] : 'not_recorded';
    
    $stmt = $conn->prepare("INSERT INTO hr_supervisor_laborer_attendance_logs (laborer_id, attendance_date, morning_status, evening_status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $labourerId, $eventDate, $morningStatus, $eveningStatus);
    
    if (!$stmt->execute()) {
        return false;
    }
    
    $attendanceId = $conn->insert_id;
    
    // Log attendance record
    logActivity(
        'create',
        'attendance',
        $attendanceId,
        null,
        $eventDate,
        "Attendance recorded for laborer ID $labourerId",
        null,
        [
            'morning_status' => $morningStatus,
            'evening_status' => $eveningStatus,
            'laborer_id' => $labourerId
        ]
    );
    
    // Process wages data if available
    if (isset($labourerData['wages'])) {
        $wagesPerDay = isset($labourerData['wages']['perDay']) ? floatval($labourerData['wages']['perDay']) : 0.00;
        
        if ($wagesPerDay > 0) {
            $stmt = $conn->prepare("INSERT INTO hr_supervisor_wage_payment_records (laborer_id, attendance_id, wages_per_day) VALUES (?, ?, ?)");
            $stmt->bind_param("iid", $labourerId, $attendanceId, $wagesPerDay);
            
            if ($stmt->execute()) {
                $wageId = $conn->insert_id;
                
                // Log wage record
                logActivity(
                    'create',
                    'wage',
                    $wageId,
                    null,
                    $eventDate,
                    "Wages recorded for laborer ID $labourerId",
                    null,
                    [
                        'wages_per_day' => $wagesPerDay,
                        'laborer_id' => $labourerId,
                        'attendance_id' => $attendanceId
                    ]
                );
            }
        }
    }
    
    // Process overtime data if available
    if (isset($labourerData['overtime'])) {
        $otHours = isset($labourerData['overtime']['hours']) ? intval($labourerData['overtime']['hours']) : 0;
        $otMinutes = isset($labourerData['overtime']['minutes']) ? intval($labourerData['overtime']['minutes']) : 0;
        $otRate = isset($labourerData['overtime']['rate']) ? floatval($labourerData['overtime']['rate']) : 0.00;
        
        // Ensure minutes is either 0 or 30
        if ($otMinutes != 0 && $otMinutes != 30) {
            $otMinutes = 0;
        }
        
        if (($otHours > 0 || $otMinutes > 0) && $otRate > 0) {
            $stmt = $conn->prepare("INSERT INTO hr_supervisor_overtime_payment_records (laborer_id, attendance_id, ot_hours, ot_minutes, ot_rate_per_hour) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiid", $labourerId, $attendanceId, $otHours, $otMinutes, $otRate);
            
            if ($stmt->execute()) {
                $overtimeId = $conn->insert_id;
                
                // Log overtime record
                logActivity(
                    'create',
                    'overtime',
                    $overtimeId,
                    null,
                    $eventDate,
                    "Overtime recorded for laborer ID $labourerId",
                    null,
                    [
                        'ot_hours' => $otHours,
                        'ot_minutes' => $otMinutes,
                        'ot_rate_per_hour' => $otRate,
                        'laborer_id' => $labourerId,
                        'attendance_id' => $attendanceId
                    ]
                );
            }
        }
    }
    
    // Process travel data if available
    if (isset($labourerData['travel'])) {
        $travelMode = isset($labourerData['travel']['mode']) ? $labourerData['travel']['mode'] : null;
        $travelAmount = isset($labourerData['travel']['amount']) ? floatval($labourerData['travel']['amount']) : 0.00;
        
        if ($travelAmount > 0) {
            // Get transport mode ID
            $transportModeId = null;
            
            if (!empty($travelMode)) {
                $stmt = $conn->prepare("SELECT transport_mode_id FROM hr_supervisor_transport_modes WHERE mode_name = ?");
                $stmt->bind_param("s", $travelMode);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $transportModeId = $row['transport_mode_id'];
                }
            }
            
            $stmt = $conn->prepare("INSERT INTO hr_supervisor_travel_expense_records (laborer_id, attendance_id, transport_mode_id, travel_amount) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $labourerId, $attendanceId, $transportModeId, $travelAmount);
            
            if ($stmt->execute()) {
                $travelId = $conn->insert_id;
                
                // Log travel expense record
                logActivity(
                    'create',
                    'travel',
                    $travelId,
                    null,
                    $eventDate,
                    "Travel expense recorded for laborer ID $labourerId",
                    null,
                    [
                        'travel_amount' => $travelAmount,
                        'transport_mode' => $travelMode,
                        'transport_mode_id' => $transportModeId,
                        'laborer_id' => $labourerId,
                        'attendance_id' => $attendanceId
                    ]
                );
            }
        }
    }
    
    return $labourerId;
}

/**
 * Get event details for a specific date
 * @param string $eventDate The event date (YYYY-MM-DD)
 * @return array The event details
 */
function getEventDetailsByDate($eventDate) {
    global $conn;
    
    // Log view activity
    logActivity(
        'view',
        'event',
        null,
        null,
        $eventDate,
        "Viewed events for date $eventDate",
        null,
        null
    );
    
    $response = [
        'status' => 'success',
        'date' => $eventDate,
        'events' => []
    ];
    
    // Get site events for this date
    $stmt = $conn->prepare("
        SELECT 
            cse.event_id,
            cse.site_id,
            cs.site_name,
            cs.site_code
        FROM 
            hr_supervisor_calendar_site_events cse
        JOIN 
            hr_supervisor_construction_sites cs ON cse.site_id = cs.site_id
        WHERE 
            cse.event_date = ?
        ORDER BY 
            cse.event_id
    ");
    
    $stmt->bind_param("s", $eventDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($event = $result->fetch_assoc()) {
        $eventId = $event['event_id'];
        $eventData = [
            'eventId' => $eventId,
            'siteName' => $event['site_name'],
            'siteCode' => $event['site_code'],
            'vendors' => []
        ];
        
        // Get vendors for this event
        $vendorStmt = $conn->prepare("
            SELECT 
                vendor_id,
                vendor_type,
                vendor_name,
                vendor_contact,
                vendor_email,
                is_custom_type,
                vendor_position
            FROM 
                hr_supervisor_vendor_registry
            WHERE 
                event_id = ?
            ORDER BY 
                vendor_position
        ");
        
        $vendorStmt->bind_param("i", $eventId);
        $vendorStmt->execute();
        $vendorResult = $vendorStmt->get_result();
        
        while ($vendor = $vendorResult->fetch_assoc()) {
            $vendorId = $vendor['vendor_id'];
            $vendorData = [
                'id' => $vendorId,
                'type' => $vendor['vendor_type'],
                'name' => $vendor['vendor_name'],
                'contact' => $vendor['vendor_contact'],
                'isCustomType' => (bool)$vendor['is_custom_type'],
                'position' => $vendor['vendor_position'],
                'labourers' => [],
                'material' => null
            ];
            
            // Get material data
            $materialStmt = $conn->prepare("
                SELECT 
                    material_id,
                    material_remark,
                    material_amount,
                    has_material_photo,
                    has_bill_photo
                FROM 
                    hr_supervisor_material_transaction_records
                WHERE 
                    vendor_id = ?
            ");
            
            $materialStmt->bind_param("i", $vendorId);
            $materialStmt->execute();
            $materialResult = $materialStmt->get_result();
            
            if ($materialRecord = $materialResult->fetch_assoc()) {
                $materialId = $materialRecord['material_id'];
                $materialData = [
                    'id' => $materialId,
                    'remark' => $materialRecord['material_remark'],
                    'amount' => $materialRecord['material_amount'],
                    'hasMaterialPhotos' => (bool)$materialRecord['has_material_photo'],
                    'hasBillPhotos' => (bool)$materialRecord['has_bill_photo'],
                    'materialPictures' => [],
                    'billPictures' => []
                ];
                
                // Get material photos
                if ($materialRecord['has_material_photo'] || $materialRecord['has_bill_photo']) {
                    $photoStmt = $conn->prepare("
                        SELECT 
                            photo_id,
                            photo_type,
                            photo_filename,
                            photo_path,
                            latitude,
                            longitude,
                            location_accuracy,
                            location_address
                        FROM 
                            hr_supervisor_material_photo_records
                        WHERE 
                            material_id = ?
                    ");
                    
                    $photoStmt->bind_param("i", $materialId);
                    $photoStmt->execute();
                    $photoResult = $photoStmt->get_result();
                    
                    while ($photo = $photoResult->fetch_assoc()) {
                        $photoData = [
                            'id' => $photo['photo_id'],
                            'filename' => $photo['photo_filename'],
                            'path' => $photo['photo_path']
                        ];
                        
                        // Add location data if available
                        if ($photo['latitude'] && $photo['longitude']) {
                            $photoData['location'] = [
                                'latitude' => $photo['latitude'],
                                'longitude' => $photo['longitude'],
                                'accuracy' => $photo['location_accuracy'],
                                'address' => $photo['location_address']
                            ];
                        }
                        
                        if ($photo['photo_type'] === 'material') {
                            $materialData['materialPictures'][] = $photoData;
                        } else {
                            $materialData['billPictures'][] = $photoData;
                        }
                    }
                }
                
                $vendorData['material'] = $materialData;
            }
            
            // Get laborers
            $laborerStmt = $conn->prepare("
                SELECT 
                    lr.laborer_id,
                    lr.laborer_name,
                    lr.laborer_contact,
                    lr.laborer_position,
                    al.attendance_id,
                    al.morning_status,
                    al.evening_status,
                    al.attendance_percentage
                FROM 
                    hr_supervisor_laborer_registry lr
                LEFT JOIN 
                    hr_supervisor_laborer_attendance_logs al ON lr.laborer_id = al.laborer_id AND al.attendance_date = ?
                WHERE 
                    lr.vendor_id = ?
                ORDER BY 
                    lr.laborer_position
            ");
            
            $laborerStmt->bind_param("si", $eventDate, $vendorId);
            $laborerStmt->execute();
            $laborerResult = $laborerStmt->get_result();
            
            while ($laborer = $laborerResult->fetch_assoc()) {
                $laborerId = $laborer['laborer_id'];
                $attendanceId = $laborer['attendance_id'];
                
                $laborerData = [
                    'id' => $laborerId,
                    'name' => $laborer['laborer_name'],
                    'contact' => $laborer['laborer_contact'],
                    'position' => $laborer['laborer_position'],
                    'attendance' => [
                        'morning' => $laborer['morning_status'],
                        'evening' => $laborer['evening_status'],
                        'percentage' => $laborer['attendance_percentage']
                    ],
                    'wages' => null,
                    'overtime' => null,
                    'travel' => null
                ];
                
                // Get wages data
                if ($attendanceId) {
                    $wagesStmt = $conn->prepare("
                        SELECT 
                            wages_per_day,
                            total_wages,
                            payment_status
                        FROM 
                            hr_supervisor_wage_payment_records
                        WHERE 
                            attendance_id = ?
                    ");
                    
                    $wagesStmt->bind_param("i", $attendanceId);
                    $wagesStmt->execute();
                    $wagesResult = $wagesStmt->get_result();
                    
                    if ($wages = $wagesResult->fetch_assoc()) {
                        $laborerData['wages'] = [
                            'perDay' => $wages['wages_per_day'],
                            'totalDay' => $wages['total_wages'],
                            'status' => $wages['payment_status']
                        ];
                    }
                    
                    // Get overtime data
                    $otStmt = $conn->prepare("
                        SELECT 
                            ot_hours,
                            ot_minutes,
                            ot_total_hours,
                            ot_rate_per_hour,
                            ot_total_amount,
                            payment_status
                        FROM 
                            hr_supervisor_overtime_payment_records
                        WHERE 
                            attendance_id = ?
                    ");
                    
                    $otStmt->bind_param("i", $attendanceId);
                    $otStmt->execute();
                    $otResult = $otStmt->get_result();
                    
                    if ($ot = $otResult->fetch_assoc()) {
                        $laborerData['overtime'] = [
                            'hours' => $ot['ot_hours'],
                            'minutes' => $ot['ot_minutes'],
                            'totalHours' => $ot['ot_total_hours'],
                            'rate' => $ot['ot_rate_per_hour'],
                            'total' => $ot['ot_total_amount'],
                            'status' => $ot['payment_status']
                        ];
                    }
                    
                    // Get travel data
                    $travelStmt = $conn->prepare("
                        SELECT 
                            te.travel_amount,
                            te.reimbursement_status,
                            tm.mode_name
                        FROM 
                            hr_supervisor_travel_expense_records te
                        LEFT JOIN 
                            hr_supervisor_transport_modes tm ON te.transport_mode_id = tm.transport_mode_id
                        WHERE 
                            te.attendance_id = ?
                    ");
                    
                    $travelStmt->bind_param("i", $attendanceId);
                    $travelStmt->execute();
                    $travelResult = $travelStmt->get_result();
                    
                    if ($travel = $travelResult->fetch_assoc()) {
                        $laborerData['travel'] = [
                            'mode' => $travel['mode_name'],
                            'amount' => $travel['travel_amount'],
                            'status' => $travel['reimbursement_status']
                        ];
                    }
                }
                
                $vendorData['labourers'][] = $laborerData;
            }
            
            $eventData['vendors'][] = $vendorData;
        }
        
        $response['events'][] = $eventData;
    }
    
    return $response;
}

/**
 * Updates payment status for wages, overtime, or travel records
 * @param string $recordType The type of record (wage, overtime, travel)
 * @param int $recordId The ID of the record
 * @param string $newStatus The new payment status
 * @param string|null $paymentDate Optional payment date
 * @param string|null $paymentReference Optional payment reference
 * @return array Response with status and message
 */
function updatePaymentStatus($recordType, $recordId, $newStatus, $paymentDate = null, $paymentReference = null) {
    global $conn;
    
    $response = [
        'status' => 'error',
        'message' => 'Failed to update payment status',
        'data' => null
    ];
    
    // Validate record type
    $validTypes = ['wage', 'overtime', 'travel'];
    if (!in_array($recordType, $validTypes)) {
        $response['message'] = 'Invalid record type';
        return $response;
    }
    
    // Validate status
    $validStatuses = ['pending', 'processed', 'paid'];
    if (!in_array($newStatus, $validStatuses)) {
        $response['message'] = 'Invalid payment status';
        return $response;
    }
    
    try {
        // Determine the table and ID column based on record type
        $tableMap = [
            'wage' => ['table' => 'hr_supervisor_wage_payment_records', 'id' => 'wage_id', 'entity_type' => 'wage'],
            'overtime' => ['table' => 'hr_supervisor_overtime_payment_records', 'id' => 'overtime_id', 'entity_type' => 'overtime'],
            'travel' => ['table' => 'hr_supervisor_travel_expense_records', 'id' => 'travel_id', 'entity_type' => 'travel']
        ];
        
        $table = $tableMap[$recordType]['table'];
        $idColumn = $tableMap[$recordType]['id'];
        $entityType = $tableMap[$recordType]['entity_type'];
        
        // Get current record data for logging
        $stmt = $conn->prepare("SELECT * FROM $table WHERE $idColumn = ?");
        $stmt->bind_param("i", $recordId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $response['message'] = 'Record not found';
            return $response;
        }
        
        $oldValues = $result->fetch_assoc();
        
        // Start transaction
        $conn->begin_transaction();
        
        // Build SQL based on parameters provided
        $sql = "UPDATE $table SET payment_status = ?";
        $types = 's';
        $params = [$newStatus];
        
        if ($paymentDate) {
            $sql .= ", payment_date = ?";
            $types .= 's';
            $params[] = $paymentDate;
        }
        
        if ($paymentReference) {
            $sql .= ", payment_reference = ?";
            $types .= 's';
            $params[] = $paymentReference;
        }
        
        $sql .= " WHERE $idColumn = ?";
        $types .= 'i';
        $params[] = $recordId;
        
        // Prepare and execute update
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update payment status: ' . $conn->error);
        }
        
        // Create new values array for logging
        $newValues = [
            'payment_status' => $newStatus,
            'payment_date' => $paymentDate,
            'payment_reference' => $paymentReference
        ];
        
        // Log status change
        logActivity(
            'payment_status_change',
            $entityType,
            $recordId,
            null,
            null,
            "Payment status updated to '$newStatus' for $recordType ID $recordId",
            $oldValues,
            $newValues
        );
        
        // Commit transaction
        $conn->commit();
        
        $response = [
            'status' => 'success',
            'message' => "Payment status updated successfully",
            'data' => [
                'record_id' => $recordId,
                'record_type' => $recordType,
                'new_status' => $newStatus
            ]
        ];
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    return $response;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = [
        'status' => 'error',
        'message' => 'Invalid action'
    ];
    
    switch ($_POST['action']) {
        case 'save_calendar_data':
            if (isset($_POST['data']) && !empty($_POST['data'])) {
                $data = json_decode($_POST['data'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $response = saveCalendarData($data);
                } else {
                    $response['message'] = 'Invalid JSON data: ' . json_last_error_msg();
                }
            } else {
                $response['message'] = 'No data provided';
            }
            break;
            
        case 'get_event_details':
            if (isset($_POST['date']) && !empty($_POST['date'])) {
                $date = $_POST['date'];
                $response = getEventDetailsByDate($date);
            } else {
                $response['message'] = 'No date provided';
            }
            break;
            
        case 'update_payment_status':
            if (isset($_POST['record_type'], $_POST['record_id'], $_POST['status'])) {
                $recordType = $_POST['record_type'];
                $recordId = intval($_POST['record_id']);
                $newStatus = $_POST['status'];
                $paymentDate = isset($_POST['payment_date']) ? $_POST['payment_date'] : null;
                $paymentReference = isset($_POST['payment_reference']) ? $_POST['payment_reference'] : null;
                
                $response = updatePaymentStatus($recordType, $recordId, $newStatus, $paymentDate, $paymentReference);
            } else {
                $response['message'] = 'Missing required parameters';
            }
            break;
            
        case 'get_activity_log':
            if (isset($_POST['date_from'], $_POST['date_to'])) {
                $dateFrom = $_POST['date_from'];
                $dateTo = $_POST['date_to'];
                $entityType = isset($_POST['entity_type']) ? $_POST['entity_type'] : null;
                $actionType = isset($_POST['action_type']) ? $_POST['action_type'] : null;
                
                $response = getActivityLog($dateFrom, $dateTo, $entityType, $actionType);
            } else {
                $response['message'] = 'Missing date range parameters';
            }
            break;
            
        default:
            $response['message'] = 'Unknown action: ' . $_POST['action'];
            break;
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/**
 * Get activity log data for a specific date range and filters
 * @param string $dateFrom Start date in YYYY-MM-DD format
 * @param string $dateTo End date in YYYY-MM-DD format
 * @param string|null $entityType Filter by entity type
 * @param string|null $actionType Filter by action type
 * @return array Activity log data with status
 */
function getActivityLog($dateFrom, $dateTo, $entityType = null, $actionType = null) {
    global $conn;
    
    $response = [
        'status' => 'success',
        'message' => 'Activity log retrieved successfully',
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'logs' => []
    ];
    
    // Start building query
    $sql = "
        SELECT 
            log_id, 
            user_id,
            user_name,
            action_type,
            entity_type,
            entity_id,
            event_id,
            event_date,
            description,
            old_values,
            new_values,
            ip_address,
            created_at
        FROM 
            hr_supervisor_activity_log
        WHERE 
            created_at BETWEEN ? AND ?
    ";
    
    $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
    $types = 'ss';
    
    // Add entity type filter if provided
    if ($entityType) {
        $sql .= " AND entity_type = ?";
        $params[] = $entityType;
        $types .= 's';
    }
    
    // Add action type filter if provided
    if ($actionType) {
        $sql .= " AND action_type = ?";
        $params[] = $actionType;
        $types .= 's';
    }
    
    // Order by most recent first
    $sql .= " ORDER BY created_at DESC LIMIT 1000";
    
    // Execute query
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetch all logs
    while ($log = $result->fetch_assoc()) {
        // Decode JSON values if present
        if ($log['old_values']) {
            $log['old_values'] = json_decode($log['old_values'], true);
        }
        
        if ($log['new_values']) {
            $log['new_values'] = json_decode($log['new_values'], true);
        }
        
        $response['logs'][] = $log;
    }
    
    // Log this view action
    logActivity(
        'view',
        'system',
        null,
        null,
        null,
        "Viewed activity log from $dateFrom to $dateTo",
        null,
        null
    );
    
    return $response;
}
?> 