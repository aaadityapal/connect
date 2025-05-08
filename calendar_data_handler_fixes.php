<?php
/**
 * Fixes for Calendar Data Handler issues
 * 
 * This file contains fixes for two issues:
 * 1. event_date storing 00:00:00 in hr_supervisor_activity_log table
 * 2. Latitude, longitude, accuracy and address not being saved in hr_supervisor_material_photo_records
 * 
 * Instructions for applying these fixes:
 * 1. Open includes/calendar_data_handler.php
 * 2. Find the logActivity function (around line 27)
 * 3. Add the date formatting code shown in Fix 1 right after the JSON encoding section
 * 4. Find the processMaterialPhotos function (around line 483)
 * 5. Replace the entire function with the code in Fix 2
 */

/*
 * FIX 1: Add this code to the logActivity function after the JSON encoding section
 * and before the prepare statement
 */

// Fix for date issue: Ensure eventDate has time component
if ($eventDate) {
    // If eventDate doesn't have a time component, add current time
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
        $eventDate .= ' ' . date('H:i:s');
    }
}


/*
 * FIX 2: Replace the entire processMaterialPhotos function with this code
 */

/**
 * Process photos for materials - FIXED VERSION
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
    
    foreach ($photos as $photoData) {
        // Handle both string filenames and objects with location data
        if (is_string($photoData)) {
            // Simple string filename (backward compatibility)
            $photoFilename = $photoData;
            $photoPath = "uploads/materials/" . date('Y/m/d/') . $photoFilename;
            $latitude = null;
            $longitude = null;
            $accuracy = null;
            $address = null;
            $timestamp = null;
        } else if (is_array($photoData) || is_object($photoData)) {
            // Object with location data
            if (is_object($photoData)) {
                $photoData = (array)$photoData;
            }
            
            // Get filename - could be directly the name or in a 'name' property
            if (isset($photoData['name'])) {
                $photoFilename = $photoData['name'];
            } else {
                // If it's just a string inside an array
                $photoFilename = is_string($photoData) ? $photoData : 'unknown_' . time() . '.jpg';
            }
            
            $photoPath = "uploads/materials/" . date('Y/m/d/') . $photoFilename;
            
            // Get location data
            $latitude = isset($photoData['latitude']) ? (float)$photoData['latitude'] : null;
            $longitude = isset($photoData['longitude']) ? (float)$photoData['longitude'] : null;
            $accuracy = isset($photoData['accuracy']) ? (float)$photoData['accuracy'] : null;
            $address = isset($photoData['address']) ? $photoData['address'] : null;
            
            // Handle location metadata if available
            if (isset($photoData['location']) && is_array($photoData['location'])) {
                $location = $photoData['location'];
                $latitude = isset($location['latitude']) ? (float)$location['latitude'] : $latitude;
                $longitude = isset($location['longitude']) ? (float)$location['longitude'] : $longitude;
                $accuracy = isset($location['accuracy']) ? (float)$location['accuracy'] : $accuracy;
                $address = isset($location['address']) ? $location['address'] : $address;
            }
            
            // Handle timestamp 
            if (isset($photoData['timestamp'])) {
                $timestamp = is_numeric($photoData['timestamp']) ? 
                           date('Y-m-d H:i:s', $photoData['timestamp']) : 
                           $photoData['timestamp'];
            } else {
                $timestamp = date('Y-m-d H:i:s');
            }
        } else {
            // Not a recognized format
            continue;
        }
        
        // Debug log
        error_log("Processing photo: $photoFilename, Lat: $latitude, Lng: $longitude, Accuracy: $accuracy");
        
        // Insert record with proper error handling
        try {
            $stmt = $conn->prepare("INSERT INTO hr_supervisor_material_photo_records 
                                  (material_id, type, filename, photo_path, latitude, longitude, location_accuracy, location_address, uploaded_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                  
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $currentTime = date('Y-m-d H:i:s');
            $stmt->bind_param("isssdddss", $materialId, $type, $photoFilename, $photoPath, 
                             $latitude, $longitude, $accuracy, $address, $currentTime);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error saving photo record: " . $e->getMessage());
            $success = false;
        }
    }
    
    return $success;
}


/*
 * ADDITIONAL SQL FIXES
 * These SQL queries should be run on the database to ensure the columns have the correct types
 */

/*
1. Make sure event_date is DATETIME in activity log table:
ALTER TABLE hr_supervisor_activity_log MODIFY COLUMN event_date DATETIME;

2. Ensure the material photo records table has the correct location columns:
ALTER TABLE hr_supervisor_material_photo_records 
MODIFY COLUMN latitude DOUBLE NULL DEFAULT NULL,
MODIFY COLUMN longitude DOUBLE NULL DEFAULT NULL,
MODIFY COLUMN accuracy DOUBLE NULL DEFAULT NULL,
MODIFY COLUMN address TEXT NULL DEFAULT NULL;
*/ 