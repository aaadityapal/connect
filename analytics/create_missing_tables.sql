-- Create missing tables for salary analytics dashboard

-- Create salary_payments table
CREATE TABLE IF NOT EXISTS salary_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method VARCHAR(50) DEFAULT 'bank_transfer',
    reference_number VARCHAR(100) DEFAULT NULL,
    month_year VARCHAR(7) NOT NULL, -- Format: YYYY-MM
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_month_year (month_year),
    INDEX idx_status (status),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create office_holidays table if it doesn't exist
CREATE TABLE IF NOT EXISTS office_holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_name VARCHAR(255) NOT NULL,
    holiday_date DATE NOT NULL,
    holiday_type ENUM('national', 'religious', 'company') DEFAULT 'company',
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_holiday_date (holiday_date),
    INDEX idx_is_active (is_active)
);

-- Insert some sample office holidays for current year
INSERT IGNORE INTO office_holidays (holiday_name, holiday_date, holiday_type, description) VALUES
('New Year Day', '2024-01-01', 'national', 'New Year celebration'),
('Republic Day', '2024-01-26', 'national', 'Republic Day of India'),
('Independence Day', '2024-08-15', 'national', 'Independence Day of India'),
('Gandhi Jayanti', '2024-10-02', 'national', 'Birth anniversary of Mahatma Gandhi'),
('Diwali', '2024-11-01', 'religious', 'Festival of lights'),
('Christmas', '2024-12-25', 'religious', 'Christmas Day');

-- Add current year holidays as well
INSERT IGNORE INTO office_holidays (holiday_name, holiday_date, holiday_type, description) VALUES
('New Year Day', '2025-01-01', 'national', 'New Year celebration'),
('Republic Day', '2025-01-26', 'national', 'Republic Day of India'),
('Independence Day', '2025-08-15', 'national', 'Independence Day of India'),
('Gandhi Jayanti', '2025-10-02', 'national', 'Birth anniversary of Mahatma Gandhi'),
('Diwali', '2025-11-01', 'religious', 'Festival of lights'),
('Christmas', '2025-12-25', 'religious', 'Christmas Day');