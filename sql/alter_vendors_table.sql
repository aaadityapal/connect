-- SQL script to add vendor_category column to existing hr_vendors table
ALTER TABLE hr_vendors 
ADD COLUMN vendor_category VARCHAR(50) AFTER vendor_type;