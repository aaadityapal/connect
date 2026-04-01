-- ============================================================
-- Purpose: Force new users to change password on first login
-- Adds:
--   - users.must_change_password (0/1)
--   - users.password_changed_at (DATETIME)
--
-- Notes:
-- - Run in phpMyAdmin or MySQL client.
-- - If your MySQL/MariaDB version does not support "IF NOT EXISTS" for
--   columns, run the ALTER statements once only.
-- ============================================================

ALTER TABLE `users`
  ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `users`
  ADD COLUMN `password_changed_at` DATETIME NULL;

-- Optional: If you want to force ALL existing users to change password on next login:
-- UPDATE `users` SET `must_change_password` = 1;

-- Optional: Force only users whose password is still a known default (example only):
-- UPDATE `users`
-- SET `must_change_password` = 1
-- WHERE `password_changed_at` IS NULL;
