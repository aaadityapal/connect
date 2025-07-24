-- Add approval columns to attendance table
ALTER TABLE attendance
ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected') DEFAULT NULL AFTER punch_out,
ADD COLUMN manager_id INT DEFAULT NULL AFTER approval_status,
ADD COLUMN approval_timestamp DATETIME DEFAULT NULL AFTER manager_id,
ADD COLUMN manager_comments TEXT DEFAULT NULL AFTER approval_timestamp;

-- Create an index for faster queries
CREATE INDEX idx_attendance_approval ON attendance(approval_status);
CREATE INDEX idx_attendance_manager_id ON attendance(manager_id);

-- Update existing records to approved status
UPDATE attendance SET approval_status = 'approved' WHERE punch_in IS NOT NULL; 