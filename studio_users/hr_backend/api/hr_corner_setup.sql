-- ============================================
-- hr_corner_setup.sql
-- Run this once in phpMyAdmin to set up the
-- HR Corner database tables
-- ============================================

CREATE TABLE IF NOT EXISTS `hr_policies` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `heading`       VARCHAR(255)  NOT NULL,
    `short_desc`    VARCHAR(500)  NOT NULL,
    `long_desc`     TEXT          NOT NULL,
    `created_by`    VARCHAR(100)  DEFAULT 'HR Admin',
    `created_at`    DATETIME      DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `is_mandatory`  TINYINT(1)    DEFAULT 1,
    `is_active`     TINYINT(1)    DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hr_notices` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `title`         VARCHAR(255)  NOT NULL,
    `short_desc`    VARCHAR(500)  NOT NULL,
    `long_desc`     TEXT          NOT NULL,
    `attachment`    VARCHAR(500)  DEFAULT NULL,
    `created_by`    VARCHAR(100)  DEFAULT 'HR Admin',
    `created_at`    DATETIME      DEFAULT CURRENT_TIMESTAMP,
    `is_mandatory`  TINYINT(1)    DEFAULT 0,
    `is_active`     TINYINT(1)    DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hr_user_compliance_records` (
    `record_id`       INT AUTO_INCREMENT PRIMARY KEY,
    `user_uid`        VARCHAR(191) NOT NULL,
    `document_type`   VARCHAR(16)  NOT NULL,
    `document_id`     INT          NOT NULL,
    `document_version` DATETIME    NULL,
    `acknowledged_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_user_doc` (`user_uid`, `document_type`, `document_id`),
    KEY `idx_doc` (`document_type`, `document_id`),
    KEY `idx_user` (`user_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hr_customs` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `title`         VARCHAR(255)  NOT NULL,
    `short_desc`    VARCHAR(500)  NOT NULL,
    `long_desc`     TEXT          NOT NULL,
    `created_by`    VARCHAR(100)  DEFAULT 'HR Admin',
    `created_at`    DATETIME      DEFAULT CURRENT_TIMESTAMP,
    `is_active`     TINYINT(1)    DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert a default policy so HR Corner is never empty on first load
INSERT INTO `hr_policies` (`heading`, `short_desc`, `long_desc`) VALUES (
    'Welcome to HR Corner',
    'This section contains all company policies and guidelines.',
    'Please check back regularly for the latest updates from the HR team. New policies will appear here as soon as they are published.'
);
