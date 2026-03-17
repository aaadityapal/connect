-- ============================================================
-- Migration: Add completion_history column
-- Table:     studio_assigned_tasks
-- Date:      2026-03-16
-- Description:
--   Adds a JSON column `completion_history` to track exactly 
--   when each individual user marked their part of the task 
--   as completed. This enables individual 30-minute undo timers.
--
--   Format: { "userId": "YYYY-MM-DD HH:MM:SS", ... }
-- ============================================================

ALTER TABLE `studio_assigned_tasks`
    ADD COLUMN `completion_history` JSON DEFAULT NULL
        COMMENT 'Map of user_id to exact completion timestamp'
    AFTER `completed_by`;
