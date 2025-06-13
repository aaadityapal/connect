-- Create table for manager payment status
CREATE TABLE IF NOT EXISTS manager_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    manager_id INT NOT NULL,
    payment_status ENUM('pending', 'approved') DEFAULT 'pending',
    amount DECIMAL(10,2) NOT NULL,
    commission_rate DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_payment (project_id, manager_id),
    FOREIGN KEY (project_id) REFERENCES project_payouts(id) ON DELETE CASCADE
);

-- Add remaining_amount column to project_payouts table
-- Using a more compatible approach that works across MySQL versions
SET @columnExists = 0;
SELECT COUNT(*) INTO @columnExists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'project_payouts' AND COLUMN_NAME = 'remaining_amount';

SET @query = IF(@columnExists = 0, 
    'ALTER TABLE project_payouts ADD COLUMN remaining_amount DECIMAL(10,2) DEFAULT 0',
    'SELECT "Column remaining_amount already exists"');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt; 