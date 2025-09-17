<?php
// Script to automatically create missing tables for salary analytics dashboard
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for authentication
session_start();

// Check if user is logged in and has HR role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    die('<div style="padding: 20px; color: #dc2626;">Access denied. HR role required.</div>');
}

echo "<h2>Setting up Missing Tables for Salary Analytics</h2>";
echo "<hr>";

try {
    // Include database connection
    require_once '../config/db_connect.php';
    
    echo "<h3>Creating Missing Tables...</h3>";
    
    // Read and execute the SQL file
    $sql_file = __DIR__ . '/create_missing_tables.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("SQL file not found: $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    
    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql_content)));
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip empty statements and comments
        }
        
        try {
            $pdo->exec($statement . ';');
            
            // Extract table name from CREATE TABLE statement
            if (preg_match('/CREATE TABLE IF NOT EXISTS\s+(\w+)/i', $statement, $matches)) {
                echo "✅ Table '{$matches[1]}' created/verified successfully<br>";
                $success_count++;
            } elseif (preg_match('/INSERT IGNORE INTO\s+(\w+)/i', $statement, $matches)) {
                echo "✅ Sample data inserted into '{$matches[1]}' successfully<br>";
                $success_count++;
            } else {
                echo "✅ SQL statement executed successfully<br>";
                $success_count++;
            }
            
        } catch (PDOException $e) {
            echo "❌ Error executing statement: " . $e->getMessage() . "<br>";
            echo "<pre style='color: #dc2626; font-size: 12px;'>" . htmlspecialchars(substr($statement, 0, 200)) . "...</pre>";
            $error_count++;
        }
    }
    
    echo "<hr>";
    echo "<h3>Summary</h3>";
    echo "✅ Successful operations: $success_count<br>";
    if ($error_count > 0) {
        echo "❌ Failed operations: $error_count<br>";
    }
    
    if ($error_count == 0) {
        echo "<div style='padding: 15px; background: #d1fae5; color: #065f46; border-radius: 8px; margin: 20px 0;'>";
        echo "<strong>🎉 All tables created successfully!</strong><br>";
        echo "You can now go back to the <a href='salary_analytics_dashboard.php' style='color: #065f46;'><u>Salary Analytics Dashboard</u></a>";
        echo "</div>";
    } else {
        echo "<div style='padding: 15px; background: #fee2e2; color: #dc2626; border-radius: 8px; margin: 20px 0;'>";
        echo "<strong>⚠️ Some operations failed.</strong><br>";
        echo "Please check the errors above and try the <a href='salary_analytics_safe_mode.php' style='color: #dc2626;'><u>Safe Mode version</u></a> instead.";
        echo "</div>";
    }
    
    // Test the created tables
    echo "<h3>Testing Created Tables</h3>";
    
    $tables_to_test = ['salary_payments', 'office_holidays'];
    
    foreach ($tables_to_test as $table) {
        try {
            $test_query = "SELECT COUNT(*) as count FROM $table";
            $stmt = $pdo->prepare($test_query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "✅ Table '$table' is working - Records: {$result['count']}<br>";
        } catch (PDOException $e) {
            echo "❌ Table '$table' test failed: " . $e->getMessage() . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "<div style='padding: 15px; background: #fee2e2; color: #dc2626; border-radius: 8px; margin: 20px 0;'>";
    echo "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='salary_analytics_dashboard.php'>← Back to Dashboard</a> | ";
echo "<a href='debug_production_issues.php'>Run Diagnostics</a> | ";
echo "<a href='salary_analytics_safe_mode.php'>Safe Mode</a></p>";
?>