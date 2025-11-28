-- ============================================================================
-- ADD DELETED ELEMENTS TRACKING COLUMN
-- Stores JSON data of elements deleted from payment entries
-- ============================================================================

-- Add column to main payment entry table to track deleted elements
ALTER TABLE `tbl_payment_entry_master_records` 
ADD COLUMN `deleted_elements_json` LONGTEXT COMMENT 'JSON data of deleted elements (acceptance methods, line items, attachments) for audit trail' AFTER `notes_admin_internal`;

-- Add column to line items table to track deleted acceptance methods
ALTER TABLE `tbl_payment_entry_line_items_detail`
ADD COLUMN `deleted_acceptance_methods_json` LONGTEXT COMMENT 'JSON data of deleted acceptance methods for this line item' AFTER `line_item_status`;

-- Create index for better query performance
CREATE INDEX idx_deleted_elements ON `tbl_payment_entry_master_records`(`deleted_elements_json`(50));
CREATE INDEX idx_line_item_deleted_methods ON `tbl_payment_entry_line_items_detail`(`deleted_acceptance_methods_json`(50));

-- ============================================================================
-- Example JSON Structure for deleted_elements_json
-- ============================================================================
-- Deleted Acceptance Methods:
-- {
--   "deleted_acceptance_methods": [
--     {
--       "acceptance_method_id": 1,
--       "payment_method_type": "Cheque",
--       "amount_received_value": 5000.00,
--       "reference_number_cheque": "CHQ12345",
--       "deleted_at": "2025-11-27 10:30:45",
--       "deleted_by_user_id": 5
--     }
--   ],
--   "deleted_line_items": [
--     {
--       "line_item_entry_id": 2,
--       "recipient_type_category": "Labour",
--       "recipient_name_display": "John Doe",
--       "line_item_amount": 3000.00,
--       "deleted_at": "2025-11-27 10:35:20",
--       "deleted_by_user_id": 5
--     }
--   ],
--   "deleted_attachments": [
--     {
--       "attachment_id": 1,
--       "attachment_file_original_name": "proof.pdf",
--       "attachment_file_stored_path": "/uploads/payment_proofs/proof_1234.pdf",
--       "deleted_at": "2025-11-27 10:40:00",
--       "deleted_by_user_id": 5
--     }
--   ]
-- }

-- ============================================================================
-- Verification Query
-- ============================================================================
-- SELECT 
--     payment_entry_id,
--     project_name_reference,
--     deleted_elements_json,
--     updated_timestamp_utc
-- FROM tbl_payment_entry_master_records
-- WHERE deleted_elements_json IS NOT NULL
-- ORDER BY updated_timestamp_utc DESC;
