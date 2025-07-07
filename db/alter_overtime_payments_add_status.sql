-- Add status column to overtime_payments table
ALTER TABLE `overtime_payments` 
ADD COLUMN `status` ENUM('paid', 'unpaid') NOT NULL DEFAULT 'unpaid' 
COMMENT 'Payment status' 
AFTER `payment_notes`;

-- Add index for the new status column
CREATE INDEX idx_overtime_payment_status ON overtime_payments(status);

-- Update existing records to have 'paid' status
-- This assumes that all existing records should be considered paid
UPDATE `overtime_payments` SET `status` = 'paid' WHERE `status` IS NULL OR `status` = ''; 