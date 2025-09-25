-- Add payment proof image column to main payment entries table
ALTER TABLE hr_payment_entries 
ADD COLUMN payment_proof_image VARCHAR(500) NULL 
COMMENT 'Path to uploaded payment proof image (PDF, JPEG, PNG)';

-- Create table for storing main payment split data
-- This table stores split payment methods for the main payment entry
CREATE TABLE IF NOT EXISTS hr_main_payment_splits (
    main_split_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_mode VARCHAR(100) NOT NULL,
    proof_file VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraint
    CONSTRAINT fk_main_split_payment 
        FOREIGN KEY (payment_id) 
        REFERENCES hr_payment_entries(payment_id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Indexes for performance
    INDEX idx_payment_id (payment_id),
    INDEX idx_payment_mode (payment_mode),
    INDEX idx_created_at (created_at)
) 
ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_unicode_ci 
COMMENT='Stores split payment methods for main payment entries when payment is divided across multiple modes';

-- Create table for storing split payment data
-- This table stores individual payment methods when a payment is split across multiple modes
CREATE TABLE IF NOT EXISTS hr_payment_splits (
    split_id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_mode VARCHAR(100) NOT NULL,
    payment_for VARCHAR(500) NOT NULL,
    proof_file VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraint
    CONSTRAINT fk_split_recipient 
        FOREIGN KEY (recipient_id) 
        REFERENCES hr_payment_recipients(recipient_id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Indexes for performance
    INDEX idx_recipient_id (recipient_id),
    INDEX idx_payment_mode (payment_mode),
    INDEX idx_created_at (created_at)
) 
ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_unicode_ci 
COMMENT='Stores split payment methods for recipients when main payment is divided across multiple modes';

-- Create table for main payment recipients (if not exists)
CREATE TABLE IF NOT EXISTS hr_payment_recipients (
    recipient_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    type VARCHAR(100) NULL,
    custom_type VARCHAR(200) NULL,
    entity_id INT NULL,
    name VARCHAR(255) NOT NULL,
    payment_for VARCHAR(500) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_mode VARCHAR(100) NOT NULL,
    created_by INT NOT NULL,
    updated_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    CONSTRAINT fk_recipient_payment 
        FOREIGN KEY (payment_id) 
        REFERENCES hr_payment_entries(payment_id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Indexes for performance
    INDEX idx_payment_id (payment_id),
    INDEX idx_category (category),
    INDEX idx_payment_mode (payment_mode),
    INDEX idx_created_at (created_at)
) 
ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_unicode_ci 
COMMENT='Stores individual recipients for each payment entry';

-- Create table for payment documents (if not exists)
CREATE TABLE IF NOT EXISTS hr_payment_documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraint
    CONSTRAINT fk_document_recipient 
        FOREIGN KEY (recipient_id) 
        REFERENCES hr_payment_recipients(recipient_id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Indexes for performance
    INDEX idx_recipient_id (recipient_id),
    INDEX idx_file_type (file_type),
    INDEX idx_uploaded_at (uploaded_at)
) 
ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_unicode_ci 
COMMENT='Stores uploaded documents/proofs for payment recipients';

-- Sample insert for testing split payment functionality
-- This shows how split payment data would be stored
/*
Example: A payment of ₹10,000 split as:
- ₹6,000 via UPI for "Material Cost"
- ₹4,000 via Cash for "Labour Charges"

INSERT INTO hr_payment_splits (recipient_id, amount, payment_mode, payment_for, proof_file) VALUES
(1, 6000.00, 'upi', 'Material Cost', 'uploads/payment_documents/payment_1/recipient_1/splits/split_1_upi_proof_abc123.jpg'),
(1, 4000.00, 'cash', 'Labour Charges', 'uploads/payment_documents/payment_1/recipient_1/splits/split_2_cash_proof_def456.jpg');
*/

-- Query to retrieve complete payment information with main splits and recipient splits
-- Use this query to fetch payment data including both main and recipient split details
/*
SELECT 
    pe.payment_id,
    pe.project_type,
    pe.payment_date,
    pe.payment_amount,
    pe.payment_mode as main_payment_mode,
    pe.payment_proof_image,
    
    -- Main payment splits
    mps.main_split_id,
    mps.amount as main_split_amount,
    mps.payment_mode as main_split_payment_mode,
    mps.proof_file as main_split_proof_file,
    
    -- Recipients
    pr.recipient_id,
    pr.name as recipient_name,
    pr.category,
    pr.amount as recipient_amount,
    pr.payment_mode as recipient_payment_mode,
    
    -- Recipient splits
    ps.split_id,
    ps.amount as recipient_split_amount,
    ps.payment_mode as recipient_split_payment_mode,
    ps.payment_for as recipient_split_payment_for,
    ps.proof_file as recipient_split_proof_file
    
FROM hr_payment_entries pe
LEFT JOIN hr_main_payment_splits mps ON pe.payment_id = mps.payment_id
LEFT JOIN hr_payment_recipients pr ON pe.payment_id = pr.payment_id
LEFT JOIN hr_payment_splits ps ON pr.recipient_id = ps.recipient_id
WHERE pe.payment_id = ?;
*/


CREATE TABLE hr_main_payment_splits (
    main_split_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_mode VARCHAR(100) NOT NULL,
    payment_for VARCHAR(500) NOT NULL,
    proof_file VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);