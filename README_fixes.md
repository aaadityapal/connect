# Calendar Database Fixes

This package contains fixes for two issues in the calendar module:

1. `event_date` storing `00:00:00` in `hr_supervisor_activity_log` table
2. Location data (latitude, longitude, location_accuracy, location_address) not being saved in `hr_supervisor_material_photo_records` table

## Database Connection Issues

Before applying the fixes, make sure your database connection is working:

1. Check that required PHP extensions are installed:
   - mysqli or PDO_MySQL

   You can verify this using:
   ```php
   <?php
   echo "mysqli extension: " . (extension_loaded('mysqli') ? "Loaded" : "Not loaded") . "\n";
   echo "PDO extension: " . (extension_loaded('pdo') ? "Loaded" : "Not loaded") . "\n";
   echo "PDO MySQL extension: " . (extension_loaded('pdo_mysql') ? "Loaded" : "Not loaded") . "\n";
   ```

2. If extensions are missing, enable them in your php.ini file:
   - Uncomment `extension=mysqli` or `extension=pdo_mysql`
   - Restart your web server

## Applying the Fixes

### Fix 1: Event Date Format Issue

1. Open `includes/calendar_data_handler.php`
2. Find the `logActivity` function (around line 27)
3. After the code that converts arrays to JSON strings, add:

```php
// Fix for date issue: Ensure eventDate has time component
if ($eventDate) {
    // If eventDate doesn't have a time component, add current time
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
        $eventDate .= ' ' . date('H:i:s');
    }
}
```

### Fix 2: Material Photo Location Data Issue

1. Open `includes/calendar_data_handler.php`
2. Find the `processMaterialPhotos` function (around line 483)
3. Replace the entire function with the code from `calendar_data_handler_fixes.php`

### Database Schema Fixes

1. Run the SQL statements in `sql_fixes.sql` on your database
2. This will:
   - Change `event_date` column in `hr_supervisor_activity_log` to DATETIME
   - Add or modify location data columns in `hr_supervisor_material_photo_records` (latitude, longitude, location_accuracy, location_address)

## Testing the Fixes

You can test the fixes using:

1. `test_fix_results.php` - Tests both fixes
2. `test_database_records.php` - Verifies database records are being saved correctly

## Additional Notes

- If you can't access the database due to missing extensions, contact your server administrator
- The fixes preserve backward compatibility with the existing code
- Existing records with '00:00:00' times will be updated when running the SQL script 