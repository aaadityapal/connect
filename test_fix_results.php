<?php
/**
 * Test script to verify fixes for database issues
 */

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if needed
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set test user data
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Test User';

// Include the calendar data handler (with fixes)
require_once 'includes/calendar_data_handler.php';

echo "<h1>Testing Fixes for Database Issues</h1>";

// Test 1: Activity Log Date Format
echo "<h2>Test 1: Activity Log Date Format</h2>";

// Create a test activity log with date-only format
$testDate = date('Y-m-d');
echo "<p>Testing with date-only format: $testDate</p>";

if (logActivity('test', 'test_entity', 1, 1, $testDate, 'Testing date fix', null, null)) {
    $insertId = $conn->insert_id;
    echo "<p style='color:green'>Inserted test activity log with ID: $insertId</p>";
    
    // Verify the test record
    $checkQuery = "SELECT event_date FROM hr_supervisor_activity_log WHERE log_id = $insertId";
    $checkResult = $conn->query($checkQuery);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        $dateRow = $checkResult->fetch_assoc();
        echo "<p>Stored event date: {$dateRow['event_date']}</p>";
        
        if (substr($dateRow['event_date'], 11) !== '00:00:00') {
            echo "<p style='color:green'>✅ Fix successful! Time component is present.</p>";
        } else {
            echo "<p style='color:red'>❌ Fix failed. Time component is still 00:00:00.</p>";
        }
    }
} else {
    echo "<p style='color:red'>Failed to insert test activity log: " . $conn->error . "</p>";
}

// Test 2: Material Photo Location Data
echo "<h2>Test 2: Material Photo Location Data</h2>";

// Create a test material record
$materialQuery = "INSERT INTO hr_supervisor_material_transaction_records (vendor_id, material_remark, material_amount, has_material_photo, has_bill_photo) VALUES (1, 'Test record', 100.00, 1, 0)";
if ($conn->query($materialQuery)) {
    $materialId = $conn->insert_id;
    echo "<p style='color:green'>Created test material record with ID: $materialId</p>";
    
    // Test with location data in array format
    $photos = [
        [
            'name' => 'test_photo_' . time() . '.jpg',
            'location' => [
                'latitude' => 28.6139,
                'longitude' => 77.2090,
                'accuracy' => 10.5,
                'address' => 'Test Address, New Delhi, India 110001'
            ],
            'timestamp' => time()
        ]
    ];
    
    echo "<p>Testing with location data in array format</p>";
    echo "<pre>" . htmlspecialchars(json_encode($photos[0], JSON_PRETTY_PRINT)) . "</pre>";
    
    if (processMaterialPhotos($materialId, $photos, 'material')) {
        echo "<p style='color:green'>✅ Successfully processed photo with location data</p>";
        
        // Verify the last inserted photo
        $checkQuery = "SELECT * FROM hr_supervisor_material_photo_records WHERE material_id = $materialId ORDER BY photo_id DESC LIMIT 1";
        $checkResult = $conn->query($checkQuery);
        
        if ($checkResult && $checkResult->num_rows > 0) {
            $photoRow = $checkResult->fetch_assoc();
            echo "<h3>Inserted Photo Record:</h3>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Material ID</th><th>Type</th><th>Filename</th><th>Path</th><th>Latitude</th><th>Longitude</th><th>Accuracy</th><th>Address</th></tr>";
            echo "<tr>";
            echo "<td>{$photoRow['photo_id']}</td>";
            echo "<td>{$photoRow['material_id']}</td>";
            echo "<td>{$photoRow['photo_type']}</td>";
            echo "<td>{$photoRow['photo_filename']}</td>";
            echo "<td>{$photoRow['photo_path']}</td>";
            
            $hasLocationData = !empty($photoRow['latitude']) && !empty($photoRow['longitude']);
            $style = $hasLocationData ? "style='color:green'" : "style='color:red'";
            
            echo "<td $style>" . (empty($photoRow['latitude']) ? "MISSING" : $photoRow['latitude']) . "</td>";
            echo "<td $style>" . (empty($photoRow['longitude']) ? "MISSING" : $photoRow['longitude']) . "</td>";
            echo "<td $style>" . (empty($photoRow['location_accuracy']) ? "MISSING" : $photoRow['location_accuracy']) . "</td>";
            echo "<td $style>" . (empty($photoRow['location_address']) ? "MISSING" : $photoRow['location_address']) . "</td>";
            echo "</tr>";
            echo "</table>";
            
            if ($hasLocationData) {
                echo "<p style='color:green'>✅ Fix for material photo location data is working!</p>";
            } else {
                echo "<p style='color:red'>❌ Fix for material photo location data is not working properly.</p>";
            }
        } else {
            echo "<p style='color:red'>Failed to retrieve inserted photo record: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:red'>Failed to process test photo with location data</p>";
    }
} else {
    echo "<p style='color:red'>Failed to create test material record: " . $conn->error . "</p>";
}

// Test with string-only format (backward compatibility)
echo "<h3>Testing Backward Compatibility</h3>";
$stringPhotos = ['simple_filename.jpg', 'another_photo.jpg'];
echo "<p>Testing with string-only format: " . implode(', ', $stringPhotos) . "</p>";

if (processMaterialPhotos($materialId, $stringPhotos, 'bill')) {
    echo "<p style='color:green'>✅ Successfully processed simple string filenames</p>";
} else {
    echo "<p style='color:red'>❌ Failed to process simple string filenames</p>";
}

echo "<h2>Summary</h2>";
echo "<p>Both fixes should now be working correctly. The database should store:</p>";
echo "<ul>";
echo "<li>Proper date and time in activity logs (no more 00:00:00 issue)</li>";
echo "<li>Location data (latitude, longitude, accuracy, address) for material photos</li>";
echo "</ul>"; 