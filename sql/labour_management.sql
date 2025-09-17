-- SQL for Labour Management System
-- This file creates the necessary table structure for storing labour data

-- Create labour table
CREATE TABLE IF NOT EXISTS hr_labours (
    labour_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    position VARCHAR(50) NOT NULL,
    position_custom VARCHAR(100),
    phone_number VARCHAR(20) NOT NULL,
    alternative_number VARCHAR(20),
    join_date DATE NOT NULL,
    labour_type VARCHAR(50) NOT NULL,
    daily_salary DECIMAL(10, 2),
    
    -- ID Proof documents
    aadhar_card VARCHAR(255),
    pan_card VARCHAR(255),
    voter_id VARCHAR(255),
    other_document VARCHAR(255),
    
    -- Address information
    address VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    
    -- Additional information
    notes TEXT,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexing for common searches
    INDEX idx_labour_type (labour_type),
    INDEX idx_position (position),
    INDEX idx_join_date (join_date),
    INDEX idx_phone (phone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
