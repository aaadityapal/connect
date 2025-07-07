-- Production-ready SQL for Overtime Management System
-- This script creates the necessary tables for overtime management without problematic foreign key constraints

-- Create overtime_notifications table if it doesn't exist
CREATE TABLE IF NOT EXISTS `overtime_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `overtime_id` int(11) NOT NULL COMMENT 'Reference to attendance record ID',
  `employee_id` int(11) NOT NULL COMMENT 'User who sent the notification',
  `manager_id` int(11) NOT NULL COMMENT 'Manager who received the notification',
  `message` text COMMENT 'Optional message from employee',
  `status` enum('unread','read','actioned','paid') NOT NULL DEFAULT 'unread',
  `manager_response` text DEFAULT NULL COMMENT 'Optional response from manager',
  `created_at` datetime NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `actioned_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `overtime_id` (`overtime_id`),
  KEY `employee_id` (`employee_id`),
  KEY `manager_id` (`manager_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores notifications when employees send overtime reports to managers';

-- Create overtime_payments table if it doesn't exist
CREATE TABLE IF NOT EXISTS `overtime_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `overtime_id` int(11) NOT NULL COMMENT 'Reference to overtime_notifications table',
  `employee_id` int(11) NOT NULL COMMENT 'User ID of the employee',
  `processed_by` int(11) NOT NULL COMMENT 'User ID of the HR who processed the payment',
  `amount` decimal(10,2) NOT NULL COMMENT 'Payment amount in rupees',
  `hours` decimal(4,2) NOT NULL COMMENT 'Number of overtime hours paid',
  `payment_date` date NOT NULL COMMENT 'Date when payment was processed',
  `payment_notes` text COMMENT 'Additional notes about the payment',
  `status` enum('paid','unpaid') NOT NULL DEFAULT 'unpaid' COMMENT 'Payment status',
  `included_in_payroll` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether payment was included in payroll',
  `payroll_date` date DEFAULT NULL COMMENT 'Date of payroll inclusion',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `overtime_id` (`overtime_id`),
  KEY `employee_id` (`employee_id`),
  KEY `processed_by` (`processed_by`),
  KEY `payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores records of overtime payments processed by HR';

-- Add overtime related columns to attendance table if they don't exist
-- First check if the overtime_status column exists in the attendance table
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'attendance' 
AND column_name = 'overtime_status';

-- Add overtime_status column only if it doesn't exist
SET @add_status_sql = IF(@column_exists = 0, 
    'ALTER TABLE `attendance` ADD COLUMN `overtime_status` enum("pending","submitted","approved","rejected") DEFAULT "pending" COMMENT "Overtime approval status" AFTER `work_report`', 
    'SELECT "overtime_status column already exists"');
PREPARE stmt FROM @add_status_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- First check if the overtime_approved_by column exists in the attendance table
SET @column_exists2 = 0;
SELECT COUNT(*) INTO @column_exists2 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'attendance' 
AND column_name = 'overtime_approved_by';

-- Add overtime_approved_by column only if it doesn't exist
SET @add_approver_sql = IF(@column_exists2 = 0, 
    'ALTER TABLE `attendance` ADD COLUMN `overtime_approved_by` int(11) DEFAULT NULL COMMENT "Manager who approved/rejected overtime" AFTER `overtime_status`', 
    'SELECT "overtime_approved_by column already exists"');
PREPARE stmt2 FROM @add_approver_sql;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- First check if the overtime_actioned_at column exists in the attendance table
SET @column_exists3 = 0;
SELECT COUNT(*) INTO @column_exists3 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'attendance' 
AND column_name = 'overtime_actioned_at';

-- Add overtime_actioned_at column only if it doesn't exist
SET @add_actioned_sql = IF(@column_exists3 = 0, 
    'ALTER TABLE `attendance` ADD COLUMN `overtime_actioned_at` datetime DEFAULT NULL COMMENT "When overtime was approved/rejected" AFTER `overtime_approved_by`', 
    'SELECT "overtime_actioned_at column already exists"');
PREPARE stmt3 FROM @add_actioned_sql;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- Add an index to help with querying payments by date range if it doesn't exist
SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'overtime_payments' 
AND index_name = 'idx_overtime_payment_date';

SET @add_index_sql = IF(@index_exists = 0, 
    'CREATE INDEX idx_overtime_payment_date ON overtime_payments(payment_date)', 
    'SELECT "Payment date index already exists"');
PREPARE stmt4 FROM @add_index_sql;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4; 