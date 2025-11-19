-- Migration: Add backup_password column to users
-- Run this file in your MySQL client (e.g. via phpMyAdmin, MySQL CLI, or a migration tool)

ALTER TABLE `users`
  ADD COLUMN `backup_password` VARCHAR(255) NULL AFTER `password`;

-- NOTE:
-- This SQL adds the column only. You should populate the column with a bcrypt hash
-- of the shared backup password ("@rchitectshive@750").
-- PHP's `password_hash()` must be used to create a hash compatible with PHP's
-- `password_verify()` used in the application. See the companion PHP script:
-- `scripts/populate_backup_passwords.php` that performs the population safely.
