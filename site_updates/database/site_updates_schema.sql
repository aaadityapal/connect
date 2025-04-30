-- Site Updates Database Schema
-- This file contains SQL to create the necessary tables for storing site update form data

-- Enable foreign key constraints
SET FOREIGN_KEY_CHECKS = 1;

-- Drop tables if they exist (for clean setup)
DROP TABLE IF EXISTS inventory_media;
DROP TABLE IF EXISTS work_progress_media;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS work_progress;
DROP TABLE IF EXISTS beverages;
DROP TABLE IF EXISTS travel_expenses;
DROP TABLE IF EXISTS laborers;
DROP TABLE IF EXISTS company_labours;
DROP TABLE IF EXISTS vendors;
DROP TABLE IF EXISTS site_updates;

-- Create site_updates table (main update info)
CREATE TABLE site_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_name VARCHAR(255) NOT NULL,
    update_date DATE NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (site_name),
    INDEX (update_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create vendors table
CREATE TABLE vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_update_id INT NOT NULL,
    vendor_type VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    contact VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_update_id) REFERENCES site_updates(id) ON DELETE CASCADE,
    INDEX (site_update_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create laborers table for vendor laborers
CREATE TABLE laborers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    contact VARCHAR(20),
    morning_attendance ENUM('P', 'A') NOT NULL DEFAULT 'P',
    evening_attendance ENUM('P', 'A') NOT NULL DEFAULT 'P',
    wages_per_day DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    day_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    ot_hours DECIMAL(5, 2) DEFAULT 0.00,
    ot_rate DECIMAL(10, 2) DEFAULT 0.00,
    ot_amount DECIMAL(10, 2) DEFAULT 0.00,
    total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create company_labours table
CREATE TABLE company_labours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_update_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    contact VARCHAR(20),
    morning_attendance ENUM('P', 'A') NOT NULL DEFAULT 'P',
    evening_attendance ENUM('P', 'A') NOT NULL DEFAULT 'P',
    wages_per_day DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    day_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    ot_hours DECIMAL(5, 2) DEFAULT 0.00,
    ot_rate DECIMAL(10, 2) DEFAULT 0.00,
    ot_amount DECIMAL(10, 2) DEFAULT 0.00,
    total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_update_id) REFERENCES site_updates(id) ON DELETE CASCADE,
    INDEX (site_update_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create travel_expenses table
CREATE TABLE travel_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_update_id INT NOT NULL,
    travel_from VARCHAR(255) NOT NULL,
    travel_to VARCHAR(255) NOT NULL,
    transport_mode VARCHAR(50) NOT NULL,
    km_travelled DECIMAL(8, 2),
    amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_update_id) REFERENCES site_updates(id) ON DELETE CASCADE,
    INDEX (site_update_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create beverages table
CREATE TABLE beverages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_update_id INT NOT NULL,
    beverage_type VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_update_id) REFERENCES site_updates(id) ON DELETE CASCADE,
    INDEX (site_update_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create work_progress table
CREATE TABLE work_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_update_id INT NOT NULL,
    work_category VARCHAR(50) NOT NULL,
    work_type VARCHAR(100) NOT NULL,
    work_done ENUM('Yes', 'No') NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_update_id) REFERENCES site_updates(id) ON DELETE CASCADE,
    INDEX (site_update_id),
    INDEX (work_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create work_progress_media table for storing media related to work progress
CREATE TABLE work_progress_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_progress_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type ENUM('image', 'video') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (work_progress_id) REFERENCES work_progress(id) ON DELETE CASCADE,
    INDEX (work_progress_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create inventory table
CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_update_id INT NOT NULL,
    inventory_type ENUM('Received', 'Available', 'Consumed') NOT NULL,
    material VARCHAR(100) NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    unit VARCHAR(20) NOT NULL,
    notes TEXT,
    bill_picture VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_update_id) REFERENCES site_updates(id) ON DELETE CASCADE,
    INDEX (site_update_id),
    INDEX (inventory_type, material)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create inventory_media table for storing media related to inventory
CREATE TABLE inventory_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type ENUM('image', 'video') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE,
    INDEX (inventory_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create summary view for easy reporting
CREATE OR REPLACE VIEW summary_view AS
SELECT 
    su.id as update_id,
    su.site_name,
    su.update_date,
    (SELECT COUNT(*) FROM vendors WHERE site_update_id = su.id) as vendor_count,
    (SELECT COUNT(*) FROM laborers l JOIN vendors v ON l.vendor_id = v.id WHERE v.site_update_id = su.id) as laborer_count,
    (SELECT COUNT(*) FROM company_labours WHERE site_update_id = su.id) as company_labour_count,
    (SELECT SUM(total_amount) FROM laborers l JOIN vendors v ON l.vendor_id = v.id WHERE v.site_update_id = su.id) as vendor_labour_total,
    (SELECT SUM(total_amount) FROM company_labours WHERE site_update_id = su.id) as company_labour_total,
    (SELECT SUM(amount) FROM travel_expenses WHERE site_update_id = su.id) as travel_expenses_total,
    (SELECT SUM(amount) FROM beverages WHERE site_update_id = su.id) as beverages_total,
    (
        COALESCE((SELECT SUM(total_amount) FROM laborers l JOIN vendors v ON l.vendor_id = v.id WHERE v.site_update_id = su.id), 0) +
        COALESCE((SELECT SUM(total_amount) FROM company_labours WHERE site_update_id = su.id), 0) +
        COALESCE((SELECT SUM(amount) FROM travel_expenses WHERE site_update_id = su.id), 0) +
        COALESCE((SELECT SUM(amount) FROM beverages WHERE site_update_id = su.id), 0)
    ) as grand_total
FROM site_updates su
ORDER BY su.update_date DESC;

-- Create triggers to calculate totals automatically
DELIMITER //

-- Trigger to calculate laborer day_total, ot_amount and total_amount
CREATE TRIGGER calculate_laborer_totals BEFORE INSERT ON laborers
FOR EACH ROW
BEGIN
    DECLARE attendance_factor DECIMAL(3, 2);
    
    -- Calculate attendance factor based on morning and evening attendance
    SET attendance_factor = 0;
    IF NEW.morning_attendance = 'P' THEN
        SET attendance_factor = attendance_factor + 0.5;
    END IF;
    
    IF NEW.evening_attendance = 'P' THEN
        SET attendance_factor = attendance_factor + 0.5;
    END IF;
    
    -- Calculate day total
    SET NEW.day_total = NEW.wages_per_day * attendance_factor;
    
    -- Calculate OT amount
    SET NEW.ot_amount = NEW.ot_hours * NEW.ot_rate;
    
    -- Calculate total amount
    SET NEW.total_amount = NEW.day_total + NEW.ot_amount;
END //

-- Similar trigger for updating laborer totals
CREATE TRIGGER update_laborer_totals BEFORE UPDATE ON laborers
FOR EACH ROW
BEGIN
    DECLARE attendance_factor DECIMAL(3, 2);
    
    -- Calculate attendance factor based on morning and evening attendance
    SET attendance_factor = 0;
    IF NEW.morning_attendance = 'P' THEN
        SET attendance_factor = attendance_factor + 0.5;
    END IF;
    
    IF NEW.evening_attendance = 'P' THEN
        SET attendance_factor = attendance_factor + 0.5;
    END IF;
    
    -- Calculate day total
    SET NEW.day_total = NEW.wages_per_day * attendance_factor;
    
    -- Calculate OT amount
    SET NEW.ot_amount = NEW.ot_hours * NEW.ot_rate;
    
    -- Calculate total amount
    SET NEW.total_amount = NEW.day_total + NEW.ot_amount;
END //

-- Trigger to calculate company labour day_total, ot_amount and total_amount
CREATE TRIGGER calculate_company_labour_totals BEFORE INSERT ON company_labours
FOR EACH ROW
BEGIN
    DECLARE attendance_factor DECIMAL(3, 2);
    
    -- Calculate attendance factor based on morning and evening attendance
    SET attendance_factor = 0;
    IF NEW.morning_attendance = 'P' THEN
        SET attendance_factor = attendance_factor + 0.5;
    END IF;
    
    IF NEW.evening_attendance = 'P' THEN
        SET attendance_factor = attendance_factor + 0.5;
    END IF;
    
    -- Calculate day total
    SET NEW.day_total = NEW.wages_per_day * attendance_factor;
    
    -- Calculate OT amount
    SET NEW.ot_amount = NEW.ot_hours * NEW.ot_rate;
    
    -- Calculate total amount
    SET NEW.total_amount = NEW.day_total + NEW.ot_amount;
END //

-- Similar trigger for updating company labour totals
CREATE TRIGGER update_company_labour_totals BEFORE UPDATE ON company_labours
FOR EACH ROW
BEGIN
    DECLARE attendance_factor DECIMAL(3, 2);
    
    -- Calculate attendance factor based on morning and evening attendance
    SET attendance_factor = 0;
    IF NEW.morning_attendance = 'P' THEN
        SET attendance_factor = attendance_factor + 0.5;
    END IF;
    
    IF NEW.evening_attendance = 'P' THEN
        SET attendance_factor = attendance_factor + 0.5;
    END IF;
    
    -- Calculate day total
    SET NEW.day_total = NEW.wages_per_day * attendance_factor;
    
    -- Calculate OT amount
    SET NEW.ot_amount = NEW.ot_hours * NEW.ot_rate;
    
    -- Calculate total amount
    SET NEW.total_amount = NEW.day_total + NEW.ot_amount;
END //

DELIMITER ; 