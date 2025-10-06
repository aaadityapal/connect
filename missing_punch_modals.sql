-- SQL file for missing punch modals data storage
-- This file creates tables to store missing punch-in and missing punch-out data with pending status

-- Create table for missing punch-in records
CREATE TABLE IF NOT EXISTS `missing_punch_in` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `punch_in_time` time NOT NULL,
  `reason` text NOT NULL,
  `confirmed` tinyint(1) DEFAULT 0,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `date` (`date`),
  KEY `status` (`status`),
  CONSTRAINT `fk_missing_punch_in_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create table for missing punch-out records
CREATE TABLE IF NOT EXISTS `missing_punch_out` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `punch_out_time` time NOT NULL,
  `reason` text NOT NULL,
  `work_report` text NOT NULL,
  `confirmed` tinyint(1) DEFAULT 0,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `date` (`date`),
  KEY `status` (`status`),
  CONSTRAINT `fk_missing_punch_out_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add columns to existing attendance table if they don't exist
-- These columns will be used to track missing punch requests
ALTER TABLE `attendance` 
ADD COLUMN IF NOT EXISTS `missing_punch_in_id` int(11) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `missing_punch_out_id` int(11) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `missing_punch_reason` text DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `missing_punch_out_reason` text DEFAULT NULL,
ADD COLUMN IF NOT EXISTS `missing_punch_approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
ADD KEY IF NOT EXISTS `idx_missing_punch_in_id` (`missing_punch_in_id`),
ADD KEY IF NOT EXISTS `idx_missing_punch_out_id` (`missing_punch_out_id`),
ADD KEY IF NOT EXISTS `idx_missing_punch_approval_status` (`missing_punch_approval_status`);

-- Add foreign key constraints for the new columns
-- Note: These will only work if the missing_punch_in and missing_punch_out tables are created first
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
ALTER TABLE `attendance` 
ADD CONSTRAINT IF NOT EXISTS `fk_attendance_missing_punch_in` FOREIGN KEY (`missing_punch_in_id`) REFERENCES `missing_punch_in` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT IF NOT EXISTS `fk_attendance_missing_punch_out` FOREIGN KEY (`missing_punch_out_id`) REFERENCES `missing_punch_out` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_missing_punch_in_user_date` ON `missing_punch_in` (`user_id`, `date`);
CREATE INDEX IF NOT EXISTS `idx_missing_punch_out_user_date` ON `missing_punch_out` (`user_id`, `date`);

-- Sample queries for inserting data (for reference only - no actual data inserted)
-- INSERT INTO missing_punch_in (user_id, date, punch_in_time, reason, confirmed) VALUES (1, '2025-10-05', '09:00:00', 'Reason for missing punch-in', 1);
-- INSERT INTO missing_punch_out (user_id, date, punch_out_time, reason, work_report, confirmed) VALUES (1, '2025-10-05', '18:00:00', 'Reason for missing punch-out', 'Work report for the day', 1);