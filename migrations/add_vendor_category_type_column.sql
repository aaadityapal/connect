-- Add vendor_category_type column to pm_vendor_registry_master table
-- This column stores the vendor category: Labour Contractor, Material Contractor, or Material Supplier

ALTER TABLE `pm_vendor_registry_master` 
ADD COLUMN `vendor_category_type` VARCHAR(50) NULL 
AFTER `vendor_type_category`;

-- Create an index for faster lookups
CREATE INDEX IF NOT EXISTS `idx_vendor_category_type` ON `pm_vendor_registry_master` (`vendor_category_type`);
