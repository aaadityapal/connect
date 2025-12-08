ALTER TABLE tbl_payment_entry_line_items_detail
ADD COLUMN approved_by INT(11) DEFAULT NULL AFTER line_item_status,
ADD COLUMN approved_at TIMESTAMP NULL DEFAULT NULL AFTER approved_by,
ADD COLUMN rejected_by INT(11) DEFAULT NULL AFTER approved_at,
ADD COLUMN rejected_at TIMESTAMP NULL DEFAULT NULL AFTER rejected_by,
ADD COLUMN rejection_reason TEXT DEFAULT NULL AFTER rejected_at,
ADD CONSTRAINT fk_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_rejected_by FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL;

-- Three columns were added to the table:

ALTER TABLE tbl_payment_entry_master_records
ADD COLUMN edited_by INT NULL AFTER updated_by_user_id;

ALTER TABLE tbl_payment_entry_master_records
ADD COLUMN edited_at TIMESTAMP NULL AFTER edited_by;

ALTER TABLE tbl_payment_entry_master_records
ADD COLUMN edit_count INT DEFAULT 0 AFTER edited_at;

-- Three columns were added to the table:

ALTER TABLE tbl_payment_entry_master_records
ADD COLUMN edited_by INT NULL AFTER updated_by_user_id;

ALTER TABLE tbl_payment_entry_master_records
ADD COLUMN edited_at TIMESTAMP NULL AFTER edited_by;

ALTER TABLE tbl_payment_entry_master_records
ADD COLUMN edit_count INT DEFAULT 0 AFTER edited_at;