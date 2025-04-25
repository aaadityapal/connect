-- Create file_activity_logs table for tracking file download activities
CREATE TABLE IF NOT EXISTS `file_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key constraints (commented out by default, uncomment if needed)
/*
ALTER TABLE `file_activity_logs` 
  ADD CONSTRAINT `fk_file_activity_logs_file_id` FOREIGN KEY (`file_id`) REFERENCES `substage_files` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_file_activity_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
*/ 