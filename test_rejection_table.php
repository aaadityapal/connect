<?php
session_start();
require_once 'config/db_connect.php';

try {
    // Check if the overtime_rejections table exists
    $table_check = "SHOW TABLES LIKE 'overtime_rejections'";
    $table_exists = $pdo->query($table_check)->rowCount() > 0;
    
    if (!$table_exists) {
        echo "Table doesn't exist. Creating it now...\n";
        
        // Create the overtime_rejections table
        $create_table = "CREATE TABLE overtime_rejections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            attendance_id INT NOT NULL,
            reason TEXT NOT NULL,
            rejected_by INT NOT NULL,
            rejected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE CASCADE,
            FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $pdo->exec($create_table);
        echo "Table created successfully!\n";
    } else {
        echo "Table already exists.\n";
    }
    
    // Show table structure
    $structure = $pdo->query("DESCRIBE overtime_rejections");
    echo "\nTable structure:\n";
    while ($row = $structure->fetch()) {
        print_r($row);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>