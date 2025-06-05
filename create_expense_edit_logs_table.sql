-- SQL script to create expense_edit_logs table

-- Check if table exists and drop it if it does
DROP TABLE IF EXISTS `expense_edit_logs`;

-- Create the table
CREATE TABLE `expense_edit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `field_name` varchar(50) NOT NULL,
  `old_value` varchar(255) DEFAULT NULL,
  `new_value` varchar(255) NOT NULL,
  `edit_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `expense_id` (`expense_id`),
  KEY `user_id` (`user_id`),
  KEY `edit_date` (`edit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add a comment to the table
ALTER TABLE `expense_edit_logs` COMMENT = 'Logs changes made to travel expense records'; 