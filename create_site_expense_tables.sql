-- Create the site_updates table
CREATE TABLE IF NOT EXISTS `site_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `site_name` varchar(255) NOT NULL,
  `update_details` text NOT NULL,
  `update_date` date NOT NULL,
  `total_wages` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_misc_expenses` decimal(10,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `site_updates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the site_vendors table
CREATE TABLE IF NOT EXISTS `site_vendors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_update_id` int(11) NOT NULL,
  `vendor_type` varchar(50) NOT NULL,
  `vendor_name` varchar(255) NOT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `work_description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `site_update_id` (`site_update_id`),
  CONSTRAINT `site_vendors_ibfk_1` FOREIGN KEY (`site_update_id`) REFERENCES `site_updates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the vendor_labours table
CREATE TABLE IF NOT EXISTS `vendor_labours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `labour_name` varchar(255) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `morning_attendance` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present',
  `afternoon_attendance` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present',
  `ot_hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  `ot_wages` decimal(10,2) NOT NULL DEFAULT '0.00',
  `wage` decimal(10,2) NOT NULL DEFAULT '0.00',
  `ot_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vendor_id` (`vendor_id`),
  CONSTRAINT `vendor_labours_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `site_vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the company_labours table
CREATE TABLE IF NOT EXISTS `company_labours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_update_id` int(11) NOT NULL,
  `labour_name` varchar(255) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `morning_attendance` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present',
  `afternoon_attendance` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present',
  `ot_hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  `ot_wages` decimal(10,2) NOT NULL DEFAULT '0.00',
  `wage` decimal(10,2) NOT NULL DEFAULT '0.00',
  `ot_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `site_update_id` (`site_update_id`),
  CONSTRAINT `company_labours_ibfk_1` FOREIGN KEY (`site_update_id`) REFERENCES `site_updates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the travel_allowances table
CREATE TABLE IF NOT EXISTS `travel_allowances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_update_id` int(11) NOT NULL,
  `from_location` varchar(255) NOT NULL,
  `to_location` varchar(255) NOT NULL,
  `mode` varchar(50) NOT NULL,
  `kilometers` decimal(10,2) NOT NULL DEFAULT '0.00',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `site_update_id` (`site_update_id`),
  CONSTRAINT `travel_allowances_ibfk_1` FOREIGN KEY (`site_update_id`) REFERENCES `site_updates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the beverages table
CREATE TABLE IF NOT EXISTS `beverages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_update_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `site_update_id` (`site_update_id`),
  CONSTRAINT `beverages_ibfk_1` FOREIGN KEY (`site_update_id`) REFERENCES `site_updates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the travel_expenses table
CREATE TABLE IF NOT EXISTS `travel_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `site_visited` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_details` text NOT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approval_notes` text,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `travel_expenses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `travel_expenses_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the work_progress table
CREATE TABLE IF NOT EXISTS `work_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_update_id` int(11) NOT NULL,
  `work_type` varchar(100) NOT NULL,
  `status` enum('Yes','No','In Progress') NOT NULL DEFAULT 'No',
  `category` enum('civil','interior') NOT NULL,
  `remarks` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `site_update_id` (`site_update_id`),
  CONSTRAINT `work_progress_ibfk_1` FOREIGN KEY (`site_update_id`) REFERENCES `site_updates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the work_progress_files table
CREATE TABLE IF NOT EXISTS `work_progress_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_progress_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` enum('image','video') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `work_progress_id` (`work_progress_id`),
  CONSTRAINT `work_progress_files_ibfk_1` FOREIGN KEY (`work_progress_id`) REFERENCES `work_progress` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the inventory table
CREATE TABLE IF NOT EXISTS `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_update_id` int(11) NOT NULL,
  `material` varchar(100) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `unit` varchar(50) NOT NULL,
  `standard_values` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `site_update_id` (`site_update_id`),
  CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`site_update_id`) REFERENCES `site_updates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create the inventory_files table
CREATE TABLE IF NOT EXISTS `inventory_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inventory_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` enum('image','video') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `inventory_id` (`inventory_id`),
  CONSTRAINT `inventory_files_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 