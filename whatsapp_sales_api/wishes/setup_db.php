<?php
// whatsapp_sales_api/wishes/setup_db.php
require_once __DIR__ . '/../helper.php';

$conn = getDBConnection();

$sql = "CREATE TABLE IF NOT EXISTS scheduled_wishes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    whatsapp_number VARCHAR(20) NOT NULL,
    scheduled_time DATETIME NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    template_name VARCHAR(100) NOT NULL,
    image_link TEXT,
    response_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'scheduled_wishes' created successfully (or already exists).";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>