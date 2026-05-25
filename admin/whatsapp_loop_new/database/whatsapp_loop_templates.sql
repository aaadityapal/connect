DROP TABLE IF EXISTS `templates`;

CREATE TABLE `templates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `template_key` VARCHAR(50) NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `language` VARCHAR(20) DEFAULT 'en_US',
  `body` TEXT NOT NULL,
  `status` ENUM('Active', 'Draft', 'Archived') DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_unique` (`template_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
