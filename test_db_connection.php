<?php
// Test database connection for process_expense_approval.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection for batch expense approval...<br>";

// Check if config/db_connect.php exists
if (file_exists('config/db_connect.php')) {
    echo "Config file exists.<br>";
    
    // Include the database connection
    require_once 'config/db_connect.php';
    
    // Check if the connection is established
    if (isset($conn) && $conn instanceof mysqli) {
        if ($conn->connect_error) {
            echo "Connection failed: " . $conn->connect_error . "<br>";
        } else {
            echo "Database connection successful!<br>";
            
            // Test if travel_expenses table exists
            $result = $conn->query("SHOW TABLES LIKE 'travel_expenses'");
            if ($result && $result->num_rows > 0) {
                echo "Travel expenses table exists.<br>";
                
                // Test if expense_action_logs table exists
                $result = $conn->query("SHOW TABLES LIKE 'expense_action_logs'");
                if ($result && $result->num_rows > 0) {
                    echo "Expense action logs table exists.<br>";
                    
                    // Show table structure
                    $result = $conn->query("DESCRIBE expense_action_logs");
                    if ($result) {
                        echo "<h3>Expense Action Logs Table Structure:</h3>";
                        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            foreach ($row as $key => $value) {
                                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                            }
                            echo "</tr>";
                        }
                        echo "</table>";
                    }
                } else {
                    echo "Expense action logs table does not exist.<br>";
                }
            } else {
                echo "Travel expenses table does not exist.<br>";
            }
        }
    } else {
        echo "Database connection variable not found.<br>";
    }
} else {
    echo "Config file does not exist.<br>";
}
?>
<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 800px;
        margin: 20px auto;
        padding: 0 20px;
    }
    h2 {
        color: #333;
        border-bottom: 2px solid #eee;
        padding-bottom: 10px;
    }
    h3 {
        color: #444;
        margin-top: 20px;
    }
    pre {
        background-color: #f5f5f5;
        padding: 10px;
        border-radius: 3px;
        overflow: auto;
    }
    .success {
        color: green;
        font-weight: bold;
    }
    .error {
        color: red;
        font-weight: bold;
    }
    ul {
        background-color: #f8f8f8;
        padding: 10px 10px 10px 30px;
        border-radius: 3px;
}
</style> 