-- Create overtime_notifications table
CREATE TABLE IF NOT EXISTS `overtime_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `overtime_id` int(11) NOT NULL COMMENT 'Reference to attendance record ID',
  `employee_id` int(11) NOT NULL COMMENT 'User who sent the notification',
  `manager_id` int(11) NOT NULL COMMENT 'Manager who received the notification',
  `message` text COMMENT 'Optional message from employee',
  `status` enum('unread','read','actioned') NOT NULL DEFAULT 'unread',
  `manager_response` text DEFAULT NULL COMMENT 'Optional response from manager',
  `created_at` datetime NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `actioned_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `overtime_id` (`overtime_id`),
  KEY `employee_id` (`employee_id`),
  KEY `manager_id` (`manager_id`),
  KEY `status` (`status`),
  CONSTRAINT `fk_overtime_notification_attendance` FOREIGN KEY (`overtime_id`) REFERENCES `attendance` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_overtime_notification_employee` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_overtime_notification_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Stores notifications when employees send overtime reports to managers';

-- First check if the overtime_status column exists in the attendance table
SET @column_exists = 0;
SELECT COUNT(*) INTO @column_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'attendance' 
AND column_name = 'overtime_status';

-- Add overtime_status column only if it doesn't exist
SET @add_status_sql = IF(@column_exists = 0, 
    'ALTER TABLE `attendance` ADD COLUMN `overtime_status` enum("pending","submitted","approved","rejected") DEFAULT "pending" COMMENT "Overtime approval status" AFTER `work_report`', 
    'SELECT "overtime_status column already exists"');
PREPARE stmt FROM @add_status_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- First check if the overtime_approved_by column exists in the attendance table
SET @column_exists2 = 0;
SELECT COUNT(*) INTO @column_exists2 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'attendance' 
AND column_name = 'overtime_approved_by';

-- Add overtime_approved_by column only if it doesn't exist
SET @add_approver_sql = IF(@column_exists2 = 0, 
    'ALTER TABLE `attendance` ADD COLUMN `overtime_approved_by` int(11) DEFAULT NULL COMMENT "Manager who approved/rejected overtime" AFTER `overtime_status`', 
    'SELECT "overtime_approved_by column already exists"');
PREPARE stmt2 FROM @add_approver_sql;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- First check if the overtime_actioned_at column exists in the attendance table
SET @column_exists3 = 0;
SELECT COUNT(*) INTO @column_exists3 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'attendance' 
AND column_name = 'overtime_actioned_at';

-- Add overtime_actioned_at column only if it doesn't exist
SET @add_actioned_sql = IF(@column_exists3 = 0, 
    'ALTER TABLE `attendance` ADD COLUMN `overtime_actioned_at` datetime DEFAULT NULL COMMENT "When overtime was approved/rejected" AFTER `overtime_approved_by`', 
    'SELECT "overtime_actioned_at column already exists"');
PREPARE stmt3 FROM @add_actioned_sql;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3; 