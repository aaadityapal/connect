<?php
require_once 'config/db_connect.php';

// Create a header
echo "<h1>Fix Drawing Numbers Utility</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    table, th, td { border: 1px solid #ddd; }
    th, td { padding: 10px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>";

// Function to log
function logMessage($message, $type = 'info') {
    echo "<p class='$type'>" . htmlspecialchars($message) . "</p>";
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // 1. Show current drawing_number stats
    echo "<h2>Current Drawing Number Statistics</h2>";
    
    $stats_sql = "SELECT 
        COUNT(*) as total_substages,
        SUM(CASE WHEN drawing_number IS NULL THEN 1 ELSE 0 END) as null_values,
        SUM(CASE WHEN drawing_number = '0' THEN 1 ELSE 0 END) as zero_string_values,
        SUM(CASE WHEN drawing_number = 0 THEN 1 ELSE 0 END) as zero_int_values,
        SUM(CASE WHEN drawing_number != '0' AND drawing_number != 0 AND drawing_number IS NOT NULL THEN 1 ELSE 0 END) as valid_values
    FROM project_substages";
    
    $result = $conn->query($stats_sql);
    $stats = $result->fetch_assoc();
    
    echo "<table>";
    echo "<tr><th>Metric</th><th>Count</th></tr>";
    echo "<tr><td>Total Substages</td><td>{$stats['total_substages']}</td></tr>";
    echo "<tr><td>NULL Values</td><td>{$stats['null_values']}</td></tr>";
    echo "<tr><td>String '0' Values</td><td>{$stats['zero_string_values']}</td></tr>";
    echo "<tr><td>Integer 0 Values</td><td>{$stats['zero_int_values']}</td></tr>";
    echo "<tr><td>Valid Drawing Numbers</td><td>{$stats['valid_values']}</td></tr>";
    echo "</table>";
    
    // 2. Show sample of problematic records
    echo "<h2>Sample of Problematic Records</h2>";
    
    $sample_sql = "SELECT id, stage_id, title, drawing_number 
                  FROM project_substages 
                  WHERE drawing_number = '0' OR drawing_number = 0 
                  LIMIT 10";
    
    $result = $conn->query($sample_sql);
    
    if ($result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Stage ID</th><th>Title</th><th>Drawing Number</th><th>Type</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['stage_id']}</td>";
            echo "<td>{$row['title']}</td>";
            echo "<td>" . var_export($row['drawing_number'], true) . "</td>";
            echo "<td>" . gettype($row['drawing_number']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        logMessage("No problematic records found!", "success");
    }
    
    // 3. Fix the issues
    if ($stats['zero_string_values'] > 0 || $stats['zero_int_values'] > 0) {
        echo "<h2>Fixing Issues</h2>";
        
        // Fix '0' and 0 values to NULL
        $fix_sql = "UPDATE project_substages SET drawing_number = NULL WHERE drawing_number = '0' OR drawing_number = 0";
        
        $result = $conn->query($fix_sql);
        
        if ($result) {
            $affected_rows = $conn->affected_rows;
            logMessage("Successfully fixed $affected_rows records!", "success");
        } else {
            logMessage("Error fixing records: " . $conn->error, "error");
        }
        
        // 4. Show updated stats
        echo "<h2>Updated Drawing Number Statistics</h2>";
        
        $result = $conn->query($stats_sql);
        $stats = $result->fetch_assoc();
        
        echo "<table>";
        echo "<tr><th>Metric</th><th>Count</th></tr>";
        echo "<tr><td>Total Substages</td><td>{$stats['total_substages']}</td></tr>";
        echo "<tr><td>NULL Values</td><td>{$stats['null_values']}</td></tr>";
        echo "<tr><td>String '0' Values</td><td>{$stats['zero_string_values']}</td></tr>";
        echo "<tr><td>Integer 0 Values</td><td>{$stats['zero_int_values']}</td></tr>";
        echo "<tr><td>Valid Drawing Numbers</td><td>{$stats['valid_values']}</td></tr>";
        echo "</table>";
    }
    
    // 5. Show valid drawing numbers
    echo "<h2>Sample of Valid Drawing Numbers</h2>";
    
    $valid_sql = "SELECT id, stage_id, title, drawing_number 
                 FROM project_substages 
                 WHERE drawing_number IS NOT NULL 
                   AND drawing_number != '0' 
                   AND drawing_number != 0 
                 LIMIT 10";
    
    $result = $conn->query($valid_sql);
    
    if ($result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Stage ID</th><th>Title</th><th>Drawing Number</th><th>Type</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['stage_id']}</td>";
            echo "<td>{$row['title']}</td>";
            echo "<td>" . var_export($row['drawing_number'], true) . "</td>";
            echo "<td>" . gettype($row['drawing_number']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        logMessage("No valid drawing numbers found!", "warning");
    }
    
    // Commit the transaction
    $conn->commit();
    logMessage("Process completed successfully!", "success");
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->rollback();
    }
    logMessage("Error: " . $e->getMessage(), "error");
}
?> 