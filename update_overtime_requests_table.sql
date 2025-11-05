ALTER TABLE `overtime_requests` 
MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected', 'submitted') DEFAULT 'pending';