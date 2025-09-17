-- SQL for Vendor Management System
-- This file creates the necessary table structure for storing vendor data

-- Create vendors table
CREATE TABLE IF NOT EXISTS hr_vendors (
    vendor_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    alternative_number VARCHAR(20),
    email VARCHAR(100) NOT NULL,
    vendor_type VARCHAR(50) NOT NULL,
    
    -- Banking details
    bank_name VARCHAR(100),
    account_number VARCHAR(50),
    routing_number VARCHAR(50),
    account_type VARCHAR(20),
    
    -- Address details
    street_address VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    zip_code VARCHAR(20),
    country VARCHAR(100),
    
    -- Additional information
    additional_notes TEXT,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexing for common searches
    INDEX idx_vendor_type (vendor_type),
    INDEX idx_email (email),
    INDEX idx_phone (phone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
