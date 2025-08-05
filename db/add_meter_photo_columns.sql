-- Add meter photo columns to travel_expenses table
ALTER TABLE travel_expenses 
ADD COLUMN meter_start_photo_path VARCHAR(255) DEFAULT NULL, 
ADD COLUMN meter_end_photo_path VARCHAR(255) DEFAULT NULL;