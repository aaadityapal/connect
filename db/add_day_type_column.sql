-- Add day_type column to leave_request table
ALTER TABLE leave_request 
ADD COLUMN day_type ENUM('first_half', 'second_half') 
AFTER duration_type;
