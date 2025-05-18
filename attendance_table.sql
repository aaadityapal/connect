-- Attendance table creation script
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `punch_in` time DEFAULT NULL,
  `punch_out` time DEFAULT NULL,
  `work_report` text DEFAULT NULL,
  `auto_punch_out` tinyint(1) DEFAULT 0,
  `location` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  `image_data` longtext DEFAULT NULL,
  `latitude` varchar(20) DEFAULT NULL,
  `longitude` varchar(20) DEFAULT NULL,
  `accuracy` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `overtime` tinyint(1) DEFAULT 0,
  `status` enum('present','absent','half_day','leave','holiday') DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `working_hours` decimal(5,2) DEFAULT NULL,
  `overtime_hours` decimal(5,2) DEFAULT NULL,
  `shift_time` varchar(50) DEFAULT NULL,
  `weekly_offs` varchar(50) DEFAULT NULL,
  `is_weekly_off` tinyint(1) DEFAULT 0,
  `punch_in_photo` varchar(255) DEFAULT NULL,
  `punch_in_latitude` varchar(20) DEFAULT NULL,
  `punch_in_longitude` varchar(20) DEFAULT NULL,
  `punch_in_accuracy` varchar(20) DEFAULT NULL,
  `punch_out_photo` varchar(255) DEFAULT NULL,
  `punch_out_latitude` varchar(20) DEFAULT NULL,
  `punch_out_longitude` varchar(20) DEFAULT NULL,
  `punch_out_accuracy` varchar(20) DEFAULT NULL,
  `shifts_id` int(11) DEFAULT NULL,
  `punch_out_address` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `date` (`date`),
  KEY `shifts_id` (`shifts_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_attendance_user_date ON attendance (user_id, date);
CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance (date);
CREATE INDEX IF NOT EXISTS idx_attendance_status ON attendance (status);

-- Add foreign key constraint if users table exists
-- ALTER TABLE `attendance` ADD CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Add foreign key constraint if shifts table exists
-- ALTER TABLE `attendance` ADD CONSTRAINT `fk_attendance_shifts` FOREIGN KEY (`shifts_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE; 