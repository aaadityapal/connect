-- Site Expenses Management System Database Schema

-- Database creation (uncomment if needed)
-- CREATE DATABASE hr_site_expenses;
-- USE hr_site_expenses;

-- Projects table
CREATE TABLE IF NOT EXISTS se_projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    project_location VARCHAR(255),
    project_code VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Payment modes table
CREATE TABLE IF NOT EXISTS se_payment_modes (
    mode_id INT AUTO_INCREMENT PRIMARY KEY,
    mode_name VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Payment types table
CREATE TABLE IF NOT EXISTS se_payment_types (
    type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users/staff who can access payments
CREATE TABLE IF NOT EXISTS se_staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_name VARCHAR(255) NOT NULL,
    role VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Vendors table
CREATE TABLE IF NOT EXISTS se_vendors (
    vendor_id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_name VARCHAR(255) NOT NULL,
    mobile_number VARCHAR(20),
    account_number VARCHAR(50),
    ifsc_code VARCHAR(20),
    upi_number VARCHAR(50),
    address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Main expenses table
CREATE TABLE IF NOT EXISTS se_expenses (
    expense_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_mode_id INT NOT NULL,
    payment_type_id INT NOT NULL,
    expense_datetime DATETIME NOT NULL,
    payment_access_by INT NOT NULL,
    remarks TEXT,
    receipt_file_path VARCHAR(255),
    status VARCHAR(20) DEFAULT 'completed',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES se_projects(project_id),
    FOREIGN KEY (payment_mode_id) REFERENCES se_payment_modes(mode_id),
    FOREIGN KEY (payment_type_id) REFERENCES se_payment_types(type_id),
    FOREIGN KEY (payment_access_by) REFERENCES se_staff(staff_id),
    FOREIGN KEY (created_by) REFERENCES se_staff(staff_id)
);

-- Vendor payments (links expenses to vendors)
CREATE TABLE IF NOT EXISTS se_vendor_payments (
    vendor_payment_id INT AUTO_INCREMENT PRIMARY KEY,
    expense_id INT NOT NULL,
    vendor_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (expense_id) REFERENCES se_expenses(expense_id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES se_vendors(vendor_id)
);

-- Equipment rental details
CREATE TABLE IF NOT EXISTS se_equipment_rentals (
    rental_id INT AUTO_INCREMENT PRIMARY KEY,
    expense_id INT NOT NULL,
    equipment_name VARCHAR(255) NOT NULL,
    rent_per_day DECIMAL(10,2) NOT NULL,
    rental_days INT NOT NULL,
    rental_total DECIMAL(12,2) NOT NULL,
    advance_amount DECIMAL(12,2) DEFAULT 0,
    balance_amount DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (expense_id) REFERENCES se_expenses(expense_id) ON DELETE CASCADE
);

-- Activity Log table for tracking all actions
CREATE TABLE IF NOT EXISTS se_activity_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT, -- Staff ID who performed the action
    activity_type ENUM('create', 'update', 'delete', 'view', 'approve', 'reject') NOT NULL,
    entity_type VARCHAR(50) NOT NULL, -- 'expense', 'vendor', 'project', etc.
    entity_id INT NOT NULL, -- ID of the affected record
    description TEXT NOT NULL, -- Detailed description of the activity
    old_values JSON, -- For updates, store previous values
    new_values JSON, -- For creates/updates, store new values
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES se_staff(staff_id)
);

-- Insert default data

-- Default payment modes
INSERT INTO se_payment_modes (mode_name) VALUES 
('Cash'),
('Bank Transfer'),
('Check'),
('Credit Card'),
('Debit Card'),
('UPI'),
('Digital Wallet');

-- Default payment types
INSERT INTO se_payment_types (type_name, description) VALUES 
('Vendor Payment', 'Payments to vendors for services or products'),
('Labour Wages', 'Payments to labourers for their work'),
('Labour Travelling', 'Travel expenses for labour'),
('Material Purchase', 'Purchase of materials for site work'),
('Travel Expenses', 'Travel expenses for staff/management'),
('Transportation', 'Transportation of materials or equipment'),
('Equipment Rental', 'Rental expenses for equipment'),
('Equipment Purchase', 'Purchase of equipment'),
('Utility Bills', 'Payment for utilities like electricity, water, etc.'),
('Miscellaneous', 'Other expenses that do not fit in above categories');

-- Default projects
INSERT INTO se_projects (project_name, project_location) VALUES 
('Project At Sector 80', 'Sector 80'),
('Project At Dilshad Garden', 'Dilshad Garden'),
('Project At Jasola', 'Jasola'),
('Project At Supertech', 'Supertech'),
('Project At Ballabgarh', 'Ballabgarh'),
('Project At Faridabad', 'Faridabad');

-- Default staff roles
INSERT INTO se_staff (staff_name, role, email) VALUES 
('Admin User', 'Administrator', 'admin@example.com'),
('Site Manager', 'Site Manager', 'site.manager@example.com'),
('Project Head', 'Project Head', 'project.head@example.com'),
('Finance Officer', 'Finance Officer', 'finance@example.com'),
('Procurement Officer', 'Procurement Officer', 'procurement@example.com');

-- Create triggers for activity logging

DELIMITER //

-- Trigger for logging expense creation
CREATE TRIGGER after_expense_insert
AFTER INSERT ON se_expenses
FOR EACH ROW
BEGIN
    INSERT INTO se_activity_log (user_id, activity_type, entity_type, entity_id, description, new_values)
    VALUES (
        NEW.created_by, 
        'create', 
        'expense', 
        NEW.expense_id, 
        CONCAT('New expense created for project ID: ', NEW.project_id, ' with amount: ', NEW.amount),
        JSON_OBJECT(
            'project_id', NEW.project_id,
            'amount', NEW.amount,
            'payment_mode_id', NEW.payment_mode_id,
            'payment_type_id', NEW.payment_type_id,
            'expense_datetime', NEW.expense_datetime,
            'payment_access_by', NEW.payment_access_by,
            'remarks', NEW.remarks,
            'receipt_file_path', NEW.receipt_file_path,
            'status', NEW.status
        )
    );
END //

-- Trigger for logging expense updates
CREATE TRIGGER after_expense_update
AFTER UPDATE ON se_expenses
FOR EACH ROW
BEGIN
    INSERT INTO se_activity_log (user_id, activity_type, entity_type, entity_id, description, old_values, new_values)
    VALUES (
        NEW.created_by, 
        'update', 
        'expense', 
        NEW.expense_id, 
        CONCAT('Expense updated for project ID: ', NEW.project_id, ' with amount: ', NEW.amount),
        JSON_OBJECT(
            'project_id', OLD.project_id,
            'amount', OLD.amount,
            'payment_mode_id', OLD.payment_mode_id,
            'payment_type_id', OLD.payment_type_id,
            'expense_datetime', OLD.expense_datetime,
            'payment_access_by', OLD.payment_access_by,
            'remarks', OLD.remarks,
            'receipt_file_path', OLD.receipt_file_path,
            'status', OLD.status
        ),
        JSON_OBJECT(
            'project_id', NEW.project_id,
            'amount', NEW.amount,
            'payment_mode_id', NEW.payment_mode_id,
            'payment_type_id', NEW.payment_type_id,
            'expense_datetime', NEW.expense_datetime,
            'payment_access_by', NEW.payment_access_by,
            'remarks', NEW.remarks,
            'receipt_file_path', NEW.receipt_file_path,
            'status', NEW.status
        )
    );
END //

-- Trigger for logging vendor payment creation
CREATE TRIGGER after_vendor_payment_insert
AFTER INSERT ON se_vendor_payments
FOR EACH ROW
BEGIN
    INSERT INTO se_activity_log (activity_type, entity_type, entity_id, description, new_values)
    VALUES (
        'create', 
        'vendor_payment', 
        NEW.vendor_payment_id, 
        CONCAT('New vendor payment created for expense ID: ', NEW.expense_id, ' to vendor ID: ', NEW.vendor_id),
        JSON_OBJECT(
            'expense_id', NEW.expense_id,
            'vendor_id', NEW.vendor_id,
            'amount', NEW.amount,
            'payment_details', NEW.payment_details
        )
    );
END //

-- Trigger for logging equipment rental creation
CREATE TRIGGER after_equipment_rental_insert
AFTER INSERT ON se_equipment_rentals
FOR EACH ROW
BEGIN
    INSERT INTO se_activity_log (activity_type, entity_type, entity_id, description, new_values)
    VALUES (
        'create', 
        'equipment_rental', 
        NEW.rental_id, 
        CONCAT('New equipment rental created for expense ID: ', NEW.expense_id, ' - ', NEW.equipment_name),
        JSON_OBJECT(
            'expense_id', NEW.expense_id,
            'equipment_name', NEW.equipment_name,
            'rent_per_day', NEW.rent_per_day,
            'rental_days', NEW.rental_days,
            'rental_total', NEW.rental_total,
            'advance_amount', NEW.advance_amount,
            'balance_amount', NEW.balance_amount
        )
    );
END //

DELIMITER ;

-- Create stored procedures for common operations

DELIMITER //

-- Procedure to add a new expense with vendor details
CREATE PROCEDURE sp_add_expense_with_vendor(
    IN p_project_id INT,
    IN p_amount DECIMAL(12,2),
    IN p_payment_mode_id INT,
    IN p_payment_type_id INT,
    IN p_expense_datetime DATETIME,
    IN p_payment_access_by INT,
    IN p_remarks TEXT,
    IN p_receipt_file_path VARCHAR(255),
    IN p_created_by INT,
    IN p_vendor_id INT,
    IN p_vendor_amount DECIMAL(12,2),
    IN p_vendor_payment_details TEXT
)
BEGIN
    DECLARE new_expense_id INT;
    
    -- Insert main expense
    INSERT INTO se_expenses (
        project_id, 
        amount, 
        payment_mode_id, 
        payment_type_id, 
        expense_datetime, 
        payment_access_by, 
        remarks, 
        receipt_file_path, 
        created_by
    ) VALUES (
        p_project_id,
        p_amount,
        p_payment_mode_id,
        p_payment_type_id,
        p_expense_datetime,
        p_payment_access_by,
        p_remarks,
        p_receipt_file_path,
        p_created_by
    );
    
    -- Get the new expense ID
    SET new_expense_id = LAST_INSERT_ID();
    
    -- Insert vendor payment if vendor ID is provided
    IF p_vendor_id IS NOT NULL THEN
        INSERT INTO se_vendor_payments (
            expense_id,
            vendor_id,
            amount,
            payment_details
        ) VALUES (
            new_expense_id,
            p_vendor_id,
            p_vendor_amount,
            p_vendor_payment_details
        );
    END IF;
    
    -- Return the new expense ID
    SELECT new_expense_id AS expense_id;
END //

-- Procedure to add a new expense with equipment rental details
CREATE PROCEDURE sp_add_expense_with_equipment_rental(
    IN p_project_id INT,
    IN p_amount DECIMAL(12,2),
    IN p_payment_mode_id INT,
    IN p_payment_type_id INT,
    IN p_expense_datetime DATETIME,
    IN p_payment_access_by INT,
    IN p_remarks TEXT,
    IN p_receipt_file_path VARCHAR(255),
    IN p_created_by INT,
    IN p_equipment_name VARCHAR(255),
    IN p_rent_per_day DECIMAL(10,2),
    IN p_rental_days INT,
    IN p_rental_total DECIMAL(12,2),
    IN p_advance_amount DECIMAL(12,2),
    IN p_balance_amount DECIMAL(12,2)
)
BEGIN
    DECLARE new_expense_id INT;
    
    -- Insert main expense
    INSERT INTO se_expenses (
        project_id, 
        amount, 
        payment_mode_id, 
        payment_type_id, 
        expense_datetime, 
        payment_access_by, 
        remarks, 
        receipt_file_path, 
        created_by
    ) VALUES (
        p_project_id,
        p_amount,
        p_payment_mode_id,
        p_payment_type_id,
        p_expense_datetime,
        p_payment_access_by,
        p_remarks,
        p_receipt_file_path,
        p_created_by
    );
    
    -- Get the new expense ID
    SET new_expense_id = LAST_INSERT_ID();
    
    -- Insert equipment rental details
    INSERT INTO se_equipment_rentals (
        expense_id,
        equipment_name,
        rent_per_day,
        rental_days,
        rental_total,
        advance_amount,
        balance_amount
    ) VALUES (
        new_expense_id,
        p_equipment_name,
        p_rent_per_day,
        p_rental_days,
        p_rental_total,
        p_advance_amount,
        p_balance_amount
    );
    
    -- Return the new expense ID
    SELECT new_expense_id AS expense_id;
END //

-- Procedure to get expense details with related information
CREATE PROCEDURE sp_get_expense_details(
    IN p_expense_id INT
)
BEGIN
    -- Main expense details
    SELECT 
        e.expense_id,
        p.project_name,
        e.amount,
        m.mode_name AS payment_mode,
        t.type_name AS payment_type,
        e.expense_datetime,
        s.staff_name AS payment_access_by,
        e.remarks,
        e.receipt_file_path,
        e.status,
        e.created_at
    FROM se_expenses e
    JOIN se_projects p ON e.project_id = p.project_id
    JOIN se_payment_modes m ON e.payment_mode_id = m.mode_id
    JOIN se_payment_types t ON e.payment_type_id = t.type_id
    JOIN se_staff s ON e.payment_access_by = s.staff_id
    WHERE e.expense_id = p_expense_id;
    
    -- Vendor payment details if any
    SELECT 
        vp.vendor_payment_id,
        v.vendor_name,
        v.mobile_number,
        v.account_number,
        v.ifsc_code,
        v.upi_number,
        vp.amount,
        vp.payment_details
    FROM se_vendor_payments vp
    JOIN se_vendors v ON vp.vendor_id = v.vendor_id
    WHERE vp.expense_id = p_expense_id;
    
    -- Equipment rental details if any
    SELECT 
        er.rental_id,
        er.equipment_name,
        er.rent_per_day,
        er.rental_days,
        er.rental_total,
        er.advance_amount,
        er.balance_amount
    FROM se_equipment_rentals er
    WHERE er.expense_id = p_expense_id;
    
    -- Activity log for this expense
    SELECT 
        log_id,
        activity_type,
        description,
        created_at
    FROM se_activity_log
    WHERE entity_type = 'expense' AND entity_id = p_expense_id
    ORDER BY created_at DESC;
END //

DELIMITER ;

-- View for summary reporting
CREATE VIEW vw_expense_summary AS
SELECT 
    p.project_name,
    t.type_name AS expense_type,
    m.mode_name AS payment_mode,
    SUM(e.amount) AS total_amount,
    COUNT(e.expense_id) AS transaction_count,
    MIN(e.expense_datetime) AS first_transaction,
    MAX(e.expense_datetime) AS last_transaction
FROM se_expenses e
JOIN se_projects p ON e.project_id = p.project_id
JOIN se_payment_types t ON e.payment_type_id = t.type_id
JOIN se_payment_modes m ON e.payment_mode_id = m.mode_id
GROUP BY p.project_id, t.type_id, m.mode_id;

-- View for recent expenses with details
CREATE VIEW vw_recent_expenses AS
SELECT 
    e.expense_id,
    p.project_name,
    e.amount,
    m.mode_name AS payment_mode,
    t.type_name AS payment_type,
    e.expense_datetime,
    s.staff_name AS payment_access_by,
    e.remarks,
    CASE 
        WHEN er.rental_id IS NOT NULL THEN CONCAT('Equipment: ', er.equipment_name)
        WHEN vp.vendor_payment_id IS NOT NULL THEN CONCAT('Vendor: ', v.vendor_name)
        ELSE NULL
    END AS additional_info,
    e.status,
    e.created_at
FROM se_expenses e
JOIN se_projects p ON e.project_id = p.project_id
JOIN se_payment_modes m ON e.payment_mode_id = m.mode_id
JOIN se_payment_types t ON e.payment_type_id = t.type_id
JOIN se_staff s ON e.payment_access_by = s.staff_id
LEFT JOIN se_equipment_rentals er ON e.expense_id = er.expense_id
LEFT JOIN se_vendor_payments vp ON e.expense_id = vp.expense_id
LEFT JOIN se_vendors v ON vp.vendor_id = v.vendor_id
ORDER BY e.created_at DESC
LIMIT 100; 