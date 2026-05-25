ALTER TABLE `sequences` 
ADD COLUMN `stop_on_reply` BOOLEAN DEFAULT TRUE AFTER `is_persistent`;

ALTER TABLE `sequence_steps`
MODIFY COLUMN `template_id` INT(11) DEFAULT NULL,
DROP FOREIGN KEY IF EXISTS `sequence_steps_ibfk_2`; -- Or whatever the FK name is; if it fails, ignore.

ALTER TABLE `sequence_steps`
ADD COLUMN `template_language` VARCHAR(20) DEFAULT 'en_US' AFTER `template_name`,
ADD COLUMN `header_type` VARCHAR(50) DEFAULT 'NONE' AFTER `delay_unit`,
ADD COLUMN `media_path` VARCHAR(255) DEFAULT NULL AFTER `header_type`,
ADD COLUMN `media_filename` VARCHAR(255) DEFAULT NULL AFTER `media_path`;

ALTER TABLE `templates`
ADD COLUMN `language` VARCHAR(20) DEFAULT 'en_US' AFTER `category`;
