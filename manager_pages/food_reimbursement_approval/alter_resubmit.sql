-- ============================================================
--  Food Reimbursement Claims — Alter Table for Resubmissions
--  File: manager_pages/food_reimbursement_approval/alter_resubmit.sql
-- ============================================================

ALTER TABLE `food_reimbursement_claims` 
ADD COLUMN `resubmit_count` INT NOT NULL DEFAULT 0 COMMENT 'Tracks number of times a rejected claim is resubmitted (max 3)';
