-- Add outside_location_reason column to attendance table
ALTER TABLE attendance 
ADD COLUMN outside_location_reason VARCHAR(255) NULL 
COMMENT 'Reason provided when punching in/out from outside geofence'; 