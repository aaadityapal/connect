DROP TABLE IF EXISTS `sequence_subscriptions`;

CREATE TABLE `sequence_subscriptions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `sequence_id` INT(11) NOT NULL,
  `sequence_name` VARCHAR(100) DEFAULT NULL COMMENT 'Snapshot of sequence name at enrollment',
  `client_id` INT(11) NOT NULL,
  `client_name` VARCHAR(100) DEFAULT NULL COMMENT 'Snapshot of client name at enrollment',
  `client_phone` VARCHAR(20) DEFAULT NULL COMMENT 'Snapshot of client phone at enrollment',
  `current_step_id` INT(11) DEFAULT NULL COMMENT 'The timeline step the client is currently processing',
  `status` ENUM('Running', 'Paused', 'Completed', 'Stopped') DEFAULT 'Running',
  `next_send_at` DATETIME DEFAULT NULL COMMENT 'Calculated absolute timestamp for next automated dispatch',
  `last_sent_at` DATETIME DEFAULT NULL,
  `enrolled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sub` (`sequence_id`, `client_id`),
  FOREIGN KEY (`sequence_id`) REFERENCES `sequences`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`current_step_id`) REFERENCES `sequence_steps`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
