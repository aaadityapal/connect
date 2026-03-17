-- SQL to create the user_reporting table
-- Based on DESC user_reporting output
-- Created: 2026-03-17

CREATE TABLE IF NOT EXISTS `user_reporting` (
    `id`             INT(11)     NOT NULL AUTO_INCREMENT,
    `subordinate_id` INT(11)     NOT NULL,
    `manager_id`     INT(11)     NOT NULL,
    PRIMARY KEY (`id`),
    KEY `subordinate_id` (`subordinate_id`),
    KEY `manager_id` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
