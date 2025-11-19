-- Migration: Populate backup_password column for all existing users
-- This file stores the bcrypt hash of "@rchitectshive@750" in the backup_password column
-- Run this file in your MySQL client (e.g. via phpMyAdmin, MySQL CLI, or command line)

-- Update all users with the hashed backup password
UPDATE `users`
SET `backup_password` = '$2y$10$inY/0aARoHB7jMDMG1y7b.1LFlCSkvOEQGv9.fw9Tr93ycV2L6IOy'
WHERE `backup_password` IS NULL;

-- Verify the update (optional - run this to check):
-- SELECT id, username, email, backup_password FROM users LIMIT 10;
