-- SQL Query to create employee salary records table
-- This table stores the base salary information for each employee by month/year

CREATE TABLE IF NOT EXISTS employee_salary_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(50) NOT NULL,
    user_id INT NOT NULL,
    base_salary DECIMAL(12, 2) NOT NULL,
    month INT NOT NULL COMMENT 'Month (1-12)',
    year INT NOT NULL COMMENT 'Year (e.g., 2025)',
    effective_from DATE,
    effective_to DATE,
    remarks VARCHAR(500),
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    -- Foreign key constraint
    CONSTRAINT fk_user_salary FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Unique constraint to prevent duplicate salary records for same employee in same month/year
    UNIQUE KEY unique_employee_salary_period (user_id, month, year),
    
    -- Indexes for better query performance
    INDEX idx_employee_id (employee_id),
    INDEX idx_user_id (user_id),
    INDEX idx_month_year (month, year),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alternative: If you want to track salary history for the same period
-- Remove the UNIQUE KEY and use a composite index instead:
-- 
-- CREATE TABLE IF NOT EXISTS employee_salary_records (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     employee_id VARCHAR(50) NOT NULL,
--     user_id INT NOT NULL,
--     base_salary DECIMAL(12, 2) NOT NULL,
--     month INT NOT NULL,
--     year INT NOT NULL,
--     effective_from DATE,
--     effective_to DATE,
--     remarks VARCHAR(500),
--     created_by INT,
--     updated_by INT,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--     deleted_at TIMESTAMP NULL,
--     
--     CONSTRAINT fk_user_salary FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
--     INDEX idx_employee_user_period (user_id, month, year),
--     INDEX idx_created_at (created_at)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample INSERT query
-- INSERT INTO employee_salary_records (employee_id, user_id, base_salary, month, year, effective_from, created_by)
-- VALUES ('EMP001', 1, 65000, 12, 2025, '2025-12-01', 1);

-- Sample UPDATE query
-- UPDATE employee_salary_records 
-- SET base_salary = 66000, updated_by = 1
-- WHERE user_id = 1 AND month = 12 AND year = 2025 AND deleted_at IS NULL;

-- Sample SELECT query to get latest salary for an employee in a specific month/year
-- SELECT * FROM employee_salary_records 
-- WHERE user_id = 1 AND month = 12 AND year = 2025 AND deleted_at IS NULL
-- ORDER BY created_at DESC LIMIT 1;
