<?php
/**
 * Apply Calendar Data Handler Fixes
 * 
 * This script applies the fixes from calendar_data_handler_fixes.php to the actual calendar_data_handler.php file
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Backup original file before making changes
$sourceFile = 'includes/calendar_data_handler.php';
$backupFile = 'includes/calendar_data_handler.php.bak';

if (!file_exists($backupFile)) {
    if (!copy($sourceFile, $backupFile)) {
        die("Failed to create backup of calendar_data_handler.php. Please check file permissions.");
    }
}

// Read the original file content
$originalContent = file_get_contents($sourceFile);
if ($originalContent === false) {
    die("Failed to read calendar_data_handler.php. Please check if the file exists and is readable.");
}

// Apply fixes
$updatedContent = $originalContent;

// Fix 1: Fix the logActivity function to ensure event_date has time component
$logActivityPattern = '/function logActivity\([^)]+\)[^{]*\{.*?\$jsonData = json_encode\(\$logData\);/s';
$logActivityReplacement = '$0

    // Fix for date issue: Ensure eventDate has time component
    if ($eventDate) {
        // If eventDate doesn\'t have a time component, add current time
        if (preg_match(\'/^\d{4}-\d{2}-\d{2}$/\', $eventDate)) {
            $eventDate .= \' \' . date(\'H:i:s\');
        }
    }';

$updatedContent = preg_replace($logActivityPattern, $logActivityReplacement, $updatedContent);

// Fix 2: Replace the processMaterialPhotos function with the fixed version
$processMaterialPhotosPattern = '/function processMaterialPhotos\([^)]+\)[^{]*\{.*?return \$success;\s*\}/s';
$processMaterialPhotosReplacement = 'function processMaterialPhotos($materialId, $photos, $type) {
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
            $photoPath = "uploads/materials/" . date(\'Y/m/d/\') . $photoFilename;
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
            
            // Get filename - could be directly the name or in a \'name\' property
            if (isset($photoData[\'name\'])) {
                $photoFilename = $photoData[\'name\'];
            } else {
                // If it\'s just a string inside an array
                $photoFilename = is_string($photoData) ? $photoData : \'unknown_\' . time() . \'.jpg\';
            }
            
            $photoPath = "uploads/materials/" . date(\'Y/m/d/\') . $photoFilename;
            
            // Get location data
            $latitude = isset($photoData[\'latitude\']) ? (float)$photoData[\'latitude\'] : null;
            $longitude = isset($photoData[\'longitude\']) ? (float)$photoData[\'longitude\'] : null;
            $accuracy = isset($photoData[\'accuracy\']) ? (float)$photoData[\'accuracy\'] : null;
            $address = isset($photoData[\'address\']) ? $photoData[\'address\'] : null;
            
            // Handle location metadata if available
            if (isset($photoData[\'location\']) && is_array($photoData[\'location\'])) {
                $location = $photoData[\'location\'];
                $latitude = isset($location[\'latitude\']) ? (float)$location[\'latitude\'] : $latitude;
                $longitude = isset($location[\'longitude\']) ? (float)$location[\'longitude\'] : $longitude;
                $accuracy = isset($location[\'accuracy\']) ? (float)$location[\'accuracy\'] : $accuracy;
                $address = isset($location[\'address\']) ? $location[\'address\'] : $address;
            }
            
            // Handle timestamp 
            if (isset($photoData[\'timestamp\'])) {
                $timestamp = is_numeric($photoData[\'timestamp\']) ? 
                           date(\'Y-m-d H:i:s\', $photoData[\'timestamp\']) : 
                           $photoData[\'timestamp\'];
            } else {
                $timestamp = date(\'Y-m-d H:i:s\');
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
            
            $currentTime = date(\'Y-m-d H:i:s\');
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
}';

$updatedContent = preg_replace($processMaterialPhotosPattern, $processMaterialPhotosReplacement, $updatedContent);

// Write the updated content back to the file
if (file_put_contents($sourceFile, $updatedContent) === false) {
    die("Failed to write to calendar_data_handler.php. Please check if the file is writable.");
}

// Check if changes were actually applied
$newContent = file_get_contents($sourceFile);
$logActivityApplied = strpos($newContent, "// Fix for date issue: Ensure eventDate has time component") !== false;
$processMaterialPhotosApplied = strpos($newContent, "// Handle both string filenames and objects with location data") !== false;

// Database queries for schema adjustments
$dbQueries = [
    "ALTER TABLE hr_supervisor_activity_log MODIFY COLUMN event_date DATETIME;",
    "ALTER TABLE hr_supervisor_material_photo_records 
    MODIFY COLUMN latitude DOUBLE NULL DEFAULT NULL,
    MODIFY COLUMN longitude DOUBLE NULL DEFAULT NULL,
    MODIFY COLUMN location_accuracy DOUBLE NULL DEFAULT NULL,
    MODIFY COLUMN location_address TEXT NULL DEFAULT NULL;"
];

// Display results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Apply Calendar Data Handler Fixes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
        }
        .section {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 4px;
        }
        .success {
            background-color: #dff0d8;
            border-left: 5px solid #5cb85c;
        }
        .warning {
            background-color: #fcf8e3;
            border-left: 5px solid #f0ad4e;
        }
        .error {
            background-color: #f2dede;
            border-left: 5px solid #d9534f;
        }
        .code {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #337ab7;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            margin-top: 10px;
        }
        .btn:hover {
            background-color: #286090;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Apply Calendar Data Handler Fixes</h1>
        
        <div class="section <?php echo ($logActivityApplied && $processMaterialPhotosApplied) ? 'success' : 'error'; ?>">
            <h2>Code Updates</h2>
            
            <p><strong>Backup Created:</strong> <?php echo file_exists($backupFile) ? '✅ Yes' : '❌ No'; ?></p>
            
            <div class="<?php echo $logActivityApplied ? 'success' : 'error'; ?> section">
                <p><strong>Fix 1 (logActivity function):</strong> <?php echo $logActivityApplied ? '✅ Applied' : '❌ Failed to apply'; ?></p>
                <?php if (!$logActivityApplied): ?>
                <p>The fix for the logActivity function could not be applied. You may need to manually update the code.</p>
                <?php endif; ?>
            </div>
            
            <div class="<?php echo $processMaterialPhotosApplied ? 'success' : 'error'; ?> section">
                <p><strong>Fix 2 (processMaterialPhotos function):</strong> <?php echo $processMaterialPhotosApplied ? '✅ Applied' : '❌ Failed to apply'; ?></p>
                <?php if (!$processMaterialPhotosApplied): ?>
                <p>The fix for the processMaterialPhotos function could not be applied. You may need to manually update the code.</p>
                <?php endif; ?>
            </div>
            
            <?php if (!$logActivityApplied || !$processMaterialPhotosApplied): ?>
            <div class="section warning">
                <h3>Manual Update Instructions</h3>
                <p>Some fixes couldn't be applied automatically. Please follow these steps:</p>
                <ol>
                    <li>Open the <code>calendar_data_handler_fixes.php</code> file</li>
                    <li>Follow the instructions at the top of the file to manually apply the changes</li>
                </ol>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="section">
            <h2>Database Schema Updates</h2>
            <p>The following SQL queries need to be executed to complete the fixes:</p>
            
            <div class="code"><?php echo implode("\n\n", $dbQueries); ?></div>
            
            <p>You can run these queries in your database administration tool (like phpMyAdmin).</p>
        </div>
        
        <div class="section">
            <h2>Next Steps</h2>
            <p>To test the fixes, use our new media upload utility:</p>
            <a href="upload_media.php" class="btn">Go to Upload Media</a>
        </div>
    </div>
</body>
</html> 