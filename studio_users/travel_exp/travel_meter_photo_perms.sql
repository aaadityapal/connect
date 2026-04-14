-- ============================================================
--  Travel Meter Photo Permissions Table
--  Stores per-user, per-mode meter photo requirement flags.
--  If a row exists for (user_id, mode) => that user MUST upload
--  meter start + end photos when selecting that transport mode.
-- ============================================================

CREATE TABLE IF NOT EXISTS `travel_meter_photo_perms` (
    `id`         INT             NOT NULL AUTO_INCREMENT,
    `user_id`    INT             NOT NULL,
    `mode`       VARCHAR(50)     NOT NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_mode` (`user_id`, `mode`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  Grant the new sidebar menu access to admin by default.
--  You can then use Sidebar Access page to grant it to others.
-- ============================================================
INSERT IGNORE INTO `sidebar_permissions` (`role`, `menu_id`, `can_access`)
VALUES ('admin', 'travel-meter-permissions', 1);
