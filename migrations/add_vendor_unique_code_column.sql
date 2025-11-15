-- Modify vendor_unique_code column to allow NULL values
-- This allows existing records to be inserted without the unique code

ALTER TABLE `pm_vendor_registry_master` 
MODIFY COLUMN `vendor_unique_code` VARCHAR(50) NULL UNIQUE;

-- Create an index on vendor_unique_code for faster lookups
CREATE INDEX IF NOT EXISTS `idx_vendor_unique_code` ON `pm_vendor_registry_master` (`vendor_unique_code`);
