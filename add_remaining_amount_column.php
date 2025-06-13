<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once __DIR__ . '/config/db_connect.php';

try {
    // Check if column exists
    $checkColumnSql = "SELECT COUNT(*) as column_exists 
                      FROM INFORMATION_SCHEMA.COLUMNS 
                      WHERE TABLE_NAME = 'project_payouts' 
                      AND COLUMN_NAME = 'remaining_amount'";
    
    $stmt = $pdo->query($checkColumnSql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['column_exists'] == 0) {
        // Column doesn't exist, add it
        $alterTableSql = "ALTER TABLE project_payouts 
                         ADD COLUMN remaining_amount DECIMAL(10,2) DEFAULT 0";
        
        $pdo->exec($alterTableSql);
        echo "<div style='color: green; padding: 20px; background-color: #f0fff0; border: 1px solid green; margin: 20px;'>
              <h3>Success!</h3>
              <p>The 'remaining_amount' column was successfully added to the project_payouts table.</p>
              </div>";
    } else {
        echo "<div style='color: blue; padding: 20px; background-color: #f0f0ff; border: 1px solid blue; margin: 20px;'>
              <h3>Information</h3>
              <p>The 'remaining_amount' column already exists in the project_payouts table.</p>
              </div>";
    }
    
    // Check if the column exists now
    $checkAgainSql = "DESCRIBE project_payouts";
    $stmt = $pdo->query($checkAgainSql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div style='padding: 20px; background-color: #f5f5f5; border: 1px solid #ddd; margin: 20px;'>
          <h3>Table Structure</h3>
          <table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>
          <tr style='background-color: #eee;'>
            <th>Field</th>
            <th>Type</th>
            <th>Null</th>
            <th>Key</th>
            <th>Default</th>
            <th>Extra</th>
          </tr>";
    
    foreach ($columns as $column) {
        $highlight = ($column['Field'] == 'remaining_amount') ? "background-color: #ffff99;" : "";
        echo "<tr style='$highlight'>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    
    echo "</table></div>";
    
    echo "<p><a href='payouts.php' style='padding: 10px 15px; background-color: #4361ee; color: white; text-decoration: none; border-radius: 4px;'>Go to Payouts Page</a></p>";
    
} catch (PDOException $e) {
    echo "<div style='color: red; padding: 20px; background-color: #fff0f0; border: 1px solid red; margin: 20px;'>
          <h3>Error</h3>
          <p>Failed to add column: " . $e->getMessage() . "</p>
          </div>";
}
?> 