-- Geofence approval manager mapping
-- Maps each employee to one or more managers who should receive
-- Conneqts Bot approval tasks when punch-in/punch-out happens outside geofence radius.

CREATE TABLE IF NOT EXISTS `geofence_approval_mapping` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `manager_id` INT NOT NULL,
    `employee_id` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_geofence_manager_employee` (`manager_id`, `employee_id`),
    KEY `idx_geofence_manager` (`manager_id`),
    KEY `idx_geofence_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;