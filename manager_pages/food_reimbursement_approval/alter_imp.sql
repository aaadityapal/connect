-- ============================================================
--  Food Reimbursement Claims — Alter Table
--  File: manager_pages/food_reimbursement_approval/alter_imp.sql
-- ============================================================

ALTER TABLE `food_reimbursement_claims` 
ADD COLUMN `manager_note` TEXT DEFAULT NULL COMMENT 'Optional for approval, required for rejection',
ADD COLUMN `hr_note` TEXT DEFAULT NULL COMMENT 'Optional for approval, required for rejection';
