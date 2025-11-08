-- SQL commands to add 'resubmitted' to the enum values in both tables

-- Update overtime_requests table
ALTER TABLE `overtime_requests` 
MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected', 'submitted', 'expired', 'resubmitted') DEFAULT 'pending';

-- Update attendance table
ALTER TABLE `attendance` 
MODIFY COLUMN `overtime_status` ENUM('pending', 'approved', 'rejected', 'submitted', 'expired', 'resubmitted') DEFAULT 'pending';