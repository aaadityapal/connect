-- ============================================================================
-- ALTER TABLE SCRIPTS - ADD "PAID VIA" COLUMN
-- For existing databases that need to be updated
-- ============================================================================
-- Execute these scripts to add the "Paid Via" column to existing tables
-- ============================================================================

-- ============================================================================
-- 1. ALTER TABLE: tbl_payment_entry_line_items_detail
-- Add column for storing which user processed the line item payment
-- ============================================================================
ALTER TABLE `tbl_payment_entry_line_items_detail`
ADD COLUMN `line_item_paid_via_user_id` INT COMMENT 'User who processed this line item payment' AFTER `line_item_payment_mode`,
ADD FOREIGN KEY (`line_item_paid_via_user_id`) REFERENCES `users`(`id`),
ADD INDEX `idx_paid_via_user` (`line_item_paid_via_user_id`);

-- ============================================================================
-- Verify the column was added successfully
-- ============================================================================
-- Run this query to verify the new column exists:
-- SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_COMMENT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'tbl_payment_entry_line_items_detail' 
-- AND COLUMN_NAME = 'line_item_paid_via_user_id';

-- ============================================================================
-- Verify the foreign key was created
-- ============================================================================
-- Run this query to verify the foreign key:
-- SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
-- FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
-- WHERE TABLE_NAME = 'tbl_payment_entry_line_items_detail' 
-- AND COLUMN_NAME = 'line_item_paid_via_user_id';

-- ============================================================================
-- Summary of changes
-- ============================================================================
-- Column Added: line_item_paid_via_user_id (INT, nullable)
-- Foreign Key: Links to users table (id column)
-- Index Added: idx_paid_via_user for performance optimization
-- Location: tbl_payment_entry_line_items_detail table
-- Purpose: Stores the user ID of who processed/authorized each line item payment
-- ============================================================================
