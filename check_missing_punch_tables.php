<?php
/**
 * Check if missing_punch_in and missing_punch_out tables exist
 */
require_once 'config/db_connect.php';

try {
    echo "<h2>Checking Missing Punch Tables</h2>";
    
    // Check if missing_punch_in table exists
    $check_table_query = "SHOW TABLES LIKE 'missing_punch_in'";
    $result = $conn->query($check_table_query);
    
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>Table 'missing_punch_in' exists.</p>";
        
        // Show table structure
        echo "<h3>missing_punch_in Table Structure</h3>";
        $structure_query = "DESCRIBE missing_punch_in";
        $structure_result = $conn->query($structure_query);
        
        if ($structure_result) {
            echo "<table border='1'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            while ($row = $structure_result->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color: red;'>Table 'missing_punch_in' does not exist.</p>";
    }
    
    // Check if missing_punch_out table exists
    $check_table_query2 = "SHOW TABLES LIKE 'missing_punch_out'";
    $result2 = $conn->query($check_table_query2);
    
    if ($result2 && $result2->num_rows > 0) {
        echo "<p style='color: green;'>Table 'missing_punch_out' exists.</p>";
        
        // Show table structure
        echo "<h3>missing_punch_out Table Structure</h3>";
        $structure_query2 = "DESCRIBE missing_punch_out";
        $structure_result2 = $conn->query($structure_query2);
        
        if ($structure_result2) {
            echo "<table border='1'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            while ($row = $structure_result2->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color: red;'>Table 'missing_punch_out' does not exist.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>