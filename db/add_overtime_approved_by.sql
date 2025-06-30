-- Add overtime_approved_by column to attendance table
ALTER TABLE `attendance` ADD COLUMN `overtime_approved_by` int(11) DEFAULT NULL COMMENT 'User ID of manager who approved/rejected overtime'; 