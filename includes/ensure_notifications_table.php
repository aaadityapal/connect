<?php
/**
 * Ensure Notifications Table
 * 
 * This script checks if the notifications table exists and creates it if it doesn't
 */

function ensure_notifications_table($conn) {
    // Check if notifications table exists
    $table_query = "SHOW TABLES LIKE 'notifications'";
    $table_result = $conn->query($table_query);
    
    if ($table_result && $table_result->num_rows == 0) {
        // Table doesn't exist, create it
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                link VARCHAR(255),
                type VARCHAR(50) NOT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at DATETIME NOT NULL,
                read_at DATETIME DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            
            -- Create index for faster queries
            CREATE INDEX idx_notifications_user_id ON notifications(user_id);
            CREATE INDEX idx_notifications_type ON notifications(type);
        ";
        
        // Split into individual statements
        $statements = array_filter(array_map('trim', explode(';', $create_table_sql)), 'strlen');
        
        // Execute each statement
        $success = true;
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                if (!$conn->query($statement)) {
                    error_log("Error creating notifications table: " . $conn->error);
                    $success = false;
                }
            }
        }
        
        return $success;
    }
    
    return true; // Table already exists
}
?> 