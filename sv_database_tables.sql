-- Construction Site Management System Database Tables
-- This SQL file contains the schema for all tables related to site supervision and management

-- Calendar Events Table - Master table for site events
CREATE TABLE IF NOT EXISTS `sv_calendar_events` (
  `event_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `event_date` DATE NOT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Event Vendors Table - Tracks vendors for each event
CREATE TABLE IF NOT EXISTS `sv_event_vendors` (
  `vendor_id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `vendor_type` VARCHAR(100) NOT NULL,
  `vendor_name` VARCHAR(255) NOT NULL,
  `contact_number` VARCHAR(20),
  `sequence_number` INT NOT NULL,
  FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vendor Materials Table - Materials provided by vendors
CREATE TABLE IF NOT EXISTS `sv_vendor_materials` (
  `material_id` INT AUTO_INCREMENT PRIMARY KEY,
  `vendor_id` INT NOT NULL,
  `remarks` TEXT,
  `amount` DECIMAL(10,2),
  FOREIGN KEY (`vendor_id`) REFERENCES `sv_event_vendors`(`vendor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Material Images Table - Images of materials
CREATE TABLE IF NOT EXISTS `sv_material_images` (
  `image_id` INT AUTO_INCREMENT PRIMARY KEY,
  `material_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`material_id`) REFERENCES `sv_vendor_materials`(`material_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bill Images Table - Images of bills/invoices
CREATE TABLE IF NOT EXISTS `sv_bill_images` (
  `bill_id` INT AUTO_INCREMENT PRIMARY KEY,
  `material_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`material_id`) REFERENCES `sv_vendor_materials`(`material_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vendor Labours Table - Workers provided by vendors
CREATE TABLE IF NOT EXISTS `sv_vendor_labours` (
  `labour_id` INT AUTO_INCREMENT PRIMARY KEY,
  `vendor_id` INT NOT NULL,
  `labour_name` VARCHAR(255) NOT NULL,
  `contact_number` VARCHAR(20),
  `sequence_number` INT NOT NULL,
  `morning_attendance` ENUM('present', 'absent') DEFAULT 'present',
  `evening_attendance` ENUM('present', 'absent') DEFAULT 'present',
  FOREIGN KEY (`vendor_id`) REFERENCES `sv_event_vendors`(`vendor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Labour Wages Table - Wages for vendor labours
CREATE TABLE IF NOT EXISTS `sv_labour_wages` (
  `wage_id` INT AUTO_INCREMENT PRIMARY KEY,
  `labour_id` INT NOT NULL,
  `daily_wage` DECIMAL(10,2) DEFAULT 0,
  `total_day_wage` DECIMAL(10,2) DEFAULT 0,
  `ot_hours` INT DEFAULT 0,
  `ot_minutes` INT DEFAULT 0,
  `ot_rate` DECIMAL(10,2) DEFAULT 0,
  `total_ot_amount` DECIMAL(10,2) DEFAULT 0,
  `transport_mode` VARCHAR(50),
  `travel_amount` DECIMAL(10,2) DEFAULT 0,
  `grand_total` DECIMAL(10,2) DEFAULT 0,
  FOREIGN KEY (`labour_id`) REFERENCES `sv_vendor_labours`(`labour_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Company Labours Table - Direct company workers
CREATE TABLE IF NOT EXISTS `sv_company_labours` (
  `company_labour_id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `labour_name` VARCHAR(255) NOT NULL,
  `contact_number` VARCHAR(20),
  `sequence_number` INT NOT NULL,
  `morning_attendance` ENUM('present', 'absent') DEFAULT 'present',
  `evening_attendance` ENUM('present', 'absent') DEFAULT 'present',
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_by` INT,
  `updated_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `attendance_date` DATE,
  `daily_wage` DECIMAL(10,2) DEFAULT 0,
  FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Event Beverages Table - Refreshments provided at events
CREATE TABLE IF NOT EXISTS `sv_event_beverages` (
  `beverage_id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `beverage_type` VARCHAR(100),
  `beverage_name` VARCHAR(100),
  `amount` DECIMAL(10,2),
  `sequence_number` INT,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT NULL,
  `updated_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Event Logs Table - Audit trail for events
CREATE TABLE IF NOT EXISTS `sv_event_logs` (
  `log_id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `action_type` ENUM('create', 'update', 'delete') NOT NULL,
  `performed_by` INT NOT NULL,
  `action_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `details` JSON,
  CONSTRAINT `fk_event_logs_event_id` FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`),
  CONSTRAINT `fk_event_logs_performed_by` FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Work Progress Table - Tracks progress of work at sites
CREATE TABLE IF NOT EXISTS `sv_work_progress` (
  `work_id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `work_category` VARCHAR(100) NOT NULL,
  `work_type` VARCHAR(100) NOT NULL,
  `work_done` ENUM('yes', 'no') DEFAULT 'yes',
  `remarks` TEXT,
  `sequence_number` INT,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT NULL,
  `updated_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Work Progress Media Table - Images/videos of work progress
CREATE TABLE IF NOT EXISTS `sv_work_progress_media` (
  `media_id` INT AUTO_INCREMENT PRIMARY KEY,
  `work_id` INT NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `media_type` ENUM('image', 'video') DEFAULT 'image',
  `file_size` INT,
  `sequence_number` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`work_id`) REFERENCES `sv_work_progress`(`work_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inventory Items Table - Tracks materials and equipment
CREATE TABLE IF NOT EXISTS `sv_inventory_items` (
  `inventory_id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `inventory_type` ENUM('received', 'consumed', 'other') DEFAULT 'received',
  `material_type` VARCHAR(100) NOT NULL,
  `quantity` DECIMAL(10,2) DEFAULT 0,
  `unit` VARCHAR(20),
  `remarks` TEXT,
  `sequence_number` INT,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT NULL,
  `updated_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL,
  FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inventory Media Table - Images/videos of inventory items
CREATE TABLE IF NOT EXISTS `sv_inventory_media` (
  `media_id` INT AUTO_INCREMENT PRIMARY KEY,
  `inventory_id` INT NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `media_type` ENUM('bill', 'photo', 'video') DEFAULT 'photo',
  `file_size` INT,
  `sequence_number` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`inventory_id`) REFERENCES `sv_inventory_items`(`inventory_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 