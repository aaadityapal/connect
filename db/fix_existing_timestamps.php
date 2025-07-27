<?php
require_once '../config/timezone_config.php';
require_once '../config/db_connect.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Ensure correct timezone
    ensureCorrectTimezone($conn);
    
    // First, get the current timezone offset
    $result = $conn->query("SELECT TIMEDIFF(NOW(), UTC_TIMESTAMP) as time_diff");
    $row = $result->fetch_assoc();
    $current_offset = $row['time_diff'];
    
    echo "Current MySQL timezone offset: " . $current_offset . "\n";
    
    if ($current_offset !== '05:30:00') {
        // Calculate the adjustment needed
        $target_offset = '05:30:00';
        
        // Convert existing timestamps
        $sql = "UPDATE site_in_out_logs 
                SET timestamp = DATE_ADD(timestamp, 
                    INTERVAL TIMEDIFF(?, ?) HOUR_SECOND)
                WHERE timestamp IS NOT NULL";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $target_offset, $current_offset);
        
        if ($stmt->execute()) {
            echo "Successfully adjusted timestamps.\n";
            echo "Rows affected: " . $stmt->affected_rows . "\n";
        } else {
            throw new Exception("Error updating timestamps: " . $conn->error);
        }
    } else {
        echo "Timezone offset is already correct.\n";
    }
    
    // Verify a few records
    $sql = "SELECT timestamp, 
            DATE_FORMAT(timestamp, '%Y-%m-%d %h:%i %p') as formatted_time 
            FROM site_in_out_logs 
            ORDER BY timestamp DESC LIMIT 5";
    
    $result = $conn->query($sql);
    
    echo "\nVerifying recent records:\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['timestamp'] . " -> " . $row['formatted_time'] . "\n";
    }
    
} catch (Exception $e) {
    die("Fix failed: " . $e->getMessage() . "\n");
}
?> 