<?php
// whatsapp_sales_api/install_tables.php
require_once __DIR__ . '/../config/db_connect.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $sql = "CREATE TABLE IF NOT EXISTS `sales_whatsapp_messages` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `wa_message_id` VARCHAR(255) DEFAULT NULL,
        `user_phone` VARCHAR(20) NOT NULL,
        `direction` ENUM('inbound', 'outbound') NOT NULL,
        `message_type` VARCHAR(50) NOT NULL,
        `body` TEXT NOT NULL,
        `status` ENUM('sent', 'delivered', 'read', 'failed') NOT NULL DEFAULT 'sent',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_wa_msg_id` (`wa_message_id`),
        KEY `idx_phone` (`user_phone`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    if ($conn->query($sql) === TRUE) {
        echo "Table `sales_whatsapp_messages` created successfully.\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }

    // Insert dummy messages for testing dynamic day/date sorting and status rendering if empty!
    $chk = $conn->query("SELECT COUNT(*) FROM sales_whatsapp_messages");
    if ($chk->fetch_row()[0] == 0) {
        // Let's seed with realistic client numbers so we have structured dates (Today, Yesterday, Last week)
        $seeds = [
            // Client 1: Dynamic statuses (sent, delivered, read)
            ['wa_msg_1', '919876543210', 'outbound', 'text', 'Hello there! Welcome to ArchitectsHive. Let us know if you need any assistance.', 'read', date('Y-m-d H:i:s', strtotime('-2 days 10:30:00'))],
            ['wa_msg_2', '919876543210', 'inbound', 'text', 'Hey! Yes, I want to know more about the bedroom designs.', 'read', date('Y-m-d H:i:s', strtotime('-2 days 10:35:00'))],
            ['wa_msg_3', '919876543210', 'outbound', 'text', 'Absolutely, we have beautiful modern themes! Here is the latest update for you.', 'read', date('Y-m-d H:i:s', strtotime('-1 day 14:00:00'))],
            ['wa_msg_4', '919876543210', 'inbound', 'text', 'Perfect, thank you! I will look into it.', 'read', date('Y-m-d H:i:s', strtotime('-1 day 14:15:00'))],
            ['wa_msg_5', '919876543210', 'outbound', 'text', 'Have you had a chance to review the bedroom designs catalog yet?', 'delivered', date('Y-m-d H:i:s', strtotime('-2 hours'))],
            ['wa_msg_6', '919876543210', 'outbound', 'text', 'Please let me know if you would like a direct call with our architect today.', 'sent', date('Y-m-d H:i:s', strtotime('-10 minutes'))],
            
            // Client 2: Some older chat logs
            ['wa_msg_7', '918765432109', 'outbound', 'text', 'Hi John, your layout renders are ready for preview!', 'read', date('Y-m-d H:i:s', strtotime('-5 days 09:00:00'))],
            ['wa_msg_8', '918765432109', 'inbound', 'text', 'Wow, that looks stunning! Thank you so much.', 'read', date('Y-m-d H:i:s', strtotime('-5 days 09:30:00'))],
            ['wa_msg_9', '918765432109', 'outbound', 'text', 'You are very welcome! Let us know if you need any adjustments.', 'read', date('Y-m-d H:i:s', strtotime('-4 days 10:00:00'))]
        ];

        $ins = $conn->prepare("INSERT INTO sales_whatsapp_messages (wa_message_id, user_phone, direction, message_type, body, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($seeds as $s) {
            $ins->bind_param("sssssss", $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6]);
            $ins->execute();
        }
        echo "Seeded database with structured historical messages successfully.\n";
    }

    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
