-- Add all overtime columns to attendance table

-- Add overtime_status column
ALTER TABLE `attendance` ADD COLUMN `overtime_status` enum('pending','submitted','approved','rejected') DEFAULT 'pending' COMMENT 'Status of overtime request';

-- Add overtime_approved_by column
ALTER TABLE `attendance` ADD COLUMN `overtime_approved_by` int(11) DEFAULT NULL COMMENT 'User ID of manager who approved/rejected overtime';

-- Add overtime_actioned_at column
ALTER TABLE `attendance` ADD COLUMN `overtime_actioned_at` datetime DEFAULT NULL COMMENT 'When overtime was approved/rejected'; 