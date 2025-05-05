<?php
/**
 * Media Upload Handler
 * 
 * Specialized functions to handle media uploads for work progress and inventory
 */

require_once 'file_upload.php';

/**
 * Process work progress media uploads and save to database
 * 
 * @param int $workId Work progress ID
 * @param array $mediaFiles Media files array from form
 * @param int $index Index of the work progress item
 * @return array Results of upload operations
 */
function handleWorkProgressMedia($workId, $mediaFiles, $index) {
    global $pdo;
    $results = ['success' => [], 'errors' => []];
    
    // Make sure we have valid file data
    if (empty($mediaFiles) || !is_array($mediaFiles) || !isset($mediaFiles['name'])) {
        $results['errors'][] = 'Invalid file data provided';
        return $results;
    }
    
    // Handle direct single file upload (simple structure)
    if (!isset($mediaFiles['name'][$index])) {
        // This is a simple direct file upload (single file)
        // Make sure we have a valid file
        if (!empty($mediaFiles['name']) && is_uploaded_file($mediaFiles['tmp_name'])) {
            $file = [
                'name' => $mediaFiles['name'],
                'type' => $mediaFiles['type'],
                'tmp_name' => $mediaFiles['tmp_name'],
                'error' => $mediaFiles['error'],
                'size' => $mediaFiles['size']
            ];
            
            $singleResults = processWorkProgressSingleFile($workId, $file, $results);
            $results['success'] = array_merge($results['success'], $singleResults['success']);
            $results['errors'] = array_merge($results['errors'], $singleResults['errors']);
            return $results;
        } else {
            $results['errors'][] = 'No valid file was uploaded';
            return $results;
        }
    }
    
    // Handle regular structure (multiple files per work progress)
    if (isset($mediaFiles['name'][$index])) {
        // Check if it's a nested array or simple array
        if (is_array($mediaFiles['name'][$index])) {
            // This is our expected format with nested arrays
            $mediaCount = count($mediaFiles['name'][$index]);
            
            for ($j = 0; $j < $mediaCount; $j++) {
                // Skip empty entries
                if (empty($mediaFiles['name'][$index][$j])) {
                    continue;
                }
                
                $file = [
                    'name' => $mediaFiles['name'][$index][$j],
                    'type' => $mediaFiles['type'][$index][$j],
                    'tmp_name' => $mediaFiles['tmp_name'][$index][$j],
                    'error' => $mediaFiles['error'][$index][$j],
                    'size' => $mediaFiles['size'][$index][$j]
                ];
                
                $singleResults = processWorkProgressSingleFile($workId, $file, $results);
                $results['success'] = array_merge($results['success'], $singleResults['success']);
                $results['errors'] = array_merge($results['errors'], $singleResults['errors']);
            }
        } elseif (isset($mediaFiles['name'][$index]) && 
                  isset($mediaFiles['type'][$index]) && 
                  isset($mediaFiles['tmp_name'][$index]) && 
                  isset($mediaFiles['error'][$index]) && 
                  isset($mediaFiles['size'][$index])) {
            // Simple array structure, just one file for this index
            $file = [
                'name' => $mediaFiles['name'][$index],
                'type' => $mediaFiles['type'][$index],
                'tmp_name' => $mediaFiles['tmp_name'][$index],
                'error' => $mediaFiles['error'][$index],
                'size' => $mediaFiles['size'][$index]
            ];
            
            $singleResults = processWorkProgressSingleFile($workId, $file, $results);
            $results['success'] = array_merge($results['success'], $singleResults['success']);
            $results['errors'] = array_merge($results['errors'], $singleResults['errors']);
        } else {
            $results['errors'][] = 'Invalid file data structure';
        }
    } else {
        $results['errors'][] = 'No media files found for work progress item';
    }
    
    return $results;
}

/**
 * Process a single file for work progress
 * 
 * @param int $workId Work progress ID
 * @param array $file File data in standard format
 * @param array $results Results array to add to
 * @return array Updated results array
 */
function processWorkProgressSingleFile($workId, $file, $results) {
    global $pdo;
    $localResults = ['success' => [], 'errors' => []];
    
    // Skip empty media entries
    if (empty($file['name'])) {
        return $localResults;
    }
    
    // Determine media type and upload to appropriate directory
    $mediaType = 'image';
    $fileType = $file['type'];
    $uploadResult = null;
    
    if (strpos($fileType, 'video/') === 0) {
        $mediaType = 'video';
        $uploadResult = uploadVideo($file, 'work_videos');
    } else {
        $uploadResult = uploadImage($file, 'work_images');
    }
    
    // Check if upload was successful
    if (isset($uploadResult['error'])) {
        $localResults['errors'][] = "Failed to upload file {$file['name']}: {$uploadResult['error']}";
        return $localResults;
    }
    
    // Insert into database
    try {
        $sql = "INSERT INTO work_progress_media (
                    work_progress_id, media_type, file_path, description, created_at
                ) VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $workId,
            $mediaType,
            $uploadResult['path'],
            'Work progress media'
        ]);
        
        $localResults['success'][] = "Media file {$file['name']} uploaded successfully";
    } catch (Exception $e) {
        $localResults['errors'][] = "Database error: " . $e->getMessage();
        // Clean up the uploaded file if database insert fails
        deleteUploadedFile($uploadResult['path']);
    }
    
    return $localResults;
}

/**
 * Process inventory item media uploads and save to database
 * 
 * @param int $inventoryId Inventory item ID
 * @param array $mediaFiles Media files array from form
 * @param int $index Index of the inventory item
 * @return array Results of upload operations
 */
function handleInventoryMedia($inventoryId, $mediaFiles, $index) {
    global $pdo;
    $results = ['success' => [], 'errors' => []];
    
    // Make sure we have valid file data
    if (empty($mediaFiles) || !is_array($mediaFiles) || !isset($mediaFiles['name'])) {
        $results['errors'][] = 'Invalid file data provided';
        return $results;
    }
    
    // Handle direct single file upload (simple structure)
    if (!isset($mediaFiles['name'][$index])) {
        // This is a simple direct file upload (single file)
        // Make sure we have a valid file
        if (!empty($mediaFiles['name']) && is_uploaded_file($mediaFiles['tmp_name'])) {
            $file = [
                'name' => $mediaFiles['name'],
                'type' => $mediaFiles['type'],
                'tmp_name' => $mediaFiles['tmp_name'],
                'error' => $mediaFiles['error'],
                'size' => $mediaFiles['size']
            ];
            
            $singleResults = processInventorySingleFile($inventoryId, $file, $results);
            $results['success'] = array_merge($results['success'], $singleResults['success']);
            $results['errors'] = array_merge($results['errors'], $singleResults['errors']);
            return $results;
        } else {
            $results['errors'][] = 'No valid file was uploaded';
            return $results;
        }
    }
    
    // Handle regular structure (multiple files per inventory item)
    if (isset($mediaFiles['name'][$index])) {
        // Check if it's a nested array or simple array
        if (is_array($mediaFiles['name'][$index])) {
            // This is our expected format with nested arrays
            $mediaCount = count($mediaFiles['name'][$index]);
            
            for ($j = 0; $j < $mediaCount; $j++) {
                // Skip empty entries
                if (empty($mediaFiles['name'][$index][$j])) {
                    continue;
                }
                
                $file = [
                    'name' => $mediaFiles['name'][$index][$j],
                    'type' => $mediaFiles['type'][$index][$j],
                    'tmp_name' => $mediaFiles['tmp_name'][$index][$j],
                    'error' => $mediaFiles['error'][$index][$j],
                    'size' => $mediaFiles['size'][$index][$j]
                ];
                
                $singleResults = processInventorySingleFile($inventoryId, $file, $results);
                $results['success'] = array_merge($results['success'], $singleResults['success']);
                $results['errors'] = array_merge($results['errors'], $singleResults['errors']);
            }
        } elseif (isset($mediaFiles['name'][$index]) && 
                  isset($mediaFiles['type'][$index]) && 
                  isset($mediaFiles['tmp_name'][$index]) && 
                  isset($mediaFiles['error'][$index]) && 
                  isset($mediaFiles['size'][$index])) {
            // Simple array structure, just one file for this index
            $file = [
                'name' => $mediaFiles['name'][$index],
                'type' => $mediaFiles['type'][$index],
                'tmp_name' => $mediaFiles['tmp_name'][$index],
                'error' => $mediaFiles['error'][$index],
                'size' => $mediaFiles['size'][$index]
            ];
            
            $singleResults = processInventorySingleFile($inventoryId, $file, $results);
            $results['success'] = array_merge($results['success'], $singleResults['success']);
            $results['errors'] = array_merge($results['errors'], $singleResults['errors']);
        } else {
            $results['errors'][] = 'Invalid file data structure';
        }
    } else {
        $results['errors'][] = 'No media files found for inventory item';
    }
    
    return $results;
}

/**
 * Process a single file for inventory
 * 
 * @param int $inventoryId Inventory ID
 * @param array $file File data in standard format
 * @param array $results Results array to add to
 * @return array Updated results array
 */
function processInventorySingleFile($inventoryId, $file, $results) {
    global $pdo;
    $localResults = ['success' => [], 'errors' => []];
    
    // Skip empty media entries
    if (empty($file['name'])) {
        return $localResults;
    }
    
    // Determine media type and upload to appropriate directory
    $mediaType = 'image';
    $fileType = $file['type'];
    $uploadResult = null;
    
    if (strpos($fileType, 'video/') === 0) {
        $mediaType = 'video';
        $uploadResult = uploadVideo($file, 'inventory_videos');
    } else {
        $uploadResult = uploadImage($file, 'inventory_images');
    }
    
    // Check if upload was successful
    if (isset($uploadResult['error'])) {
        $localResults['errors'][] = "Failed to upload file {$file['name']}: {$uploadResult['error']}";
        return $localResults;
    }
    
    // Insert into database
    try {
        $sql = "INSERT INTO inventory_media (
                    inventory_id, media_type, file_path, description, created_at
                ) VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $inventoryId,
            $mediaType,
            $uploadResult['path'],
            'Inventory item media'
        ]);
        
        $localResults['success'][] = "Media file {$file['name']} uploaded successfully";
    } catch (Exception $e) {
        $localResults['errors'][] = "Database error: " . $e->getMessage();
        // Clean up the uploaded file if database insert fails
        deleteUploadedFile($uploadResult['path']);
    }
    
    return $localResults;
}

/**
 * Process single file upload for bill pictures or other individual media
 * 
 * @param array $file Single file array from form
 * @param string $destination Destination directory
 * @return array Upload result
 */
function handleSingleMediaUpload($file, $destination = 'general') {
    if (empty($file['name'])) {
        return ['error' => 'No file provided'];
    }
    
    $fileType = $file['type'];
    
    if (strpos($fileType, 'video/') === 0) {
        return uploadVideo($file, $destination);
    } else if (strpos($fileType, 'image/') === 0) {
        return uploadImage($file, $destination);
    } else {
        return uploadFile($file, $destination);
    }
} 