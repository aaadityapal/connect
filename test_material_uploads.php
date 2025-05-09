<?php
/**
 * Test script for material photo uploads
 * This script tests the directory creation and file handling for material photos
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

// Include necessary files - Only include calendar_data_handler.php which already includes the database connection
require_once 'includes/calendar_data_handler.php';

// Log errors to browser
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create a header
echo "<!DOCTYPE html>
<html>
<head>
    <title>Material Photo Upload Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .section { margin-bottom: 20px; padding: 10px; border: 1px solid #ccc; }
        .warning { color: orange; }
        .info { color: blue; font-style: italic; }
    </style>
</head>
<body>
    <h1>Material Photo Upload Test</h1>";

echo "<div class='section'>";
echo "<h2>Testing Directory Structure</h2>";

// Test base directories creation
$uploads_base_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads';
if (!file_exists($uploads_base_dir)) {
    if (mkdir($uploads_base_dir, 0755, true)) {
        echo "<p class='success'>Created base uploads directory: $uploads_base_dir</p>";
    } else {
        echo "<p class='error'>Failed to create base uploads directory: $uploads_base_dir</p>";
    }
} else {
    echo "<p>Base uploads directory already exists: $uploads_base_dir</p>";
}

// Test materials directory creation
$materials_dir = $uploads_base_dir . '/materials';
if (!file_exists($materials_dir)) {
    if (mkdir($materials_dir, 0755, true)) {
        echo "<p class='success'>Created materials directory: $materials_dir</p>";
    } else {
        echo "<p class='error'>Failed to create materials directory: $materials_dir</p>";
    }
} else {
    echo "<p>Materials directory already exists: $materials_dir</p>";
}

// Test date directory creation
$date_path = date('Y/m/d');
$target_dir = $materials_dir . '/' . $date_path;
if (!file_exists($target_dir)) {
    if (mkdir($target_dir, 0755, true)) {
        echo "<p class='success'>Created date directory: $target_dir</p>";
    } else {
        echo "<p class='error'>Failed to create date directory: $target_dir</p>";
    }
} else {
    echo "<p>Date directory already exists: $target_dir</p>";
}

echo "</div>";

// Test creating a sample test image
echo "<div class='section'>";
echo "<h2>Testing File Creation</h2>";

$test_image_name = 'test_image_' . time() . '.jpg';
$test_image_path = $target_dir . '/' . $test_image_name;

// Try to create a simple test image file without using GD library
// This is a minimal valid JPEG file content
$jpeg_header = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigD//2Q==');

$success = false;

// First attempt - try with JPEG data
if (file_put_contents($test_image_path, $jpeg_header)) {
    $success = true;
} else {
    // Fallback 1 - create a simple text file with .jpg extension
    $alt_test_image_name = 'test_image_alt_' . time() . '.jpg';
    $alt_test_image_path = $target_dir . '/' . $alt_test_image_name;
    
    $dummy_content = "This is a test file with .jpg extension to simulate an image file.";
    
    if (file_put_contents($alt_test_image_path, $dummy_content)) {
        $test_image_name = $alt_test_image_name;
        $test_image_path = $alt_test_image_path;
        $success = true;
        echo "<p class='success'>Created alternative test image: $test_image_path</p>";
    } else {
        // Fallback 2 - look for any existing image in the uploads directory
        $existing_images = glob($uploads_base_dir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        
        if (!empty($existing_images)) {
            $random_image = $existing_images[array_rand($existing_images)];
            $test_image_name = basename($random_image);
            $test_image_path = $random_image;
            $success = true;
            echo "<p class='warning'>Using existing image: $test_image_path</p>";
        } else {
            // Fallback 3 - just use a placeholder filename without actually creating a file
            $test_image_name = 'placeholder_image.jpg';
            $test_image_path = $uploads_base_dir . '/' . $test_image_name;
            $success = true;
            echo "<p class='warning'>Using placeholder image filename without actual file: $test_image_name</p>";
            echo "<p class='info'>Note: This is just to test the database functionality without an actual image file.</p>";
        }
    }
}

if ($success) {
    echo "<p class='success'>Test image name: $test_image_name</p>";
    
    // Test inserting into database
    echo "<h3>Testing Database Insertion</h3>";
    
    // Create test vendor record
    $vendor_stmt = $conn->prepare("
        INSERT INTO hr_supervisor_vendor_registry 
        (event_id, vendor_name, vendor_type, vendor_contact, vendor_position) 
        VALUES (1, 'Test Vendor', 'material', '1234567890', 1)
    ");
    
    if ($vendor_stmt->execute()) {
        $vendor_id = $conn->insert_id;
        echo "<p class='success'>Created test vendor with ID: $vendor_id</p>";
        
        // Create test material record
        $material_stmt = $conn->prepare("
            INSERT INTO hr_supervisor_material_transaction_records 
            (vendor_id, material_remark, material_amount, has_material_photo, has_bill_photo) 
            VALUES (?, 'Test Material', 1000.00, 1, 0)
        ");
        
        $material_stmt->bind_param("i", $vendor_id);
        
        if ($material_stmt->execute()) {
            $material_id = $conn->insert_id;
            echo "<p class='success'>Created test material with ID: $material_id</p>";
            
            // Create test photo data
            $photo_data = [
                'name' => $test_image_name,
                'latitude' => 28.6139,
                'longitude' => 77.2090,
                'accuracy' => 10.0,
                'address' => 'Test Location, New Delhi',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Call processMaterialPhotos
            if (processMaterialPhotos($material_id, [$photo_data], 'material')) {
                echo "<p class='success'>Successfully processed material photo</p>";
                
                // Get the photo record
                $photo_stmt = $conn->prepare("
                    SELECT photo_id, photo_filename, photo_path 
                    FROM hr_supervisor_material_photo_records 
                    WHERE material_id = ? 
                    ORDER BY photo_id DESC 
                    LIMIT 1
                ");
                
                $photo_stmt->bind_param("i", $material_id);
                $photo_stmt->execute();
                $photo_result = $photo_stmt->get_result();
                
                if ($photo = $photo_result->fetch_assoc()) {
                    echo "<p class='success'>Retrieved photo record: ID = {$photo['photo_id']}, Path = {$photo['photo_path']}</p>";
                    
                    // Display the image using get_photo.php
                    echo "<h3>Test Viewing Image</h3>";
                    echo "<p>If you see an image below, the test was successful:</p>";
                    echo "<img src='get_photo.php?id={$photo['photo_id']}&type=material' alt='Test Image' style='border:1px solid #ccc;'>";
                    
                    // Add link to view_photos.php
                    echo "<p><a href='view_photos.php?material_id=$material_id'>View in Photo Viewer</a></p>";
                } else {
                    echo "<p class='error'>Failed to retrieve photo record</p>";
                }
            } else {
                echo "<p class='error'>Failed to process material photo</p>";
            }
        } else {
            echo "<p class='error'>Failed to create test material: " . $conn->error . "</p>";
        }
    } else {
        echo "<p class='error'>Failed to create test vendor: " . $conn->error . "</p>";
    }
} else {
    echo "<p class='error'>Failed to create test image</p>";
}

echo "</div>";

// Clean up (optional for testing)
echo "<div class='section'>";
echo "<h2>Test Complete</h2>";
echo "<p>The test has been completed. You can inspect the file system and database to verify the results.</p>";
echo "</div>";

echo "</body></html>";
?> 