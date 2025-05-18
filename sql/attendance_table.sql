-- Attendance Table Structure
-- This table stores the attendance records for users including punch in/out times

-- Drop table if it exists (only for setup/migration)
-- DROP TABLE IF EXISTS `attendance`;

-- Create the attendance table
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `punch_in` time DEFAULT NULL,
  `punch_out` time DEFAULT NULL,
  `working_hours` decimal(5,2) DEFAULT NULL,
  `overtime_hours` decimal(5,2) DEFAULT NULL,
  `location` text DEFAULT NULL,
  `ip_address` varchar(100) DEFAULT NULL,
  `device_info` text DEFAULT NULL,
  `latitude` decimal(10,6) DEFAULT NULL,
  `longitude` decimal(10,6) DEFAULT NULL,
  `accuracy` decimal(10,2) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('present','absent','late','half_day', 'weekend', 'holiday') DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `punch_in_photo` varchar(255) DEFAULT NULL,
  `punch_out_photo` varchar(255) DEFAULT NULL,
  `punch_in_latitude` decimal(10,6) DEFAULT NULL,
  `punch_in_longitude` decimal(10,6) DEFAULT NULL,
  `punch_in_accuracy` decimal(10,2) DEFAULT NULL,
  `punch_out_latitude` decimal(10,6) DEFAULT NULL,
  `punch_out_longitude` decimal(10,6) DEFAULT NULL,
  `punch_out_accuracy` decimal(10,2) DEFAULT NULL,
  `punch_in_address` text DEFAULT NULL,
  `punch_out_address` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `date` (`date`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add indexes for better query performance
CREATE INDEX IF NOT EXISTS `idx_attendance_user_date` ON `attendance` (`user_id`, `date`); 