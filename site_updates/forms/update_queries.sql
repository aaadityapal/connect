-- Site Updates SQL Queries
-- This file contains all SQL queries used for the site updates functionality

-- Check if site_updates table exists, if not create it
CREATE TABLE IF NOT EXISTS `site_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_name` varchar(255) NOT NULL,
  `update_date` date NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Check if site_name column exists in site_updates table
-- Used in process_update.php to check if column exists before inserting data
SHOW COLUMNS FROM site_updates LIKE 'site_name';

-- Check if update_date column exists in site_updates table
-- Used in process_update.php to check if column exists before inserting data
SHOW COLUMNS FROM site_updates LIKE 'update_date';

-- Add site_name column if it does not exist
ALTER TABLE site_updates ADD COLUMN site_name VARCHAR(255) AFTER id;

-- Add update_date column if it does not exist
ALTER TABLE site_updates ADD COLUMN update_date DATE AFTER id;

-- Insert new site update
-- Used in both update_form.php and process_update.php
INSERT INTO site_updates (site_name, update_date, created_by, created_at) 
VALUES (?, ?, ?, NOW());

-- Get all site updates ordered by creation date (newest first)
-- Used in site_updates.php to display updates
SELECT * FROM site_updates ORDER BY created_at DESC;

-- Get recent site updates (limited to 5)
-- Used in site_updates.php to display recent updates
SELECT * FROM site_updates ORDER BY created_at DESC LIMIT 5;

-- Get updates from a specific month (for month filter functionality)
-- Replace MONTH_NUMBER with the selected month (1-12)
SELECT * FROM site_updates 
WHERE MONTH(update_date) = MONTH_NUMBER 
ORDER BY update_date DESC, created_at DESC;

-- Get updates for a specific site
-- Replace SITE_NAME with the site name to filter by
SELECT * FROM site_updates 
WHERE site_name = 'SITE_NAME' 
ORDER BY update_date DESC, created_at DESC;

-- Get updates created by a specific user
-- Replace USER_ID with the ID of the user
SELECT su.*, u.name as creator_name
FROM site_updates su
JOIN users u ON su.created_by = u.id
WHERE su.created_by = USER_ID
ORDER BY su.created_at DESC;

-- Update an existing site update
-- Used when editing an existing update
UPDATE site_updates
SET site_name = ?, update_date = ?, updated_at = NOW()
WHERE id = ?;

-- Delete a site update
-- Used when removing an update
DELETE FROM site_updates WHERE id = ?; 