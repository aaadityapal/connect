-- Add verification columns to travel_expenses table

-- Add column for destination verification
ALTER TABLE travel_expenses ADD COLUMN destination_verified TINYINT(1) DEFAULT 0 COMMENT 'Whether the destination has been verified by reviewer';

-- Add column for policy compliance verification
ALTER TABLE travel_expenses ADD COLUMN policy_verified TINYINT(1) DEFAULT 0 COMMENT 'Whether compliance with company policy has been verified';

-- Add column for meter picture verification
ALTER TABLE travel_expenses ADD COLUMN meter_verified TINYINT(1) DEFAULT 0 COMMENT 'Whether the meter pictures have been verified for accurate distance';