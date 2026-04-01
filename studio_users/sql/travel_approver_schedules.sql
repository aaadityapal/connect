-- SQL Schema for Travel Approver Schedules
-- Table: travel_approver_schedules
-- Purpose: Defines the days and specific times each individual approver (Manager, HR, Senior Manager) can approve/reject travel expenses.

CREATE TABLE IF NOT EXISTS `travel_approver_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `active_days` varchar(255) DEFAULT 'Monday,Tuesday,Wednesday,Thursday,Friday',
  `start_time` time DEFAULT '09:00:00',
  `end_time` time DEFAULT '18:00:00',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_tas_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
