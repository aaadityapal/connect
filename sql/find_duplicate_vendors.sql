-- SQL script to find duplicate vendors in the database
-- This helps identify existing duplicates before applying unique constraints

-- Find duplicate vendors by phone number
SELECT 
    phone_number, 
    COUNT(*) as duplicate_count,
    GROUP_CONCAT(vendor_id ORDER BY vendor_id) as vendor_ids,
    GROUP_CONCAT(full_name ORDER BY vendor_id) as vendor_names
FROM hr_vendors 
GROUP BY phone_number 
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC;

-- Find duplicate vendors by email
SELECT 
    email, 
    COUNT(*) as duplicate_count,
    GROUP_CONCAT(vendor_id ORDER BY vendor_id) as vendor_ids,
    GROUP_CONCAT(full_name ORDER BY vendor_id) as vendor_names
FROM hr_vendors 
WHERE email IS NOT NULL AND email != ''
GROUP BY email 
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC;

-- Find duplicate vendors by GST number
SELECT 
    gst_number, 
    COUNT(*) as duplicate_count,
    GROUP_CONCAT(vendor_id ORDER BY vendor_id) as vendor_ids,
    GROUP_CONCAT(full_name ORDER BY vendor_id) as vendor_names
FROM hr_vendors 
WHERE gst_number IS NOT NULL AND gst_number != ''
GROUP BY gst_number 
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC;

-- Find potential duplicates by name and phone (more comprehensive check)
SELECT 
    full_name,
    phone_number,
    COUNT(*) as duplicate_count,
    GROUP_CONCAT(vendor_id ORDER BY vendor_id) as vendor_ids,
    GROUP_CONCAT(email ORDER BY vendor_id) as emails
FROM hr_vendors 
WHERE phone_number IS NOT NULL AND phone_number != ''
GROUP BY full_name, phone_number
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC;

-- Summary of all duplicates
SELECT 
    'Phone Number' as duplicate_type,
    COUNT(*) as duplicate_sets
FROM (
    SELECT phone_number
    FROM hr_vendors 
    GROUP BY phone_number 
    HAVING COUNT(*) > 1
) as phone_duplicates

UNION ALL

SELECT 
    'Email' as duplicate_type,
    COUNT(*) as duplicate_sets
FROM (
    SELECT email
    FROM hr_vendors 
    WHERE email IS NOT NULL AND email != ''
    GROUP BY email 
    HAVING COUNT(*) > 1
) as email_duplicates

UNION ALL

SELECT 
    'GST Number' as duplicate_type,
    COUNT(*) as duplicate_sets
FROM (
    SELECT gst_number
    FROM hr_vendors 
    WHERE gst_number IS NOT NULL AND gst_number != ''
    GROUP BY gst_number 
    HAVING COUNT(*) > 1
) as gst_duplicates;