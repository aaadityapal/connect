DROP TABLE IF EXISTS `sequence_deliveries`;

CREATE TABLE `sequence_deliveries` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `subscription_id` INT(11) NOT NULL COMMENT 'Link to the client enrollment',
  `sequence_step_id` INT(11) NOT NULL COMMENT 'The specific step that was sent',
  `client_id` INT(11) NOT NULL,
  `client_name` VARCHAR(100) NOT NULL COMMENT 'Snapshot of name at time of send',
  `client_phone` VARCHAR(20) NOT NULL COMMENT 'Snapshot of phone at time of send',
  `template_name` VARCHAR(100) NOT NULL COMMENT 'Snapshot of template label used',
  `status` ENUM('Pending', 'Sent', 'Delivered', 'Read', 'Replied', 'Failed') DEFAULT 'Sent',
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `delivered_at` DATETIME DEFAULT NULL,
  `read_at` DATETIME DEFAULT NULL,
  `replied_at` DATETIME DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`subscription_id`) REFERENCES `sequence_subscriptions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sequence_step_id`) REFERENCES `sequence_steps`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
