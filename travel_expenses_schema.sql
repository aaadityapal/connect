-- Travel Expenses Schema
-- SQL Script to create tables for travel expenses functionality

-- Create travel_expenses table
CREATE TABLE IF NOT EXISTS `travel_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `mode_of_transport` varchar(50) NOT NULL,
  `from_location` varchar(255) NOT NULL,
  `to_location` varchar(255) NOT NULL,
  `travel_date` date NOT NULL,
  `distance` decimal(10,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_travel_date` (`travel_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create travel_expense_attachments table for future enhancement
-- This allows users to attach receipts or other documents to their expenses
CREATE TABLE IF NOT EXISTS `travel_expense_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_expense_id` (`expense_id`),
  CONSTRAINT `fk_expense_attachment` FOREIGN KEY (`expense_id`) REFERENCES `travel_expenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create travel_expense_approvals table for tracking approval flow
CREATE TABLE IF NOT EXISTS `travel_expense_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_id` int(11) NOT NULL,
  `approver_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `comments` text DEFAULT NULL,
  `action_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_expense_id` (`expense_id`),
  KEY `idx_approver_id` (`approver_id`),
  CONSTRAINT `fk_expense_approval` FOREIGN KEY (`expense_id`) REFERENCES `travel_expenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create travel_expense_settings table for company policies
CREATE TABLE IF NOT EXISTS `travel_expense_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default settings
INSERT INTO `travel_expense_settings` (`setting_key`, `setting_value`, `description`) VALUES
('max_amount_per_day', '1000', 'Maximum amount allowed per day in rupees'),
('allowed_transport_modes', 'Car,Bike,Public Transport,Taxi,Other', 'Comma-separated list of allowed transport modes'),
('rate_per_km_car', '8', 'Reimbursement rate per kilometer for car travel in rupees'),
('rate_per_km_bike', '4', 'Reimbursement rate per kilometer for bike travel in rupees'),
('approval_threshold', '5000', 'Amount threshold above which higher level approval is required');

-- Create travel_expense_summary view for easy reporting
CREATE OR REPLACE VIEW `travel_expense_summary` AS
SELECT 
    u.username,
    u.id AS user_id,
    COUNT(te.id) AS total_expenses,
    SUM(te.amount) AS total_amount,
    MIN(te.travel_date) AS earliest_date,
    MAX(te.travel_date) AS latest_date,
    COUNT(CASE WHEN te.status = 'approved' THEN 1 END) AS approved_count,
    COUNT(CASE WHEN te.status = 'rejected' THEN 1 END) AS rejected_count,
    COUNT(CASE WHEN te.status = 'pending' THEN 1 END) AS pending_count,
    SUM(CASE WHEN te.status = 'approved' THEN te.amount ELSE 0 END) AS approved_amount
FROM 
    users u
LEFT JOIN 
    travel_expenses te ON u.id = te.user_id
GROUP BY 
    u.id, u.username;

-- Add foreign key constraint to travel_expenses table
-- Note: This assumes the users table already exists in your database
ALTER TABLE `travel_expenses`
ADD CONSTRAINT `fk_user_travel_expense` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE; 