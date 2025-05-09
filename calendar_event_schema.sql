-- Calendar Events Database Schema

-- Table for calendar events
CREATE TABLE IF NOT EXISTS `sv_calendar_events` (
  `event_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `event_date` DATE NOT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for vendors
CREATE TABLE IF NOT EXISTS `sv_event_vendors` (
  `vendor_id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `vendor_type` VARCHAR(100) NOT NULL,
  `vendor_name` VARCHAR(255) NOT NULL,
  `contact_number` VARCHAR(20),
  `sequence_number` INT NOT NULL,
  FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for vendor materials
CREATE TABLE IF NOT EXISTS `sv_vendor_materials` (
  `material_id` INT AUTO_INCREMENT PRIMARY KEY,
  `vendor_id` INT NOT NULL,
  `remarks` TEXT,
  `amount` DECIMAL(10,2),
  FOREIGN KEY (`vendor_id`) REFERENCES `sv_event_vendors`(`vendor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for material images
CREATE TABLE IF NOT EXISTS `sv_material_images` (
  `image_id` INT AUTO_INCREMENT PRIMARY KEY,
  `material_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`material_id`) REFERENCES `sv_vendor_materials`(`material_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for bill images
CREATE TABLE IF NOT EXISTS `sv_bill_images` (
  `bill_id` INT AUTO_INCREMENT PRIMARY KEY,
  `material_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `upload_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`material_id`) REFERENCES `sv_vendor_materials`(`material_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for vendor labours
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

-- Table for labour wages
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

-- Table for company labours
CREATE TABLE IF NOT EXISTS `sv_company_labours` (
  `company_labour_id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `labour_name` VARCHAR(255) NOT NULL,
  `contact_number` VARCHAR(20),
  `sequence_number` INT NOT NULL,
  `morning_attendance` ENUM('present', 'absent') DEFAULT 'present',
  `evening_attendance` ENUM('present', 'absent') DEFAULT 'present',
  FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for company labour wages
CREATE TABLE IF NOT EXISTS `sv_company_wages` (
  `wage_id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_labour_id` INT NOT NULL,
  `daily_wage` DECIMAL(10,2) DEFAULT 0,
  `total_day_wage` DECIMAL(10,2) DEFAULT 0,
  `ot_hours` INT DEFAULT 0,
  `ot_minutes` INT DEFAULT 0,
  `ot_rate` DECIMAL(10,2) DEFAULT 0,
  `total_ot_amount` DECIMAL(10,2) DEFAULT 0,
  `transport_mode` VARCHAR(50),
  `travel_amount` DECIMAL(10,2) DEFAULT 0,
  `grand_total` DECIMAL(10,2) DEFAULT 0,
  FOREIGN KEY (`company_labour_id`) REFERENCES `sv_company_labours`(`company_labour_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 