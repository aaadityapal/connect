-- SQL Script to create travel_expenses table

-- Create travel_expenses table if it doesn't exist
CREATE TABLE IF NOT EXISTS travel_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    mode_of_transport VARCHAR(50) NOT NULL,
    from_location VARCHAR(255) NOT NULL,
    to_location VARCHAR(255) NOT NULL,
    travel_date DATE NOT NULL,
    distance DECIMAL(10,2) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    notes TEXT,
    bill_file_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add indexes for better performance
CREATE INDEX idx_travel_expenses_user_id ON travel_expenses(user_id);
CREATE INDEX idx_travel_expenses_travel_date ON travel_expenses(travel_date);
CREATE INDEX idx_travel_expenses_status ON travel_expenses(status);

-- Create table for travel expense approvals
CREATE TABLE IF NOT EXISTS travel_expense_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_id INT NOT NULL,
    approver_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (expense_id) REFERENCES travel_expenses(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for travel expense settings
CREATE TABLE IF NOT EXISTS travel_expense_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO travel_expense_settings (setting_key, setting_value)
VALUES ('approval_threshold', '5000')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Sample data for testing (uncomment to use)
/*
INSERT INTO travel_expenses (user_id, purpose, mode_of_transport, from_location, to_location, travel_date, distance, amount, status, notes)
VALUES 
(1, 'Client meeting', 'Car', 'Office', 'Client Site', '2023-06-15', 25.5, 350.00, 'approved', 'Met with client to discuss project requirements'),
(1, 'Site visit', 'Taxi', 'Office', 'Construction Site', '2023-06-16', 30.0, 500.00, 'pending', 'Visited construction site for inspection'),
(1, 'Team outing', 'Bus', 'Office', 'Resort', '2023-06-18', 15.0, 150.00, 'pending', 'Team building activity'),
(1, 'Conference', 'Train', 'City', 'Conference Center', '2023-06-20', 100.0, 800.00, 'rejected', 'Attended industry conference'),
(1, 'Document delivery', 'Bike', 'Office', 'Client Office', '2023-06-22', 10.0, 120.00, 'approved', 'Delivered important documents to client');
*/ 