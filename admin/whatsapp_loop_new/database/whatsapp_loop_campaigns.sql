DROP TABLE IF EXISTS `campaigns`;

CREATE TABLE `campaigns` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `template_id` INT(11) NOT NULL,
  `template_name` VARCHAR(100) DEFAULT NULL COMMENT 'Snapshot of template label used',
  `button_link` VARCHAR(255) DEFAULT NULL,
  `target_audience` ENUM('All', 'Selected') DEFAULT 'All',
  `schedule_type` ENUM('Now', 'Later') DEFAULT 'Now',
  `scheduled_at` DATETIME DEFAULT NULL,
  `is_repeat` BOOLEAN DEFAULT FALSE,
  `repeat_interval` VARCHAR(50) DEFAULT NULL,
  `stop_on_reply` BOOLEAN DEFAULT TRUE,
  `status` ENUM('Draft', 'Running', 'Paused', 'Completed') DEFAULT 'Draft',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`template_id`) REFERENCES `templates`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
