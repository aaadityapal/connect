-- Add HR verification columns to travel_expenses table

-- Add column for HR confirmed distance
ALTER TABLE travel_expenses ADD COLUMN hr_confirmed_distance DECIMAL(10,2) NULL COMMENT 'Distance confirmed by HR from database';

-- Add column for HR who confirmed the distance
ALTER TABLE travel_expenses ADD COLUMN hr_id INT NULL COMMENT 'ID of the HR user who confirmed the distance';

-- Add column for timestamp when HR confirmed the distance
ALTER TABLE travel_expenses ADD COLUMN hr_confirmed_at DATETIME NULL COMMENT 'Timestamp when distance was confirmed by HR';

-- Add index for faster queries
CREATE INDEX idx_travel_expenses_hr_verification ON travel_expenses(hr_id, hr_confirmed_at);