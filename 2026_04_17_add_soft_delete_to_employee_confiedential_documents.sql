ALTER TABLE `employee_confiedential_documents`
    ADD COLUMN IF NOT EXISTS `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `file_mime`,
    ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME NULL AFTER `is_deleted`,
    ADD COLUMN IF NOT EXISTS `deleted_by` INT NULL AFTER `deleted_at`;

ALTER TABLE `employee_confiedential_documents`
    ADD INDEX `idx_emp_conf_docs_is_deleted` (`is_deleted`);

ALTER TABLE `employee_confiedential_documents`
    ADD CONSTRAINT `fk_emp_conf_docs_deleted_by`
    FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;
