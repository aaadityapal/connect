<?php
// Script to check the status of the incremented salary analytics table
require_once '../config/db_connect.php';

echo "<h2>Database Table Status Check</h2>";

try {
    echo "<h3>1. Checking if 'incremented_salary_analytics' table exists...</h3>";
    
    // Check if table exists
    $check_table_query = "SHOW TABLES LIKE 'incremented_salary_analytics'";
    $result = $pdo->query($check_table_query);
    
    if ($result->rowCount() == 0) {
        echo "<p style='color: red;'>❌ Table 'incremented_salary_analytics' does NOT exist!</p>";
        echo "<p><strong>Solution:</strong> Run the setup script first:</p>";
        echo "<p><a href='setup_incremented_salary_table.php'>Click here to create the table</a></p>";
    } else {
        echo "<p style='color: green;'>✅ Table 'incremented_salary_analytics' exists!</p>";
        
        // Check table structure
        echo "<h3>2. Checking table structure...</h3>";
        $structure_query = "DESCRIBE incremented_salary_analytics";
        $structure_result = $pdo->query($structure_query);
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = $structure_result->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if required columns exist
        echo "<h3>3. Checking for required columns...</h3>";
        $required_columns = [
            'previous_incremented_salary',
            'actual_change_amount', 
            'actual_change_percentage'
        ];
        
        $existing_columns = [];
        $structure_result = $pdo->query($structure_query);
        while ($row = $structure_result->fetch(PDO::FETCH_ASSOC)) {
            $existing_columns[] = $row['Field'];
        }
        
        foreach ($required_columns as $column) {
            if (in_array($column, $existing_columns)) {
                echo "<p style='color: green;'>✅ Column '$column' exists</p>";
            } else {
                echo "<p style='color: orange;'>⚠️ Column '$column' missing - run update script</p>";
            }
        }
        
        if (!in_array('previous_incremented_salary', $existing_columns)) {
            echo "<p><strong>Solution:</strong> Run the update script:</p>";
            echo "<p><a href='update_incremented_salary_table.php'>Click here to update the table</a></p>";
        }
    }
    
    echo "<h3>4. Checking 'salary_change_log' table...</h3>";
    $check_log_table = "SHOW TABLES LIKE 'salary_change_log'";
    $log_result = $pdo->query($check_log_table);
    
    if ($log_result->rowCount() == 0) {
        echo "<p style='color: orange;'>⚠️ Table 'salary_change_log' does NOT exist (optional)</p>";
    } else {
        echo "<p style='color: green;'>✅ Table 'salary_change_log' exists!</p>";
    }
    
    echo "<h3>5. Testing database connection...</h3>";
    $test_query = "SELECT 1 as test";
    $test_result = $pdo->query($test_query);
    if ($test_result) {
        echo "<p style='color: green;'>✅ Database connection is working!</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ General Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h3>Quick Actions:</h3>";
echo "<p><a href='setup_incremented_salary_table.php' style='background: #007bff; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>Setup Tables</a></p>";
echo "<p><a href='update_incremented_salary_table.php' style='background: #28a745; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>Update Tables</a></p>";
echo "<p><a href='salary_analytics_dashboard.php' style='background: #6c757d; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>Back to Dashboard</a></p>";
?>