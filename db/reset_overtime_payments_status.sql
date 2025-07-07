-- Reset Overtime Payments Status
-- This script resets all overtime payments to 'unpaid' status for testing purposes

-- First, check if the table exists
SET @table_exists = (
    SELECT COUNT(*) 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'overtime_payments'
);

-- If the table exists, update all records to 'unpaid'
SET @sql = IF(@table_exists > 0, 
              'UPDATE overtime_payments SET status = "unpaid"', 
              'SELECT "Table does not exist" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Show the result
SELECT 'All overtime payments have been reset to unpaid status' AS result;

-- Also show the current status of payments
SELECT id, overtime_id, employee_id, status, payment_date
FROM overtime_payments
ORDER BY id DESC
LIMIT 10; 