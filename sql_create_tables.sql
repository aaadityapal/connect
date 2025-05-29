-- SQL Script to create required tables for Calendar Events

-- Create sv_calendar_events table if not exists
CREATE TABLE IF NOT EXISTS `sv_calendar_events` (
    `event_id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `event_date` DATE NOT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create sv_work_progress table if not exists
CREATE TABLE IF NOT EXISTS `sv_work_progress` (
    `work_id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT NOT NULL,
    `work_category` VARCHAR(100) NOT NULL,
    `work_type` VARCHAR(100) NOT NULL,
    `work_done` ENUM('yes', 'no') DEFAULT 'yes',
    `remarks` TEXT,
    `sequence_number` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create sv_work_progress_media table if not exists
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

-- Create other related tables for completeness

-- Create vendor table if it doesn't exist
CREATE TABLE IF NOT EXISTS `sv_event_vendors` (
    `vendor_id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT NOT NULL,
    `vendor_type` VARCHAR(100) NOT NULL,
    `vendor_name` VARCHAR(255) NOT NULL,
    `contact_number` VARCHAR(20),
    `sequence_number` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create vendor materials table if it doesn't exist
CREATE TABLE IF NOT EXISTS `sv_vendor_materials` (
    `material_id` INT AUTO_INCREMENT PRIMARY KEY,
    `vendor_id` INT NOT NULL,
    `remarks` TEXT,
    `amount` DECIMAL(10,2) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`vendor_id`) REFERENCES `sv_event_vendors`(`vendor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create material images table if it doesn't exist
CREATE TABLE IF NOT EXISTS `sv_material_images` (
    `image_id` INT AUTO_INCREMENT PRIMARY KEY,
    `material_id` INT NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`material_id`) REFERENCES `sv_vendor_materials`(`material_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create bill images table if it doesn't exist
CREATE TABLE IF NOT EXISTS `sv_bill_images` (
    `image_id` INT AUTO_INCREMENT PRIMARY KEY,
    `material_id` INT NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`material_id`) REFERENCES `sv_vendor_materials`(`material_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create event beverages table if it doesn't exist
CREATE TABLE IF NOT EXISTS `sv_event_beverages` (
    `beverage_id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT NOT NULL,
    `beverage_type` VARCHAR(100),
    `beverage_name` VARCHAR(100),
    `amount` DECIMAL(10,2),
    `sequence_number` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 