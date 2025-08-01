-- Add confirmed_distance column
ALTER TABLE travel_expenses ADD COLUMN confirmed_distance DECIMAL(10,2) NULL COMMENT 'Distance confirmed by reviewer from image';

-- Add distance_confirmed_by column
ALTER TABLE travel_expenses ADD COLUMN distance_confirmed_by VARCHAR(100) NULL COMMENT 'Name of the user who confirmed the distance';

-- Add distance_confirmed_at column
ALTER TABLE travel_expenses ADD COLUMN distance_confirmed_at DATETIME NULL COMMENT 'Timestamp when distance was confirmed';

-- Add an index to improve query performance
CREATE INDEX idx_travel_expenses_user_date ON travel_expenses(user_id, travel_date); 



-- Add verification columns to travel_expenses table

-- Add column for destination verification
ALTER TABLE travel_expenses ADD COLUMN destination_verified TINYINT(1) DEFAULT 0 COMMENT 'Whether the destination has been verified by reviewer';

-- Add column for policy compliance verification
ALTER TABLE travel_expenses ADD COLUMN policy_verified TINYINT(1) DEFAULT 0 COMMENT 'Whether compliance with company policy has been verified';

-- Add column for meter picture verification
ALTER TABLE travel_expenses ADD COLUMN meter_verified TINYINT(1) DEFAULT 0 COMMENT 'Whether the meter pictures have been verified for accurate distance';