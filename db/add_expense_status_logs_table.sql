-- Create table for expense status update logs
CREATE TABLE IF NOT EXISTS `expense_status_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(20) NOT NULL,
  `status_field` varchar(50) NOT NULL,
  `new_status` varchar(20) NOT NULL,
  `reason` text,
  `affected_rows` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;