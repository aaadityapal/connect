<?php
/**
 * Simple test script for material photo upload directories
 * This script tests only the directory creation and database connection
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
    <title>Simple Upload Test</title>
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
    <h1>Simple Upload Test</h1>";

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

// Test creating a simple text file
$test_file_path = $target_dir . '/test_file.txt';
$test_content = "This is a test file created on " . date('Y-m-d H:i:s');

if (file_put_contents($test_file_path, $test_content)) {
    echo "<p class='success'>Created test text file: $test_file_path</p>";
} else {
    echo "<p class='error'>Failed to create test text file. This might indicate a permissions issue.</p>";
}

echo "</div>";

// Test database connection
echo "<div class='section'>";
echo "<h2>Testing Database Connection</h2>";

try {
    // Simple query to verify database connection
    $test_query = "SHOW TABLES";
    $test_result = $conn->query($test_query);
    
    if ($test_result) {
        $tables = [];
        while ($row = $test_result->fetch_array()) {
            $tables[] = $row[0];
        }
        
        echo "<p class='success'>Database connection successful. Found " . count($tables) . " tables.</p>";
        
        // Test vendor table specifically
        if (in_array('hr_supervisor_vendor_registry', $tables)) {
            echo "<p class='success'>Vendor table exists.</p>";
            
            // First check if an event with ID 1 exists
            $check_event = $conn->prepare("SELECT event_id FROM hr_supervisor_calendar_site_events WHERE event_id = 1");
            $check_event->execute();
            $event_result = $check_event->get_result();
            
            $event_exists = $event_result->num_rows > 0;
            
            if (!$event_exists) {
                echo "<p class='warning'>No event found with ID 1. Creating a test event record...</p>";
                
                // First, we need a site record
                $site_stmt = $conn->prepare("
                    SELECT site_id FROM hr_supervisor_construction_sites LIMIT 1
                ");
                $site_stmt->execute();
                $site_result = $site_stmt->get_result();
                
                if ($site_result->num_rows > 0) {
                    $site_row = $site_result->fetch_assoc();
                    $site_id = $site_row['site_id'];
                    
                    // Create a test event
                    $event_date = date('Y-m-d');
                    $event_day = date('d');
                    $event_month = date('m');
                    $event_year = date('Y');
                    
                    $create_event = $conn->prepare("
                        INSERT INTO hr_supervisor_calendar_site_events 
                        (event_id, site_id, event_date, event_day, event_month, event_year, created_by) 
                        VALUES (1, ?, ?, ?, ?, ?, 1)
                        ON DUPLICATE KEY UPDATE site_id = VALUES(site_id)
                    ");
                    
                    $create_event->bind_param("issii", $site_id, $event_date, $event_day, $event_month, $event_year);
                    
                    if ($create_event->execute()) {
                        echo "<p class='success'>Created test event with ID: 1</p>";
                        $event_exists = true;
                    } else {
                        echo "<p class='error'>Failed to create test event: " . $conn->error . "</p>";
                    }
                } else {
                    echo "<p class='error'>No site records found. Cannot create test event.</p>";
                    
                    // Try to create a site
                    $create_site = $conn->prepare("
                        INSERT INTO hr_supervisor_construction_sites 
                        (site_code, site_name, site_address, is_custom, created_by) 
                        VALUES ('TEST001', 'Test Site', 'Test Address', 1, 1)
                    ");
                    
                    if ($create_site->execute()) {
                        $site_id = $conn->insert_id;
                        echo "<p class='success'>Created test site with ID: $site_id</p>";
                        
                        // Now create the event
                        $event_date = date('Y-m-d');
                        $event_day = date('d');
                        $event_month = date('m');
                        $event_year = date('Y');
                        
                        $create_event = $conn->prepare("
                            INSERT INTO hr_supervisor_calendar_site_events 
                            (event_id, site_id, event_date, event_day, event_month, event_year, created_by) 
                            VALUES (1, ?, ?, ?, ?, ?, 1)
                            ON DUPLICATE KEY UPDATE site_id = VALUES(site_id)
                        ");
                        
                        $create_event->bind_param("issii", $site_id, $event_date, $event_day, $event_month, $event_year);
                        
                        if ($create_event->execute()) {
                            echo "<p class='success'>Created test event with ID: 1</p>";
                            $event_exists = true;
                        } else {
                            echo "<p class='error'>Failed to create test event: " . $conn->error . "</p>";
                        }
                    } else {
                        echo "<p class='error'>Failed to create test site: " . $conn->error . "</p>";
                    }
                }
            } else {
                echo "<p class='success'>Event with ID 1 already exists.</p>";
            }
            
            // Only proceed with vendor creation if we have a valid event
            if ($event_exists) {
                // Try a simple insert for testing
                $test_vendor_name = "Test Vendor " . time();
                $vendor_stmt = $conn->prepare("
                    INSERT INTO hr_supervisor_vendor_registry 
                    (event_id, vendor_name, vendor_type, vendor_contact, vendor_position) 
                    VALUES (1, ?, 'material', '1234567890', 1)
                ");
                
                $vendor_stmt->bind_param("s", $test_vendor_name);
                
                if ($vendor_stmt->execute()) {
                    $vendor_id = $conn->insert_id;
                    echo "<p class='success'>Created test vendor with ID: $vendor_id</p>";
                    
                    // Test material table
                    $material_stmt = $conn->prepare("
                        INSERT INTO hr_supervisor_material_transaction_records 
                        (vendor_id, material_remark, material_amount, has_material_photo, has_bill_photo) 
                        VALUES (?, 'Test Material', 1000.00, 1, 0)
                    ");
                    
                    $material_stmt->bind_param("i", $vendor_id);
                    
                    if ($material_stmt->execute()) {
                        $material_id = $conn->insert_id;
                        echo "<p class='success'>Created test material with ID: $material_id</p>";
                        
                        // Create a dummy photo record
                        $dummy_filename = "dummy_file_" . time() . ".jpg";
                        $dummy_path = "uploads/materials/" . date('Y/m/d/') . $dummy_filename;
                        
                        $photo_stmt = $conn->prepare("
                            INSERT INTO hr_supervisor_material_photo_records 
                            (material_id, photo_type, photo_filename, photo_path, latitude, longitude, location_accuracy, location_address, location_timestamp) 
                            VALUES (?, 'material', ?, ?, 28.6139, 77.2090, 10.0, 'Test Address', NOW())
                        ");
                        
                        $photo_stmt->bind_param("iss", $material_id, $dummy_filename, $dummy_path);
                        
                        if ($photo_stmt->execute()) {
                            $photo_id = $conn->insert_id;
                            echo "<p class='success'>Created test photo record with ID: $photo_id</p>";
                            echo "<p>Photo path in database: $dummy_path</p>";
                            
                            // Add link to view_photos.php
                            echo "<p><a href='view_photos.php?material_id=$material_id'>View in Photo Viewer</a></p>";
                        } else {
                            echo "<p class='error'>Failed to create photo record: " . $conn->error . "</p>";
                        }
                    } else {
                        echo "<p class='error'>Failed to create test material: " . $conn->error . "</p>";
                    }
                } else {
                    echo "<p class='error'>Failed to create test vendor: " . $conn->error . "</p>";
                }
            } else {
                echo "<p class='error'>Cannot create vendor without a valid event record.</p>";
            }
        } else {
            echo "<p class='error'>Vendor table does not exist.</p>";
        }
    } else {
        echo "<p class='error'>Database query failed: " . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

echo "</div>";

// Clean up (optional for testing)
echo "<div class='section'>";
echo "<h2>Test Complete</h2>";
echo "<p>The simplified test has been completed.</p>";
echo "</div>";

echo "</body></html>";
?> 