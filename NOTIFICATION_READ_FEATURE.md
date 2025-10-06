# Notification Read Status Feature

## Overview
This feature implements tracking of when attendance notifications are read by users. When a user opens the notification dropdown, any missing punch notifications are marked as "read" and will display a "read" tag instead of "new" on subsequent visits.

## Components

### 1. Database Table
**File:** `db/create_attendance_notification_read_table.sql`

Creates the `attendance_notification_read` table with the following structure:
- `id`: Primary key
- `user_id`: Foreign key to users table
- `attendance_date`: Date of the attendance notification
- `read_at`: Timestamp when the notification was read

### 2. Backend Changes

#### get_missing_punches.php
- Automatically marks fetched notifications as read in the `attendance_notification_read` table
- Uses `ON DUPLICATE KEY UPDATE` to handle cases where notifications are already marked as read

#### check_notification_read_status.php
- New AJAX endpoint to check which attendance notifications have been read
- Accepts an array of dates and returns which ones are marked as read

### 3. Frontend Changes

#### recent_time_widget.php
- Modified `fetchMissingPunches()` function to check read status after fetching notifications
- Added `checkNotificationReadStatus()` function to query the read status endpoint
- Updated `updateNotificationList()` function to display "read" or "new" tags based on read status
- Added CSS styles for notification status tags

## How It Works

1. When a user clicks the notification bell icon, the dropdown opens
2. JavaScript calls `get_missing_punches.php` to fetch missing attendance notifications
3. The PHP script automatically marks all returned notifications as read in the database
4. JavaScript then calls `check_notification_read_status.php` to get the read status of the notifications
5. The notification list is updated with "read" tags for previously read notifications and "new" tags for new ones

## Database Schema

```sql
CREATE TABLE IF NOT EXISTS attendance_notification_read (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_date (user_id, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Files Modified

1. `ajax_handlers/get_missing_punches.php` - Added read status marking
2. `ajax_handlers/check_notification_read_status.php` - New file for checking read status
3. `components/dashboard_widgets/recent_time_widget.php` - Updated JavaScript and CSS for UI changes
4. `db/create_attendance_notification_read_table.sql` - Database schema

## Testing

The feature has been tested and verified to work correctly:
- Database table creation
- Marking notifications as read
- Checking read status
- Displaying appropriate tags in the UI