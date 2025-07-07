-- Drop foreign key constraint on overtime_id column
-- This script removes the foreign key constraint that is causing issues with the payment process

-- First, check if the constraint exists
SET @constraint_name = (
    SELECT CONSTRAINT_NAME 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'overtime_payments' 
    AND COLUMN_NAME = 'overtime_id'
    AND REFERENCED_TABLE_NAME = 'overtime_notifications'
    LIMIT 1
);

-- If the constraint exists, drop it
SET @sql = IF(@constraint_name IS NOT NULL, 
              CONCAT('ALTER TABLE overtime_payments DROP FOREIGN KEY ', @constraint_name), 
              'SELECT "No constraint found" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add a comment to confirm completion
SELECT 'Foreign key constraint dropped or not found' AS result; 