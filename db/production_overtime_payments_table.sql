-- Production-ready SQL for Overtime Payments Table
-- This script creates only the overtime_payments table without foreign key constraints

-- Create overtime_payments table if it doesn't exist
CREATE TABLE IF NOT EXISTS `overtime_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `overtime_id` int(11) NOT NULL COMMENT 'Reference to overtime notification ID',
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
PREPARE stmt FROM @add_index_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt; 