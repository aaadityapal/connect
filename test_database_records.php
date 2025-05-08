<?php
// Test file to check database record issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if needed
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set test user data
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Test User';

// Database connection
require_once 'includes/config/db_connect.php';

echo "<h1>Database Records Test</h1>";

// Function to test activity log date format
function test_activity_log() {
    global $conn;
    
    echo "<h2>Testing Activity Log Date Format</h2>";
    
    // Check for existing records to see date format
    $query = "SELECT * FROM hr_supervisor_activity_log ORDER BY log_id DESC LIMIT 5";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        echo "<h3>Current Activity Log Records:</h3>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>Log ID</th><th>User ID</th><th>Event Date</th><th>Activity Type</th><th>Description</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['log_id']}</td>";
            echo "<td>{$row['user_id']}</td>";
            echo "<td>{$row['event_date']} <span style='color:red;'>" . 
                 (preg_match('/00:00:00$/', $row['event_date']) ? '(00:00:00 issue)' : '') . 
                 "</span></td>";
            echo "<td>{$row['activity_type']}</td>";
            echo "<td>{$row['description']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No activity log records found.</p>";
    }
    
    // Test inserting a new record with current time
    $userId = $_SESSION['user_id'];
    $eventDate = date('Y-m-d H:i:s'); // Current date and time
    $activityType = 'TEST';
    $description = 'Test activity with proper timestamp: ' . $eventDate;
    
    // Check the structure of the table
    echo "<h3>Activity Log Table Structure:</h3>";
    $structureQuery = "SHOW CREATE TABLE hr_supervisor_activity_log";
    $structureResult = $conn->query($structureQuery);
    if ($structureResult && $structureResult->num_rows > 0) {
        $row = $structureResult->fetch_assoc();
        echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
    }
    
    // Insert test record
    $insertQuery = "INSERT INTO hr_supervisor_activity_log (user_id, event_date, activity_type, description) 
                    VALUES (?, ?, ?, ?)";
    
    try {
        $stmt = $conn->prepare($insertQuery);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("isss", $userId, $eventDate, $activityType, $description);
        $success = $stmt->execute();
        
        if ($success) {
            $newId = $conn->insert_id;
            echo "<p style='color:green;'>Successfully inserted test record with ID: $newId</p>";
            
            // Verify the inserted record
            $verifyQuery = "SELECT * FROM hr_supervisor_activity_log WHERE log_id = ?";
            $verifyStmt = $conn->prepare($verifyQuery);
            $verifyStmt->bind_param("i", $newId);
            $verifyStmt->execute();
            $verifyResult = $verifyStmt->get_result();
            
            if ($verifyResult && $verifyResult->num_rows > 0) {
                $insertedRow = $verifyResult->fetch_assoc();
                echo "<h3>Inserted Record:</h3>";
                echo "<table border='1' cellpadding='5' cellspacing='0'>";
                echo "<tr><th>Log ID</th><th>User ID</th><th>Event Date</th><th>Activity Type</th><th>Description</th></tr>";
                echo "<tr>";
                echo "<td>{$insertedRow['log_id']}</td>";
                echo "<td>{$insertedRow['user_id']}</td>";
                echo "<td>{$insertedRow['event_date']} <span style='color:red;'>" . 
                     (preg_match('/00:00:00$/', $insertedRow['event_date']) ? '(00:00:00 issue still exists)' : '(correct time format)') . 
                     "</span></td>";
                echo "<td>{$insertedRow['activity_type']}</td>";
                echo "<td>{$insertedRow['description']}</td>";
                echo "</tr>";
                echo "</table>";
                
                // Compare the inserted time with the original time
                echo "<p>Original time: <strong>$eventDate</strong></p>";
                echo "<p>Stored time: <strong>{$insertedRow['event_date']}</strong></p>";
                
                if ($eventDate != $insertedRow['event_date']) {
                    echo "<p style='color:red;'>Warning: Times do not match!</p>";
                }
            }
            
            $verifyStmt->close();
        } else {
            echo "<p style='color:red;'>Failed to insert test record: " . $stmt->error . "</p>";
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    }
}

// Function to test material photo records
function test_material_photo_records() {
    global $conn;
    
    echo "<h2>Testing Material Photo Records</h2>";
    
    // Check table structure
    echo "<h3>Material Photo Records Table Structure:</h3>";
    $structureQuery = "SHOW CREATE TABLE hr_supervisor_material_photo_records";
    $structureResult = $conn->query($structureQuery);
    if ($structureResult && $structureResult->num_rows > 0) {
        $row = $structureResult->fetch_assoc();
        echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
    } else {
        echo "<p style='color:red;'>Could not retrieve table structure. Error: " . $conn->error . "</p>";
    }
    
    // Check for existing records
    $query = "SELECT * FROM hr_supervisor_material_photo_records ORDER BY photo_id DESC LIMIT 5";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        echo "<h3>Current Material Photo Records:</h3>";
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>ID</th><th>Material ID</th><th>Type</th><th>Filename</th><th>Path</th><th>Latitude</th><th>Longitude</th><th>Accuracy</th><th>Address</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['photo_id']}</td>";
            echo "<td>{$row['material_id']}</td>";
            echo "<td>{$row['photo_type']}</td>";
            echo "<td>{$row['photo_filename']}</td>";
            echo "<td>{$row['photo_path']}</td>";
            echo "<td>" . (empty($row['latitude']) ? "<span style='color:red;'>MISSING</span>" : $row['latitude']) . "</td>";
            echo "<td>" . (empty($row['longitude']) ? "<span style='color:red;'>MISSING</span>" : $row['longitude']) . "</td>";
            echo "<td>" . (empty($row['location_accuracy']) ? "<span style='color:red;'>MISSING</span>" : $row['location_accuracy']) . "</td>";
            echo "<td>" . (empty($row['location_address']) ? "<span style='color:red;'>MISSING</span>" : substr($row['location_address'], 0, 50) . '...') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No material photo records found or error: " . $conn->error . "</p>";
    }
    
    // Step 1: First, check for existing material records we can use
    echo "<h3>Checking for existing material records:</h3>";
    $materialId = null;
    $findMaterialQuery = "SELECT mtr.material_id 
                          FROM hr_supervisor_material_transaction_records mtr
                          JOIN hr_supervisor_vendor_registry vr ON mtr.vendor_id = vr.vendor_id
                          LIMIT 1";
    $findResult = $conn->query($findMaterialQuery);
    if ($findResult && $findResult->num_rows > 0) {
        $materialId = $findResult->fetch_assoc()['material_id'];
        echo "<p style='color:green;'>Found existing material ID: $materialId (will use this for test)</p>";
    } else {
        echo "<p>No existing material records found with valid vendor ID. Will create new records.</p>";
        
        // Step 2: Create a site and event first (required for vendor)
        echo "<h3>Creating site and event records:</h3>";
        $siteName = "Test Site " . time();
        
        // 2.1 Create site
        $siteQuery = "INSERT INTO hr_supervisor_construction_sites (site_code, site_name, is_custom, created_by) 
                     VALUES (?, ?, 1, ?)";
        try {
            $siteStmt = $conn->prepare($siteQuery);
            if (!$siteStmt) {
                throw new Exception("Site prepare failed: " . $conn->error);
            }
            
            $siteCode = 'test-' . time();
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
            
            $siteStmt->bind_param("ssi", $siteCode, $siteName, $userId);
            $siteSuccess = $siteStmt->execute();
            
            if ($siteSuccess) {
                $siteId = $conn->insert_id;
                echo "<p style='color:green;'>Successfully created site record with ID: $siteId</p>";
            } else {
                throw new Exception("Failed to create site record: " . $siteStmt->error);
            }
            
            $siteStmt->close();
            
            // 2.2 Create event
            $eventDate = date('Y-m-d');
            $day = date('j');
            $month = date('n');
            $year = date('Y');
            
            $eventQuery = "INSERT INTO hr_supervisor_calendar_site_events 
                          (site_id, event_date, event_day, event_month, event_year, created_by) 
                          VALUES (?, ?, ?, ?, ?, ?)";
            
            $eventStmt = $conn->prepare($eventQuery);
            if (!$eventStmt) {
                throw new Exception("Event prepare failed: " . $conn->error);
            }
            
            $eventStmt->bind_param("isiiii", $siteId, $eventDate, $day, $month, $year, $userId);
            $eventSuccess = $eventStmt->execute();
            
            if ($eventSuccess) {
                $eventId = $conn->insert_id;
                echo "<p style='color:green;'>Successfully created event record with ID: $eventId</p>";
            } else {
                throw new Exception("Failed to create event record: " . $eventStmt->error);
            }
            
            $eventStmt->close();
            
            // Step 3: Create a vendor
            echo "<h3>Creating vendor record:</h3>";
            $vendorType = 'supplier';
            $vendorName = 'Test Vendor ' . time();
            $vendorContact = '1234567890';
            $vendorPosition = 0;
            
            $vendorQuery = "INSERT INTO hr_supervisor_vendor_registry 
                           (event_id, vendor_type, vendor_name, vendor_contact, is_custom_type, vendor_position) 
                           VALUES (?, ?, ?, ?, 0, ?)";
                           
            $vendorStmt = $conn->prepare($vendorQuery);
            if (!$vendorStmt) {
                throw new Exception("Vendor prepare failed: " . $conn->error);
            }
            
            $vendorStmt->bind_param("isssi", $eventId, $vendorType, $vendorName, $vendorContact, $vendorPosition);
            $vendorSuccess = $vendorStmt->execute();
            
            if ($vendorSuccess) {
                $vendorId = $conn->insert_id;
                echo "<p style='color:green;'>Successfully created vendor record with ID: $vendorId</p>";
            } else {
                throw new Exception("Failed to create vendor record: " . $vendorStmt->error);
            }
            
            $vendorStmt->close();
            
            // Step 4: Create a material transaction record
            echo "<h3>Creating material transaction record:</h3>";
            $materialRemark = "Test material record for photo test";
            $materialAmount = 100.00;
            $hasMaterialPhoto = 1;
            $hasBillPhoto = 0;
            
            $materialQuery = "INSERT INTO hr_supervisor_material_transaction_records 
                            (vendor_id, material_remark, material_amount, has_material_photo, has_bill_photo) 
                            VALUES (?, ?, ?, ?, ?)";
                            
            $materialStmt = $conn->prepare($materialQuery);
            if (!$materialStmt) {
                throw new Exception("Material prepare failed: " . $conn->error);
            }
            
            $materialStmt->bind_param("isdii", $vendorId, $materialRemark, $materialAmount, $hasMaterialPhoto, $hasBillPhoto);
            $materialSuccess = $materialStmt->execute();
            
            if ($materialSuccess) {
                $materialId = $conn->insert_id;
                echo "<p style='color:green;'>Successfully created material record with ID: $materialId</p>";
            } else {
                throw new Exception("Failed to create material record: " . $materialStmt->error);
            }
            
            $materialStmt->close();
            
        } catch (Exception $e) {
            echo "<p style='color:red;'>Error in setup: " . $e->getMessage() . "</p>";
            return; // Exit if setup fails
        }
    }
    
    // Now test inserting a photo record with location data using the valid material ID
    if ($materialId) {
        echo "<h3>Now inserting photo record with material ID: $materialId</h3>";
        $photoType = 'material'; // or 'bill'
        $filename = 'test_image_' . time() . '.jpg';
        $photoPath = "uploads/materials/" . date('Y/m/d/') . $filename;
        $latitude = 28.6139;
        $longitude = 77.2090;
        $accuracy = 10.5;
        $address = 'Test Address, New Delhi, India 110001';
        $locationTimestamp = date('Y-m-d H:i:s');
        
        // Insert test record
        $insertQuery = "INSERT INTO hr_supervisor_material_photo_records 
                        (material_id, photo_type, photo_filename, photo_path, latitude, longitude, location_accuracy, location_address, location_timestamp) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $conn->prepare($insertQuery);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("isssdddss", $materialId, $photoType, $filename, $photoPath, $latitude, $longitude, $accuracy, $address, $locationTimestamp);
            $success = $stmt->execute();
            
            if ($success) {
                $newId = $conn->insert_id;
                echo "<p style='color:green;'>Successfully inserted test photo record with ID: $newId</p>";
                
                // Verify the inserted record
                $verifyQuery = "SELECT * FROM hr_supervisor_material_photo_records WHERE photo_id = ?";
                $verifyStmt = $conn->prepare($verifyQuery);
                $verifyStmt->bind_param("i", $newId);
                $verifyStmt->execute();
                $verifyResult = $verifyStmt->get_result();
                
                if ($verifyResult && $verifyResult->num_rows > 0) {
                    $insertedRow = $verifyResult->fetch_assoc();
                    echo "<h3>Inserted Photo Record:</h3>";
                    echo "<table border='1' cellpadding='5' cellspacing='0'>";
                    echo "<tr><th>ID</th><th>Material ID</th><th>Type</th><th>Filename</th><th>Path</th><th>Latitude</th><th>Longitude</th><th>Accuracy</th><th>Address</th></tr>";
                    echo "<tr>";
                    echo "<td>{$insertedRow['photo_id']}</td>";
                    echo "<td>{$insertedRow['material_id']}</td>";
                    echo "<td>{$insertedRow['photo_type']}</td>";
                    echo "<td>{$insertedRow['photo_filename']}</td>";
                    echo "<td>{$insertedRow['photo_path']}</td>";
                    echo "<td>" . (empty($insertedRow['latitude']) ? "<span style='color:red;'>MISSING</span>" : $insertedRow['latitude']) . "</td>";
                    echo "<td>" . (empty($insertedRow['longitude']) ? "<span style='color:red;'>MISSING</span>" : $insertedRow['longitude']) . "</td>";
                    echo "<td>" . (empty($insertedRow['location_accuracy']) ? "<span style='color:red;'>MISSING</span>" : $insertedRow['location_accuracy']) . "</td>";
                    echo "<td>" . (empty($insertedRow['location_address']) ? "<span style='color:red;'>MISSING</span>" : $insertedRow['location_address']) . "</td>";
                    echo "</tr>";
                    echo "</table>";
                    
                    // Check if location data was saved correctly
                    if ($insertedRow['latitude'] != $latitude || $insertedRow['longitude'] != $longitude || 
                        $insertedRow['location_accuracy'] != $accuracy || $insertedRow['location_address'] != $address) {
                        echo "<p style='color:red;'>Warning: Location data was not saved correctly!</p>";
                        echo "<p>Expected: Lat=$latitude, Lng=$longitude, Accuracy=$accuracy, Address='$address'</p>";
                        echo "<p>Actual: Lat={$insertedRow['latitude']}, Lng={$insertedRow['longitude']}, 
                                  Accuracy={$insertedRow['location_accuracy']}, Address='{$insertedRow['location_address']}'</p>";
                    } else {
                        echo "<p style='color:green;'>Location data was saved correctly!</p>";
                    }
                }
                
                $verifyStmt->close();
            } else {
                echo "<p style='color:red;'>Failed to insert test photo record: " . $stmt->error . "</p>";
            }
            
            $stmt->close();
        } catch (Exception $e) {
            echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color:red;'>No valid material ID available. Cannot insert photo record.</p>";
    }
}

// Run tests
echo "<div style='max-width: 1200px; margin: 0 auto; font-family: Arial, sans-serif;'>";
test_activity_log();
echo "<hr>";
test_material_photo_records();
echo "</div>"; 