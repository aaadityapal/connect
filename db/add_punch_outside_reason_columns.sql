-- Drop the existing single column for outside location reason
ALTER TABLE attendance 
DROP COLUMN IF EXISTS outside_location_reason;

-- Add separate columns for punch-in and punch-out outside location reasons
ALTER TABLE attendance 
ADD COLUMN punch_in_outside_reason VARCHAR(255) NULL 
COMMENT 'Reason provided when punching in from outside geofence';

ALTER TABLE attendance 
ADD COLUMN punch_out_outside_reason VARCHAR(255) NULL 
COMMENT 'Reason provided when punching out from outside geofence'; 