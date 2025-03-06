CREATE TABLE hr_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    upload_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Company Settings
CREATE TABLE company_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255),
    company_address TEXT,
    company_email VARCHAR(255),
    company_phone VARCHAR(50),
    company_website VARCHAR(255),
    tax_id VARCHAR(50),
    fiscal_year_start DATE,
    timezone VARCHAR(100),
    date_format VARCHAR(50),
    currency VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Leave Settings
CREATE TABLE leave_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    annual_leave_days INT,
    sick_leave_days INT,
    casual_leave_days INT,
    maternity_leave_days INT,
    paternity_leave_days INT,
    carry_forward_limit INT,
    leave_approval_chain JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Attendance Settings
CREATE TABLE attendance_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    work_hours_per_day DECIMAL(4,2),
    grace_time_minutes INT,
    half_day_hours DECIMAL(4,2),
    overtime_threshold DECIMAL(4,2),
    weekend_days JSON,
    ip_restriction BOOLEAN,
    allowed_ips JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Payroll Settings
CREATE TABLE payroll_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    salary_calculation_type ENUM('monthly', 'hourly'),
    payment_date INT,
    tax_calculation_method ENUM('progressive', 'flat'),
    pf_contribution_rate DECIMAL(5,2),
    insurance_deduction JSON,
    bonus_calculation JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


ALTER TABLE hr_documents
ADD COLUMN file_size BIGINT,
ADD COLUMN file_type VARCHAR(100),
ADD COLUMN uploaded_by INT,
ADD COLUMN last_modified DATETIME,
ADD COLUMN status VARCHAR(20) DEFAULT 'published',
ADD FOREIGN KEY (uploaded_by) REFERENCES users(id);

CREATE TABLE hr_documents_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT,
    action VARCHAR(50),
    action_by INT,
    action_date DATETIME,
    document_type VARCHAR(100),
    FOREIGN KEY (document_id) REFERENCES hr_documents(id),
    FOREIGN KEY (action_by) REFERENCES users(id)
);


-- Drop existing foreign key
ALTER TABLE hr_documents_log 
DROP FOREIGN KEY hr_documents_log_ibfk_1;

-- Add new foreign key with CASCADE
ALTER TABLE hr_documents_log
ADD CONSTRAINT hr_documents_log_ibfk_1 
FOREIGN KEY (document_id) 
REFERENCES hr_documents(id) 
ON DELETE CASCADE;


CREATE TABLE document_acknowledgments (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    document_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    acknowledged_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_acknowledgment (document_id, user_id),
    FOREIGN KEY (document_id) REFERENCES hr_documents(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);









ALTER TABLE projects
ADD INDEX idx_status (status),
ADD INDEX idx_dates (start_date, end_date),
ADD INDEX idx_deleted (deleted_at);


CREATE TABLE daily_thoughts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    thought TEXT NOT NULL,
    author VARCHAR(100),
    date_added DATE NOT NULL
);


INSERT INTO daily_thoughts (thought, author, date_added) VALUES
('Success is not final, failure is not fatal: it is the courage to continue that counts.', 'Winston Churchill', CURRENT_DATE),
('The only way to do great work is to love what you do.', 'Steve Jobs', DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)),
('Innovation distinguishes between a leader and a follower.', 'Steve Jobs', DATE_SUB(CURRENT_DATE, INTERVAL 2 DAY)),
('The future belongs to those who believe in the beauty of their dreams.', 'Eleanor Roosevelt', DATE_SUB(CURRENT_DATE, INTERVAL 3 DAY)),
('It does not matter how slowly you go as long as you do not stop.', 'Confucius', DATE_SUB(CURRENT_DATE, INTERVAL 4 DAY));