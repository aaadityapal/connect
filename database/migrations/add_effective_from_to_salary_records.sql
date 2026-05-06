-- ============================================================
-- Migration: Add separate effective_from dates for
--            Base Salary and TDS Percentage
-- Table    : employee_salary_records
-- Date     : 2026-05-06
-- Author   : connect CRM
-- ============================================================

-- Separate effective date for Base Salary
ALTER TABLE `employee_salary_records`
    ADD COLUMN `base_salary_effective_from` DATE NULL DEFAULT NULL
    COMMENT 'Date from which the base salary is effective'
    AFTER `base_salary`;

-- Separate effective date for TDS Percentage
ALTER TABLE `employee_salary_records`
    ADD COLUMN `tds_effective_from` DATE NULL DEFAULT NULL
    COMMENT 'Date from which the TDS percentage is effective'
    AFTER `tds_percentage`;

-- Verify columns were added
-- SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT
-- FROM INFORMATION_SCHEMA.COLUMNS
-- WHERE TABLE_SCHEMA = 'crm'
--   AND TABLE_NAME   = 'employee_salary_records'
--   AND COLUMN_NAME  IN ('base_salary_effective_from', 'tds_effective_from')
-- ORDER BY ORDINAL_POSITION;
