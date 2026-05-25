DROP TABLE IF EXISTS `sequence_steps`;

CREATE TABLE `sequence_steps` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `sequence_id` INT(11) NOT NULL,
  `template_id` INT(11) DEFAULT NULL,
  `template_name` VARCHAR(100) DEFAULT NULL COMMENT 'Snapshot of template label used',
  `template_language` VARCHAR(20) DEFAULT 'en_US',
  `step_order` INT(11) NOT NULL COMMENT 'The numerical sequence order of the step',
  `delay_value` INT(11) DEFAULT 0 COMMENT 'Wait time interval before triggering send',
  `delay_unit` ENUM('days', 'weeks', 'months') DEFAULT 'days',
  `header_type` VARCHAR(50) DEFAULT 'NONE',
  `media_path` VARCHAR(255) DEFAULT NULL,
  `media_filename` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`sequence_id`) REFERENCES `sequences`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
