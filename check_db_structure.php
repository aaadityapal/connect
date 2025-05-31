<?php
// Include database configuration
require_once 'config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting database check...\n";

// Function to check if column exists
function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM $table LIKE ?");
        $stmt->execute([$column]);
        $exists = $stmt->rowCount() > 0;
        echo "Checking if column '$column' exists in table '$table': " . ($exists ? "YES" : "NO") . "\n";
        return $exists;
    } catch (PDOException $e) {
        echo "Error checking column: " . $e->getMessage() . "\n";
        return false;
    }
}

// Function to add column if it doesn't exist
function addColumnIfNotExists($pdo, $table, $column, $definition) {
    if (!columnExists($pdo, $table, $column)) {
        $sql = "ALTER TABLE $table ADD COLUMN $column $definition";
        echo "Column '$column' doesn't exist. SQL to add it:\n";
        echo "$sql\n\n";
        
        try {
            $result = $pdo->exec($sql);
            echo "Column added successfully! Affected rows: $result\n";
            return true;
        } catch (PDOException $e) {
            echo "Error adding column: " . $e->getMessage() . "\n";
            return false;
        }
    } else {
        echo "Column '$column' already exists in table '$table'.\n";
        return true;
    }
}

// Check if the table exists
try {
    echo "Checking if table 'sv_calendar_events' exists...\n";
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'sv_calendar_events'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "Table 'sv_calendar_events' exists.\n\n";
        
        // Get table structure
        $stmt = $pdo->prepare("DESCRIBE sv_calendar_events");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Current columns in sv_calendar_events:\n";
        foreach ($columns as $column) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
        echo "\n";
        
        // Generate SQL to add the is_custom_title column
        $sql = "ALTER TABLE sv_calendar_events ADD COLUMN is_custom_title TINYINT(1) NOT NULL DEFAULT 0 AFTER title";
        echo "SQL to add the missing column:\n$sql\n\n";
        
        // Add the is_custom_title column if it doesn't exist
        addColumnIfNotExists($pdo, 'sv_calendar_events', 'is_custom_title', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER title');
        
    } else {
        echo "Table 'sv_calendar_events' does not exist.\n";
        echo "SQL to create table with the required column:\n";
        echo "CREATE TABLE sv_calendar_events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    is_custom_title TINYINT(1) NOT NULL DEFAULT 0,
    event_date DATE NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nDatabase check completed.\n";
?> 