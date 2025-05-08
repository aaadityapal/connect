-- SQL fixes for database issues

-- 1. Fix for event_date storing 00:00:00 in hr_supervisor_activity_log table
-- Make sure the column is DATETIME instead of DATE

-- Check if column exists and is DATE type
SELECT COLUMN_NAME, DATA_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'hr_supervisor_activity_log' 
AND COLUMN_NAME = 'event_date';

-- Alter the column to DATETIME if needed
ALTER TABLE hr_supervisor_activity_log MODIFY COLUMN event_date DATETIME;

-- Update existing records with 00:00:00 time
UPDATE hr_supervisor_activity_log 
SET event_date = CONCAT(DATE(event_date), ' ', TIME(NOW())) 
WHERE event_date LIKE '%00:00:00';

-- 2. Fix for location data not being saved in hr_supervisor_material_photo_records

-- Check if columns exist
SELECT COLUMN_NAME, DATA_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'hr_supervisor_material_photo_records' 
AND COLUMN_NAME IN ('latitude', 'longitude', 'location_accuracy', 'location_address');

-- Add or modify the columns
-- For latitude, longitude, location_accuracy, location_address
ALTER TABLE hr_supervisor_material_photo_records 
MODIFY COLUMN latitude DOUBLE NULL DEFAULT NULL,
MODIFY COLUMN longitude DOUBLE NULL DEFAULT NULL,
MODIFY COLUMN location_accuracy DOUBLE NULL DEFAULT NULL,
MODIFY COLUMN location_address TEXT NULL DEFAULT NULL;

-- Verify the column structure
SELECT COLUMN_NAME, DATA_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'hr_supervisor_material_photo_records' 
AND COLUMN_NAME IN ('latitude', 'longitude', 'location_accuracy', 'location_address');

-- If columns don't exist, add them
-- Run these commands if needed
/*
ALTER TABLE hr_supervisor_material_photo_records 
ADD COLUMN latitude DOUBLE NULL DEFAULT NULL,
ADD COLUMN longitude DOUBLE NULL DEFAULT NULL,
ADD COLUMN location_accuracy DOUBLE NULL DEFAULT NULL,
ADD COLUMN location_address TEXT NULL DEFAULT NULL;
*/ 