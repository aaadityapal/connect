-- Add ot_minutes column to the company_labours table
ALTER TABLE company_labours ADD COLUMN ot_minutes INT DEFAULT 0 AFTER ot_hours;

-- Add ot_minutes column to the laborers table
ALTER TABLE laborers ADD COLUMN ot_minutes INT DEFAULT 0 AFTER ot_hours;

-- Comment explaining the changes
-- These ALTER TABLE commands add a new column 'ot_minutes' to store the minutes portion of overtime
-- The column is placed right after the ot_hours column for logical organization
-- Default value is set to 0 so existing records will have 0 minutes
-- The data type is INT which is appropriate for storing minute values (0-59) 

-- First, add the new columns without constraints
ALTER TABLE site_updates 
ADD COLUMN updated_by INT DEFAULT NULL,
ADD COLUMN last_updated DATETIME DEFAULT NULL;

-- Then add the foreign key constraint separately
-- If you get an error, make sure the users table has a PRIMARY KEY on id
ALTER TABLE site_updates
ADD CONSTRAINT fk_updated_by FOREIGN KEY (updated_by) REFERENCES users(id);

-- If you continue to have issues, try this version without naming the constraint
-- ALTER TABLE site_updates
-- ADD FOREIGN KEY (updated_by) REFERENCES users(id);