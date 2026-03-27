-- ==========================================
-- TRAVEL ROLE CONFIGURATION TABLE
-- ==========================================

CREATE TABLE IF NOT EXISTS `travel_role_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `require_meters` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- POPULATE ROLES FROM EXISTING USERS
-- ==========================================

INSERT IGNORE INTO `travel_role_config` (`role_name`) 
SELECT DISTINCT `role` FROM `users` WHERE `role` IS NOT NULL;

-- Example: Enabling meter photos for specific roles
-- UPDATE `travel_role_config` SET `require_meters` = 1 WHERE `role_name` IN ('Site Supervisor', 'Sales');
