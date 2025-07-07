-- Overtime Payments Table
-- This table stores records of overtime payments processed by HR
-- It references the overtime_notifications table for overtime details

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
  CONSTRAINT `fk_overtime_payment_overtime` FOREIGN KEY (`overtime_id`) REFERENCES `overtime_notifications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_overtime_payment_employee` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_overtime_payment_processor` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add an index to help with querying payments by date range
CREATE INDEX idx_overtime_payment_date ON overtime_payments(payment_date);

-- Add an index to help with querying payments by payroll status
CREATE INDEX idx_overtime_payment_payroll ON overtime_payments(included_in_payroll, payroll_date);

-- Add an index to help with querying payments by payment status
CREATE INDEX idx_overtime_payment_status ON overtime_payments(status); 