<?php
// Include database connection
require_once 'config/db_connect.php';

// Check if the expense_action_logs table exists
$table_exists = false;
$result = $conn->query("SHOW TABLES LIKE 'expense_action_logs'");
if ($result && $result->num_rows > 0) {
    $table_exists = true;
}

// If the table doesn't exist, create it
if (!$table_exists) {
    $create_table_sql = "
    CREATE TABLE `expense_action_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `expense_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `action_type` varchar(20) NOT NULL,
        `notes` text,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `expense_id` (`expense_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($conn->query($create_table_sql) === TRUE) {
        echo "Table expense_action_logs created successfully";
    } else {
        echo "Error creating table: " . $conn->error;
    }
} else {
    echo "Table expense_action_logs already exists";
}

$conn->close();
?> 