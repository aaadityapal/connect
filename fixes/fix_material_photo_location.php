<?php
/**
 * Fix for material photo location data not being saved
 * This script diagnoses and fixes issues with location data in hr_supervisor_material_photo_records table
 */

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if needed
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../includes/config/db_connect.php';

echo "<h1>Fixing Material Photo Location Data</h1>";

// Step 1: Check table structure
echo "<h2>Table Structure</h2>";
$structureQuery = "SHOW CREATE TABLE hr_supervisor_material_photo_records";
$structureResult = $conn->query($structureQuery);

$columnsToCheck = [
    'latitude' => 'DOUBLE',
    'longitude' => 'DOUBLE',
    'location_accuracy' => 'DOUBLE',
    'location_address' => 'TEXT'
];

$columnIssues = [];

if ($structureResult && $structureResult->num_rows > 0) {
    $row = $structureResult->fetch_assoc();
    echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
    
    // Check columns against expected
    foreach ($columnsToCheck as $column => $expectedType) {
        // Check if column exists with correct type
        if (strpos($row['Create Table'], $column) === false) {
            $columnIssues[$column] = "Column doesn't exist";
        } else if (strpos($row['Create Table'], "$column $expectedType") === false && 
                 strpos($row['Create Table'], "$column " . strtolower($expectedType)) === false) {
            $columnIssues[$column] = "Column exists but has wrong type";
        }
    }
} else {
    echo "<p style='color:red'>Failed to retrieve table structure: " . $conn->error . "</p>";
}

// Fix column issues if any
if (!empty($columnIssues)) {
    echo "<h3>Detected Column Issues</h3>";
    echo "<ul>";
    foreach ($columnIssues as $column => $issue) {
        echo "<li style='color:red'>$column: $issue</li>";
    }
    echo "</ul>";
    
    echo "<p>Attempting to fix column issues...</p>";
    
    $alterQueries = [
        'latitude' => "ALTER TABLE hr_supervisor_material_photo_records MODIFY COLUMN latitude DOUBLE NULL DEFAULT NULL",
        'longitude' => "ALTER TABLE hr_supervisor_material_photo_records MODIFY COLUMN longitude DOUBLE NULL DEFAULT NULL",
        'location_accuracy' => "ALTER TABLE hr_supervisor_material_photo_records MODIFY COLUMN location_accuracy DOUBLE NULL DEFAULT NULL",
        'location_address' => "ALTER TABLE hr_supervisor_material_photo_records MODIFY COLUMN location_address TEXT NULL DEFAULT NULL"
    ];
    
    $addQueries = [
        'latitude' => "ALTER TABLE hr_supervisor_material_photo_records ADD COLUMN latitude DOUBLE NULL DEFAULT NULL",
        'longitude' => "ALTER TABLE hr_supervisor_material_photo_records ADD COLUMN longitude DOUBLE NULL DEFAULT NULL",
        'location_accuracy' => "ALTER TABLE hr_supervisor_material_photo_records ADD COLUMN location_accuracy DOUBLE NULL DEFAULT NULL",
        'location_address' => "ALTER TABLE hr_supervisor_material_photo_records ADD COLUMN location_address TEXT NULL DEFAULT NULL"
    ];
    
    foreach ($columnIssues as $column => $issue) {
        if ($issue === "Column doesn't exist") {
            if ($conn->query($addQueries[$column])) {
                echo "<p style='color:green'>Successfully added column $column</p>";
            } else {
                echo "<p style='color:red'>Failed to add column $column: " . $conn->error . "</p>";
            }
        } else {
            if ($conn->query($alterQueries[$column])) {
                echo "<p style='color:green'>Successfully modified column $column</p>";
            } else {
                echo "<p style='color:red'>Failed to modify column $column: " . $conn->error . "</p>";
            }
        }
    }
} else {
    echo "<p style='color:green'>All columns exist with correct types</p>";
}

// Step 2: Check existing records
echo "<h2>Current Photo Records</h2>";
$query = "SELECT id, event_id, vendor_id, type, filename, latitude, longitude, accuracy, address FROM hr_supervisor_material_photo_records ORDER BY id DESC LIMIT 10";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<p>Found " . $result->num_rows . " recent photo records.</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Event ID</th><th>Vendor ID</th><th>Type</th><th>Filename</th><th>Latitude</th><th>Longitude</th><th>Accuracy</th><th>Address</th></tr>";
    
    $missingLocationCount = 0;
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['event_id']}</td>";
        echo "<td>{$row['vendor_id']}</td>";
        echo "<td>{$row['type']}</td>";
        echo "<td>{$row['filename']}</td>";
        
        $hasLocationData = !empty($row['latitude']) && !empty($row['longitude']);
        $style = $hasLocationData ? "" : "style='color:red'";
        
        echo "<td $style>" . (empty($row['latitude']) ? "MISSING" : $row['latitude']) . "</td>";
        echo "<td $style>" . (empty($row['longitude']) ? "MISSING" : $row['longitude']) . "</td>";
        echo "<td $style>" . (empty($row['location_accuracy']) ? "MISSING" : $row['location_accuracy']) . "</td>";
        echo "<td $style>" . (empty($row['location_address']) ? "MISSING" : substr($row['location_address'], 0, 50) . '...') . "</td>";
        echo "</tr>";
        
        if (!$hasLocationData) {
            $missingLocationCount++;
        }
    }
    
    echo "</table>";
    
    if ($missingLocationCount > 0) {
        echo "<p style='color:orange'>Found $missingLocationCount records with missing location data.</p>";
    }
} else {
    echo "<p>No photo records found or table is empty.</p>";
}

// Step 3: Examine the processMaterialPhotos function
echo "<h2>Code Analysis: processMaterialPhotos Function</h2>";
echo "<p>The issue appears to be in the processMaterialPhotos function in calendar_data_handler.php.</p>";

echo "<pre>
// Current problematic implementation
function processMaterialPhotos(\$materialId, \$photos, \$type) {
    global \$conn;
    
    if (!is_array(\$photos) || empty(\$photos)) {
        return false;
    }
    
    \$success = true;
    
    foreach (\$photos as \$photo) {
        // In a real implementation, you would handle file uploads and storage
        // This is a placeholder for the actual photo handling logic
        \$photoFilename = \$photo;
        \$photoPath = \"uploads/materials/\" . date('Y/m/d/') . \$photoFilename;
        
        // Mock location data - in a real implementation, this would come from the frontend
        \$latitude = isset(\$photo['latitude']) ? \$photo['latitude'] : null;
        \$longitude = isset(\$photo['longitude']) ? \$photo['longitude'] : null;
        \$accuracy = isset(\$photo['accuracy']) ? \$photo['accuracy'] : null;
        \$address = isset(\$photo['address']) ? \$photo['address'] : null;
        \$timestamp = isset(\$photo['timestamp']) ? date('Y-m-d H:i:s', \$photo['timestamp']) : null;
        
        \$stmt = \$conn->prepare(\"INSERT INTO hr_supervisor_material_photo_records (material_id, photo_type, photo_filename, photo_path, latitude, longitude, location_accuracy, location_address, location_timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)\");
        \$stmt->bind_param(\"isssdddss\", \$materialId, \$type, \$photoFilename, \$photoPath, \$latitude, \$longitude, \$accuracy, \$address, \$timestamp);
        
        if (!\$stmt->execute()) {
            \$success = false;
        }
    }
    
    return \$success;
}
</pre>";

echo "<p>The issue is that the code treats \$photo as both a string (filename) and an array (with location data). It is actually an array with location data in the frontend javascript but is being passed as just string filenames to this PHP function.</p>";

// Step 4: Test inserting a record with location data
echo "<h2>Testing Material Photo Record Insertion</h2>";

// Create a test material record
$materialQuery = "INSERT INTO hr_supervisor_material_transaction_records (vendor_id, material_remark, material_amount, has_material_photo, has_bill_photo) VALUES (1, 'Test record', 100.00, 1, 0)";
if ($conn->query($materialQuery)) {
    $materialId = $conn->insert_id;
    echo "<p style='color:green'>Created test material record with ID: $materialId</p>";
    
    // Test location data
    $filename = "test_photo_" . time() . ".jpg";
    $photoPath = "uploads/materials/" . date('Y/m/d/') . $filename;
    $latitude = 28.6139;
    $longitude = 77.2090;
    $accuracy = 10.5;
    $address = "Test Address, New Delhi, India";
    $timestamp = date('Y-m-d H:i:s');
    
    echo "<p>Testing with location data: Lat: $latitude, Lng: $longitude, Accuracy: $accuracy, Address: $address</p>";
    
    // Insert with correct column names
    $query = "INSERT INTO hr_supervisor_material_photo_records (
        material_id, 
        photo_type, 
        photo_filename, 
        photo_path, 
        latitude, 
        longitude, 
        location_accuracy, 
        location_address, 
        location_timestamp
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $photoType = "material";
        $stmt->bind_param("isssdddss", $materialId, $photoType, $filename, $photoPath, $latitude, $longitude, $accuracy, $address);
        $success = $stmt->execute();
        
        if ($success) {
            $photoId = $conn->insert_id;
            echo "<p style='color:green'>Successfully inserted test photo record with ID: $photoId</p>";
            
            // Verify the inserted record
            $verifyQuery = "SELECT * FROM hr_supervisor_material_photo_records WHERE photo_id = ?";
            $verifyStmt = $conn->prepare($verifyQuery);
            $verifyStmt->bind_param("i", $photoId);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            
            if ($verifyResult && $verifyResult->num_rows > 0) {
                $insertedRow = $verifyResult->fetch_assoc();
                echo "<h3>Inserted Photo Record:</h3>";
                echo "<table border='1' cellpadding='5'>";
                echo "<tr>";
                echo "<th>ID</th>";
                echo "<th>Material ID</th>";
                echo "<th>Type</th>";
                echo "<th>Filename</th>";
                echo "<th>Path</th>";
                echo "<th>Latitude</th>";
                echo "<th>Longitude</th>";
                echo "<th>Accuracy</th>";
                echo "<th>Address</th>";
                echo "</tr>";
                echo "<tr>";
                echo "<td>{$insertedRow['photo_id']}</td>";
                echo "<td>{$insertedRow['material_id']}</td>";
                echo "<td>{$insertedRow['photo_type']}</td>";
                echo "<td>{$insertedRow['photo_filename']}</td>";
                echo "<td>{$insertedRow['photo_path']}</td>";
                echo "<td>" . (empty($insertedRow['latitude']) ? "<span style='color:red'>MISSING</span>" : $insertedRow['latitude']) . "</td>";
                echo "<td>" . (empty($insertedRow['longitude']) ? "<span style='color:red'>MISSING</span>" : $insertedRow['longitude']) . "</td>";
                echo "<td>" . (empty($insertedRow['location_accuracy']) ? "<span style='color:red'>MISSING</span>" : $insertedRow['location_accuracy']) . "</td>";
                echo "<td>" . (empty($insertedRow['location_address']) ? "<span style='color:red'>MISSING</span>" : $insertedRow['location_address']) . "</td>";
                echo "</tr>";
                echo "</table>";
                
                // Check if location data was saved correctly
                if (empty($insertedRow['latitude']) || empty($insertedRow['longitude']) || 
                    empty($insertedRow['location_accuracy']) || empty($insertedRow['location_address'])) {
                    echo "<p style='color:red'>Warning: Location data was not saved correctly!</p>";
                } else {
                    echo "<p style='color:green'>Location data was saved correctly!</p>";
                }
            }
            
            $verifyStmt->close();
        } else {
            echo "<p style='color:red'>Failed to insert test photo record: " . $stmt->error . "</p>";
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>Failed to create test material record: " . $conn->error . "</p>";
}

// Step 5: Provide the fixed function
echo "<h2>Fixed processMaterialPhotos Function</h2>";
echo "<pre>
/**
 * Process photos for materials - FIXED VERSION
 * @param int \$materialId The material ID
 * @param array \$photos The photo data
 * @param string \$type The photo type (material/bill)
 * @return bool Success flag
 */
function processMaterialPhotos(\$materialId, \$photos, \$type) {
    global \$conn;
    
    if (!is_array(\$photos) || empty(\$photos)) {
        return false;
    }
    
    \$success = true;
    
    foreach (\$photos as \$photoData) {
        // Handle both string filenames and objects with location data
        if (is_string(\$photoData)) {
            // Simple string filename (backward compatibility)
            \$photoFilename = \$photoData;
            \$photoPath = \"uploads/materials/\" . date('Y/m/d/') . \$photoFilename;
            \$latitude = null;
            \$longitude = null;
            \$accuracy = null;
            \$address = null;
            \$timestamp = null;
        } else if (is_array(\$photoData) || is_object(\$photoData)) {
            // Object with location data
            if (is_object(\$photoData)) {
                \$photoData = (array)\$photoData;
            }
            
            // Get filename - could be directly the name or in a 'name' property
            if (isset(\$photoData['name'])) {
                \$photoFilename = \$photoData['name'];
            } else {
                // If it's just a string inside an array
                \$photoFilename = is_string(\$photoData) ? \$photoData : 'unknown_' . time() . '.jpg';
            }
            
            \$photoPath = \"uploads/materials/\" . date('Y/m/d/') . \$photoFilename;
            
            // Get location data
            \$latitude = isset(\$photoData['latitude']) ? (float)\$photoData['latitude'] : null;
            \$longitude = isset(\$photoData['longitude']) ? (float)\$photoData['longitude'] : null;
            \$accuracy = isset(\$photoData['accuracy']) ? (float)\$photoData['accuracy'] : null;
            \$address = isset(\$photoData['address']) ? \$photoData['address'] : null;
            
            // Handle location metadata if available
            if (isset(\$photoData['location']) && is_array(\$photoData['location'])) {
                \$location = \$photoData['location'];
                \$latitude = isset(\$location['latitude']) ? (float)\$location['latitude'] : \$latitude;
                \$longitude = isset(\$location['longitude']) ? (float)\$location['longitude'] : \$longitude;
                \$accuracy = isset(\$location['accuracy']) ? (float)\$location['accuracy'] : \$accuracy;
                \$address = isset(\$location['address']) ? \$location['address'] : \$address;
            }
            
            // Handle timestamp 
            if (isset(\$photoData['timestamp'])) {
                \$timestamp = is_numeric(\$photoData['timestamp']) ? 
                           date('Y-m-d H:i:s', \$photoData['timestamp']) : 
                           \$photoData['timestamp'];
            } else {
                \$timestamp = date('Y-m-d H:i:s');
            }
        } else {
            // Not a recognized format
            continue;
        }
        
        // Debug log
        error_log(\"Processing photo: \$photoFilename, Lat: \$latitude, Lng: \$longitude, Accuracy: \$accuracy\");
        
        // Insert record with proper error handling
        try {
            \$stmt = \$conn->prepare(\"INSERT INTO hr_supervisor_material_photo_records 
                                  (material_id, type, filename, photo_path, latitude, longitude, location_accuracy, location_address, uploaded_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)\");
                                  
            if (!\$stmt) {
                throw new Exception(\"Prepare failed: \" . \$conn->error);
            }
            
            \$currentTime = date('Y-m-d H:i:s');
            \$stmt->bind_param(\"isssdddss\", \$materialId, \$type, \$photoFilename, \$photoPath, 
                             \$latitude, \$longitude, \$accuracy, \$address, \$currentTime);
            
            if (!\$stmt->execute()) {
                throw new Exception(\"Execute failed: \" . \$stmt->error);
            }
            
            \$stmt->close();
        } catch (Exception \$e) {
            error_log(\"Error saving photo record: \" . \$e->getMessage());
            \$success = false;
        }
    }
    
    return \$success;
}
</pre>";

// Instructions for updating the original file
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>Open <code>includes/calendar_data_handler.php</code></li>";
echo "<li>Replace the <code>processMaterialPhotos</code> function (around line 483) with the fixed version above</li>";
echo "<li>If necessary, update the table structure:
<pre>
ALTER TABLE hr_supervisor_material_photo_records 
MODIFY COLUMN latitude DOUBLE NULL DEFAULT NULL,
MODIFY COLUMN longitude DOUBLE NULL DEFAULT NULL,
MODIFY COLUMN location_accuracy DOUBLE NULL DEFAULT NULL,
MODIFY COLUMN location_address TEXT NULL DEFAULT NULL;
</pre>
</li>";
echo "<li>Test by uploading photos in the calendar event form</li>";
echo "</ol>";

echo "<h2>Other recommendations:</h2>";
echo "<ul>";
echo "<li>Update the JavaScript in calendar-modal.js to ensure photo location data is correctly structured</li>";
echo "<li>Consider adding debug logging to trace photo uploads</li>";
echo "<li>Verify that location data is being properly collected in the frontend</li>";
echo "</ul>"; 