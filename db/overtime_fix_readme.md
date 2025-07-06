# Overtime Approval System Fix

This document explains the fixes implemented to address issues with duplicate entries and incorrect status persistence in the overtime approval system.

## Issues Fixed

1. **Duplicate entries after approval**:
   - When approving overtime, two entries appeared in the table
   - This was caused by duplicate data between `overtime_notification` and `overtime_notifications` tables

2. **Rejected entries reappearing as "Submitted"**:
   - After rejecting an entry, it still showed up with "Submitted" status
   - Required multiple rejections before it disappeared
   - Only disappeared permanently when approved

## Solution Implemented

### 1. Fixed the API Endpoint (`api/update_overtime_status.php`)

- Modified to check if a notification record already exists before creating a new one
- If a record exists, it updates the existing record instead of creating a duplicate
- Updated the status parameter in the attendance query to use the current status instead of always "submitted"

### 2. Created a Table Merge Utility (`db/merge_overtime_tables.php`)

- This script merges data from `overtime_notification` (singular) into `overtime_notifications` (plural)
- It transfers any unique records from the singular table to the plural table
- It then drops the singular table to prevent future issues

### 3. Updated the Main Query in `overtime_reports.php`

- Added a filter to exclude rejected entries from the initial view
- Added GROUP BY to prevent duplicate entries
- This ensures each overtime record appears only once in the list

## How to Apply the Fix

1. **Run the Table Merge Utility**:
   - Navigate to `http://your-server/hr/db/merge_overtime_tables.php`
   - This will merge data from the singular table to the plural table and drop the singular table

2. **Test the Overtime Approval System**:
   - Approve and reject overtime entries to ensure they behave correctly
   - Verify that approved entries show as approved and don't appear twice
   - Verify that rejected entries don't reappear as "Submitted"

## Additional Notes

- The fix maintains all existing data and functionality
- It's backward compatible with existing code
- If you encounter any issues, check the error logs for details

## Technical Details

The root cause was having two similarly named tables:
- `overtime_notification` (singular)
- `overtime_notifications` (plural)

The code was inconsistently referencing and updating these tables, leading to data inconsistency.
The fix standardizes on the plural version (`overtime_notifications`) and ensures proper status tracking. 