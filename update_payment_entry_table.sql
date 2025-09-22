-- Add payment_for column to hr_payment_splits table
ALTER TABLE hr_payment_splits 
ADD COLUMN payment_for VARCHAR(255) AFTER payment_mode;

-- If you want to make it required (optional)
-- ALTER TABLE hr_payment_splits 
-- MODIFY COLUMN payment_for VARCHAR(255) NOT NULL;