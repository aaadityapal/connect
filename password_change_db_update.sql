-- Add columns to users table for password change requirements
ALTER TABLE users 
ADD COLUMN password_change_required TINYINT(1) DEFAULT 1 COMMENT 'Flag to indicate if password change is required (1=yes, 0=no)',
ADD COLUMN last_password_change DATETIME DEFAULT NULL COMMENT 'Timestamp of last password change';

-- Set all existing users to require password change
UPDATE users SET password_change_required = 1;

-- Create an index for faster queries
CREATE INDEX idx_password_change ON users (password_change_required);

-- Sample query to mark specific users as not requiring password change
-- UPDATE users SET password_change_required = 0, last_password_change = NOW() WHERE id IN (1, 2, 3);
