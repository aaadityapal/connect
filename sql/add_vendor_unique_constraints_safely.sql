-- SQL script to add unique constraints to prevent duplicate vendors (safe version)
-- This adds database-level protection against duplicate vendors without deleting existing data

-- IMPORTANT: Before running this script, you should manually check for and resolve any existing duplicates
-- You can find duplicates with the following queries:

-- Find duplicate vendors by phone number:
-- SELECT phone_number, COUNT(*) as count FROM hr_vendors GROUP BY phone_number HAVING COUNT(*) > 1;

-- Find duplicate vendors by email:
-- SELECT email, COUNT(*) as count FROM hr_vendors WHERE email IS NOT NULL GROUP BY email HAVING COUNT(*) > 1;

-- Find duplicate vendors by GST number:
-- SELECT gst_number, COUNT(*) as count FROM hr_vendors WHERE gst_number IS NOT NULL GROUP BY gst_number HAVING COUNT(*) > 1;

-- After resolving duplicates, you can add the unique constraints:

-- Add unique constraint on phone number (most reliable identifier)
-- ALTER TABLE hr_vendors ADD CONSTRAINT uk_vendor_phone UNIQUE (phone_number);

-- Add unique constraint on email (optional, might be shared by departments)
-- ALTER TABLE hr_vendors ADD CONSTRAINT uk_vendor_email UNIQUE (email);

-- Add unique constraint on GST number (optional, might be shared by branches)
-- ALTER TABLE hr_vendors ADD CONSTRAINT uk_vendor_gst UNIQUE (gst_number);

-- Alternative approach: Create composite unique constraint
-- This would prevent duplicates based on a combination of fields
-- ALTER TABLE hr_vendors ADD CONSTRAINT uk_vendor_unique UNIQUE (full_name, phone_number, email);

-- Recommended approach: Add indexes to improve duplicate checking performance
-- These won't prevent duplicates but will make the application-level checks faster
ALTER TABLE hr_vendors ADD INDEX idx_vendor_phone (phone_number);
ALTER TABLE hr_vendors ADD INDEX idx_vendor_email (email);
ALTER TABLE hr_vendors ADD INDEX idx_vendor_gst (gst_number);

-- Composite index for more comprehensive duplicate checking
ALTER TABLE hr_vendors ADD INDEX idx_vendor_composite (full_name, phone_number, email);