-- SQL script to add unique constraints to prevent duplicate vendors
-- This adds database-level protection against duplicate vendors

-- First, clean up any existing duplicates (if any)
-- This is a simple approach that keeps the first occurrence and removes duplicates
-- Note: In a production environment, you might want to handle this more carefully

-- Add unique constraints on phone number, email, and GST number
-- We'll use ALTER IGNORE which will skip duplicate rows during constraint creation
-- Note: This approach may not work on all MySQL versions

-- Alternative approach: Add unique indexes with proper handling
-- First, let's identify and remove duplicates

-- Create a temporary table with unique vendors based on phone number
CREATE TEMPORARY TABLE temp_unique_vendors AS
SELECT MIN(vendor_id) as vendor_id, phone_number
FROM hr_vendors
GROUP BY phone_number
HAVING COUNT(*) > 1;

-- Delete duplicate vendors, keeping only the ones with the lowest vendor_id
DELETE v1 FROM hr_vendors v1
INNER JOIN temp_unique_vendors t ON v1.phone_number = t.phone_number
WHERE v1.vendor_id > t.vendor_id;

-- Now add unique constraints
-- Add unique constraint on phone number
ALTER TABLE hr_vendors 
ADD CONSTRAINT uk_vendor_phone UNIQUE (phone_number);

-- Add unique constraint on email (allowing NULLs)
ALTER TABLE hr_vendors 
ADD CONSTRAINT uk_vendor_email UNIQUE (email);

-- Add unique constraint on GST number (allowing NULLs)
ALTER TABLE hr_vendors 
ADD CONSTRAINT uk_vendor_gst UNIQUE (gst_number);

-- Note: In a real-world scenario, you might want to use a more sophisticated approach
-- for identifying duplicates, such as considering combinations of fields rather than
-- individual fields.