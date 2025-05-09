<?php
/**
 * Test File Upload Script
 * This script tests if material and bill images are being saved correctly in the uploads directory
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set a fake user ID for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Test User';
}

// Include necessary files
require_once 'includes/calendar_data_handler.php';

// Log errors to browser
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create a header
echo "<!DOCTYPE html>
<html>
<head>
    <title>Material and Bill Photo Upload Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; font-style: italic; }
        .section { margin-bottom: 20px; padding: 10px; border: 1px solid #ccc; }
    </style>
</head>
<body>
    <h1>Material and Bill Photo Upload Test</h1>";

echo "<div class='section'>";
echo "<h2>Testing File Uploads</h2>";

// First, make sure we have a test material record to use
try {
    // Create a test vendor
    $vendor_query = "
        INSERT INTO hr_supervisor_vendor_registry 
        (event_id, vendor_name, vendor_type, vendor_contact, vendor_position) 
        VALUES (1, 'Test Vendor for File Upload', 'material', '1234567890', 1)
    ";
    
    $conn->query($vendor_query);
    $vendor_id = $conn->insert_id;
    echo "<p class='info'>Created test vendor with ID: $vendor_id</p>";
    
    // Create a test material record
    $material_query = "
        INSERT INTO hr_supervisor_material_transaction_records 
        (vendor_id, material_remark, material_amount, has_material_photo, has_bill_photo) 
        VALUES ($vendor_id, 'Test Material for File Upload', 1000.00, 1, 1)
    ";
    
    $conn->query($material_query);
    $material_id = $conn->insert_id;
    echo "<p class='info'>Created test material with ID: $material_id</p>";
    
    // Create test files in temp directory
    $temp_dir = sys_get_temp_dir();
    
    // Create a material photo (simple text file)
    $material_filename = 'test_material_' . time() . '.jpg';
    $material_content = "This is a test material photo content created on " . date('Y-m-d H:i:s');
    $material_path = $temp_dir . '/' . $material_filename;
    
    if (file_put_contents($material_path, $material_content)) {
        echo "<p class='success'>Created test material photo in temp directory: $material_path</p>";
    } else {
        echo "<p class='error'>Failed to create test material photo</p>";
    }
    
    // Create a bill photo (simple text file)
    $bill_filename = 'test_bill_' . time() . '.jpg';
    $bill_content = "This is a test bill photo content created on " . date('Y-m-d H:i:s');
    $bill_path = $temp_dir . '/' . $bill_filename;
    
    if (file_put_contents($bill_path, $bill_content)) {
        echo "<p class='success'>Created test bill photo in temp directory: $bill_path</p>";
    } else {
        echo "<p class='error'>Failed to create test bill photo</p>";
    }
    
    echo "<h3>Testing Material Photo Upload</h3>";
    
    // Test material photo upload
    $material_photo_data = [
        'name' => $material_filename,
        'tmp_name' => $material_path,
        'latitude' => 28.6139,
        'longitude' => 77.2090,
        'accuracy' => 10.0,
        'address' => 'Test Location, New Delhi',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Call the processMaterialPhotos function
    if (processMaterialPhotos($material_id, [$material_photo_data], 'material')) {
        echo "<p class='success'>Successfully processed material photo</p>";
        
        // Check if the file was saved in the correct location
        $expected_upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/' . date('Y/m/d/');
        $expected_material_path = $expected_upload_dir . $material_filename;
        
        // Debug information about the paths
        echo "<div style='background-color: #f5f5f5; padding: 10px; border: 1px solid #ddd; margin: 10px 0;'>";
        echo "<h4>Path Debug Information</h4>";
        echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
        echo "<p>Expected directory: $expected_upload_dir</p>";
        echo "<p>Expected file path: $expected_material_path</p>";

        // Check if the directory exists
        if (file_exists($expected_upload_dir)) {
            echo "<p class='success'>Directory exists: $expected_upload_dir</p>";
            // Check directory permissions
            $perms = substr(sprintf('%o', fileperms($expected_upload_dir)), -4);
            echo "<p>Directory permissions: $perms</p>";
        } else {
            echo "<p class='error'>Directory does not exist: $expected_upload_dir</p>";
            // Try to create it
            if (mkdir($expected_upload_dir, 0755, true)) {
                echo "<p class='success'>Created directory: $expected_upload_dir</p>";
            } else {
                echo "<p class='error'>Failed to create directory: $expected_upload_dir</p>";
                echo "<p>Error: " . error_get_last()['message'] . "</p>";
            }
        }

        // Try to copy the file directly
        if (copy($material_path, $expected_material_path)) {
            echo "<p class='success'>Manually copied file to: $expected_material_path</p>";
        } else {
            echo "<p class='error'>Failed to manually copy file</p>";
            echo "<p>Error: " . error_get_last()['message'] . "</p>";
        }
        echo "</div>";

        if (file_exists($expected_material_path)) {
            echo "<p class='success'>Material photo saved in correct location: $expected_material_path</p>";
            echo "<p class='info'>File content: " . htmlspecialchars(file_get_contents($expected_material_path)) . "</p>";
        } else {
            echo "<p class='error'>Material photo not found in expected location: $expected_material_path</p>";
            
            // List all files in the materials directory to see where it might be
            echo "<p class='info'>Files in uploads/materials directory:</p>";
            $files = glob($_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/*');
            echo "<ul>";
            foreach ($files as $file) {
                echo "<li>" . htmlspecialchars($file) . "</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p class='error'>Failed to process material photo</p>";
    }
    
    echo "<h3>Testing Bill Photo Upload</h3>";
    
    // Test bill photo upload
    $bill_photo_data = [
        'name' => $bill_filename,
        'tmp_name' => $bill_path,
        'latitude' => 28.6139,
        'longitude' => 77.2090,
        'accuracy' => 10.0,
        'address' => 'Test Location, New Delhi',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Call the processMaterialPhotos function (with type = 'bill')
    if (processMaterialPhotos($material_id, [$bill_photo_data], 'bill')) {
        echo "<p class='success'>Successfully processed bill photo</p>";
        
        // Check if the file was saved in the correct location
        $expected_upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/' . date('Y/m/d/');
        $expected_bill_path = $expected_upload_dir . $bill_filename;
        
        if (file_exists($expected_bill_path)) {
            echo "<p class='success'>Bill photo saved in correct location: $expected_bill_path</p>";
            echo "<p class='info'>File content: " . htmlspecialchars(file_get_contents($expected_bill_path)) . "</p>";
        } else {
            echo "<p class='error'>Bill photo not found in expected location: $expected_bill_path</p>";
            
            // List all files in the materials directory to see where it might be
            echo "<p class='info'>Files in uploads/materials directory:</p>";
            $files = glob($_SERVER['DOCUMENT_ROOT'] . '/uploads/materials/*');
            echo "<ul>";
            foreach ($files as $file) {
                echo "<li>" . htmlspecialchars($file) . "</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p class='error'>Failed to process bill photo</p>";
    }
    
    // Query the database to verify photo records were created
    $photo_query = "
        SELECT photo_id, photo_type, photo_filename, photo_path 
        FROM hr_supervisor_material_photo_records 
        WHERE material_id = $material_id
    ";
    
    $photo_result = $conn->query($photo_query);
    
    echo "<h3>Database Records Created</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>Photo ID</th><th>Type</th><th>Filename</th><th>Path</th></tr>";
    
    while ($photo = $photo_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $photo['photo_id'] . "</td>";
        echo "<td>" . $photo['photo_type'] . "</td>";
        echo "<td>" . $photo['photo_filename'] . "</td>";
        echo "<td>" . $photo['photo_path'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Add link to view in photo viewer
    echo "<p><a href='view_photos.php?material_id=$material_id'>View in Photo Viewer</a></p>";

} catch (Exception $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

echo "</div>";

echo "</body></html>";
?> 