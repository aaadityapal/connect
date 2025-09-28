-- SQL script to add QR code and GST details columns to hr_vendors table
-- This script adds the necessary columns to store QR codes and GST information for vendors

ALTER TABLE hr_vendors 
ADD COLUMN qr_code_path VARCHAR(255) AFTER account_type,
ADD COLUMN gst_number VARCHAR(15) AFTER qr_code_path,
ADD COLUMN gst_registration_date DATE AFTER gst_number,
ADD COLUMN gst_state VARCHAR(100) AFTER gst_registration_date,
ADD COLUMN gst_type VARCHAR(20) AFTER gst_state;

-- Add indexes for better query performance
ALTER TABLE hr_vendors 
ADD INDEX idx_gst_number (gst_number),
ADD INDEX idx_qr_code_path (qr_code_path);