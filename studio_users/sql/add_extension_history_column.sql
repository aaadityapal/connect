-- ============================================================
-- Migration: Add extension_history column
-- Table:     studio_assigned_tasks
-- Date:      2026-03-16
-- Description:
--   Adds a JSON column `extension_history` that stores a full
--   per-user, per-extension audit trail directly on the task row.
--
--   Each array entry shape:
--   {
--     "extension_number" : 1,
--     "user_id"          : 5,
--     "user_name"        : "Aditya",
--     "previous_due_date": "2026-03-14",
--     "previous_due_time": "18:00:00",
--     "new_due_date"     : "2026-03-20",
--     "new_due_time"     : "20:00:00",
--     "extended_at"      : "2026-03-14 17:45:00",
--     "days_added"       : 6
--   }
-- ============================================================

ALTER TABLE `studio_assigned_tasks`
    ADD COLUMN `extension_history` JSON DEFAULT NULL
        COMMENT 'Full per-user extension audit trail as a JSON array'
    AFTER `extended_by`;

-- ============================================================
-- Verify column was added
-- ============================================================
-- SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT
-- FROM INFORMATION_SCHEMA.COLUMNS
-- WHERE TABLE_NAME   = 'studio_assigned_tasks'
--   AND COLUMN_NAME  = 'extension_history';
