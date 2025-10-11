-- SQL script to create table for storing modal suppression preferences
-- This table will store user preferences for not showing the instant modal for 24 hours

CREATE TABLE IF NOT EXISTS `modal_suppression` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `modal_type` varchar(50) NOT NULL DEFAULT 'instant_modal',
  `suppressed_until` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_modal` (`user_id`, `modal_type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_suppressed_until` (`suppressed_until`),
  CONSTRAINT `fk_modal_suppression_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sample query to check if a user's modal is suppressed
-- SELECT * FROM modal_suppression WHERE user_id = ? AND modal_type = 'instant_modal' AND suppressed_until > NOW();

-- Sample query to insert/update suppression
-- INSERT INTO modal_suppression (user_id, modal_type, suppressed_until) VALUES (?, 'instant_modal', DATE_ADD(NOW(), INTERVAL 24 HOUR))
-- ON DUPLICATE KEY UPDATE suppressed_until = DATE_ADD(NOW(), INTERVAL 24 HOUR);