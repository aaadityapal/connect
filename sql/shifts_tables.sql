-- Create shifts table
CREATE TABLE IF NOT EXISTS `shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shift_name` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create user_shifts table
CREATE TABLE IF NOT EXISTS `user_shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `weekly_offs` varchar(100) DEFAULT 'Saturday,Sunday',
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `shift_id` (`shift_id`),
  CONSTRAINT `user_shifts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_shifts_ibfk_2` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default shifts if table is empty
INSERT INTO `shifts` (`shift_name`, `start_time`, `end_time`)
SELECT 'Morning Shift', '09:00:00', '18:00:00'
WHERE NOT EXISTS (SELECT 1 FROM `shifts` LIMIT 1);

INSERT INTO `shifts` (`shift_name`, `start_time`, `end_time`)
SELECT 'Afternoon Shift', '12:00:00', '21:00:00'
WHERE EXISTS (SELECT 1 FROM `shifts` LIMIT 1);

INSERT INTO `shifts` (`shift_name`, `start_time`, `end_time`)
SELECT 'Night Shift', '22:00:00', '07:00:00'
WHERE EXISTS (SELECT 1 FROM `shifts` LIMIT 1); 