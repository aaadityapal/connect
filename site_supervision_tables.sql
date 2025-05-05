-- Site Supervision Database Tables
-- This file contains all the necessary tables for the site supervision system

-- Activity Log Table
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_table` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Site Events Table (Master table for daily site updates)
CREATE TABLE IF NOT EXISTS `site_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_name` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `event_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vendors Table
CREATE TABLE IF NOT EXISTS `event_vendors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `vendor_type` varchar(100) NOT NULL,
  `vendor_name` varchar(255) NOT NULL,
  `vendor_contact` varchar(20) NOT NULL,
  `material_remark` text DEFAULT NULL,
  `material_amount` decimal(10,2) DEFAULT 0.00,
  `material_picture` varchar(255) DEFAULT NULL,
  `bill_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `fk_vendor_event` FOREIGN KEY (`event_id`) REFERENCES `site_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vendor Laborers Table
CREATE TABLE IF NOT EXISTS `vendor_laborers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `labor_name` varchar(255) NOT NULL,
  `labor_contact` varchar(20) NOT NULL,
  `morning_attendance` enum('P','A') NOT NULL,
  `evening_attendance` enum('P','A') NOT NULL,
  `wages_per_day` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_day_wages` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ot_hours` int(11) DEFAULT 0,
  `ot_minutes` int(11) DEFAULT 0,
  `ot_rate` decimal(10,2) DEFAULT 0.00,
  `total_ot_amount` decimal(10,2) DEFAULT 0.00,
  `transport_mode` varchar(50) DEFAULT NULL,
  `travel_amount` decimal(10,2) DEFAULT 0.00,
  `grand_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vendor_id` (`vendor_id`),
  CONSTRAINT `fk_labor_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `event_vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Company Labours Table
CREATE TABLE IF NOT EXISTS `event_company_labours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `labour_name` varchar(255) NOT NULL,
  `labour_contact` varchar(20) NOT NULL,
  `morning_attendance` enum('P','A') NOT NULL,
  `evening_attendance` enum('P','A') NOT NULL,
  `wages_per_day` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_day_wages` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ot_hours` int(11) DEFAULT 0,
  `ot_minutes` int(11) DEFAULT 0,
  `ot_rate` decimal(10,2) DEFAULT 0.00,
  `total_ot_amount` decimal(10,2) DEFAULT 0.00,
  `transport_mode` varchar(50) DEFAULT NULL,
  `travel_amount` decimal(10,2) DEFAULT 0.00,
  `grand_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `fk_company_labour_event` FOREIGN KEY (`event_id`) REFERENCES `site_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Travel Expenses Table
CREATE TABLE IF NOT EXISTS `event_travel_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `from_location` varchar(255) NOT NULL,
  `to_location` varchar(255) NOT NULL,
  `transport_mode` varchar(50) NOT NULL,
  `distance_km` decimal(10,2) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `travel_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `fk_travel_event` FOREIGN KEY (`event_id`) REFERENCES `site_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Beverages Table
CREATE TABLE IF NOT EXISTS `event_beverages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `beverage_type` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `bill_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `fk_beverage_event` FOREIGN KEY (`event_id`) REFERENCES `site_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Work Progress Table
CREATE TABLE IF NOT EXISTS `event_work_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `work_category` varchar(100) NOT NULL,
  `work_type` varchar(100) DEFAULT NULL,
  `description` text NOT NULL,
  `status` varchar(50) NOT NULL,
  `completion_percentage` int(11) DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `fk_work_event` FOREIGN KEY (`event_id`) REFERENCES `site_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Work Progress Media Table
CREATE TABLE IF NOT EXISTS `work_progress_media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work_progress_id` int(11) NOT NULL,
  `media_type` enum('image','video') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `work_progress_id` (`work_progress_id`),
  CONSTRAINT `fk_media_work` FOREIGN KEY (`work_progress_id`) REFERENCES `event_work_progress` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inventory Items Table
CREATE TABLE IF NOT EXISTS `event_inventory_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `inventory_type` varchar(100) NOT NULL,
  `material` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `units` varchar(50) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `remaining_quantity` decimal(10,2) DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `bill_number` varchar(100) DEFAULT NULL,
  `bill_date` date DEFAULT NULL,
  `bill_picture` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `fk_inventory_event` FOREIGN KEY (`event_id`) REFERENCES `site_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inventory Media Table
CREATE TABLE IF NOT EXISTS `inventory_media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inventory_id` int(11) NOT NULL,
  `media_type` enum('image','video') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `inventory_id` (`inventory_id`),
  CONSTRAINT `fk_media_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `event_inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 