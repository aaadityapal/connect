-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS crm;
USE crm;

-- Create managers table
CREATE TABLE IF NOT EXISTS managers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    initials VARCHAR(5) NOT NULL,
    color VARCHAR(20) NOT NULL,
    department ENUM('architecture', 'interior', 'construction') NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    fixed_remuneration DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create project_payouts table
CREATE TABLE IF NOT EXISTS project_payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    project_type ENUM('architecture', 'interior', 'construction') NOT NULL,
    client_name VARCHAR(100) NOT NULL,
    project_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_mode VARCHAR(50) NOT NULL,
    project_stage INT NOT NULL,
    manager_id INT NOT NULL,
    notes TEXT,
    remaining_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES managers(id)
);

-- Insert sample managers
INSERT INTO managers (name, initials, color, department, fixed_remuneration) VALUES
('Rajesh Singh', 'RS', 'primary', 'architecture', 40000),
('Anjali Patel', 'AP', 'info', 'interior', 40000),
('Vikram Kumar', 'VK', 'warning', 'construction', 40000);

-- Insert sample project payouts for June 2025
INSERT INTO project_payouts (project_name, project_type, client_name, project_date, amount, payment_mode, project_stage, manager_id, notes) VALUES
-- Rajesh Singh (Architecture)
('Skyline Tower', 'architecture', 'Global Developers', '2025-06-05', 100000, 'bank_transfer', 3, 1, 'First payment for Q2'),
('Skyline Tower', 'architecture', 'Global Developers', '2025-06-15', 145800, 'bank_transfer', 5, 1, 'Final payment for Q2'),

-- Anjali Patel (Interior)
('Modern Office Complex', 'interior', 'TechSpace Inc.', '2025-06-10', 100000, 'bank_transfer', 4, 2, 'Advance payment'),
('Modern Office Complex', 'interior', 'TechSpace Inc.', '2025-06-20', 118400, 'bank_transfer', 7, 2, 'Milestone completion payment'),

-- Vikram Kumar (Construction)
('Riverside Residences', 'construction', 'Urban Living Ltd.', '2025-06-05', 150000, 'bank_transfer', 2, 3, 'Initial phase completion');

-- Insert sample project payouts for May 2025
INSERT INTO project_payouts (project_name, project_type, client_name, project_date, amount, payment_mode, project_stage, manager_id, notes) VALUES
-- Rajesh Singh (Architecture)
('Green Valley Resort', 'architecture', 'Leisure Hospitality', '2025-05-10', 100000, 'bank_transfer', 2, 1, 'Design phase completion'),
('Green Valley Resort', 'architecture', 'Leisure Hospitality', '2025-05-25', 128500, 'bank_transfer', 4, 1, 'Construction documents'),

-- Anjali Patel (Interior)
('Luxury Apartments', 'interior', 'Elite Homes', '2025-05-15', 203300, 'bank_transfer', 6, 2, 'Full payment for May'),

-- Vikram Kumar (Construction)
('Commercial Plaza', 'construction', 'Business Ventures', '2025-05-10', 100000, 'bank_transfer', 3, 3, 'Foundation work'),
('Commercial Plaza', 'construction', 'Business Ventures', '2025-05-25', 118500, 'bank_transfer', 5, 3, 'Structure completion');

-- Insert sample project payouts for April 2025
INSERT INTO project_payouts (project_name, project_type, client_name, project_date, amount, payment_mode, project_stage, manager_id, notes) VALUES
-- Rajesh Singh (Architecture)
('Mountain View Condos', 'architecture', 'Alpine Developers', '2025-04-15', 231800, 'bank_transfer', 5, 1, 'Full payment for April'),

-- Anjali Patel (Interior)
('Corporate Headquarters', 'interior', 'Mega Corp', '2025-04-12', 100000, 'bank_transfer', 2, 2, 'Initial design payment'),
('Corporate Headquarters', 'interior', 'Mega Corp', '2025-04-25', 107500, 'bank_transfer', 4, 2, 'Material selection phase');

-- Insert sample project payouts for 2024
INSERT INTO project_payouts (project_name, project_type, client_name, project_date, amount, payment_mode, project_stage, manager_id, notes) VALUES
-- Rajesh Singh (Architecture)
('Urban Lofts', 'architecture', 'City Living', '2024-06-10', 100000, 'bank_transfer', 3, 1, 'Design phase'),
('Urban Lofts', 'architecture', 'City Living', '2024-06-20', 121400, 'bank_transfer', 5, 1, 'Final payment'),
('Seaside Villas', 'architecture', 'Coastal Properties', '2024-05-15', 206500, 'bank_transfer', 4, 1, 'Full payment'),

-- Anjali Patel (Interior)
('Boutique Hotel', 'interior', 'Hospitality Group', '2024-06-15', 194000, 'bank_transfer', 6, 2, 'Full payment'),

-- Vikram Kumar (Construction)
('Shopping Mall', 'construction', 'Retail Developers', '2024-12-15', 204000, 'bank_transfer', 5, 3, 'Full payment');

-- Insert sample project payouts for 2023
INSERT INTO project_payouts (project_name, project_type, client_name, project_date, amount, payment_mode, project_stage, manager_id, notes) VALUES
-- Anjali Patel (Interior)
('Heritage Restoration', 'interior', 'Historical Society', '2023-12-10', 80000, 'bank_transfer', 2, 2, 'Initial payment'),
('Heritage Restoration', 'interior', 'Historical Society', '2023-12-20', 100000, 'bank_transfer', 4, 2, 'Final payment'); 