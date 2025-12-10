# Geofencing Implementation Summary

## Overview
Complete geofencing validation system has been added to the punch-in/out modal, enabling location-based access control with mandatory reason requirement when users punch in/out outside designated work locations.

## Components Implemented

### 1. Frontend - punch-modal.js

#### New Properties in Constructor
```javascript
this.geofences = [];           // Array of active geofence locations
this.currentLocation = null;   // Current GPS coordinates
this.isWithinGeofence = false; // Boolean flag for geofence status
```

#### New Methods Added

**fetchGeofences()** - Async method to fetch active geofence locations from API
- Calls `api_get_geofences.php`
- Stores geofences in `this.geofences` array
- Handles errors gracefully

**getDistanceBetweenCoordinates(lat1, lon1, lat2, lon2)** - Distance calculation
- Uses Haversine formula
- Returns distance in meters
- Accurate for GPS coordinate comparison

**checkIfWithinGeofence(userLat, userLon)** - Geofence boundary check
- Iterates through all active geofences
- Checks if user location is within any radius
- Returns true if within ANY geofence, false if outside all

**updateGeofenceStatusUI(userLat, userLon)** - UI status display
- Displays "Within Geofence" box (green) if inside
- Shows location name if within geofence
- Displays "Outside Geofence" box (red) if outside any radius
- Shows reason textarea only when outside

**initializeGeofenceReasonCounter()** - Word count validation for reason
- Real-time word counter (min 10 words)
- Color feedback: red border if < 10 words, green if >= 10
- Integrated with reason textarea

**validateGeofenceReason()** - Validation before submission
- Checks if user is outside geofence
- Validates 10+ word minimum if reason is required
- Shows error message if validation fails

**getGeofenceReason()** - Retrieves reason text from textarea
- Returns null if not outside geofence
- Returns trimmed text if reason provided

#### Modified Methods

**openModal()**
- Added `await this.fetchGeofences()` call
- Geofences loaded when modal opens

**showPreview()**
- Calls `updateGeofenceStatusUI()` to display geofence status
- Initializes `initializeGeofenceReasonCounter()` for reason field
- Shows geofence warning section before work report section

**confirmPunchIn()**
- Added `if (!this.validateGeofenceReason()) return;` validation
- Includes `geofence_outside_reason` in API request payload if applicable

**confirmPunchOut()**
- Added `if (!this.validateGeofenceReason()) return;` validation
- Validates both work report (20 words) AND geofence reason (10 words) if needed
- Includes `geofence_outside_reason` in API request payload if applicable

#### New HTML Structure in Modal
```html
<!-- Geofence Status Section -->
<div id="geofenceStatusSection" style="display: none;">
  <!-- Within Geofence Box (Green) -->
  <div id="geofenceWithinBox" style="display: none;">
    <div style="display: flex; align-items: center; gap: 0.8rem;">
      <i data-feather="check-circle"></i>
      <div>
        <div style="font-weight: 600;">Within Geofence</div>
        <div id="geofenceLocationName"></div>
      </div>
    </div>
  </div>

  <!-- Outside Geofence Box (Red) -->
  <div id="geofenceOutsideBox" style="display: none;">
    <div style="display: flex; align-items: center; gap: 0.8rem; margin-bottom: 0.8rem;">
      <i data-feather="alert-circle"></i>
      <div style="font-weight: 600;">Outside Geofence</div>
    </div>
    <p>Please provide reason for being outside authorized location (10 words minimum)</p>
    <textarea id="geofenceReasonTextarea" placeholder="..."></textarea>
    <div style="display: flex; justify-content: space-between;">
      <span id="geofenceWordCount">0 / 10 words</span>
      <span id="geofenceWordWarning" style="display: none;">Minimum 10 words required</span>
    </div>
  </div>
</div>
```

### 2. Backend - api_punch_in.php

#### Changes Made
- Added extraction of `geofence_outside_reason` from request payload
- Added validation: reason must contain minimum 10 words if provided
- Updated INSERT query to store `geofence_outside_reason` in attendance table
- Added parameter binding for the reason field

#### New Validation Logic
```php
if ($geofenceOutsideReason) {
    $wordCount = count(array_filter(explode(' ', trim($geofenceOutsideReason))));
    if ($wordCount < 10) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Geofence reason must contain at least 10 words']);
        exit;
    }
}
```

### 3. Backend - api_punch_out.php

#### Changes Made
- Added extraction of `geofence_outside_reason` from request payload
- Added validation: reason must contain minimum 10 words if provided
- Updated UPDATE query to store `geofence_outside_reason` in attendance table
- Added parameter binding for the reason field
- Maintains existing work report validation (20 words minimum)

#### New Validation Logic
```php
if ($geofenceOutsideReason) {
    $geofenceWordCount = count(array_filter(explode(' ', trim($geofenceOutsideReason))));
    if ($geofenceWordCount < 10) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Geofence reason must contain at least 10 words']);
        exit;
    }
}
```

### 4. Database Migration

#### Migration File: add_geofence_reason_column.sql
```sql
ALTER TABLE attendance ADD COLUMN geofence_outside_reason TEXT NULL AFTER work_report;
```

#### Migration Script: migrate_add_geofence_reason.php
- Checks if column already exists
- Creates column if needed
- Handles migration errors safely
- Safe to run multiple times

### 5. Existing Geofence API Endpoint

#### api_get_geofences.php
- Returns all active geofence locations
- Includes: id, name, latitude, longitude, radius
- Used by frontend to check user location

## Feature Workflow

### Punch-In Flow with Geofencing
1. User opens punch-in modal
2. Geofences are fetched from API
3. User captures photo
4. GPS coordinates obtained
5. Check if within ANY geofence radius using Haversine formula
6. If WITHIN geofence:
   - Show green "Within Geofence" box with location name
   - Allow punch-in without reason
7. If OUTSIDE all geofences:
   - Show red "Outside Geofence" warning
   - Display reason textarea (hidden by default)
   - Require minimum 10-word reason
   - Validate before submission
8. Submit with photo, location, and optional reason

### Punch-Out Flow with Geofencing
1. User in punch-out mode (already punched in)
2. Geofence status checked same as punch-in
3. ALSO requires work report (20 words minimum)
4. If outside geofence: BOTH validations required
   - Work report: 20+ words
   - Geofence reason: 10+ words
5. Submit with photo, work report, location, and optional reason

## Data Storage

### Attendance Table New Column
- `geofence_outside_reason` - TEXT column
- NULL if user was within geofence
- Contains reason text if user was outside
- Stored after `work_report` column

### Data Flow to Database
- punch-in with outside reason → stored in attendance table
- punch-out with outside reason → stored in attendance table
- Both operations validate reason before storage

## Validation Rules Summary

| Scenario | Validation Required |
|----------|-------------------|
| Punch-In, Within Geofence | Photo + GPS only |
| Punch-In, Outside Geofence | Photo + GPS + 10+ word reason |
| Punch-Out, Within Geofence | Photo + GPS + 20+ word report |
| Punch-Out, Outside Geofence | Photo + GPS + 20+ word report + 10+ word reason |

## Files Modified

1. **punch-modal.js** (1114 lines)
   - Added geofencing properties, methods, and validations
   - Updated openModal(), showPreview(), confirmPunchIn(), confirmPunchOut()
   - Added HTML for geofence status section

2. **api_punch_in.php** (188 lines)
   - Added geofence reason extraction and validation
   - Updated INSERT query with geofence_outside_reason column

3. **api_punch_out.php** (185 lines)
   - Added geofence reason extraction and validation
   - Updated UPDATE query with geofence_outside_reason column

## Files Created

1. **add_geofence_reason_column.sql** - SQL migration for database column
2. **migrate_add_geofence_reason.php** - Safe PHP migration script

## Existing Files Used (No Changes)

1. **api_get_geofences.php** - Already created, fetches active geofences
2. **geofence_locations table** - Already exists in database

## Testing Checklist

- [ ] Run migration script: `migrate_add_geofence_reason.php`
- [ ] Test punch-in from within geofence (no reason needed)
- [ ] Test punch-in from outside geofence (reason required)
- [ ] Test punch-out from within geofence (work report required)
- [ ] Test punch-out from outside geofence (both validations required)
- [ ] Verify reason word counter works (10 word minimum)
- [ ] Verify work report word counter works (20 word minimum)
- [ ] Verify data stored correctly in attendance table
- [ ] Test error messages for insufficient words
- [ ] Test with different geofence locations

## API Integration Points

### Frontend to Backend
- POST to `api_punch_in.php` with payload including `geofence_outside_reason`
- POST to `api_punch_out.php` with payload including `geofence_outside_reason`
- GET from `api_get_geofences.php` to fetch geofence list

### Backend Database
- INSERT into `attendance` table with `geofence_outside_reason`
- UPDATE `attendance` table with `geofence_outside_reason`

## Performance Considerations

- Geofences fetched once when modal opens (async)
- Haversine distance calculation performed client-side
- No additional API calls during location validation
- Minimal database impact (single column addition)

## Browser Compatibility

- Requires: Geolocation API support
- Requires: Modern JavaScript (ES6+)
- Requires: Fetch API support
- Works on: Chrome, Firefox, Safari, Edge (all modern versions)

## Security Considerations

1. **Server-side validation**: Word count validated on both client and server
2. **Session authentication**: Requires valid user session
3. **Location data**: GPS coordinates stored with accuracy metric
4. **Reason text**: Stored as-is in database (no sanitization needed, but can add if required)
5. **IP tracking**: IP address and device info stored with punch records

## Future Enhancements

1. Add admin dashboard to view outside-geofence reasons
2. Add notifications when employee punches outside geofence
3. Add multiple geofence assignment per employee
4. Add time-based geofencing (different fences for different shifts)
5. Add GPS accuracy threshold validation
6. Add geofence entry/exit history logging
7. Add manager approval workflow for outside-geofence punches
