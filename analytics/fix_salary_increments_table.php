<?php
// Script to fix the salary_increments table structure
require_once '../config/db_connect.php';

echo "<h2>Fixing Salary Increments Table</h2>";

try {
    echo "<h3>1. Checking salary_increments table structure...</h3>";
    
    // Check if table exists
    $check_table = "SHOW TABLES LIKE 'salary_increments'";
    $result = $pdo->query($check_table);
    
    if ($result->rowCount() == 0) {
        echo "<p style='color: red;'>❌ Table 'salary_increments' does NOT exist!</p>";
        echo "<p>Creating table...</p>";
        
        $create_table = "CREATE TABLE salary_increments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            salary_before_increment DECIMAL(10,2) DEFAULT NULL,
            salary_after_increment DECIMAL(10,2) NOT NULL,
            effective_from DATE NOT NULL,
            status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
            reason TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_user_id (user_id),
            INDEX idx_effective_from (effective_from),
            INDEX idx_status (status),
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $pdo->exec($create_table);
        echo "<p style='color: green;'>✅ Table created successfully!</p>";
    } else {
        echo "<p style='color: green;'>✅ Table exists. Checking columns...</p>";
        
        // Check table structure
        $structure_query = "DESCRIBE salary_increments";
        $structure_result = $pdo->query($structure_query);
        
        $existing_columns = [];
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        
        while ($row = $structure_result->fetch(PDO::FETCH_ASSOC)) {
            $existing_columns[] = $row['Field'];
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check for required columns
        $required_columns = [
            'salary_before_increment' => 'DECIMAL(10,2) DEFAULT NULL',
            'salary_after_increment' => 'DECIMAL(10,2) NOT NULL',
            'effective_from' => 'DATE NOT NULL',
            'status' => "ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending'",
            'reason' => 'TEXT DEFAULT NULL'
        ];
        
        foreach ($required_columns as $column => $definition) {
            if (!in_array($column, $existing_columns)) {
                echo "<p style='color: orange;'>⚠️ Adding missing column: $column</p>";
                try {
                    $alter_sql = "ALTER TABLE salary_increments ADD COLUMN $column $definition";
                    $pdo->exec($alter_sql);
                    echo "<p style='color: green;'>✅ Column '$column' added successfully</p>";
                } catch (PDOException $e) {
                    echo "<p style='color: red;'>❌ Error adding column '$column': " . $e->getMessage() . "</p>";
                }
            } else {
                echo "<p style='color: green;'>✅ Column '$column' exists</p>";
            }
        }
    }
    
    echo "<h3>2. Testing insert operation...</h3>";
    
    // Test if we can insert a record
    $test_query = "INSERT INTO salary_increments 
                   (user_id, salary_after_increment, effective_from, status, reason) 
                   VALUES (1, 25000.00, CURDATE(), 'approved', 'Test record - will be deleted')";
    
    try {
        $pdo->exec($test_query);
        echo "<p style='color: green;'>✅ Insert test successful</p>";
        
        // Clean up test record
        $pdo->exec("DELETE FROM salary_increments WHERE reason = 'Test record - will be deleted'");
        echo "<p style='color: green;'>✅ Test record cleaned up</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>❌ Insert test failed: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3>🎉 Salary increments table is now ready!</h3>";
    echo "<p><a href='salary_analytics_dashboard.php' style='background: #007bff; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>Go back to Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>