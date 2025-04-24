-- Convert attendance table time columns back to TIME data type
-- This script converts punch_in and punch_out columns from VARCHAR(8) to TIME

-- First, check current column types
SET @db_name = DATABASE();
SELECT CONCAT('Current database: ', @db_name) AS 'Info';

-- Display current column types before changes
SELECT 
    column_name, 
    column_type,
    CONCAT('Column ', column_name, ' is currently ', column_type) AS 'Before Change'
FROM 
    information_schema.columns 
WHERE 
    table_name = 'attendance' 
    AND table_schema = @db_name
    AND column_name IN ('punch_in', 'punch_out');

-- Make sure all time values are valid before conversion
-- Replace any invalid/empty values with default times
UPDATE attendance SET punch_in = '09:00:00' WHERE punch_in = '00:00:00' OR punch_in IS NULL OR punch_in = '';
UPDATE attendance SET punch_out = '18:00:00' WHERE punch_out = '00:00:00' AND punch_out IS NOT NULL;

-- Convert punch_in column to TIME data type
ALTER TABLE attendance 
MODIFY COLUMN punch_in TIME NOT NULL DEFAULT '09:00:00';

-- Convert punch_out column to TIME data type
ALTER TABLE attendance 
MODIFY COLUMN punch_out TIME DEFAULT NULL;

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

-- Add MySQL configuration to handle zero dates
-- Run this once per session to avoid invalid TIME issues
SET SESSION sql_mode = '';

-- Count of records with default times
SELECT 
    CONCAT('Found ', COUNT(*), ' records with default punch-in times (9:00 AM)') AS 'Time Check'
FROM 
    attendance 
WHERE 
    punch_in = '09:00:00';

-- Provide a success message
SELECT 'Schema update completed successfully - columns are now TIME data type' AS 'Status'; 