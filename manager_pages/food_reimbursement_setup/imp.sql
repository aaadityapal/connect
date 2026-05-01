-- ============================================================
--  Food Reimbursement Setup — SQL Tables
--  File: manager_pages/food_reimbursement_setup/imp.sql
-- ============================================================

-- ------------------------------------------------------------
-- TABLE 1: food_reimbursement_price
-- Stores the per-meal reimbursement amount for each employee.
-- Default is ₹100.00 (applied at application layer).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `food_reimbursement_price` (
    `id`            INT             NOT NULL AUTO_INCREMENT,
    `user_id`       INT             NOT NULL                    COMMENT 'FK → users.id',
    `price_per_meal` DECIMAL(10,2)  NOT NULL DEFAULT 100.00     COMMENT 'Reimbursement amount per late-night meal in ₹',
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE  KEY `uq_frp_user`  (`user_id`),
    KEY             `idx_frp_user` (`user_id`),

    CONSTRAINT `fk_frp_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-employee food reimbursement meal price configuration';


-- ------------------------------------------------------------
-- TABLE 2: food_reimbursement_payment_permissions
-- Controls which users are allowed to mark food
-- reimbursement claims as "Paid".
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `food_reimbursement_payment_permissions` (
    `id`            INT         NOT NULL AUTO_INCREMENT,
    `user_id`       INT         NOT NULL                COMMENT 'FK → users.id',
    `can_mark_paid` TINYINT(1)  NOT NULL DEFAULT 0      COMMENT '1 = allowed to mark claims as Paid, 0 = restricted',
    `created_at`    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE  KEY `uq_frpp_user`  (`user_id`),
    KEY             `idx_frpp_user` (`user_id`),

    CONSTRAINT `fk_frpp_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Controls who can mark food reimbursement claims as Paid';
