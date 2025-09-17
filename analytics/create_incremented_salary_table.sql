-- Create table to store incremented salary data from analytics dashboard
CREATE TABLE IF NOT EXISTS incremented_salary_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filter_month VARCHAR(7) NOT NULL, -- Format: YYYY-MM
    base_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00, -- Original salary from users table
    previous_incremented_salary DECIMAL(10,2) DEFAULT NULL, -- Previous incremented salary (if any)
    incremented_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00, -- New incremented salary
    increment_amount DECIMAL(10,2) GENERATED ALWAYS AS (incremented_salary - base_salary) STORED,
    actual_change_amount DECIMAL(10,2) GENERATED ALWAYS AS (
        CASE 
            WHEN previous_incremented_salary IS NOT NULL 
            THEN (incremented_salary - previous_incremented_salary)
            ELSE (incremented_salary - base_salary)
        END
    ) STORED,
    increment_percentage DECIMAL(5,2) GENERATED ALWAYS AS (
        CASE 
            WHEN base_salary > 0 THEN ((incremented_salary - base_salary) / base_salary * 100)
            ELSE 0 
        END
    ) STORED,
    actual_change_percentage DECIMAL(5,2) GENERATED ALWAYS AS (
        CASE 
            WHEN previous_incremented_salary IS NOT NULL AND previous_incremented_salary > 0 
            THEN ((incremented_salary - previous_incremented_salary) / previous_incremented_salary * 100)
            WHEN base_salary > 0 
            THEN ((incremented_salary - base_salary) / base_salary * 100)
            ELSE 0 
        END
    ) STORED,
    working_days INT DEFAULT 0,
    present_days INT DEFAULT 0,
    excess_days INT DEFAULT 0,
    late_punch_in_days INT DEFAULT 0,
    late_deduction_amount DECIMAL(10,2) DEFAULT 0.00,
    leave_taken_days DECIMAL(4,1) DEFAULT 0.0,
    leave_deduction_amount DECIMAL(10,2) DEFAULT 0.00,
    one_hour_late_days INT DEFAULT 0,
    one_hour_late_deduction_amount DECIMAL(10,2) DEFAULT 0.00,
    fourth_saturday_penalty_amount DECIMAL(10,2) DEFAULT 0.00,
    total_deductions DECIMAL(10,2) DEFAULT 0.00,
    monthly_salary_after_deductions DECIMAL(10,2) DEFAULT 0.00,
    final_salary_percentage DECIMAL(5,2) GENERATED ALWAYS AS (
        CASE 
            WHEN incremented_salary > 0 THEN (monthly_salary_after_deductions / incremented_salary * 100)
            ELSE 0 
        END
    ) STORED,
    created_by INT DEFAULT NULL, -- HR user who made the change
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    notes TEXT DEFAULT NULL,
    status ENUM('active', 'archived', 'cancelled') DEFAULT 'active',
    
    -- Indexes for better performance
    INDEX idx_user_month (user_id, filter_month),
    INDEX idx_filter_month (filter_month),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status),
    
    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Unique constraint to prevent duplicate entries for same user/month
    UNIQUE KEY unique_user_month (user_id, filter_month)
);

-- Create a view for easy analytics reporting
CREATE OR REPLACE VIEW v_incremented_salary_analytics AS
SELECT 
    isa.*,
    u.username,
    u.employee_id,
    u.email,
    u.department,
    u.designation,
    creator.username as created_by_username,
    CONCAT(u.username, ' (', u.employee_id, ')') as user_display_name,
    DATE_FORMAT(isa.filter_month, '%M %Y') as month_display,
    CASE 
        WHEN isa.actual_change_amount > 0 THEN 'Increment'
        WHEN isa.actual_change_amount < 0 THEN 'Decrement'
        ELSE 'No Change'
    END as increment_type,
    CASE 
        WHEN isa.final_salary_percentage >= 90 THEN 'Excellent'
        WHEN isa.final_salary_percentage >= 80 THEN 'Good'
        WHEN isa.final_salary_percentage >= 70 THEN 'Average'
        ELSE 'Needs Attention'
    END as performance_rating
FROM incremented_salary_analytics isa
LEFT JOIN users u ON isa.user_id = u.id
LEFT JOIN users creator ON isa.created_by = creator.id
WHERE isa.status = 'active'
ORDER BY isa.filter_month DESC, u.username ASC;

-- Insert sample data (optional - remove if not needed)
-- INSERT INTO incremented_salary_analytics 
-- (user_id, filter_month, base_salary, incremented_salary, working_days, present_days, created_by)
-- VALUES 
-- (1, '2024-12', 25000.00, 27000.00, 25, 24, 1),
-- (2, '2024-12', 30000.00, 32000.00, 25, 25, 1);

-- Create the log table first
CREATE TABLE IF NOT EXISTS salary_change_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filter_month VARCHAR(7) NOT NULL,
    old_salary DECIMAL(10,2) NOT NULL,
    new_salary DECIMAL(10,2) NOT NULL,
    change_type VARCHAR(50) NOT NULL,
    changed_by INT DEFAULT NULL,
    change_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT DEFAULT NULL,
    
    INDEX idx_user_id (user_id),
    INDEX idx_filter_month (filter_month),
    INDEX idx_change_date (change_date),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
);