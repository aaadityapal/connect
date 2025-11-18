-- ============================================================================
-- PAYMENT ENTRY SYSTEM - DATABASE SCHEMA
-- Unique Table Names for Complete Payment Entry Modal Data Storage
-- ============================================================================

-- ============================================================================
-- 1. PRIMARY PAYMENT ENTRIES TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS `tbl_payment_entry_master_records` (
    `payment_entry_id` BIGINT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique payment entry identifier',
    `project_type_category` VARCHAR(50) NOT NULL COMMENT 'Architecture, Interior, Construction',
    `project_name_reference` VARCHAR(255) NOT NULL COMMENT 'Project name selected',
    `project_id_fk` INT NOT NULL COMMENT 'Foreign key to projects table',
    `payment_amount_base` DECIMAL(15, 2) NOT NULL COMMENT 'Main payment amount',
    `payment_date_logged` DATE NOT NULL COMMENT 'Payment transaction date',
    `authorized_user_id_fk` INT NOT NULL COMMENT 'User who authorized payment',
    `payment_mode_selected` VARCHAR(50) NOT NULL COMMENT 'Single, Multiple Acceptance, Cash, Cheque, etc',
    `payment_proof_document_path` VARCHAR(500) COMMENT 'Path to uploaded proof image/PDF',
    `payment_proof_filename_original` VARCHAR(255) COMMENT 'Original filename of proof',
    `payment_proof_filesize_bytes` BIGINT COMMENT 'File size in bytes',
    `payment_proof_mime_type` VARCHAR(100) COMMENT 'MIME type of proof file',
    `entry_status_current` ENUM('draft', 'submitted', 'approved', 'rejected', 'pending') DEFAULT 'draft' COMMENT 'Current status',
    `created_by_user_id` INT NOT NULL COMMENT 'User who created entry',
    `created_timestamp_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation time',
    `updated_by_user_id` INT COMMENT 'Last user to update',
    `updated_timestamp_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update time',
    `notes_admin_internal` TEXT COMMENT 'Internal admin notes',
    FOREIGN KEY (`authorized_user_id_fk`) REFERENCES `users`(`id`),
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`),
    INDEX `idx_payment_date` (`payment_date_logged`),
    INDEX `idx_payment_mode` (`payment_mode_selected`),
    INDEX `idx_entry_status` (`entry_status_current`),
    INDEX `idx_project_type` (`project_type_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Main payment entry records with proof documents';

-- ============================================================================
-- 2. MULTIPLE ACCEPTANCE METHODS TABLE (for main payment)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `tbl_payment_acceptance_methods_primary` (
    `acceptance_method_id` BIGINT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique acceptance method identifier',
    `payment_entry_id_fk` BIGINT NOT NULL COMMENT 'Link to main payment entry',
    `payment_method_type` VARCHAR(50) NOT NULL COMMENT 'Cash, Cheque, Bank Transfer, Credit Card, Online, UPI',
    `amount_received_value` DECIMAL(15, 2) NOT NULL COMMENT 'Amount received in this method',
    `reference_number_cheque` VARCHAR(100) COMMENT 'Cheque no., Transaction ID, Reference no.',
    `method_sequence_order` INT COMMENT 'Order of method entry',
    `supporting_document_path` VARCHAR(500) COMMENT 'Path to supporting media file',
    `supporting_document_original_name` VARCHAR(255) COMMENT 'Original filename',
    `supporting_document_filesize` BIGINT COMMENT 'File size in bytes',
    `supporting_document_mime_type` VARCHAR(100) COMMENT 'MIME type (PDF, JPG, PNG, MP4, etc)',
    `recorded_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Recording time',
    FOREIGN KEY (`payment_entry_id_fk`) REFERENCES `tbl_payment_entry_master_records`(`payment_entry_id`) ON DELETE CASCADE,
    INDEX `idx_payment_entry` (`payment_entry_id_fk`),
    INDEX `idx_method_type` (`payment_method_type`),
    UNIQUE KEY `uk_unique_entry_method_order` (`payment_entry_id_fk`, `method_sequence_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Multiple acceptance payment methods for main payment entry';

-- ============================================================================
-- 3. ADDITIONAL PAYMENT ENTRIES TABLE (line items)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `tbl_payment_entry_line_items_detail` (
    `line_item_entry_id` BIGINT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique line item identifier',
    `payment_entry_master_id_fk` BIGINT NOT NULL COMMENT 'Link to master payment entry',
    `recipient_type_category` VARCHAR(100) NOT NULL COMMENT 'Labour, Labour Skilled, Material Steel, Material Bricks, Supplier Cement, etc',
    `recipient_id_reference` INT COMMENT 'Recipient/Vendor/Labour ID',
    `recipient_name_display` VARCHAR(255) COMMENT 'Display name of recipient',
    `payment_description_notes` TEXT COMMENT 'What payment is for',
    `line_item_amount` DECIMAL(15, 2) NOT NULL COMMENT 'Amount for this line item',
    `line_item_payment_mode` VARCHAR(50) NOT NULL COMMENT 'Payment mode for this item',
    `line_item_sequence_number` INT NOT NULL COMMENT 'Line item number (1, 2, 3...)',
    `line_item_media_upload_path` VARCHAR(500) COMMENT 'Path to attached media file',
    `line_item_media_original_filename` VARCHAR(255) COMMENT 'Original filename',
    `line_item_media_filesize_bytes` BIGINT COMMENT 'File size in bytes',
    `line_item_media_mime_type` VARCHAR(100) COMMENT 'MIME type',
    `line_item_status` ENUM('pending', 'verified', 'approved', 'rejected') DEFAULT 'pending',
    `created_at_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `modified_at_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`payment_entry_master_id_fk`) REFERENCES `tbl_payment_entry_master_records`(`payment_entry_id`) ON DELETE CASCADE,
    INDEX `idx_master_payment_id` (`payment_entry_master_id_fk`),
    INDEX `idx_recipient_type` (`recipient_type_category`),
    INDEX `idx_line_sequence` (`payment_entry_master_id_fk`, `line_item_sequence_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Line items/additional entries within each payment entry';

-- ============================================================================
-- 4. MULTIPLE ACCEPTANCE METHODS FOR LINE ITEMS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS `tbl_payment_acceptance_methods_line_items` (
    `line_item_acceptance_method_id` BIGINT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique identifier',
    `line_item_entry_id_fk` BIGINT NOT NULL COMMENT 'Link to line item entry',
    `payment_entry_master_id_fk` BIGINT NOT NULL COMMENT 'Link to master payment (for indexing)',
    `method_type_category` VARCHAR(50) NOT NULL COMMENT 'Cash, Cheque, Bank Transfer, Credit Card, Online, UPI',
    `method_amount_received` DECIMAL(15, 2) NOT NULL COMMENT 'Amount in this payment method',
    `method_reference_identifier` VARCHAR(100) COMMENT 'Cheque no., Transaction ID, etc',
    `method_display_sequence` INT COMMENT 'Order of display',
    `method_supporting_media_path` VARCHAR(500) COMMENT 'Path to supporting document',
    `method_supporting_media_filename` VARCHAR(255) COMMENT 'Original filename',
    `method_supporting_media_size` BIGINT COMMENT 'File size in bytes',
    `method_supporting_media_type` VARCHAR(100) COMMENT 'MIME type (PDF, JPG, PNG, MP4, MOV, AVI)',
    `method_recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`line_item_entry_id_fk`) REFERENCES `tbl_payment_entry_line_items_detail`(`line_item_entry_id`) ON DELETE CASCADE,
    FOREIGN KEY (`payment_entry_master_id_fk`) REFERENCES `tbl_payment_entry_master_records`(`payment_entry_id`) ON DELETE CASCADE,
    INDEX `idx_line_item_id` (`line_item_entry_id_fk`),
    INDEX `idx_master_payment_id` (`payment_entry_master_id_fk`),
    INDEX `idx_method_type` (`method_type_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Multiple acceptance methods for each line item';

-- ============================================================================
-- 5. PAYMENT ENTRY FILE UPLOADS TRACKING TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS `tbl_payment_entry_file_attachments_registry` (
    `attachment_id` BIGINT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique attachment identifier',
    `payment_entry_master_id_fk` BIGINT NOT NULL COMMENT 'Link to master payment entry',
    `attachment_type_category` ENUM('proof_image', 'acceptance_method_media', 'line_item_media', 'line_item_method_media') COMMENT 'Type of attachment',
    `attachment_reference_id` VARCHAR(100) COMMENT 'Reference: acceptance_media_0, entryMedia_1, etc',
    `attachment_file_original_name` VARCHAR(255) NOT NULL COMMENT 'Original filename',
    `attachment_file_stored_path` VARCHAR(500) NOT NULL COMMENT 'Full path to stored file',
    `attachment_file_size_bytes` BIGINT NOT NULL COMMENT 'File size in bytes',
    `attachment_file_mime_type` VARCHAR(100) NOT NULL COMMENT 'MIME type',
    `attachment_file_extension` VARCHAR(10) COMMENT 'File extension (pdf, jpg, png, mp4, etc)',
    `attachment_upload_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Upload time',
    `attachment_verification_status` ENUM('pending', 'verified', 'quarantined', 'deleted') DEFAULT 'pending',
    `attachment_integrity_hash` VARCHAR(64) COMMENT 'SHA256 hash for integrity verification',
    `uploaded_by_user_id` INT COMMENT 'User who uploaded',
    FOREIGN KEY (`payment_entry_master_id_fk`) REFERENCES `tbl_payment_entry_master_records`(`payment_entry_id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users`(`id`),
    INDEX `idx_master_payment_id` (`payment_entry_master_id_fk`),
    INDEX `idx_attachment_type` (`attachment_type_category`),
    INDEX `idx_upload_timestamp` (`attachment_upload_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registry of all file attachments in payment entries';

-- ============================================================================
-- 6. PAYMENT ENTRY AUDIT LOG TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS `tbl_payment_entry_audit_activity_log` (
    `audit_log_id` BIGINT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique audit log identifier',
    `payment_entry_id_fk` BIGINT NOT NULL COMMENT 'Link to payment entry',
    `audit_action_type` VARCHAR(100) NOT NULL COMMENT 'created, updated, submitted, approved, rejected, etc',
    `audit_change_description` TEXT COMMENT 'Description of changes',
    `audit_performed_by_user_id` INT NOT NULL COMMENT 'User who performed action',
    `audit_action_timestamp_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `audit_ip_address_captured` VARCHAR(45) COMMENT 'IP address of user',
    `audit_user_agent_info` VARCHAR(500) COMMENT 'Browser user agent info',
    FOREIGN KEY (`payment_entry_id_fk`) REFERENCES `tbl_payment_entry_master_records`(`payment_entry_id`) ON DELETE CASCADE,
    FOREIGN KEY (`audit_performed_by_user_id`) REFERENCES `users`(`id`),
    INDEX `idx_payment_entry_id` (`payment_entry_id_fk`),
    INDEX `idx_action_type` (`audit_action_type`),
    INDEX `idx_action_timestamp` (`audit_action_timestamp_utc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for all payment entry activities';

-- ============================================================================
-- 7. PAYMENT ENTRY TOTALS SUMMARY TABLE (for reporting)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `tbl_payment_entry_summary_totals` (
    `summary_id` BIGINT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique summary identifier',
    `payment_entry_master_id_fk` BIGINT NOT NULL UNIQUE COMMENT 'Link to main payment entry (1-to-1)',
    `total_amount_main_payment` DECIMAL(15, 2) NOT NULL COMMENT 'Main payment total',
    `total_amount_acceptance_methods` DECIMAL(15, 2) COMMENT 'Sum of all acceptance methods',
    `total_amount_line_items` DECIMAL(15, 2) COMMENT 'Sum of all line items',
    `total_amount_grand_aggregate` DECIMAL(15, 2) NOT NULL COMMENT 'Grand total of all amounts',
    `acceptance_methods_count` INT DEFAULT 0 COMMENT 'Number of acceptance methods',
    `line_items_count` INT DEFAULT 0 COMMENT 'Number of line items',
    `total_files_attached` INT DEFAULT 0 COMMENT 'Total attachments count',
    `summary_calculated_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`payment_entry_master_id_fk`) REFERENCES `tbl_payment_entry_master_records`(`payment_entry_id`) ON DELETE CASCADE,
    INDEX `idx_payment_entry_id` (`payment_entry_master_id_fk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Summary totals for each payment entry (for reporting and analytics)';

-- ============================================================================
-- 8. PAYMENT ENTRY STATUS HISTORY TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS `tbl_payment_entry_status_transition_history` (
    `status_history_id` BIGINT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique history record identifier',
    `payment_entry_master_id_fk` BIGINT NOT NULL COMMENT 'Link to payment entry',
    `status_from_previous` VARCHAR(50) COMMENT 'Previous status',
    `status_to_current` VARCHAR(50) NOT NULL COMMENT 'New status',
    `status_changed_by_user_id` INT NOT NULL COMMENT 'User who changed status',
    `status_change_reason_notes` TEXT COMMENT 'Reason for status change',
    `status_change_timestamp_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`payment_entry_master_id_fk`) REFERENCES `tbl_payment_entry_master_records`(`payment_entry_id`) ON DELETE CASCADE,
    FOREIGN KEY (`status_changed_by_user_id`) REFERENCES `users`(`id`),
    INDEX `idx_payment_entry_id` (`payment_entry_master_id_fk`),
    INDEX `idx_status_current` (`status_to_current`),
    INDEX `idx_change_timestamp` (`status_change_timestamp_utc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Status change history for audit trail';

-- ============================================================================
-- 9. PAYMENT ENTRY REJECTION REASONS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS `tbl_payment_entry_rejection_reasons_detail` (
    `rejection_id` BIGINT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique rejection record identifier',
    `payment_entry_master_id_fk` BIGINT NOT NULL COMMENT 'Link to rejected payment entry',
    `rejection_reason_code` VARCHAR(50) NOT NULL COMMENT 'Standardized reason code',
    `rejection_reason_description` TEXT NOT NULL COMMENT 'Detailed reason for rejection',
    `rejected_by_user_id` INT NOT NULL COMMENT 'User who rejected',
    `rejection_timestamp_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `rejection_attachments_notes` TEXT COMMENT 'Issues with attachments, if any',
    `resubmission_requested` BOOLEAN DEFAULT FALSE COMMENT 'Whether resubmission is requested',
    FOREIGN KEY (`payment_entry_master_id_fk`) REFERENCES `tbl_payment_entry_master_records`(`payment_entry_id`) ON DELETE CASCADE,
    FOREIGN KEY (`rejected_by_user_id`) REFERENCES `users`(`id`),
    INDEX `idx_payment_entry_id` (`payment_entry_master_id_fk`),
    INDEX `idx_rejection_reason` (`rejection_reason_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Details on rejected payment entries';

-- ============================================================================
-- 10. PAYMENT ENTRY APPROVAL RECORDS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS `tbl_payment_entry_approval_records_final` (
    `approval_id` BIGINT PRIMARY KEY AUTO_INCREMENT COMMENT 'Unique approval record identifier',
    `payment_entry_master_id_fk` BIGINT NOT NULL COMMENT 'Link to approved payment entry',
    `approved_by_user_id` INT NOT NULL COMMENT 'User who approved',
    `approval_timestamp_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `approval_notes_comments` TEXT COMMENT 'Approval comments or notes',
    `approval_authorized_amount` DECIMAL(15, 2) COMMENT 'Authorized payment amount',
    `approval_clearance_code` VARCHAR(50) UNIQUE COMMENT 'Unique clearance code for reference',
    `approval_reference_document_number` VARCHAR(100) COMMENT 'Reference to voucher/document number',
    FOREIGN KEY (`payment_entry_master_id_fk`) REFERENCES `tbl_payment_entry_master_records`(`payment_entry_id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by_user_id`) REFERENCES `users`(`id`),
    INDEX `idx_payment_entry_id` (`payment_entry_master_id_fk`),
    INDEX `idx_approved_by_user` (`approved_by_user_id`),
    INDEX `idx_approval_timestamp` (`approval_timestamp_utc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Final approval records for payment entries';

-- ============================================================================
-- VIEW: Payment Entry Complete Details
-- ============================================================================
CREATE OR REPLACE VIEW `vw_payment_entry_complete_details` AS
SELECT 
    m.payment_entry_id,
    m.project_type_category,
    m.project_name_reference,
    m.payment_amount_base,
    m.payment_date_logged,
    m.payment_mode_selected,
    m.entry_status_current,
    COUNT(DISTINCT l.line_item_entry_id) as total_line_items,
    COUNT(DISTINCT a.acceptance_method_id) as total_acceptance_methods,
    SUM(DISTINCT l.line_item_amount) as sum_line_items,
    SUM(DISTINCT a.amount_received_value) as sum_acceptance_amounts,
    s.total_amount_grand_aggregate,
    m.created_timestamp_utc,
    m.updated_timestamp_utc
FROM tbl_payment_entry_master_records m
LEFT JOIN tbl_payment_entry_line_items_detail l ON m.payment_entry_id = l.payment_entry_master_id_fk
LEFT JOIN tbl_payment_acceptance_methods_primary a ON m.payment_entry_id = a.payment_entry_id_fk
LEFT JOIN tbl_payment_entry_summary_totals s ON m.payment_entry_id = s.payment_entry_master_id_fk
GROUP BY m.payment_entry_id;

-- ============================================================================
-- VIEW: Payment Entry Line Item Details with Methods
-- ============================================================================
CREATE OR REPLACE VIEW `vw_payment_entry_line_item_breakdown` AS
SELECT 
    l.line_item_entry_id,
    l.payment_entry_master_id_fk,
    l.line_item_sequence_number,
    l.recipient_type_category,
    l.recipient_name_display,
    l.line_item_amount,
    l.line_item_payment_mode,
    COUNT(m.line_item_acceptance_method_id) as method_count,
    SUM(m.method_amount_received) as total_methods_amount,
    l.line_item_status
FROM tbl_payment_entry_line_items_detail l
LEFT JOIN tbl_payment_acceptance_methods_line_items m ON l.line_item_entry_id = m.line_item_entry_id_fk
GROUP BY l.line_item_entry_id;

-- ============================================================================
-- Indexes for Performance Optimization
-- ============================================================================
CREATE INDEX idx_payment_entry_date_status ON tbl_payment_entry_master_records(payment_date_logged, entry_status_current);
CREATE INDEX idx_payment_entry_project ON tbl_payment_entry_master_records(project_type_category, project_id_fk);
CREATE INDEX idx_line_items_master_sequence ON tbl_payment_entry_line_items_detail(payment_entry_master_id_fk, line_item_sequence_number);
CREATE INDEX idx_file_attachment_upload_time ON tbl_payment_entry_file_attachments_registry(attachment_upload_timestamp DESC);

