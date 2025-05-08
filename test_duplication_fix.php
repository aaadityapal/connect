<?php
// Test file to verify duplication fix in calendar_data_handler.php

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set a test user in session for logging
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Test User';

// Make sure to include db_connect.php first to ensure database connection is available
require_once 'includes/config/db_connect.php';

// Include the calendar data handler
require_once 'includes/calendar_data_handler.php';

echo "<h1>Testing Duplication Fix</h1>";

// Test Data
$testEventData = [
    'siteName' => 'duplication-test-site',
    'day' => 10,
    'month' => 5,
    'year' => 2025,
    'vendors' => [
        [
            'type' => 'supplier',
            'name' => 'Test Vendor 1',
            'contact' => '1234567890',
            'labourers' => [
                [
                    'name' => 'Test Labourer 1',
                    'contact' => '1111111111',
                    'attendance' => [
                        'morning' => 'present',
                        'evening' => 'present'
                    ],
                    'wages' => [
                        'perDay' => 500
                    ]
                ]
            ]
        ]
    ]
];

echo "<h2>Step 1: Create Initial Calendar Event</h2>";
try {
    $result1 = saveCalendarData($testEventData);
    
    // Add debugging information
    echo "<pre>";
    // Check if we can get the event from the database
    $eventId = $result1['data']['eventId'];
    echo "Event ID from saveCalendarData: $eventId\n";
    
    // Check if the event is recognized as existing
    $conn = $GLOBALS['conn'];
    $isExistingEvent = $conn->query("SELECT COUNT(*) as count FROM hr_supervisor_vendor_registry WHERE event_id = $eventId")->fetch_assoc()['count'] > 0;
    echo "Is recognized as existing event: " . ($isExistingEvent ? "Yes" : "No") . "\n";
    
    // Print the event record
    $eventRecord = $conn->query("SELECT * FROM hr_supervisor_calendar_site_events WHERE event_id = $eventId")->fetch_assoc();
    echo "Event Record:\n";
    print_r($eventRecord);
    echo "</pre>";
    
    print_r($result1);
    echo "</pre>";
    
    if ($result1['status'] === 'success') {
        echo "<p style='color:green'>✅ Initial event created successfully!</p>";
        $eventId = $result1['data']['eventId'];
        
        // Query to count vendors for this event
        $conn = $GLOBALS['conn'];
        $vendorCount = $conn->query("SELECT COUNT(*) as count FROM hr_supervisor_vendor_registry WHERE event_id = $eventId")->fetch_assoc()['count'];
        echo "<p>Number of vendors in database: <strong>$vendorCount</strong></p>";
    } else {
        echo "<p style='color:red'>❌ Test failed: " . $result1['message'] . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Test failed with exception: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>Step 2: Update Same Calendar Event (should replace, not duplicate)</h2>";
// Modify the data slightly for the second save
$testEventData['vendors'][0]['labourers'][0]['name'] = 'Test Labourer 1 (Updated)';
$testEventData['vendors'][0]['labourers'][0]['wages']['perDay'] = 600;

try {
    $result2 = saveCalendarData($testEventData);
    
    // Add debugging information
    echo "<pre>";
    // Check if we can get the event from the database
    $eventId = $result2['data']['eventId'];
    echo "Event ID from saveCalendarData: $eventId\n";
    
    // Check if the event is recognized as existing
    $conn = $GLOBALS['conn'];
    $isExistingEvent = $conn->query("SELECT COUNT(*) as count FROM hr_supervisor_vendor_registry WHERE event_id = $eventId")->fetch_assoc()['count'] > 0;
    echo "Is recognized as existing event: " . ($isExistingEvent ? "Yes" : "No") . "\n";
    
    // Print the event record
    $eventRecord = $conn->query("SELECT * FROM hr_supervisor_calendar_site_events WHERE event_id = $eventId")->fetch_assoc();
    echo "Event Record:\n";
    print_r($eventRecord);
    echo "</pre>";
    
    echo "<pre>";
    print_r($result2);
    echo "</pre>";
    
    if ($result2['status'] === 'success') {
        echo "<p style='color:green'>✅ Update succeeded!</p>";
        $eventId = $result2['data']['eventId'];
        
        // Check if vendors were duplicated
        $conn = $GLOBALS['conn'];
        $vendorCount = $conn->query("SELECT COUNT(*) as count FROM hr_supervisor_vendor_registry WHERE event_id = $eventId")->fetch_assoc()['count'];
        echo "<p>Number of vendors in database after update: <strong>$vendorCount</strong></p>";
        
        if ($vendorCount === 1) {
            echo "<p style='color:green'>✅ DUPLICATION FIX SUCCESSFUL! Vendors were not duplicated.</p>";
        } else {
            echo "<p style='color:red'>❌ DUPLICATION ISSUE STILL EXISTS! Found $vendorCount vendors when there should be only 1.</p>";
        }
        
        // Get details about the updated laborer
        $laborerQuery = "
            SELECT 
                lr.laborer_name, 
                wpr.wages_per_day 
            FROM 
                hr_supervisor_vendor_registry vr
            JOIN 
                hr_supervisor_laborer_registry lr ON vr.vendor_id = lr.vendor_id
            JOIN 
                hr_supervisor_laborer_attendance_logs al ON lr.laborer_id = al.laborer_id
            JOIN 
                hr_supervisor_wage_payment_records wpr ON al.attendance_id = wpr.attendance_id
            WHERE 
                vr.event_id = $eventId
        ";
        
        $laborerResult = $conn->query($laborerQuery);
        if ($laborerResult && $laborerResult->num_rows > 0) {
            echo "<h3>Updated Laborer Details:</h3>";
            echo "<ul>";
            while ($row = $laborerResult->fetch_assoc()) {
                echo "<li>Name: <strong>{$row['laborer_name']}</strong>, Wages Per Day: <strong>{$row['wages_per_day']}</strong></li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color:red'>❌ Update failed: " . $result2['message'] . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Update failed with exception: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Add test for a third save to confirm behavior
echo "<h2>Step 3: Update With Two Vendors (should replace all vendors)</h2>";
// Add another vendor for the third save
$testEventData['vendors'][] = [
    'type' => 'contractor',
    'name' => 'Test Contractor',
    'contact' => '9876543210',
    'labourers' => [
        [
            'name' => 'Contractor Labourer',
            'contact' => '2222222222',
            'attendance' => [
                'morning' => 'present',
                'evening' => 'absent'
            ],
            'wages' => [
                'perDay' => 700
            ]
        ]
    ]
];

try {
    $result3 = saveCalendarData($testEventData);
    
    // Add debugging information
    echo "<pre>";
    // Check if we can get the event from the database
    $eventId = $result3['data']['eventId'];
    echo "Event ID from saveCalendarData: $eventId\n";
    
    // Check if the event is recognized as existing
    $conn = $GLOBALS['conn'];
    $isExistingEvent = $conn->query("SELECT COUNT(*) as count FROM hr_supervisor_vendor_registry WHERE event_id = $eventId")->fetch_assoc()['count'] > 0;
    echo "Is recognized as existing event: " . ($isExistingEvent ? "Yes" : "No") . "\n";
    
    // Print the event record
    $eventRecord = $conn->query("SELECT * FROM hr_supervisor_calendar_site_events WHERE event_id = $eventId")->fetch_assoc();
    echo "Event Record:\n";
    print_r($eventRecord);
    echo "</pre>";
    
    echo "<pre>";
    print_r($result3);
    echo "</pre>";
    
    if ($result3['status'] === 'success') {
        echo "<p style='color:green'>✅ Second update succeeded!</p>";
        $eventId = $result3['data']['eventId'];
        
        // Check vendor count after adding a second vendor
        $conn = $GLOBALS['conn'];
        $vendorCount = $conn->query("SELECT COUNT(*) as count FROM hr_supervisor_vendor_registry WHERE event_id = $eventId")->fetch_assoc()['count'];
        echo "<p>Number of vendors in database after second update: <strong>$vendorCount</strong></p>";
        
        if ($vendorCount === 2) {
            echo "<p style='color:green'>✅ VENDOR COUNT CORRECT! Found exactly 2 vendors as expected.</p>";
        } else {
            echo "<p style='color:red'>❌ VENDOR COUNT INCORRECT! Found $vendorCount vendors when there should be exactly 2.</p>";
        }
        
        // Get details about all laborers
        $laborerQuery = "
            SELECT 
                vr.vendor_type,
                vr.vendor_name,
                lr.laborer_name, 
                wpr.wages_per_day,
                al.morning_status,
                al.evening_status
            FROM 
                hr_supervisor_vendor_registry vr
            JOIN 
                hr_supervisor_laborer_registry lr ON vr.vendor_id = lr.vendor_id
            JOIN 
                hr_supervisor_laborer_attendance_logs al ON lr.laborer_id = al.laborer_id
            JOIN 
                hr_supervisor_wage_payment_records wpr ON al.attendance_id = wpr.attendance_id
            WHERE 
                vr.event_id = $eventId
            ORDER BY
                vr.vendor_position, lr.laborer_position
        ";
        
        $laborerResult = $conn->query($laborerQuery);
        if ($laborerResult && $laborerResult->num_rows > 0) {
            echo "<h3>All Laborer Details:</h3>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>Vendor Type</th><th>Vendor Name</th><th>Laborer Name</th><th>Wages/Day</th><th>Morning</th><th>Evening</th></tr>";
            while ($row = $laborerResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['vendor_type']}</td>";
                echo "<td>{$row['vendor_name']}</td>";
                echo "<td>{$row['laborer_name']}</td>";
                echo "<td>{$row['wages_per_day']}</td>";
                echo "<td>{$row['morning_status']}</td>";
                echo "<td>{$row['evening_status']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color:red'>❌ Second update failed: " . $result3['message'] . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Second update failed with exception: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<p><a href='test_calendar_modal.php'>Go back to calendar modal test</a></p>";
?> 