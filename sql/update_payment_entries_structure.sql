-- Update hr_payment_entries table to add created_by and updated_by columns if they don't exist

-- Add created_by column
ALTER TABLE hr_payment_entries 
ADD COLUMN IF NOT EXISTS created_by INT COMMENT 'User ID who created this entry';

-- Add updated_by column  
ALTER TABLE hr_payment_entries 
ADD COLUMN IF NOT EXISTS updated_by INT COMMENT 'User ID who last updated this entry';

-- Add indexes for the new columns
ALTER TABLE hr_payment_entries 
ADD INDEX IF NOT EXISTS idx_created_by (created_by),
ADD INDEX IF NOT EXISTS idx_updated_by (updated_by);

-- Insert sample payment entries for testing (only if table is empty)
INSERT INTO hr_payment_entries (
    project_type, project_id, payment_date, payment_amount, 
    payment_done_via, payment_mode, recipient_count, 
    created_by, updated_by
) 
SELECT * FROM (SELECT
    'salary_payment' as project_type,
    101 as project_id,
    CURDATE() as payment_date,
    25000.00 as payment_amount,
    1 as payment_done_via,
    'bank_transfer' as payment_mode,
    5 as recipient_count,
    1 as created_by,
    1 as updated_by
UNION ALL SELECT
    'vendor_payment' as project_type,
    102 as project_id,
    DATE_SUB(CURDATE(), INTERVAL 1 DAY) as payment_date,
    15000.00 as payment_amount,
    1 as payment_done_via,
    'cash' as payment_mode,
    2 as recipient_count,
    1 as created_by,
    1 as updated_by
UNION ALL SELECT
    'contractor_payment' as project_type,
    103 as project_id,
    DATE_SUB(CURDATE(), INTERVAL 2 DAY) as payment_date,
    35000.00 as payment_amount,
    1 as payment_done_via,
    'upi' as payment_mode,
    1 as recipient_count,
    1 as created_by,
    1 as updated_by
UNION ALL SELECT
    'supplier_payment' as project_type,
    104 as project_id,
    DATE_SUB(CURDATE(), INTERVAL 3 DAY) as payment_date,
    8500.00 as payment_amount,
    1 as payment_done_via,
    'cheque' as payment_mode,
    3 as recipient_count,
    1 as created_by,
    1 as updated_by
UNION ALL SELECT
    'service_payment' as project_type,
    105 as project_id,
    DATE_SUB(CURDATE(), INTERVAL 5 DAY) as payment_date,
    12000.00 as payment_amount,
    1 as payment_done_via,
    'bank_transfer' as payment_mode,
    2 as recipient_count,
    1 as created_by,
    1 as updated_by
) tmp
WHERE NOT EXISTS (
    SELECT 1 FROM hr_payment_entries LIMIT 1
);