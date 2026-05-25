-- sales_whatsapp_messages schema
CREATE TABLE IF NOT EXISTS `sales_whatsapp_messages` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
