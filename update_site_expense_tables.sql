-- Alter site_updates table to add totals columns
ALTER TABLE `site_updates`
ADD COLUMN `total_wages` decimal(10,2) NOT NULL DEFAULT '0.00' AFTER `update_date`,
ADD COLUMN `total_misc_expenses` decimal(10,2) NOT NULL DEFAULT '0.00' AFTER `total_wages`,
ADD COLUMN `grand_total` decimal(10,2) NOT NULL DEFAULT '0.00' AFTER `total_misc_expenses`;

-- Create the site_vendors table if it doesn't exist
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

-- Create the vendor_labours table if it doesn't exist
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
  `attendance` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present',
  `ot_hours` decimal(5,2) NOT NULL DEFAULT '0.00',
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

-- Migration script for existing vendor_labours data
-- If the table already exists with the old structure
DELIMITER //
CREATE PROCEDURE migrate_vendor_labours()
BEGIN
    DECLARE table_exists INT;
    DECLARE attendance_column_exists INT;
    DECLARE morning_column_exists INT;
    
    -- Check if table exists
    SELECT COUNT(1) INTO table_exists 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'vendor_labours';
    
    IF table_exists > 0 THEN
        -- Check if we have the old structure
        SELECT COUNT(1) INTO attendance_column_exists
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND table_name = 'vendor_labours'
        AND column_name = 'attendance';
        
        SELECT COUNT(1) INTO morning_column_exists
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND table_name = 'vendor_labours'
        AND column_name = 'morning_attendance';
        
        -- If we have the old structure, migrate the data
        IF attendance_column_exists > 0 AND morning_column_exists = 0 THEN
            -- Add temporary columns
            ALTER TABLE `vendor_labours`
            ADD COLUMN `morning_attendance_temp` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present',
            ADD COLUMN `afternoon_attendance_temp` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present',
            ADD COLUMN `ot_wages` decimal(10,2) NOT NULL DEFAULT '0.00';
            
            -- Copy attendance to both morning and afternoon
            UPDATE `vendor_labours` SET 
            `morning_attendance_temp` = `attendance`,
            `afternoon_attendance_temp` = `attendance`;
            
            -- Drop old column and rename new ones
            ALTER TABLE `vendor_labours`
            DROP COLUMN `attendance`,
            CHANGE COLUMN `morning_attendance_temp` `morning_attendance` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present',
            CHANGE COLUMN `afternoon_attendance_temp` `afternoon_attendance` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present';
        END IF;
    END IF;
END //
DELIMITER ;

CALL migrate_vendor_labours();
DROP PROCEDURE IF EXISTS migrate_vendor_labours;

-- Migration script for existing company_labours data
-- If the table already exists with the old structure
DELIMITER //
CREATE PROCEDURE migrate_company_labours()
BEGIN
    DECLARE table_exists INT;
    DECLARE attendance_column_exists INT;
    DECLARE morning_column_exists INT;
    
    -- Check if table exists
    SELECT COUNT(1) INTO table_exists 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = 'company_labours';
    
    IF table_exists > 0 THEN
        -- Check if we have the old structure
        SELECT COUNT(1) INTO attendance_column_exists
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND table_name = 'company_labours'
        AND column_name = 'attendance';
        
        SELECT COUNT(1) INTO morning_column_exists
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND table_name = 'company_labours'
        AND column_name = 'morning_attendance';
        
        -- If we have the old structure, migrate the data
        IF attendance_column_exists > 0 AND morning_column_exists = 0 THEN
            -- Add temporary columns
            ALTER TABLE `company_labours`
            ADD COLUMN `morning_attendance_temp` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present',
            ADD COLUMN `afternoon_attendance_temp` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present',
            ADD COLUMN `ot_wages` decimal(10,2) NOT NULL DEFAULT '0.00';
            
            -- Copy attendance to both morning and afternoon
            UPDATE `company_labours` SET 
            `morning_attendance_temp` = `attendance`,
            `afternoon_attendance_temp` = `attendance`;
            
            -- Drop old column and rename new ones
            ALTER TABLE `company_labours`
            DROP COLUMN `attendance`,
            CHANGE COLUMN `morning_attendance_temp` `morning_attendance` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present',
            CHANGE COLUMN `afternoon_attendance_temp` `afternoon_attendance` enum('Present','Absent','Half-day') NOT NULL DEFAULT 'Present';
        END IF;
    END IF;
END //
DELIMITER ;

CALL migrate_company_labours();
DROP PROCEDURE IF EXISTS migrate_company_labours; 