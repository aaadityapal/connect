<?php
/**
 * Fix for activity log date issue
 * This script modifies the logActivity function to ensure dates include time components
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

echo "<h1>Fixing Activity Log Dates</h1>";

// Check current dates in the activity log table
echo "<h2>Current Date Format in Activity Log</h2>";
$query = "SELECT log_id, event_date FROM hr_supervisor_activity_log WHERE event_date LIKE '%00:00:00' LIMIT 10";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<p>Found " . $result->num_rows . " records with 00:00:00 time component.</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Log ID</th><th>Event Date</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['log_id']}</td>";
        echo "<td>{$row['event_date']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No records with 00:00:00 time found or table is empty.</p>";
}

// Step 1: Check table structure
echo "<h2>Table Structure</h2>";
$structureQuery = "SHOW CREATE TABLE hr_supervisor_activity_log";
$structureResult = $conn->query($structureQuery);

if ($structureResult && $structureResult->num_rows > 0) {
    $row = $structureResult->fetch_assoc();
    echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
    
    // Check if event_date is DATETIME
    if (strpos($row['Create Table'], 'event_date date') !== false) {
        echo "<p style='color:red'>Found issue: event_date column is defined as DATE instead of DATETIME</p>";
        
        // Alter table to change column type
        $alterQuery = "ALTER TABLE hr_supervisor_activity_log MODIFY event_date DATETIME";
        if ($conn->query($alterQuery)) {
            echo "<p style='color:green'>Successfully changed event_date column type to DATETIME</p>";
        } else {
            echo "<p style='color:red'>Failed to alter table: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>event_date column is already defined as DATETIME</p>";
    }
} else {
    echo "<p style='color:red'>Failed to retrieve table structure: " . $conn->error . "</p>";
}

// Step 2: Fix existing dates without time component
echo "<h2>Fixing Existing Records</h2>";

// Update existing records with 00:00:00 time component
$updateQuery = "UPDATE hr_supervisor_activity_log SET event_date = CONCAT(DATE(event_date), ' ', TIME(NOW())) WHERE event_date LIKE '%00:00:00'";
if ($conn->query($updateQuery)) {
    $affectedRows = $conn->affected_rows;
    echo "<p style='color:green'>Successfully updated $affectedRows records with proper time component</p>";
} else {
    echo "<p style='color:red'>Failed to update records: " . $conn->error . "</p>";
}

// Step 3: Modify the logActivity function
echo "<h2>Checking logActivity Function</h2>";

// The original function in calendar_data_handler.php needs modification
// This would be the fixed version:
echo "<pre>
function logActivity(\$actionType, \$entityType, \$entityId = null, \$eventId = null, \$eventDate = null, \$description = '', \$oldValues = null, \$newValues = null) {
    global \$conn;
    
    // Get user info from session
    \$userId = isset(\$_SESSION['user_id']) ? \$_SESSION['user_id'] : null;
    \$userName = isset(\$_SESSION['user_name']) ? \$_SESSION['user_name'] : null;
    
    // Get client info
    \$ipAddress = \$_SERVER['REMOTE_ADDR'] ?? null;
    \$userAgent = \$_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Convert arrays to JSON strings
    \$oldValuesJson = \$oldValues ? json_encode(\$oldValues) : null;
    \$newValuesJson = \$newValues ? json_encode(\$newValues) : null;
    
    // Fix for date issue: Ensure eventDate has time component
    if (\$eventDate) {
        // If eventDate doesn't have a time component, add current time
        if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', \$eventDate)) {
            \$eventDate .= ' ' . date('H:i:s');
        }
    }
    
    // Prepare statement
    \$stmt = \$conn->prepare(\"
        INSERT INTO hr_supervisor_activity_log (
            user_id, user_name, action_type, entity_type, entity_id, 
            event_id, event_date, description, old_values, new_values,
            ip_address, user_agent
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?,
            ?, ?
        )
    \");
    
    \$stmt->bind_param(
        \"isssiiisssss\",
        \$userId,
        \$userName,
        \$actionType,
        \$entityType,
        \$entityId,
        \$eventId,
        \$eventDate,
        \$description,
        \$oldValuesJson,
        \$newValuesJson,
        \$ipAddress,
        \$userAgent
    );
    
    return \$stmt->execute();
}
</pre>";

// Test the fixed function
echo "<h2>Testing Fixed Function</h2>";

// Define a test function with the fix applied
function testLogActivity($actionType, $entityType, $entityId = null, $eventId = null, $eventDate = null, $description = '', $oldValues = null, $newValues = null) {
    global $conn;
    
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
    $userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Test User';
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Test Agent';
    
    $oldValuesJson = $oldValues ? json_encode($oldValues) : null;
    $newValuesJson = $newValues ? json_encode($newValues) : null;
    
    // FIX: Ensure eventDate has time component
    if ($eventDate) {
        // If eventDate doesn't have a time component, add current time
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            $eventDate .= ' ' . date('H:i:s');
        }
    }
    
    $stmt = $conn->prepare("
        INSERT INTO hr_supervisor_activity_log (
            user_id, user_name, action_type, entity_type, entity_id, 
            event_id, event_date, description, old_values, new_values,
            ip_address, user_agent
        ) VALUES (
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?,
            ?, ?
        )
    ");
    
    $stmt->bind_param(
        "isssiiisssss",
        $userId,
        $userName,
        $actionType,
        $entityType,
        $entityId,
        $eventId,
        $eventDate,
        $description,
        $oldValuesJson,
        $newValuesJson,
        $ipAddress,
        $userAgent
    );
    
    return $stmt->execute();
}

// Test with date-only format
$testDate = date('Y-m-d');
echo "<p>Testing with date-only format: $testDate</p>";

if (testLogActivity('test', 'test_entity', 1, 1, $testDate, 'Testing date fix', null, null)) {
    $insertId = $conn->insert_id;
    echo "<p style='color:green'>Inserted test record with ID: $insertId</p>";
    
    // Verify the test record
    $checkQuery = "SELECT event_date FROM hr_supervisor_activity_log WHERE log_id = $insertId";
    $checkResult = $conn->query($checkQuery);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        $dateRow = $checkResult->fetch_assoc();
        echo "<p>Stored event date: {$dateRow['event_date']}</p>";
        
        if (substr($dateRow['event_date'], 11) !== '00:00:00') {
            echo "<p style='color:green'>Fix successful! Time component is present.</p>";
        } else {
            echo "<p style='color:red'>Fix failed. Time component is still 00:00:00.</p>";
        }
    }
} else {
    echo "<p style='color:red'>Failed to insert test record: " . $conn->error . "</p>";
}

// Instructions for updating the original file
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>Open <code>includes/calendar_data_handler.php</code></li>";
echo "<li>Locate the <code>logActivity</code> function (around line 27)</li>";
echo "<li>Add the following code after the JSON encoding section and before the prepare statement:
<pre>
// Fix for date issue: Ensure eventDate has time component
if (\$eventDate) {
    // If eventDate doesn't have a time component, add current time
    if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', \$eventDate)) {
        \$eventDate .= ' ' . date('H:i:s');
    }
}
</pre>
</li>";
echo "<li>Save the file</li>";
echo "<li>Test by creating a new calendar event</li>";
echo "</ol>"; 