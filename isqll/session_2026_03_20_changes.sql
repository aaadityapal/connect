-- ============================================================
--  Session Changes SQL
--  Project  : Connect Studio
--  Date     : 2026-03-20
--  Covers   : All schema modifications made in this session
--             (Notifications, Task Assignment, Recurrence)
-- ============================================================


-- ============================================================
-- 1. global_activity_logs — Add is_dismissed column
--    Used for soft-delete of notifications (keeps audit trail)
-- ============================================================
ALTER TABLE global_activity_logs
    ADD COLUMN is_dismissed TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 = user cleared this notification (soft delete)';


-- ============================================================
-- 2. global_activity_logs — Full table reference (with all columns)
--    Run this only on a fresh install; skip if table already exists.
-- ============================================================
CREATE TABLE IF NOT EXISTS global_activity_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NULL,
    action_type VARCHAR(100) NOT NULL  COMMENT 'e.g. punch_in, punch_out, task_assigned, extend_deadline, task_completed, recurrence_extended',
    entity_type VARCHAR(100) NOT NULL  COMMENT 'e.g. attendance, task, leave',
    entity_id   INT          NULL      COMMENT 'ID of the related record',
    description TEXT         NOT NULL  COMMENT 'Human-readable description',
    metadata    JSON         NULL      COMMENT 'Extra data: project_name, stage_number, priority, due_date, task_description, etc.',
    created_at  TIMESTAMP    NOT NULL  DEFAULT CURRENT_TIMESTAMP,
    is_read     TINYINT(1)   NOT NULL  DEFAULT 0 COMMENT '1 = read by the user',
    is_dismissed TINYINT(1)  NOT NULL  DEFAULT 0 COMMENT '1 = cleared/dismissed by the user',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Useful indexes for the notification queries
CREATE INDEX idx_gal_user_dismissed
    ON global_activity_logs (user_id, is_dismissed, created_at DESC);

CREATE INDEX idx_gal_action_type
    ON global_activity_logs (action_type);


-- ============================================================
-- 3. studio_assigned_tasks — Add recurrence_extra column
--    Tracks how many extra recurrence cycles the user has granted.
--    effectiveMax = base_limit × (1 + recurrence_extra)
--
--    Base limits per frequency:
--      Hourly  → until 18:00 same day
--      Daily   → 90  instances
--      Weekly  → 15  instances
--      Monthly → 12  instances
--      Yearly  →  5  instances
-- ============================================================
ALTER TABLE studio_assigned_tasks
    ADD COLUMN recurrence_extra INT NOT NULL DEFAULT 0
        COMMENT 'Extra recurrence cycles added via Extend Recurrence. Each +1 adds one full base-limit batch.';


-- ============================================================
-- 4. Verify columns exist (diagnostic — safe to run anytime)
-- ============================================================
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'global_activity_logs'
  AND COLUMN_NAME  IN ('is_read', 'is_dismissed', 'metadata')
ORDER BY COLUMN_NAME;

SELECT
    COLUMN_NAME,
    DATA_TYPE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'studio_assigned_tasks'
  AND COLUMN_NAME  IN ('is_recurring', 'recurrence_freq', 'recurrence_extra',
                       'recurrence_parent_id', 'custom_freq_value', 'custom_freq_unit')
ORDER BY COLUMN_NAME;


-- ============================================================
-- 5. studio_assigned_tasks — Add carried_over_from column
--    Links a Monday carry-forward task back to its original.
--    NULL means it's a regular task (not carried over).
-- ============================================================
ALTER TABLE studio_assigned_tasks
    ADD COLUMN carried_over_from INT NULL DEFAULT NULL
        COMMENT 'ID of the original task this was carried forward from (incomplete tasks)';


-- ============================================================
-- 6. studio_assigned_tasks — Add Incomplete to status ENUM
--    Original tasks that were not done before Sunday 8 PM
--    get status = Incomplete.
--    A new Monday 08:30 AM copy is created automatically.
-- ============================================================
ALTER TABLE studio_assigned_tasks
    MODIFY COLUMN status
        ENUM('Pending','In Progress','Completed','Cancelled','Incomplete')
        NOT NULL DEFAULT 'Pending';
