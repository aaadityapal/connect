-- Add punch-out location columns to the attendance table
ALTER TABLE attendance 
ADD COLUMN punch_out_latitude DECIMAL(10, 8) NULL AFTER punch_out_photo,
ADD COLUMN punch_out_longitude DECIMAL(11, 8) NULL AFTER punch_out_latitude,
ADD COLUMN punch_out_accuracy FLOAT NULL AFTER punch_out_longitude; 