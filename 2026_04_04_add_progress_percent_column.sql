-- Add task progress percentage for Studio assigned tasks
-- Safe for repeated runs

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'studio_assigned_tasks'
      AND COLUMN_NAME = 'progress_percent'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE studio_assigned_tasks ADD COLUMN progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status',
    'SELECT "progress_percent column already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
