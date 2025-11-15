-- Vendor Management System Table
-- Unique Table Name: pm_vendor_registry_master
-- Purpose: Stores comprehensive vendor information including basic details, banking, GST, and address information

CREATE TABLE IF NOT EXISTS `pm_vendor_registry_master` (
    `vendor_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique vendor identifier',
    `vendor_unique_code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique vendor code generated automatically (e.g., VND-20251114-001)',
    
    -- Basic Vendor Information
    `vendor_full_name` VARCHAR(255) NOT NULL COMMENT 'Full name of vendor',
    `vendor_phone_primary` VARCHAR(10) NOT NULL COMMENT 'Primary contact number (10 digits for India)',
    `vendor_phone_alternate` VARCHAR(10) COMMENT 'Alternate contact number (10 digits for India)',
    `vendor_email_address` VARCHAR(255) NOT NULL COMMENT 'Email address of vendor',
    `vendor_type_category` VARCHAR(100) NOT NULL COMMENT 'Vendor type: Labour Contractor, Material Contractor, Material Supplier, or custom',
    
    -- Banking Details
    `bank_name` VARCHAR(255) COMMENT 'Name of the bank',
    `bank_account_number` VARCHAR(50) COMMENT 'Bank account number',
    `bank_ifsc_code` VARCHAR(11) COMMENT 'IFSC code of the bank branch',
    `bank_account_type` VARCHAR(50) COMMENT 'Type of account: Savings, Current, Business',
    `bank_qr_code_filename` VARCHAR(255) COMMENT 'Filename of uploaded QR code image',
    `bank_qr_code_path` VARCHAR(500) COMMENT 'Full path to QR code file',
    
    -- GST Details
    `gst_number` VARCHAR(15) COMMENT 'GST registration number',
    `gst_state` VARCHAR(100) COMMENT 'State for GST purposes',
    `gst_type_category` VARCHAR(50) COMMENT 'GST type: CGST, SGST, IGST, UGST',
    
    -- Address Details
    `address_street` VARCHAR(500) COMMENT 'Street address',
    `address_city` VARCHAR(100) COMMENT 'City name',
    `address_state` VARCHAR(100) COMMENT 'State name',
    `address_postal_code` VARCHAR(6) COMMENT 'Postal code (6 digits for India)',
    
    -- Metadata
    `created_by_user_id` INT(11) COMMENT 'User ID who created this record',
    `created_date_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
    `updated_by_user_id` INT(11) COMMENT 'User ID who last updated this record',
    `updated_date_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
    `vendor_status` ENUM('active', 'inactive', 'suspended', 'archived') DEFAULT 'active' COMMENT 'Status of vendor',
    
    -- Indexes
    KEY `idx_vendor_phone` (`vendor_phone_primary`),
    KEY `idx_vendor_email` (`vendor_email_address`),
    KEY `idx_vendor_type` (`vendor_type_category`),
    KEY `idx_gst_number` (`gst_number`),
    KEY `idx_created_date` (`created_date_time`),
    KEY `idx_vendor_status` (`vendor_status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master table for storing vendor information across all categories';

-- Add foreign key constraint if users table exists
-- ALTER TABLE `pm_vendor_registry_master` 
-- ADD CONSTRAINT `fk_vendor_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
-- ADD CONSTRAINT `fk_vendor_updated_by` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Create a secondary table for vendor contact history (optional but useful for tracking communication)
CREATE TABLE IF NOT EXISTS `pm_vendor_contact_history` (
    `contact_history_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique contact history record ID',
    `vendor_id` INT(11) NOT NULL COMMENT 'Foreign key to pm_vendor_registry_master',
    `contact_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time of contact',
    `contact_method` ENUM('phone', 'email', 'in_person', 'other') COMMENT 'Method of contact',
    `contact_details` TEXT COMMENT 'Details of the contact/interaction',
    `contacted_by_user_id` INT(11) COMMENT 'User who made the contact',
    
    FOREIGN KEY (`vendor_id`) REFERENCES `pm_vendor_registry_master`(`vendor_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    KEY `idx_vendor_id` (`vendor_id`),
    KEY `idx_contact_date` (`contact_date`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks vendor contact history and interactions';

