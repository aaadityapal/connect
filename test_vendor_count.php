<?php
// Test file to check vendor counts

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Testing Vendor Counts</h1>";

try {
    // Include the database connection file
    require_once 'includes/config/db_connect.php';
    
    // Test query to list events
    $eventsQuery = "SELECT * FROM hr_supervisor_calendar_site_events ORDER BY event_date DESC LIMIT 10";
    $eventsResult = $conn->query($eventsQuery);
    
    if ($eventsResult && $eventsResult->num_rows > 0) {
        echo "<h2>Recent Events</h2>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Event ID</th><th>Site ID</th><th>Date</th><th>Vendors</th><th>Actions</th></tr>";
        
        while ($event = $eventsResult->fetch_assoc()) {
            // Count vendors for this event
            $vendorCountQuery = "SELECT COUNT(*) as count FROM hr_supervisor_vendor_registry WHERE event_id = {$event['event_id']}";
            $vendorCountResult = $conn->query($vendorCountQuery);
            $vendorCount = $vendorCountResult->fetch_assoc()['count'];
            
            echo "<tr>";
            echo "<td>{$event['event_id']}</td>";
            echo "<td>{$event['site_id']}</td>";
            echo "<td>{$event['event_date']}</td>";
            echo "<td>{$vendorCount}</td>";
            echo "<td><a href='?action=debug&event_id={$event['event_id']}'>Debug</a> | <a href='?action=cleanup&event_id={$event['event_id']}'>Cleanup</a></td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No events found in the database.</p>";
    }
    
    // Handle actions
    if (isset($_GET['action']) && isset($_GET['event_id'])) {
        $action = $_GET['action'];
        $eventId = (int)$_GET['event_id'];
        
        echo "<h2>Action: $action for Event ID: $eventId</h2>";
        
        if ($action === 'debug') {
            // Get event details
            $eventQuery = "SELECT * FROM hr_supervisor_calendar_site_events WHERE event_id = $eventId";
            $eventResult = $conn->query($eventQuery);
            $event = $eventResult->fetch_assoc();
            
            echo "<h3>Event Details</h3>";
            echo "<pre>";
            print_r($event);
            echo "</pre>";
            
            // Get vendors
            $vendorsQuery = "SELECT * FROM hr_supervisor_vendor_registry WHERE event_id = $eventId";
            $vendorsResult = $conn->query($vendorsQuery);
            
            if ($vendorsResult && $vendorsResult->num_rows > 0) {
                echo "<h3>Vendors ({$vendorsResult->num_rows})</h3>";
                echo "<ul>";
                
                while ($vendor = $vendorsResult->fetch_assoc()) {
                    echo "<li>ID: {$vendor['vendor_id']} - Type: {$vendor['vendor_type']} - Name: {$vendor['vendor_name']}</li>";
                    
                    // Get laborers for this vendor
                    $laborersQuery = "SELECT * FROM hr_supervisor_laborer_registry WHERE vendor_id = {$vendor['vendor_id']}";
                    $laborersResult = $conn->query($laborersQuery);
                    
                    if ($laborersResult && $laborersResult->num_rows > 0) {
                        echo "<ul>";
                        
                        while ($laborer = $laborersResult->fetch_assoc()) {
                            echo "<li>Laborer ID: {$laborer['laborer_id']} - Name: {$laborer['laborer_name']}</li>";
                        }
                        
                        echo "</ul>";
                    } else {
                        echo "<ul><li>No laborers found for this vendor</li></ul>";
                    }
                }
                
                echo "</ul>";
            } else {
                echo "<p>No vendors found for this event.</p>";
            }
        } elseif ($action === 'cleanup') {
            // Import the cleanup function
            require_once 'includes/calendar_data_handler.php';
            
            // Call the cleanup function directly
            $cleanupResult = cleanupExistingEventData($eventId);
            
            if ($cleanupResult) {
                echo "<p style='color:green'>✅ Successfully cleaned up event data!</p>";
            } else {
                echo "<p style='color:red'>❌ Failed to clean up event data.</p>";
            }
            
            // Count vendors after cleanup
            $vendorCountQuery = "SELECT COUNT(*) as count FROM hr_supervisor_vendor_registry WHERE event_id = $eventId";
            $vendorCountResult = $conn->query($vendorCountQuery);
            $vendorCount = $vendorCountResult->fetch_assoc()['count'];
            
            echo "<p>Vendors remaining after cleanup: $vendorCount</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?> 