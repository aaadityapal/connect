-- Create overtime_notification table
CREATE TABLE IF NOT EXISTS `overtime_notification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `overtime_id` int(11) NOT NULL COMMENT 'Reference to attendance.id',
  `employee_id` int(11) NOT NULL COMMENT 'User ID of employee',
  `manager_id` int(11) NOT NULL COMMENT 'User ID of manager',
  `message` varchar(255) NOT NULL COMMENT 'Notification message',
  `status` enum('approved','rejected','pending') NOT NULL DEFAULT 'pending' COMMENT 'Status of overtime',
  `manager_response` text COMMENT 'Manager response/reason',
  `created_at` datetime NOT NULL COMMENT 'Timestamp of creation',
  `read_at` datetime DEFAULT NULL COMMENT 'Timestamp when read by employee',
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `manager_id` (`manager_id`),
  KEY `overtime_id` (`overtime_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Notifications for overtime approvals/rejections'; 