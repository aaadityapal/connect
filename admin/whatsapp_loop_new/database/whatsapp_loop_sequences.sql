DROP TABLE IF EXISTS `sequences`;

CREATE TABLE `sequences` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_persistent` BOOLEAN DEFAULT TRUE COMMENT 'If TRUE, sequence continues triggering chronologically even if client replies',
  `stop_on_reply` BOOLEAN DEFAULT TRUE COMMENT 'If TRUE, stops the sequence if client replies',
  `status` ENUM('Active', 'Paused', 'Archived') DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
