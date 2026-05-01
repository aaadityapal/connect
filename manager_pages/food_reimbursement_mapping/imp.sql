CREATE TABLE IF NOT EXISTS `food_reimbursement_mapping` (
    `id`          INT            NOT NULL AUTO_INCREMENT,
    `employee_id` INT            NOT NULL                  COMMENT 'FK → users.id (the claimant)',
    `manager_id`  INT            DEFAULT NULL              COMMENT 'Level-1 approver → users.id (Direct Manager)',
    `hr_id`       INT            DEFAULT NULL              COMMENT 'Level-2 approver → users.id (HR Approver)',
    `created_at`  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE  KEY `uq_employee`  (`employee_id`),
    KEY           `idx_manager` (`manager_id`),
    KEY           `idx_hr`      (`hr_id`),

    CONSTRAINT `fk_frm_employee` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_frm_manager`  FOREIGN KEY (`manager_id`)  REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_frm_hr`       FOREIGN KEY (`hr_id`)       REFERENCES `users` (`id`) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Maps each employee to their food reimbursement approval chain (Manager → HR)';
