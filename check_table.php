<?php
// Include database connection
require_once 'config/db_connect.php';

// Ensure we're using the correct database
mysqli_query($conn, "USE crm");

// Check if overtime_notifications table exists
$query = "SHOW TABLES LIKE 'overtime_notifications'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    echo "Table 'overtime_notifications' exists.\n";
    
    // Check table structure
    $structureQuery = "DESCRIBE overtime_notifications";
    $structureResult = mysqli_query($conn, $structureQuery);
    
    echo "Table structure:\n";
    while ($row = mysqli_fetch_assoc($structureResult)) {
        echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Null'] . " - " . $row['Key'] . "\n";
    }
} else {
    echo "Table 'overtime_notifications' does not exist.\n";
    
    // Check if overtime_notification (singular) exists
    $singularQuery = "SHOW TABLES LIKE 'overtime_notification'";
    $singularResult = mysqli_query($conn, $singularQuery);
    
    if (mysqli_num_rows($singularResult) > 0) {
        echo "Table 'overtime_notification' (singular) exists.\n";
        
        // Check table structure
        $structureQuery = "DESCRIBE overtime_notification";
        $structureResult = mysqli_query($conn, $structureQuery);
        
        echo "Table structure:\n";
        while ($row = mysqli_fetch_assoc($structureResult)) {
            echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Null'] . " - " . $row['Key'] . "\n";
        }
    } else {
        echo "Table 'overtime_notification' (singular) does not exist either.\n";
    }
}

// Close connection
mysqli_close($conn);
?> 