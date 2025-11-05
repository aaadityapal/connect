-- Add 'expired' enum value to attendance table's overtime_status column
ALTER TABLE `attendance` 
MODIFY COLUMN `overtime_status` ENUM('pending', 'approved', 'rejected', 'submitted', 'expired') DEFAULT 'pending';

-- Add 'expired' enum value to overtime_requests table's status column
ALTER TABLE `overtime_requests` 
MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected', 'submitted', 'expired') DEFAULT 'pending';