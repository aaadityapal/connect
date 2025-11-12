-- Add waved_off column to attendance table
ALTER TABLE attendance 
ADD COLUMN waved_off TINYINT(1) DEFAULT 0 COMMENT '0 = Not Waved Off, 1 = Waved Off';

-- Add index for better query performance
CREATE INDEX idx_attendance_waved_off ON attendance (waved_off);