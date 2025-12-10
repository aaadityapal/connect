# Geofencing Setup Guide

## Quick Start

### Step 1: Database Migration
Run the migration script to add the `geofence_outside_reason` column to the attendance table:

```bash
php migrate_add_geofence_reason.php
```

Or execute the SQL directly:
```sql
ALTER TABLE attendance ADD COLUMN geofence_outside_reason TEXT NULL AFTER work_report;
```

### Step 2: Verify Geofence Locations Exist
Ensure the `geofence_locations` table has at least one active location:

```sql
SELECT * FROM geofence_locations WHERE is_active = 1;
```

Example insert:
```sql
INSERT INTO geofence_locations (name, latitude, longitude, radius, is_active) 
VALUES ('Main Office', 28.6139, 77.2090, 500, 1);
```

### Step 3: Test the Feature

1. Open the application dashboard
2. Click the punch button to open the modal
3. Allow location access when prompted
4. Capture a photo
5. Check if geofence status shows
6. Test both scenarios:
   - Inside geofence: No reason needed
   - Outside geofence: Reason required (10+ words)

## Key Features

### Geofence Status Display
- **Green box**: User is within an active geofence radius
- **Red box**: User is outside all geofence radii
- **Location name**: Shows the closest geofence location when inside

### Validation Rules

#### Punch-In
- **Inside geofence**: Photo + GPS location required
- **Outside geofence**: Photo + GPS + 10+ word reason required

#### Punch-Out
- **Inside geofence**: Photo + GPS + 20+ word work report required
- **Outside geofence**: Photo + GPS + 20+ word work report + 10+ word reason required

## Files Included

| File | Purpose |
|------|---------|
| punch-modal.js | Frontend modal with geofencing logic |
| api_punch_in.php | Punch-in API with geofence reason handling |
| api_punch_out.php | Punch-out API with geofence reason handling |
| api_get_geofences.php | Fetches active geofence locations |
| migrate_add_geofence_reason.php | Database migration script |
| add_geofence_reason_column.sql | SQL migration file |

## Configuration

### Geofence Settings
Manage geofence locations at: `/admin/manage_geofence_locations.php`

- Name: Location identifier (e.g., "Main Office")
- Address: Physical address
- Latitude: GPS latitude
- Longitude: GPS longitude
- Radius: Detection radius in meters (e.g., 500 for 500m radius)
- Is Active: Toggle to enable/disable geofence

### Word Count Requirements
- **Work Report**: Minimum 20 words (punch-out only)
- **Geofence Reason**: Minimum 10 words (only if outside geofence)

## Troubleshooting

### Location Not Detected
1. Check if browser has permission to access location
2. Ensure HTTPS or localhost (geolocation requires secure context)
3. Check browser console for geolocation errors

### Reason Still Required After Adding Words
1. Need to type at least 10 words separated by spaces
2. Word counter shows "0 / 10 words" - type more
3. Red border indicates insufficient words
4. Green border indicates valid (10+ words)

### Column Already Exists
The migration script checks if the column exists. If you get an error:
```bash
php migrate_add_geofence_reason.php
```
It will safely detect and skip if already present.

## Database Queries

### View Geofence Locations
```sql
SELECT id, name, latitude, longitude, radius, is_active 
FROM geofence_locations 
WHERE is_active = 1;
```

### View Punch Records with Geofence Reasons
```sql
SELECT user_id, date, punch_in, punch_out, work_report, geofence_outside_reason
FROM attendance
WHERE geofence_outside_reason IS NOT NULL;
```

### Count Outside-Geofence Punches
```sql
SELECT COUNT(*) as outside_count
FROM attendance
WHERE geofence_outside_reason IS NOT NULL
AND DATE(date) >= CURDATE() - INTERVAL 7 DAY;
```

## API Endpoints

### GET /api_get_geofences.php
Fetches all active geofence locations
```json
{
  "success": true,
  "count": 2,
  "geofences": [
    {
      "id": 1,
      "name": "Main Office",
      "latitude": 28.6139,
      "longitude": 77.2090,
      "radius": 500
    }
  ]
}
```

### POST /api_punch_in.php
Records punch-in with optional geofence reason
```json
{
  "photo": "base64_encoded_image",
  "latitude": 28.6140,
  "longitude": 77.2091,
  "accuracy": 15,
  "camera": "front",
  "geofence_outside_reason": "String with 10+ words if outside"
}
```

### POST /api_punch_out.php
Records punch-out with work report and optional geofence reason
```json
{
  "photo": "base64_encoded_image",
  "latitude": 28.6140,
  "longitude": 77.2091,
  "accuracy": 15,
  "camera": "front",
  "attendance_id": 123,
  "workReport": "String with 20+ words",
  "geofence_outside_reason": "String with 10+ words if outside"
}
```

## Security Notes

1. **Location Privacy**: GPS coordinates stored with accuracy radius
2. **Validation**: Both client and server validate word counts
3. **Authentication**: All endpoints require valid session
4. **Data Storage**: Reasons stored in attendance table for audit trail
5. **Immutable Records**: Once punch is recorded, cannot be modified

## Performance

- **Geofence Fetch**: Once per modal open (async, non-blocking)
- **Distance Calculation**: Client-side using Haversine formula
- **API Calls**: Minimal - only during punch-in/out
- **Database Impact**: Single column addition, minimal storage overhead

## Browser Requirements

- Modern browser with Geolocation API support
- HTTPS or localhost (geolocation requires secure context)
- JavaScript enabled
- Fetch API support
- Camera/microphone permissions (for photo capture)

## Support

For issues or questions:
1. Check browser console for errors
2. Verify geolocation permissions granted
3. Confirm geofence locations are active in database
4. Test with sufficient word count in inputs
5. Check network tab for API response codes
