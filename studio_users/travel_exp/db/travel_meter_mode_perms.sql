-- ============================================================
--  TRAVEL METER MODE PERMISSIONS - SQL Setup
--  File: studio_users/travel_exp/db/travel_meter_mode_perms.sql
--  Run this in phpMyAdmin before using the new page.
-- ============================================================

-- ──────────────────────────────────────────────────────────
-- STEP 1: Add the `mode` column to travel_meter_mode_config
--         (only if it does not already exist)
-- ──────────────────────────────────────────────────────────
ALTER TABLE `travel_meter_mode_config`
    ADD COLUMN `mode` VARCHAR(30) NOT NULL DEFAULT 'Bike'
        COMMENT 'Transport mode: Bike or Car'
        AFTER `user_id`;

-- ──────────────────────────────────────────────────────────
-- STEP 2: Drop the old primary/unique key on just user_id
--         and replace with a composite unique on (user_id, mode)
--         so each user can have one row per mode.
--
--  NOTE: If your table already had PRIMARY KEY on user_id,
--        run the ALTER below. If it throws an error about the
--        key not existing, just skip this block safely.
-- ──────────────────────────────────────────────────────────

-- 2a. Remove old unique/primary key on user_id alone (if it exists)
ALTER TABLE `travel_meter_mode_config`
    DROP PRIMARY KEY;

-- 2b. Add a new auto-increment primary key + unique composite key
ALTER TABLE `travel_meter_mode_config`
    ADD COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
    ADD PRIMARY KEY (`id`),
    ADD UNIQUE KEY `uq_user_mode` (`user_id`, `mode`);

-- ──────────────────────────────────────────────────────────
-- STEP 3: If STEP 2 fails (table has different structure),
--         use this FULL CREATE as a fallback (run manually)
-- ──────────────────────────────────────────────────────────
/*
CREATE TABLE IF NOT EXISTS `travel_meter_mode_config` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `user_id`     INT             NOT NULL,
    `mode`        VARCHAR(30)     NOT NULL DEFAULT 'Bike'
                  COMMENT 'Transport mode: Bike or Car',
    `meter_mode`  TINYINT(1)      NOT NULL DEFAULT 0
                  COMMENT '1 = use uploaded meter photos, 0 = use punch-in/out attendance photos',
    `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_mode` (`user_id`, `mode`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-user, per-mode meter photo source configuration (Bike/Car)';
*/

-- ──────────────────────────────────────────────────────────
-- STEP 4: Seed default rows for Bike and Car for all users
--         who have existing rows (sets mode = 'Bike' for them
--         if they were the old single-row style)
-- ──────────────────────────────────────────────────────────
-- Ensure every existing user also has a Car row (defaulting OFF)
INSERT IGNORE INTO `travel_meter_mode_config` (`user_id`, `mode`, `meter_mode`)
SELECT `user_id`, 'Car', 0
FROM   `travel_meter_mode_config`
WHERE  `mode` = 'Bike';

-- ──────────────────────────────────────────────────────────
-- STEP 5: Register the sidebar permission menu entry
--         so it appears in Sidebar Role Access config.
--         Admin always sees it; grant other roles via that page.
-- ──────────────────────────────────────────────────────────
INSERT IGNORE INTO `sidebar_permissions` (`role`, `menu_id`, `can_access`)
VALUES ('admin', 'travel-meter-mode-permissions', 1);

-- ──────────────────────────────────────────────────────────
-- Done. Summary of what this script does:
--  1. Adds `mode` column (VARCHAR 30) to travel_meter_mode_config
--  2. Restructures the key to UNIQUE on (user_id, mode)
--  3. Seeds a Car row for any user who had an existing Bike row
--  4. Grants admin access to the new sidebar menu item
-- ──────────────────────────────────────────────────────────
