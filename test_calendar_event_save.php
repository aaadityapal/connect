<?php
// Test file to verify database connection and calendar event storage

// Include database connection
require_once 'config.php';

// Function to output test results in a readable format
function outputResult($title, $success, $message = '') {
    echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid " . ($success ? "#4CAF50" : "#F44336") . "; border-radius: 5px;'>";
    echo "<h3 style='margin-top: 0; color: " . ($success ? "#4CAF50" : "#F44336") . ";'>" . ($success ? "✓" : "✗") . " $title</h3>";
    if (!empty($message)) {
        echo "<p>$message</p>";
    }
    echo "</div>";
}

// Start the test
echo "<h1>Calendar Event Save Test</h1>";

// Test database connection
try {
    // Check if connection is established
    if ($pdo instanceof PDO) {
        $pdo->query("SELECT 1"); // Test query
        outputResult("Database Connection", true, "Successfully connected to database");
    } else {
        outputResult("Database Connection", false, "Connection issue: PDO instance not available");
        exit;
    }
} catch (Exception $e) {
    outputResult("Database Connection", false, "Exception: " . $e->getMessage());
    exit;
}

// Verify tables exist
$tables = ["sv_calendar_events", "sv_event_vendors", "sv_vendor_materials", "sv_material_images", "sv_bill_images", "sv_vendor_labours", "sv_labour_wages", "sv_company_labours", "sv_company_wages"];
$allTablesExist = true;
$missingTables = [];

foreach ($tables as $table) {
    $result = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($result->rowCount() == 0) {
        $allTablesExist = false;
        $missingTables[] = $table;
    }
}

if ($allTablesExist) {
    outputResult("Database Tables Check", true, "All required tables exist");
} else {
    outputResult("Database Tables Check", false, "Missing tables: " . implode(", ", $missingTables) . ". Please run the calendar_event_schema.sql file or use run_schema.php.");
}

// Test data insertion (mock data)
try {
    // Start transaction
    $pdo->beginTransaction();
    
    echo "<h2>Testing Data Insertion</h2>";
    
    // Insert test event
    $event_title = "Test Event " . date('Y-m-d H:i:s');
    $event_date = date('Y-m-d');
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1; // Use session user ID if available
    
    $sql = "INSERT INTO sv_calendar_events (title, event_date, created_by) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed");
    }
    
    $stmt->execute([$event_title, $event_date, $user_id]);
    
    $event_id = $pdo->lastInsertId();
    outputResult("Event Creation", true, "Successfully created test event with ID: $event_id");
    
    // Insert test vendor
    $vendor_type = "Test Vendor Type";
    $vendor_name = "Test Vendor";
    $contact_number = "1234567890";
    $sequence_number = 1;
    
    $sql = "INSERT INTO sv_event_vendors (event_id, vendor_type, vendor_name, contact_number, sequence_number) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed");
    }
    
    $stmt->execute([$event_id, $vendor_type, $vendor_name, $contact_number, $sequence_number]);
    
    $vendor_id = $pdo->lastInsertId();
    outputResult("Vendor Creation", true, "Successfully created test vendor with ID: $vendor_id");
    
    // Insert test material
    $remarks = "Test Material Remarks";
    $amount = 1000.50;
    
    $sql = "INSERT INTO sv_vendor_materials (vendor_id, remarks, amount) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed");
    }
    
    $stmt->execute([$vendor_id, $remarks, $amount]);
    
    $material_id = $pdo->lastInsertId();
    outputResult("Material Creation", true, "Successfully created test material with ID: $material_id");
    
    // Insert test labour
    $labour_name = "Test Labour";
    $labour_contact = "9876543210";
    $morning_attendance = "present";
    $evening_attendance = "present";
    
    $sql = "INSERT INTO sv_vendor_labours (vendor_id, labour_name, contact_number, sequence_number, morning_attendance, evening_attendance) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed");
    }
    
    $stmt->execute([$vendor_id, $labour_name, $labour_contact, $sequence_number, $morning_attendance, $evening_attendance]);
    
    $labour_id = $pdo->lastInsertId();
    outputResult("Labour Creation", true, "Successfully created test labour with ID: $labour_id");
    
    // Now check if uploads directory exists and can be created
    $upload_dir = 'uploads/calendar_events/';
    $material_images_dir = $upload_dir . 'material_images/';
    $bill_images_dir = $upload_dir . 'bill_images/';
    
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            outputResult("Uploads Directory", false, "Failed to create upload directory. Please check permissions.");
        } else {
            outputResult("Uploads Directory", true, "Successfully created upload directory");
        }
    } else {
        outputResult("Uploads Directory", true, "Upload directory already exists");
    }
    
    if (!file_exists($material_images_dir)) {
        if (!mkdir($material_images_dir, 0777, true)) {
            outputResult("Material Images Directory", false, "Failed to create material images directory. Please check permissions.");
        } else {
            outputResult("Material Images Directory", true, "Successfully created material images directory");
        }
    } else {
        outputResult("Material Images Directory", true, "Material images directory already exists");
    }
    
    if (!file_exists($bill_images_dir)) {
        if (!mkdir($bill_images_dir, 0777, true)) {
            outputResult("Bill Images Directory", false, "Failed to create bill images directory. Please check permissions.");
        } else {
            outputResult("Bill Images Directory", true, "Successfully created bill images directory");
        }
    } else {
        outputResult("Bill Images Directory", true, "Bill images directory already exists");
    }
    
    // Clean up (rollback test data)
    $pdo->rollBack();
    outputResult("Test Cleanup", true, "Successfully rolled back test data");
    
    echo "<h2>Test Summary</h2>";
    echo "<p>All database operations work correctly. The data can be stored in the database.</p>";
    echo "<p>If your form data is not saving, check the following:</p>";
    echo "<ol>";
    echo "<li>Ensure your form has the correct field names matching the PHP backend script</li>";
    echo "<li>Verify the form action is pointing to <code>backend/save_calendar_event.php</code></li>";
    echo "<li>Make sure <code>enctype=\"multipart/form-data\"</code> is set on your form for file uploads</li>";
    echo "<li>Check that there are no JavaScript errors in your browser console</li>";
    echo "<li>Verify that your user session has a valid user_id</li>";
    echo "</ol>";
    
    // Check session data
    echo "<h3>Current Session Data:</h3>";
    if (isset($_SESSION['user_id'])) {
        echo "<p style='color: green;'>user_id is set to: " . $_SESSION['user_id'] . "</p>";
    } else {
        echo "<p style='color: red;'>user_id is NOT set in session. This will cause the backend to reject the request.</p>";
    }
    
} catch (Exception $e) {
    // Something went wrong, show error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    outputResult("Database Operations", false, "Error: " . $e->getMessage());
}

// No need to close PDO connection
?>

<style>
    body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        margin: 20px;
        max-width: 900px;
    }
    h1 {
        border-bottom: 2px solid #2196F3;
        padding-bottom: 10px;
        color: #0D47A1;
    }
    h2 {
        margin-top: 30px;
        color: #1976D2;
        border-left: 5px solid #2196F3;
        padding-left: 10px;
    }
    h3 {
        color: #1976D2;
    }
    code {
        background-color: #f5f5f5;
        padding: 2px 5px;
        border-radius: 3px;
        font-family: monospace;
    }
    ol, ul {
        margin-left: 20px;
    }
    li {
        margin: 8px 0;
    }
</style> 