-- ============================================================
-- Migration: Add extended_by column
-- Description: Tracks individual time extension requests for 
--              tasks assigned to multiple users.
-- ============================================================

ALTER TABLE `studio_assigned_tasks`
ADD COLUMN `extended_by` TEXT DEFAULT NULL COMMENT 'Comma-separated user IDs who extended the deadline' AFTER `extension_count`;
