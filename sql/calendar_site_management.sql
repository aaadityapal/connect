-- Calendar Site Management Database Schema
-- Created: 2023
-- Description: Schema for storing site supervisor calendar data including vendors, laborers, attendance, and expenses

-- Drop tables if they exist (in reverse order of creation to avoid foreign key constraints)
DROP TABLE IF EXISTS hr_supervisor_activity_log;
DROP TABLE IF EXISTS hr_supervisor_travel_expense_records;
DROP TABLE IF EXISTS hr_supervisor_overtime_payment_records;
DROP TABLE IF EXISTS hr_supervisor_wage_payment_records;
DROP TABLE IF EXISTS hr_supervisor_laborer_attendance_logs;
DROP TABLE IF EXISTS hr_supervisor_material_photo_records;
DROP TABLE IF EXISTS hr_supervisor_material_transaction_records;
DROP TABLE IF EXISTS hr_supervisor_laborer_registry;
DROP TABLE IF EXISTS hr_supervisor_vendor_registry;
DROP TABLE IF EXISTS hr_supervisor_calendar_site_events;
DROP TABLE IF EXISTS hr_supervisor_construction_sites;
DROP TABLE IF EXISTS hr_supervisor_transport_modes;

-- Transport modes lookup table
CREATE TABLE hr_supervisor_transport_modes (
    transport_mode_id INT AUTO_INCREMENT PRIMARY KEY,
    mode_name VARCHAR(50) NOT NULL UNIQUE,
    mode_description VARCHAR(255),
    active_status BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default transport modes
INSERT INTO hr_supervisor_transport_modes (mode_name) VALUES 
('bus'), ('train'), ('auto'), ('taxi'), ('own'), ('other');

-- Construction Sites table
CREATE TABLE hr_supervisor_construction_sites (
    site_id INT AUTO_INCREMENT PRIMARY KEY,
    site_code VARCHAR(30) NOT NULL UNIQUE,
    site_name VARCHAR(100) NOT NULL,
    site_location VARCHAR(255),
    site_address TEXT,
    site_coordinates POINT,
    site_status ENUM('active', 'completed', 'paused', 'planning') DEFAULT 'active',
    is_predefined BOOLEAN DEFAULT FALSE,
    is_custom BOOLEAN DEFAULT FALSE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- Calendar Site Events table
CREATE TABLE hr_supervisor_calendar_site_events (
    event_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    event_date DATE NOT NULL,
    event_day INT NOT NULL,
    event_month INT NOT NULL,
    event_year INT NOT NULL,
    event_status ENUM('draft', 'submitted', 'approved', 'rejected') DEFAULT 'submitted',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES hr_supervisor_construction_sites(site_id),
    UNIQUE KEY unique_site_date (site_id, event_date)
);

-- Vendor Registry table
CREATE TABLE hr_supervisor_vendor_registry (
    vendor_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,
    vendor_type VARCHAR(50) NOT NULL,
    vendor_name VARCHAR(100) NOT NULL,
    vendor_contact VARCHAR(20),
    vendor_email VARCHAR(100),
    is_custom_type BOOLEAN DEFAULT FALSE,
    vendor_position INT DEFAULT 0, -- For ordering vendors
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES hr_supervisor_calendar_site_events(event_id) ON DELETE CASCADE
);

-- Laborer Registry table
CREATE TABLE hr_supervisor_laborer_registry (
    laborer_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    vendor_id BIGINT NOT NULL,
    laborer_name VARCHAR(100) NOT NULL,
    laborer_contact VARCHAR(20),
    laborer_position INT DEFAULT 0, -- For ordering laborers
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES hr_supervisor_vendor_registry(vendor_id) ON DELETE CASCADE
);

-- Material Transaction Records table
CREATE TABLE hr_supervisor_material_transaction_records (
    material_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    vendor_id BIGINT NOT NULL,
    material_remark TEXT,
    material_amount DECIMAL(12,2) DEFAULT 0.00,
    has_material_photo BOOLEAN DEFAULT FALSE,
    has_bill_photo BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES hr_supervisor_vendor_registry(vendor_id) ON DELETE CASCADE
);

-- Material Photo Records table
CREATE TABLE hr_supervisor_material_photo_records (
    photo_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    material_id BIGINT NOT NULL,
    photo_type ENUM('material', 'bill') NOT NULL,
    photo_filename VARCHAR(255) NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    photo_size INT,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    location_accuracy DECIMAL(10,2),
    location_address TEXT,
    location_timestamp TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES hr_supervisor_material_transaction_records(material_id) ON DELETE CASCADE
);

-- Laborer Attendance Logs table
CREATE TABLE hr_supervisor_laborer_attendance_logs (
    attendance_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    laborer_id BIGINT NOT NULL,
    attendance_date DATE NOT NULL,
    morning_status ENUM('present', 'absent', 'not_recorded') DEFAULT 'not_recorded',
    evening_status ENUM('present', 'absent', 'not_recorded') DEFAULT 'not_recorded',
    attendance_percentage DECIMAL(5,2) DEFAULT 0.00, -- 0.00, 50.00, 100.00
    attendance_verified BOOLEAN DEFAULT FALSE,
    verified_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (laborer_id) REFERENCES hr_supervisor_laborer_registry(laborer_id) ON DELETE CASCADE,
    UNIQUE KEY unique_laborer_date (laborer_id, attendance_date)
);

-- Wage Payment Records table
CREATE TABLE hr_supervisor_wage_payment_records (
    wage_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    laborer_id BIGINT NOT NULL,
    attendance_id BIGINT NOT NULL,
    wages_per_day DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_wages DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_status ENUM('pending', 'processed', 'paid') DEFAULT 'pending',
    payment_date DATE,
    payment_reference VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (laborer_id) REFERENCES hr_supervisor_laborer_registry(laborer_id) ON DELETE CASCADE,
    FOREIGN KEY (attendance_id) REFERENCES hr_supervisor_laborer_attendance_logs(attendance_id) ON DELETE CASCADE,
    UNIQUE KEY unique_laborer_attendance_wage (laborer_id, attendance_id)
);

-- Overtime Payment Records table
CREATE TABLE hr_supervisor_overtime_payment_records (
    overtime_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    laborer_id BIGINT NOT NULL,
    attendance_id BIGINT NOT NULL,
    ot_hours INT NOT NULL DEFAULT 0,
    ot_minutes INT NOT NULL DEFAULT 0, -- Only 0 or 30 allowed
    ot_total_hours DECIMAL(6,2) NOT NULL DEFAULT 0.00, -- Calculated field (hours + minutes/60)
    ot_rate_per_hour DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ot_total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_status ENUM('pending', 'processed', 'paid') DEFAULT 'pending',
    payment_date DATE,
    payment_reference VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (laborer_id) REFERENCES hr_supervisor_laborer_registry(laborer_id) ON DELETE CASCADE,
    FOREIGN KEY (attendance_id) REFERENCES hr_supervisor_laborer_attendance_logs(attendance_id) ON DELETE CASCADE,
    UNIQUE KEY unique_laborer_attendance_ot (laborer_id, attendance_id)
);

-- Travel Expense Records table
CREATE TABLE hr_supervisor_travel_expense_records (
    travel_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    laborer_id BIGINT NOT NULL,
    attendance_id BIGINT NOT NULL,
    transport_mode_id INT,
    travel_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    reimbursement_status ENUM('pending', 'processed', 'paid') DEFAULT 'pending',
    payment_date DATE,
    payment_reference VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (laborer_id) REFERENCES hr_supervisor_laborer_registry(laborer_id) ON DELETE CASCADE,
    FOREIGN KEY (attendance_id) REFERENCES hr_supervisor_laborer_attendance_logs(attendance_id) ON DELETE CASCADE,
    FOREIGN KEY (transport_mode_id) REFERENCES hr_supervisor_transport_modes(transport_mode_id),
    UNIQUE KEY unique_laborer_attendance_travel (laborer_id, attendance_id)
);

-- Create a view for consolidated laborer payments
CREATE OR REPLACE VIEW hr_supervisor_consolidated_laborer_payments AS
SELECT 
    lr.laborer_id,
    lr.laborer_name,
    lr.laborer_contact,
    vr.vendor_id,
    vr.vendor_name,
    vr.vendor_type,
    cse.event_id,
    cse.event_date,
    cs.site_name,
    al.attendance_id,
    al.morning_status,
    al.evening_status,
    al.attendance_percentage,
    wp.wages_per_day,
    wp.total_wages,
    op.ot_hours,
    op.ot_minutes,
    op.ot_rate_per_hour,
    op.ot_total_amount,
    tm.mode_name AS transport_mode,
    te.travel_amount,
    (IFNULL(wp.total_wages, 0) + IFNULL(op.ot_total_amount, 0) + IFNULL(te.travel_amount, 0)) AS grand_total
FROM 
    hr_supervisor_laborer_registry lr
JOIN 
    hr_supervisor_vendor_registry vr ON lr.vendor_id = vr.vendor_id
JOIN 
    hr_supervisor_calendar_site_events cse ON vr.event_id = cse.event_id
JOIN 
    hr_supervisor_construction_sites cs ON cse.site_id = cs.site_id
LEFT JOIN 
    hr_supervisor_laborer_attendance_logs al ON lr.laborer_id = al.laborer_id AND al.attendance_date = cse.event_date
LEFT JOIN 
    hr_supervisor_wage_payment_records wp ON al.attendance_id = wp.attendance_id
LEFT JOIN 
    hr_supervisor_overtime_payment_records op ON al.attendance_id = op.attendance_id
LEFT JOIN 
    hr_supervisor_travel_expense_records te ON al.attendance_id = te.attendance_id
LEFT JOIN 
    hr_supervisor_transport_modes tm ON te.transport_mode_id = tm.transport_mode_id;

-- Create a view for daily site summary
CREATE OR REPLACE VIEW hr_supervisor_daily_site_summary AS
SELECT 
    cse.event_date,
    cs.site_name,
    COUNT(DISTINCT vr.vendor_id) AS total_vendors,
    COUNT(DISTINCT lr.laborer_id) AS total_laborers,
    SUM(CASE WHEN al.morning_status = 'present' THEN 1 ELSE 0 END) AS morning_present,
    SUM(CASE WHEN al.evening_status = 'present' THEN 1 ELSE 0 END) AS evening_present,
    SUM(IFNULL(wp.total_wages, 0)) AS total_wages_paid,
    SUM(IFNULL(op.ot_total_amount, 0)) AS total_overtime_paid,
    SUM(IFNULL(te.travel_amount, 0)) AS total_travel_expenses,
    SUM(IFNULL(mtr.material_amount, 0)) AS total_material_cost,
    (SUM(IFNULL(wp.total_wages, 0)) + SUM(IFNULL(op.ot_total_amount, 0)) + 
     SUM(IFNULL(te.travel_amount, 0)) + SUM(IFNULL(mtr.material_amount, 0))) AS daily_total_expenses
FROM 
    hr_supervisor_calendar_site_events cse
JOIN 
    hr_supervisor_construction_sites cs ON cse.site_id = cs.site_id
LEFT JOIN 
    hr_supervisor_vendor_registry vr ON cse.event_id = vr.event_id
LEFT JOIN 
    hr_supervisor_laborer_registry lr ON vr.vendor_id = lr.vendor_id
LEFT JOIN 
    hr_supervisor_laborer_attendance_logs al ON lr.laborer_id = al.laborer_id AND al.attendance_date = cse.event_date
LEFT JOIN 
    hr_supervisor_wage_payment_records wp ON al.attendance_id = wp.attendance_id
LEFT JOIN 
    hr_supervisor_overtime_payment_records op ON al.attendance_id = op.attendance_id
LEFT JOIN 
    hr_supervisor_travel_expense_records te ON al.attendance_id = te.attendance_id
LEFT JOIN 
    hr_supervisor_material_transaction_records mtr ON vr.vendor_id = mtr.vendor_id
GROUP BY 
    cse.event_date, cs.site_name
ORDER BY 
    cse.event_date DESC;

-- Triggers to automatically calculate values
DELIMITER //

-- Trigger to set attendance percentage based on morning/evening status
CREATE TRIGGER hr_calculate_attendance_percentage
BEFORE INSERT ON hr_supervisor_laborer_attendance_logs
FOR EACH ROW
BEGIN
    IF NEW.morning_status = 'present' AND NEW.evening_status = 'present' THEN
        SET NEW.attendance_percentage = 100.00;
    ELSEIF NEW.morning_status = 'present' OR NEW.evening_status = 'present' THEN
        SET NEW.attendance_percentage = 50.00;
    ELSE
        SET NEW.attendance_percentage = 0.00;
    END IF;
END//

-- Trigger to update attendance percentage when status changes
CREATE TRIGGER hr_update_attendance_percentage
BEFORE UPDATE ON hr_supervisor_laborer_attendance_logs
FOR EACH ROW
BEGIN
    IF NEW.morning_status = 'present' AND NEW.evening_status = 'present' THEN
        SET NEW.attendance_percentage = 100.00;
    ELSEIF NEW.morning_status = 'present' OR NEW.evening_status = 'present' THEN
        SET NEW.attendance_percentage = 50.00;
    ELSE
        SET NEW.attendance_percentage = 0.00;
    END IF;
END//

-- Trigger to calculate total OT hours
CREATE TRIGGER hr_calculate_ot_total_hours
BEFORE INSERT ON hr_supervisor_overtime_payment_records
FOR EACH ROW
BEGIN
    -- Ensure minutes is either 0 or 30
    IF NEW.ot_minutes NOT IN (0, 30) THEN
        SET NEW.ot_minutes = 0;
    END IF;
    
    -- Calculate total hours (hours + minutes/60)
    SET NEW.ot_total_hours = NEW.ot_hours + (NEW.ot_minutes / 60);
    
    -- Calculate total OT amount
    SET NEW.ot_total_amount = NEW.ot_total_hours * NEW.ot_rate_per_hour;
END//

-- Trigger to update total OT hours when values change
CREATE TRIGGER hr_update_ot_total_hours
BEFORE UPDATE ON hr_supervisor_overtime_payment_records
FOR EACH ROW
BEGIN
    -- Ensure minutes is either 0 or 30
    IF NEW.ot_minutes NOT IN (0, 30) THEN
        SET NEW.ot_minutes = 0;
    END IF;
    
    -- Calculate total hours (hours + minutes/60)
    SET NEW.ot_total_hours = NEW.ot_hours + (NEW.ot_minutes / 60);
    
    -- Calculate total OT amount
    SET NEW.ot_total_amount = NEW.ot_total_hours * NEW.ot_rate_per_hour;
END//

-- Trigger to calculate total wages based on attendance percentage
CREATE TRIGGER hr_calculate_total_wages
BEFORE INSERT ON hr_supervisor_wage_payment_records
FOR EACH ROW
BEGIN
    DECLARE attendance_pct DECIMAL(5,2);
    
    -- Get attendance percentage
    SELECT attendance_percentage INTO attendance_pct
    FROM hr_supervisor_laborer_attendance_logs
    WHERE attendance_id = NEW.attendance_id;
    
    -- Calculate total wages based on attendance percentage
    SET NEW.total_wages = NEW.wages_per_day * (attendance_pct / 100);
END//

-- Trigger to update total wages when values change
CREATE TRIGGER hr_update_total_wages
BEFORE UPDATE ON hr_supervisor_wage_payment_records
FOR EACH ROW
BEGIN
    DECLARE attendance_pct DECIMAL(5,2);
    
    -- Get attendance percentage
    SELECT attendance_percentage INTO attendance_pct
    FROM hr_supervisor_laborer_attendance_logs
    WHERE attendance_id = NEW.attendance_id;
    
    -- Calculate total wages based on attendance percentage
    SET NEW.total_wages = NEW.wages_per_day * (attendance_pct / 100);
END//

DELIMITER ;

-- Add indexes for better performance
CREATE INDEX idx_event_date ON hr_supervisor_calendar_site_events(event_date);
CREATE INDEX idx_vendor_event ON hr_supervisor_vendor_registry(event_id);
CREATE INDEX idx_laborer_vendor ON hr_supervisor_laborer_registry(vendor_id);
CREATE INDEX idx_attendance_date ON hr_supervisor_laborer_attendance_logs(attendance_date);
CREATE INDEX idx_material_vendor ON hr_supervisor_material_transaction_records(vendor_id);
CREATE INDEX idx_photo_material ON hr_supervisor_material_photo_records(material_id);
CREATE INDEX idx_wage_laborer ON hr_supervisor_wage_payment_records(laborer_id);
CREATE INDEX idx_overtime_laborer ON hr_supervisor_overtime_payment_records(laborer_id);
CREATE INDEX idx_travel_laborer ON hr_supervisor_travel_expense_records(laborer_id);

-- Activity Log Table to track all form operations
CREATE TABLE hr_supervisor_activity_log (
    log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    user_name VARCHAR(100),
    action_type ENUM('create', 'update', 'delete', 'view', 'login', 'logout', 'payment_status_change') NOT NULL,
    entity_type ENUM('site', 'event', 'vendor', 'laborer', 'material', 'attendance', 'wage', 'overtime', 'travel', 'user', 'system') NOT NULL,
    entity_id BIGINT,
    event_id BIGINT,
    event_date DATE,
    description TEXT,
    old_values TEXT COMMENT 'JSON string of old values if applicable',
    new_values TEXT COMMENT 'JSON string of new values if applicable',
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES hr_supervisor_calendar_site_events(event_id) ON DELETE SET NULL
);

-- Create indexes for activity log
CREATE INDEX idx_activity_user ON hr_supervisor_activity_log(user_id);
CREATE INDEX idx_activity_action ON hr_supervisor_activity_log(action_type);
CREATE INDEX idx_activity_entity ON hr_supervisor_activity_log(entity_type, entity_id);
CREATE INDEX idx_activity_event ON hr_supervisor_activity_log(event_id);
CREATE INDEX idx_activity_date ON hr_supervisor_activity_log(event_date);
CREATE INDEX idx_activity_created ON hr_supervisor_activity_log(created_at); 