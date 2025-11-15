-- Labour Records Table
CREATE TABLE IF NOT EXISTS `labour_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `labour_unique_code` VARCHAR(50) UNIQUE NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `contact_number` VARCHAR(10) NOT NULL,
    `alt_number` VARCHAR(10),
    `join_date` DATE,
    `labour_type` ENUM('permanent', 'temporary', 'vendor', 'other') NOT NULL,
    `daily_salary` DECIMAL(10, 2),
    `street_address` VARCHAR(255),
    `city` VARCHAR(100),
    `state` VARCHAR(100),
    `zip_code` VARCHAR(6),
    `aadhar_card` VARCHAR(255),
    `pan_card` VARCHAR(255),
    `voter_id` VARCHAR(255),
    `other_document` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT,
    `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    INDEX `idx_labour_code` (`labour_unique_code`),
    INDEX `idx_full_name` (`full_name`),
    INDEX `idx_contact_number` (`contact_number`),
    INDEX `idx_labour_type` (`labour_type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create index for quick searches
CREATE INDEX `idx_created_date` ON `labour_records` (`created_at`);
