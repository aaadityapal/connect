-- Change duration to decimal to support partial days
ALTER TABLE leave_request MODIFY COLUMN duration DECIMAL(4,2) NOT NULL;

-- Drop redundant half_day_type column if exists
ALTER TABLE leave_request DROP COLUMN IF EXISTS half_day_type;

-- Ensure day_type enum includes 'full'
ALTER TABLE leave_request MODIFY COLUMN day_type ENUM('full','first_half','second_half') NULL;
