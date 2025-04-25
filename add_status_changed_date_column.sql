-- SQL to add status_changed_date column to users table
ALTER TABLE users 
ADD COLUMN status_changed_date DATETIME DEFAULT NULL COMMENT 'Records when user status was last changed';

-- Optional: Set existing records with a default value
-- UPDATE users 
-- SET status_changed_date = NOW() 
-- WHERE status_changed_date IS NULL; 