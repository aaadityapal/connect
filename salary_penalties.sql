CREATE TABLE `salary_penalties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `penalty_month` varchar(7) NOT NULL,
  `penalty_days` float DEFAULT 0,
  `leave_penalty_days` float DEFAULT 0,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `penalty_month` (`penalty_month`),
  CONSTRAINT `salary_penalties_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
); 