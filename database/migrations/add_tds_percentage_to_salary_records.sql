-- ============================================================
-- Migration: Add TDS Percentage to Employee Salary Records
-- Table    : employee_salary_records
-- Date     : 2026-05-06
-- Author   : connect CRM
-- ============================================================

-- Add tds_percentage column right after base_salary
ALTER TABLE `employee_salary_records`
    ADD COLUMN `tds_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00
    COMMENT 'TDS (Tax Deducted at Source) percentage applied on base salary (0.00 - 100.00)'
    AFTER `base_salary`;

-- Verify the column was added
-- SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT, COLUMN_COMMENT
-- FROM INFORMATION_SCHEMA.COLUMNS
-- WHERE TABLE_SCHEMA = 'crm'
--   AND TABLE_NAME   = 'employee_salary_records'
--   AND COLUMN_NAME  = 'tds_percentage';
