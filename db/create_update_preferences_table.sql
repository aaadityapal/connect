-- Create table for storing user preferences about update notifications
CREATE TABLE IF NOT EXISTS `user_update_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `update_version` varchar(20) NOT NULL,
  `dont_show` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_version_idx` (`user_id`, `update_version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add index for faster lookups
CREATE INDEX IF NOT EXISTS `user_update_preferences_user_id_idx` ON `user_update_preferences` (`user_id`);
CREATE INDEX IF NOT EXISTS `user_update_preferences_version_idx` ON `user_update_preferences` (`update_version`); 