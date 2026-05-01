-- ============================================================
--  Food Reimbursement Claims — SQL Table
--  File: manager_pages/food_reimbursement_approval/imp.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `food_reimbursement_claims` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `attendance_id`  INT NOT NULL COMMENT 'FK to attendance.id',
    `user_id`        INT NOT NULL COMMENT 'The employee who made the claim',
    `late_minutes`   INT NOT NULL DEFAULT 0,
    `claim_status`   VARCHAR(20) DEFAULT 'draft' COMMENT 'draft, submitted',
    `manager_status` VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, approved, rejected',
    `hr_status`      VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, approved, rejected',
    `payment_status` VARCHAR(20) DEFAULT 'unpaid' COMMENT 'unpaid, paid',
    `amount`         DECIMAL(10,2) DEFAULT NULL,
    `category`       VARCHAR(50) DEFAULT NULL,
    `meal_type`      VARCHAR(50) DEFAULT NULL,
    `vendor_name`    VARCHAR(255) DEFAULT NULL,
    `description`    TEXT DEFAULT NULL,
    `notes`          TEXT DEFAULT NULL,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `uq_attendance` (`attendance_id`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
