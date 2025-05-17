-- SQL to add punch-out location columns to the attendance table
ALTER TABLE attendance 
ADD COLUMN punch_out_latitude DECIMAL(10, 8) NULL AFTER punch_out_photo,
ADD COLUMN punch_out_longitude DECIMAL(11, 8) NULL AFTER punch_out_latitude,
ADD COLUMN punch_out_accuracy FLOAT NULL AFTER punch_out_longitude;

-- Add comments to the columns for documentation
ALTER TABLE attendance
MODIFY COLUMN punch_out_latitude DECIMAL(10, 8) NULL COMMENT 'Latitude where user punched out',
MODIFY COLUMN punch_out_longitude DECIMAL(11, 8) NULL COMMENT 'Longitude where user punched out',
MODIFY COLUMN punch_out_accuracy FLOAT NULL COMMENT 'Accuracy of the location in meters when punched out';

-- Verify existing punch-in location columns
-- If these don't exist, you can uncomment the following block to add them

/*
ALTER TABLE attendance 
ADD COLUMN latitude DECIMAL(10, 8) NULL COMMENT 'Latitude where user punched in' AFTER location,
ADD COLUMN longitude DECIMAL(11, 8) NULL COMMENT 'Longitude where user punched in' AFTER latitude,
ADD COLUMN accuracy FLOAT NULL COMMENT 'Accuracy of the location in meters when punched in' AFTER longitude;
*/

-- Display the updated table structure
DESCRIBE attendance; 