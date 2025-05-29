 -- Add updated_by and updated_at columns to sv_calendar_events table
ALTER TABLE sv_calendar_events
ADD COLUMN updated_by INT NULL AFTER created_at,
ADD COLUMN updated_at TIMESTAMP NULL AFTER updated_by,
ADD CONSTRAINT fk_events_updated_by FOREIGN KEY (updated_by) REFERENCES users(id);

-- Add is_deleted, updated_by and updated_at columns to sv_event_vendors table
ALTER TABLE sv_event_vendors
ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER sequence_number,
ADD COLUMN created_by INT NULL AFTER is_deleted,
ADD COLUMN updated_by INT NULL AFTER created_by,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER updated_by,
ADD COLUMN updated_at TIMESTAMP NULL AFTER created_at,
ADD CONSTRAINT fk_vendors_created_by FOREIGN KEY (created_by) REFERENCES users(id),
ADD CONSTRAINT fk_vendors_updated_by FOREIGN KEY (updated_by) REFERENCES users(id);

-- Add is_deleted, updated_by and updated_at columns to sv_vendor_labours table
ALTER TABLE sv_vendor_labours
ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER sequence_number,
ADD COLUMN created_by INT NULL AFTER is_deleted,
ADD COLUMN updated_by INT NULL AFTER created_by,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER updated_by,
ADD COLUMN updated_at TIMESTAMP NULL AFTER created_at,
ADD CONSTRAINT fk_vendor_labours_created_by FOREIGN KEY (created_by) REFERENCES users(id),
ADD CONSTRAINT fk_vendor_labours_updated_by FOREIGN KEY (updated_by) REFERENCES users(id);

-- Add is_deleted, updated_by and updated_at columns to sv_company_labours table
ALTER TABLE sv_company_labours
ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER sequence_number,
ADD COLUMN created_by INT NULL AFTER is_deleted,
ADD COLUMN updated_by INT NULL AFTER created_by,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER updated_by,
ADD COLUMN updated_at TIMESTAMP NULL AFTER created_at,
ADD CONSTRAINT fk_company_labours_created_by FOREIGN KEY (created_by) REFERENCES users(id),
ADD CONSTRAINT fk_company_labours_updated_by FOREIGN KEY (updated_by) REFERENCES users(id);

-- Add is_deleted, updated_by and updated_at columns to sv_event_beverages table
ALTER TABLE sv_event_beverages
ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER sequence_number,
ADD COLUMN created_by INT NULL AFTER is_deleted,
ADD COLUMN updated_by INT NULL AFTER created_by,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER updated_by,
ADD COLUMN updated_at TIMESTAMP NULL AFTER created_at,
ADD CONSTRAINT fk_beverages_created_by FOREIGN KEY (created_by) REFERENCES users(id),
ADD CONSTRAINT fk_beverages_updated_by FOREIGN KEY (updated_by) REFERENCES users(id);

-- Add is_deleted, updated_by and updated_at columns to sv_work_progress table
ALTER TABLE sv_work_progress
ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER sequence_number,
ADD COLUMN created_by INT NULL AFTER is_deleted,
ADD COLUMN updated_by INT NULL AFTER created_by,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER updated_by,
ADD COLUMN updated_at TIMESTAMP NULL AFTER created_at,
ADD CONSTRAINT fk_work_progress_created_by FOREIGN KEY (created_by) REFERENCES users(id),
ADD CONSTRAINT fk_work_progress_updated_by FOREIGN KEY (updated_by) REFERENCES users(id);

-- Add is_deleted, updated_by and updated_at columns to sv_inventory_items table
ALTER TABLE sv_inventory_items
ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER sequence_number,
ADD COLUMN created_by INT NULL AFTER is_deleted,
ADD COLUMN updated_by INT NULL AFTER created_by,
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER updated_by,
ADD COLUMN updated_at TIMESTAMP NULL AFTER created_at,
ADD CONSTRAINT fk_inventory_created_by FOREIGN KEY (created_by) REFERENCES users(id),
ADD CONSTRAINT fk_inventory_updated_by FOREIGN KEY (updated_by) REFERENCES users(id);

-- Create event logs table to track all changes
CREATE TABLE sv_event_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    action_type ENUM('create', 'update', 'delete') NOT NULL,
    performed_by INT NOT NULL,
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    details JSON,
    CONSTRAINT fk_event_logs_event_id FOREIGN KEY (event_id) REFERENCES sv_calendar_events(event_id),
    CONSTRAINT fk_event_logs_performed_by FOREIGN KEY (performed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;