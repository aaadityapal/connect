<?php
/**
 * Drop All Foreign Key Constraints
 * 
 * This script drops all foreign key constraints on the overtime_notifications table
 * to prevent any issues with the payment processing.
 */

// Include database connection
require_once __DIR__ . '/../config/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Drop All Foreign Key Constraints</h1>";

// Check if the overtime_notifications table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'overtime_notifications'");
if (mysqli_num_rows($table_check) == 0) {
    die("<p style='color:red'>Error: The overtime_notifications table does not exist!</p>");
}

// Get all constraints for the table
$constraint_query = "
    SELECT CONSTRAINT_NAME 
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'overtime_notifications' 
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
";

$constraint_result = mysqli_query($conn, $constraint_query);

if (!$constraint_result) {
    echo "<p style='color:red'>Error querying constraints: " . mysqli_error($conn) . "</p>";
    exit;
}

if (mysqli_num_rows($constraint_result) == 0) {
    echo "<p style='color:orange'>No foreign key constraints found on overtime_notifications table</p>";
    exit;
}

$constraints_dropped = 0;
$constraints_failed = 0;

echo "<h2>Found constraints:</h2>";
echo "<ul>";

while ($row = mysqli_fetch_assoc($constraint_result)) {
    $constraint_name = $row['CONSTRAINT_NAME'];
    echo "<li>" . htmlspecialchars($constraint_name);
    
    // Drop the constraint
    $drop_query = "ALTER TABLE overtime_notifications DROP FOREIGN KEY " . $constraint_name;
    
    if (mysqli_query($conn, $drop_query)) {
        echo " - <span style='color:green'>Successfully dropped!</span>";
        $constraints_dropped++;
    } else {
        echo " - <span style='color:red'>Failed to drop: " . mysqli_error($conn) . "</span>";
        $constraints_failed++;
    }
    
    echo "</li>";
}

echo "</ul>";

echo "<h2>Summary:</h2>";
echo "<p>Total constraints dropped: $constraints_dropped</p>";
echo "<p>Total constraints failed to drop: $constraints_failed</p>";

if ($constraints_dropped > 0 && $constraints_failed == 0) {
    echo "<p style='color:green'>All foreign key constraints were successfully dropped!</p>";
    echo "<p>You should now be able to process payments without any foreign key constraint errors.</p>";
} else if ($constraints_dropped > 0) {
    echo "<p style='color:orange'>Some foreign key constraints were dropped, but others failed.</p>";
} else {
    echo "<p style='color:red'>Failed to drop any foreign key constraints.</p>";
}

// Close connection
mysqli_close($conn);
?> 