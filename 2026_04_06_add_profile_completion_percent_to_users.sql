-- Add profile completion percent to users table
SET @exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'profile_completion_percent'
);

SET @sql := IF(
    @exists = 0,
    'ALTER TABLE users ADD COLUMN profile_completion_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status_changed_date',
    'SELECT "profile_completion_percent column already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
