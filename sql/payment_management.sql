-- SQL for Payment Management System
-- This file creates the necessary table structure for storing payment entries and recipients

-- Create main payment entries table
CREATE TABLE IF NOT EXISTS hr_payment_entries (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    project_type VARCHAR(50) NOT NULL,
    project_id INT NOT NULL,
    payment_date DATE NOT NULL,
    payment_amount DECIMAL(15, 2) NOT NULL,
    payment_done_via INT NOT NULL COMMENT 'User ID who made the payment',
    payment_mode VARCHAR(50) NOT NULL,
    recipient_count INT NOT NULL DEFAULT 0,
    created_by INT COMMENT 'User ID who created this entry',
    updated_by INT COMMENT 'User ID who last updated this entry',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexing for common searches
    INDEX idx_project_id (project_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_payment_done_via (payment_done_via),
    INDEX idx_created_by (created_by),
    INDEX idx_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payment recipients table
CREATE TABLE IF NOT EXISTS hr_payment_recipients (
    recipient_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    category VARCHAR(50) NOT NULL COMMENT 'vendor, supplier, labour, contractor, employee, service_provider, other',
    type VARCHAR(100) NOT NULL,
    custom_type VARCHAR(100),
    entity_id INT COMMENT 'ID of the vendor/labour/etc. if applicable',
    name VARCHAR(100) NOT NULL,
    payment_for VARCHAR(255) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    payment_mode VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key relationship
    FOREIGN KEY (payment_id) REFERENCES hr_payment_entries(payment_id) ON DELETE CASCADE,
    
    -- Indexing for common searches
    INDEX idx_payment_id (payment_id),
    INDEX idx_category (category),
    INDEX idx_entity_id (entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table for split payments
CREATE TABLE IF NOT EXISTS hr_payment_splits (
    split_id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    payment_mode VARCHAR(50) NOT NULL,
    proof_file VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key relationship
    FOREIGN KEY (recipient_id) REFERENCES hr_payment_recipients(recipient_id) ON DELETE CASCADE,
    
    -- Indexing
    INDEX idx_recipient_id (recipient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create table for payment documents/proofs
CREATE TABLE IF NOT EXISTS hr_payment_documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key relationship
    FOREIGN KEY (recipient_id) REFERENCES hr_payment_recipients(recipient_id) ON DELETE CASCADE,
    
    -- Indexing
    INDEX idx_recipient_id (recipient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
