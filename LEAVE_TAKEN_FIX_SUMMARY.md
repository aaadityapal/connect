# Leave Taken Calculation Fix - Summary

## Problem
User 21's approved leave on 26/11/2025 was showing as **0 days** in the Leave Taken column of the Monthly Analytics Dashboard, despite having an approved leave request.

## Root Cause
The original query in `fetch_monthly_analytics_data.php` was using MySQL's `DATE_CONCAT()` function to construct date strings:

```php
SELECT SUM(DATEDIFF(
    LEAST(end_date, DATE_CONCAT(?, '-', LPAD(?, 2, '0'), '-31')),
    GREATEST(start_date, DATE_CONCAT(?, '-', LPAD(?, 2, '0'), '-01'))
) + 1) as total_leave_days
```

The `DATE_CONCAT()` function is not available or working properly in the current MySQL version (MariaDB 10.4.28), causing the query to fail silently and return NULL/0.

## Solution
Replaced the dynamic `DATE_CONCAT()` functions with direct date parameter binding:

### Before (Broken):
```php
$leaveStmt->execute([
    $year, $month,
    $year, $month,
    $employee['id'],
    $month, $year,
    $month, $year,
    $year, $month,
    $year, $month
]);
```

### After (Fixed):
```php
// Calculate month boundaries
$monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
$firstDayOfMonth = "$year-$monthStr-01";
$lastDayOfMonth = date('Y-m-t', strtotime($firstDayOfMonth));

// Use the calculated dates directly in the query
$leaveStmt->execute([
    $lastDayOfMonth,      // Month end date
    $firstDayOfMonth,     // Month start date
    $employee['id'],
    $month, $year,
    $month, $year,
    $firstDayOfMonth,     // Direct date binding
    $lastDayOfMonth       // Direct date binding
]);
```

## Files Modified
- **fetch_monthly_analytics_data.php** (Lines 275-315)
  - Updated the `leave_taken` calculation query to use direct date parameter binding instead of DATE_CONCAT()

## Verification
✅ **Test Result**: User 21's approved leave on 26/11/2025 now correctly shows as **1 day** in the Leave Taken column.

The fix has been verified to work correctly for:
- Single-day leaves
- Multi-day leaves spanning the selected month
- Leaves that start/end in previous/next months but overlap with the selected month

## Query Logic
The query correctly handles all three scenarios:
1. **Leaves starting in the selected month**: `MONTH(start_date) = ? AND YEAR(start_date) = ?`
2. **Leaves ending in the selected month**: `MONTH(end_date) = ? AND YEAR(end_date) = ?`
3. **Leaves spanning across the selected month**: `start_date < ? AND end_date > ?`

For each matching leave, it calculates the overlapping days using:
```
DATEDIFF(LEAST(end_date, month_end), GREATEST(start_date, month_start)) + 1
```

This ensures accurate calculation of leave days even when leaves span multiple months.
