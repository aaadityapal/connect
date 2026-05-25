DROP TABLE IF EXISTS `campaign_deliveries`;

CREATE TABLE `campaign_deliveries` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` INT(11) NOT NULL,
  `client_id` INT(11) NOT NULL,
  `client_name` VARCHAR(100) DEFAULT NULL COMMENT 'Snapshot of name at time of send',
  `client_phone` VARCHAR(20) DEFAULT NULL COMMENT 'Snapshot of phone at time of send',
  `template_name` VARCHAR(100) DEFAULT NULL COMMENT 'Snapshot of template label used',
  `status` ENUM('Pending', 'Sent', 'Delivered', 'Read', 'Replied', 'Failed') DEFAULT 'Pending',
  `sent_at` DATETIME DEFAULT NULL,
  `delivered_at` DATETIME DEFAULT NULL,
  `read_at` DATETIME DEFAULT NULL,
  `replied_at` DATETIME DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
