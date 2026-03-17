-- ============================================================
-- Migration: Add completed_by column
-- Description: Tracks individual completion statuses for tasks 
--              assigned to multiple users.
-- ============================================================

ALTER TABLE `studio_assigned_tasks`
ADD COLUMN `completed_by` TEXT DEFAULT NULL COMMENT 'Comma-separated user IDs who marked it done' AFTER `completed_at`;
