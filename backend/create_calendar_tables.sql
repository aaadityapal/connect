-- Calendar Events Table
CREATE TABLE IF NOT EXISTS `sv_calendar_events` (
  `event_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `event_date` DATE NOT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Event Vendors Table
CREATE TABLE IF NOT EXISTS `sv_event_vendors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `vendor_name` VARCHAR(255) NOT NULL,
  `vendor_type` VARCHAR(50),
  `contact_phone` VARCHAR(20),
  `attendance_status` ENUM('present', 'absent', 'late') DEFAULT 'present',
  `travel_mode` VARCHAR(50),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Company Labours Table
CREATE TABLE IF NOT EXISTS `sv_company_labours` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `worker_name` VARCHAR(255) NOT NULL,
  `labour_type` VARCHAR(50),
  `attendance_status` ENUM('present', 'absent', 'late') DEFAULT 'present',
  `travel_mode` VARCHAR(50),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Event Beverages Table
CREATE TABLE IF NOT EXISTS `sv_event_beverages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `price_per_unit` DECIMAL(10,2),
  `total_price` DECIMAL(10,2),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Work Progress Table
CREATE TABLE IF NOT EXISTS `sv_work_progress` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `work_type` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `completion_percentage` INT DEFAULT 0,
  `progress_status` ENUM('not_started', 'in_progress', 'completed', 'delayed') DEFAULT 'not_started',
  `reported_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inventory Items Table
CREATE TABLE IF NOT EXISTS `sv_inventory_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `unit` VARCHAR(20),
  `type` VARCHAR(50),
  `condition` ENUM('new', 'good', 'fair', 'poor') DEFAULT 'good',
  `image_path` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `sv_calendar_events`(`event_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample data for testing
INSERT INTO `sv_calendar_events` (`title`, `event_date`, `created_by`)
SELECT 'Safety Inspection', CURDATE(), id FROM users WHERE role = 'Site Supervisor' LIMIT 1
ON DUPLICATE KEY UPDATE title = title;

INSERT INTO `sv_calendar_events` (`title`, `event_date`, `created_by`)
SELECT 'Material Delivery', DATE_ADD(CURDATE(), INTERVAL 2 DAY), id FROM users WHERE role = 'Site Supervisor' LIMIT 1
ON DUPLICATE KEY UPDATE title = title;

INSERT INTO `sv_calendar_events` (`title`, `event_date`, `created_by`)
SELECT 'Team Meeting', DATE_ADD(CURDATE(), INTERVAL 1 DAY), id FROM users WHERE role = 'Site Supervisor' LIMIT 1
ON DUPLICATE KEY UPDATE title = title; 