-- SQL queries to add admin_notes columns to missing punch tables

-- Add admin_notes column to missing_punch_in table
ALTER TABLE `missing_punch_in` 
ADD COLUMN IF NOT EXISTS `admin_notes` TEXT DEFAULT NULL AFTER `status`;

-- Add admin_notes column to missing_punch_out table
ALTER TABLE `missing_punch_out` 
ADD COLUMN IF NOT EXISTS `admin_notes` TEXT DEFAULT NULL AFTER `status`;

-- Verify the columns were added
DESCRIBE `missing_punch_in`;
DESCRIBE `missing_punch_out`;