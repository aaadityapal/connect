-- ============================================================
-- Table: studio_assigned_tasks
-- Description: Stores tasks assigned via the Studio Users 
--              "Assign Task" card on the dashboard.
-- Created: 2026-03-13
-- ============================================================

CREATE TABLE IF NOT EXISTS `studio_assigned_tasks` (
    `id`                INT(11)         NOT NULL AUTO_INCREMENT,

    -- Project & Stage
    `project_id`        INT(11)         DEFAULT NULL COMMENT 'FK to projects.id',
    `project_name`      VARCHAR(255)    DEFAULT NULL COMMENT 'Snapshot of project title at time of assignment',
    `stage_id`          INT(11)         DEFAULT NULL COMMENT 'FK to project_stages.id',
    `stage_number`      VARCHAR(100)    DEFAULT NULL COMMENT 'Snapshot of stage number at time of assignment',

    -- Task Details
    `task_description`  TEXT            NOT NULL COMMENT 'The task body / instructions',
    `priority`          ENUM('Low','Medium','High') NOT NULL DEFAULT 'Low',

    -- Assignees (stored as comma-separated user IDs for flexibility)
    `assigned_to`       TEXT            DEFAULT NULL COMMENT 'Comma-separated user IDs from users.id',
    `assigned_names`    TEXT            DEFAULT NULL COMMENT 'Comma-separated user names (snapshot)',

    -- Deadline
    `due_date`          DATE            DEFAULT NULL,
    `due_time`          TIME            DEFAULT NULL COMMENT 'Deadline time',
    `extension_count`   INT(11)         DEFAULT 0    COMMENT 'Number of times deadline was extended',
    `previous_due_date` DATE            DEFAULT NULL COMMENT 'The deadline date before the most recent extension',
    `previous_due_time` TIME            DEFAULT NULL COMMENT 'The deadline time before the most recent extension',

    -- Recurrence
    `is_recurring`      TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '0 = No, 1 = Yes',
    `recurrence_freq`   VARCHAR(50)     DEFAULT NULL COMMENT 'e.g. Hourly, Daily, Weekly, Monthly, Yearly, Custom',
    `custom_freq_value` INT(11)         DEFAULT NULL COMMENT 'Used for Custom recurrence number',
    `custom_freq_unit`  VARCHAR(20)     DEFAULT NULL COMMENT 'Used for Custom recurrence unit (minute, hour, day...)',

    -- Status
    `status`            ENUM('Pending','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    `completed_at`      DATETIME        DEFAULT NULL COMMENT 'Exact time the task was marked completed',

    -- Audit
    `created_by`        INT(11)         NOT NULL COMMENT 'FK to users.id — who assigned the task',
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `updated_by`        INT(11)         DEFAULT NULL COMMENT 'FK to users.id — who last edited',
    `deleted_at`        DATETIME        DEFAULT NULL COMMENT 'Soft delete timestamp',
    `deleted_by`        INT(11)         DEFAULT NULL,

    PRIMARY KEY (`id`),

    -- Indexes for common lookups
    KEY `idx_project_id`  (`project_id`),
    KEY `idx_stage_id`    (`stage_id`),
    KEY `idx_created_by`  (`created_by`),
    KEY `idx_status`      (`status`),
    KEY `idx_due_date`    (`due_date`),

    -- Foreign Key Constraints
    CONSTRAINT `fk_sat_project` FOREIGN KEY (`project_id`)  REFERENCES `projects`(`id`)        ON DELETE SET NULL,
    CONSTRAINT `fk_sat_stage`   FOREIGN KEY (`stage_id`)    REFERENCES `project_stages`(`id`)  ON DELETE SET NULL,
    CONSTRAINT `fk_sat_creator` FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)           ON DELETE RESTRICT,
    CONSTRAINT `fk_sat_editor`  FOREIGN KEY (`updated_by`)  REFERENCES `users`(`id`)           ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores tasks assigned via the Studio Users dashboard Assign Task card';

-- Migration snippet for extending deadline feature:
-- ALTER TABLE `studio_assigned_tasks`
--   ADD COLUMN `extension_count` INT(11) DEFAULT 0 AFTER `due_time`,
--   ADD COLUMN `previous_due_date` DATE DEFAULT NULL AFTER `extension_count`,
--   ADD COLUMN `previous_due_time` TIME DEFAULT NULL AFTER `previous_due_date`;

-- Migration snippet for completion tracking:
-- ALTER TABLE `studio_assigned_tasks`
--   ADD COLUMN `completed_at` DATETIME DEFAULT NULL AFTER `status`;
