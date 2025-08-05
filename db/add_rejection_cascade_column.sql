-- Add rejection_cascade column to travel_expenses table
-- First check if column exists
SET @dbname = DATABASE();
SET @tablename = "travel_expenses";
SET @columnname = "rejection_cascade";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " VARCHAR(50) NULL COMMENT 'Tracks which role initiated rejection cascade (e.g., HR_REJECTED, ACCOUNTANT_REJECTED)' AFTER hr_reason;")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index on rejection_cascade column for faster queries
-- First check if index exists
SET @indexname = "idx_rejection_cascade";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (INDEX_NAME = @indexname)
  ) > 0,
  "SELECT 1",
  CONCAT("CREATE INDEX ", @indexname, " ON ", @tablename, " (", @columnname, ");")
));
PREPARE createIndexIfNotExists FROM @preparedStatement;
EXECUTE createIndexIfNotExists;
DEALLOCATE PREPARE createIndexIfNotExists;
























     ALTER TABLE travel_expenses 
     ADD COLUMN meter_start_photo_path VARCHAR(255) DEFAULT NULL AFTER bill_file_path, 
     ADD COLUMN meter_end_photo_path VARCHAR(255) DEFAULT NULL AFTER meter_start_photo_path;