-- whatsapp_sessions schema
CREATE TABLE IF NOT EXISTS `whatsapp_sessions` (
  `phone_number` VARCHAR(50) NOT NULL,
  `current_node_id` VARCHAR(50) DEFAULT NULL,
  `variables` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
