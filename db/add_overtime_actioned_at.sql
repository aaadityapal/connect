-- Add overtime_actioned_at column to attendance table
ALTER TABLE `attendance` ADD COLUMN `overtime_actioned_at` datetime DEFAULT NULL COMMENT 'When overtime was approved/rejected'; 