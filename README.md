# HR Management System - Attendance Module

This repository contains the HR Management System with an integrated attendance tracking module.

## Attendance System Features

1. **Personalized Greeting**
   - Displays greetings based on time of day (morning/afternoon/evening/night)
   - Shows unique icons for different times of day
   - Displays current date and time

2. **Punch In/Out System**
   - Single button toggles between punch in and punch out
   - Shows shift time remaining display
   - Prevents multiple punch-ins on the same day
   - Disables punch-in/out after completing attendance for the day

3. **Photo Verification**
   - Takes selfie for attendance verification
   - Saves photos to filesystem in `uploads/attendance/` directory
   - Ensures accountability and prevents buddy punching

4. **Geolocation Tracking**
   - Records coordinates at punch-in/out
   - Reverse geocoding to get user's address
   - Stores location data for attendance verification

5. **Data Persistence**
   - Maintains punch status even after page refresh
   - Checks server for current status when page loads
   - Shows appropriate UI based on punch status

## API Endpoints

The attendance system uses the following API endpoints:

- **GET /api/check_punch_status.php** - Checks current punch status
- **POST /api/process_punch.php** - Process punch in/out data

## Database Structure

The system uses the `attendance` table in the database. The SQL structure is defined in `sql/attendance_table.sql`.

## Recent Changes

- Moved API endpoints to dedicated `/api` folder
- Fixed issue with punch-out data not being stored correctly
- Added validation to prevent punching in multiple times on the same day
- Added feature to mark attendance as completed for the day after punch-out
- Improved UI to show different states (punch in, punch out, completed)
- Fixed parameter binding in database queries
- Added comprehensive error handling

## Setup

To set up the attendance system, follow these steps:

1. Ensure the database table is created using the SQL in `sql/attendance_table.sql`
2. Make sure the `uploads/attendance` directory is writable by the web server
3. Configure the database connection in `config/db_connect.php`

## Development

When extending this system, follow these guidelines:

1. Keep API endpoints in the `/api` folder
2. Maintain the existing data structure for compatibility
3. Follow the established UI patterns for consistency 