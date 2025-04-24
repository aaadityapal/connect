-- Fix attendance table schema to prevent 00:00:00 time issues
-- This script converts punch_in and punch_out columns from TIME to VARCHAR(8)

-- First, check if the columns need to be altered (only if they are TIME type)
SET @db_name = DATABASE();
SELECT CONCAT('Current database: ', @db_name) AS 'Info';

-- Display current column types before changes
SELECT 
    column_name, 
    column_type,
    CONCAT('Column ', column_name, ' is ', column_type) AS 'Before Change'
FROM 
    information_schema.columns 
WHERE 
    table_name = 'attendance' 
    AND table_schema = @db_name
    AND column_name IN ('punch_in', 'punch_out');

-- Fix punch_in column - convert from TIME to VARCHAR(8)
ALTER TABLE attendance 
MODIFY COLUMN punch_in VARCHAR(8) NOT NULL DEFAULT '09:00:00';

-- Fix punch_out column - convert from TIME to VARCHAR(8)
ALTER TABLE attendance 
MODIFY COLUMN punch_out VARCHAR(8) DEFAULT NULL;

-- Display column types after changes
SELECT 
    column_name, 
    column_type,
    CONCAT('Column ', column_name, ' is now ', column_type) AS 'After Change'
FROM 
    information_schema.columns 
WHERE 
    table_name = 'attendance' 
    AND table_schema = @db_name
    AND column_name IN ('punch_in', 'punch_out');

-- Fix existing records with 00:00:00 times
UPDATE attendance SET punch_in = '09:00:00' WHERE punch_in = '00:00:00';

-- Count of records fixed
SELECT 
    CONCAT('Fixed ', COUNT(*), ' records with invalid punch_in times') AS 'Results'
FROM 
    attendance 
WHERE 
    punch_in = '09:00:00';

-- Provide a success message
SELECT 'Schema update completed successfully' AS 'Status'; 