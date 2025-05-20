-- SQL to add the bill_file_path column to the travel_expenses table
ALTER TABLE travel_expenses ADD COLUMN IF NOT EXISTS bill_file_path VARCHAR(255) DEFAULT NULL; 