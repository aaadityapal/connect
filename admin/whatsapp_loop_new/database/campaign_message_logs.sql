DROP TABLE IF EXISTS `campaign_message_logs`;

CREATE TABLE `campaign_message_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` INT(11) NOT NULL,
  `campaign_delivery_id` INT(11) DEFAULT NULL,
  `client_id` INT(11) DEFAULT NULL,
  `client_name` VARCHAR(100) DEFAULT NULL,
  `client_phone` VARCHAR(20) DEFAULT NULL,
  `template_name` VARCHAR(100) DEFAULT NULL,
  `message_id` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(30) NOT NULL,
  `details` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_campaign_delivery_id` (`campaign_delivery_id`),
  KEY `idx_message_id` (`message_id`),
  CONSTRAINT `fk_campaign_message_logs_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_campaign_message_logs_delivery` FOREIGN KEY (`campaign_delivery_id`) REFERENCES `campaign_deliveries`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
