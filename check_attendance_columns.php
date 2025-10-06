<?php
/**
 * Check attendance table structure for missing punch columns
 */
require_once 'config/db_connect.php';

try {
    // Check if the required columns exist in the attendance table
    $query = "SHOW COLUMNS FROM attendance LIKE '%missing_punch%'";
    $result = $conn->query($query);
    
    echo "<h2>Missing Punch Columns in Attendance Table</h2>";
    
    if ($result && $result->num_rows > 0) {
        echo "<p>Found the following missing punch columns:</p>";
        echo "<ul>";
        while ($row = $result->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($row['Field']) . " (" . htmlspecialchars($row['Type']) . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No missing punch columns found in attendance table.</p>";
    }
    
    // Check for approval_status column
    $query2 = "SHOW COLUMNS FROM attendance LIKE 'approval_status'";
    $result2 = $conn->query($query2);
    
    echo "<h3>Approval Status Column Check</h3>";
    
    if ($result2 && $result2->num_rows > 0) {
        echo "<p>Found approval_status column:</p>";
        echo "<ul>";
        while ($row = $result2->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($row['Field']) . " (" . htmlspecialchars($row['Type']) . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No approval_status column found.</p>";
    }
    
    // Check for missing_punch_approval_status column
    $query3 = "SHOW COLUMNS FROM attendance LIKE 'missing_punch_approval_status'";
    $result3 = $conn->query($query3);
    
    echo "<h3>Missing Punch Approval Status Column Check</h3>";
    
    if ($result3 && $result3->num_rows > 0) {
        echo "<p>Found missing_punch_approval_status column:</p>";
        echo "<ul>";
        while ($row = $result3->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($row['Field']) . " (" . htmlspecialchars($row['Type']) . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No missing_punch_approval_status column found.</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>