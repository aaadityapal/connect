# Geofencing Data Flow Diagram

## Complete Punch-In/Out Flow with Geofencing

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           PUNCH-IN/OUT PROCESS                              │
└─────────────────────────────────────────────────────────────────────────────┘

1. USER OPENS PUNCH MODAL
   │
   ├─ Modal opens (openModal)
   │  ├─ Load shift information
   │  ├─ Fetch geofences from API
   │  │  └─ GET /api_get_geofences.php
   │  │     └─ Returns: [{id, name, latitude, longitude, radius}]
   │  └─ Start camera
   │
   └─> Show camera feed to user

2. USER CAPTURES PHOTO
   │
   ├─ Photo captured as base64
   ├─ Get GPS location (geolocation API)
   │  └─ Returns: latitude, longitude, accuracy
   └─> Show preview with captured photo

3. PREVIEW DISPLAY (showPreview)
   │
   ├─ Display captured photo
   ├─ Get user location
   ├─ Check geofence status
   │  ├─ Call checkIfWithinGeofence(lat, lon)
   │  ├─ Use Haversine formula for distance calculation
   │  └─ Compare distance with all geofence radii
   │
   └─ Update UI based on geofence status:
      │
      ├─ IF WITHIN GEOFENCE:
      │  ├─ Show green "Within Geofence" box
      │  ├─ Display closest location name
      │  └─ Hide reason textarea
      │
      └─ IF OUTSIDE ALL GEOFENCES:
         ├─ Show red "Outside Geofence" box
         ├─ Display reason textarea
         └─ Initialize word counter (10+ words required)

4. PUNCH-IN SUBMISSION
   │
   ├─ User clicks "Confirm & Punch In"
   ├─ Validate geofence reason (if outside):
   │  └─ If outside AND reason < 10 words → Error, prevent submission
   │
   └─ If validation passes:
      │
      ├─ Prepare payload:
      │  ├─ photo: base64 image
      │  ├─ latitude: user GPS latitude
      │  ├─ longitude: user GPS longitude
      │  ├─ accuracy: GPS accuracy in meters
      │  ├─ camera: 'front' or 'back'
      │  └─ geofence_outside_reason: (optional, if outside)
      │
      └─> POST /api_punch_in.php

5. BACKEND PROCESSING (api_punch_in.php)
   │
   ├─ Validate user session
   ├─ Extract JSON data
   ├─ Validate geofence reason (if provided):
   │  └─ Word count must be >= 10
   │
   ├─ Store photo:
   │  └─ Save to /uploads/attendance/{userId}_{date}_{time}_{ms}.jpeg
   │
   ├─ Check for duplicate punch-in today
   │
   ├─ Fetch shift information
   │
   └─ INSERT into attendance table:
      ├─ user_id
      ├─ date
      ├─ punch_in (time)
      ├─ punch_in_photo (file path)
      ├─ punch_in_latitude
      ├─ punch_in_longitude
      ├─ punch_in_accuracy
      ├─ shifts_id
      ├─ geofence_outside_reason (NULL or reason text)
      └─ ... other fields

6. DATABASE STORAGE (attendance table)
   │
   └─ Record created with all data:
      ├─ If inside geofence → geofence_outside_reason = NULL
      └─ If outside geofence → geofence_outside_reason = (10+ word reason)

7. RESPONSE & SUCCESS
   │
   ├─ API returns success with attendance_id
   ├─ Update LocalStorage with punch-in data
   ├─ Update button state to "Punch Out"
   ├─ Show success message
   └─> Close modal after delay

┌─────────────────────────────────────────────────────────────────────────────┐
│                         PUNCH-OUT ADDS COMPLEXITY                           │
└─────────────────────────────────────────────────────────────────────────────┘

PUNCH-OUT has TWO validations (instead of one):
   │
   ├─ Validation 1: WORK REPORT (20+ words REQUIRED)
   │  └─ Show textarea with real-time word counter
   │
   └─ Validation 2: GEOFENCE REASON (10+ words IF OUTSIDE)
      └─ Only required if outside all geofences

EXAMPLE PUNCH-OUT PAYLOAD:
{
  "photo": "base64_image",
  "latitude": 28.6140,
  "longitude": 77.2091,
  "accuracy": 15,
  "camera": "front",
  "attendance_id": 123,
  "workReport": "Completed project tasks, attended team meetings, reviewed code, and prepared documentation for the new features implemented. Had discussions with product team about upcoming releases.",
  "geofence_outside_reason": "Was at client location for meeting regarding project requirements and technical implementation. Had approval from manager."
}

BACKEND PROCESSING (api_punch_out.php):
   │
   ├─ Validate work report (20+ words) ✓
   ├─ Validate geofence reason (10+ words IF provided) ✓
   ├─ Store photos and all data
   │
   └─ UPDATE attendance table:
      ├─ punch_out (time)
      ├─ punch_out_photo (file path)
      ├─ punch_out_latitude
      ├─ punch_out_longitude
      ├─ punch_out_accuracy
      ├─ work_report (20+ words)
      └─ geofence_outside_reason (NULL or 10+ words)

┌─────────────────────────────────────────────────────────────────────────────┐
│                        DISTANCE CALCULATION (CLIENT-SIDE)                   │
└─────────────────────────────────────────────────────────────────────────────┘

HAVERSINE FORMULA:
   
   Input:
   ├─ User location: (userLat, userLon)
   ├─ Geofence center: (geofenceLat, geofenceLon)
   ├─ Earth radius: 6,371,000 meters
   └─ Geofence radius: varies (e.g., 500m, 1000m)

   Calculation:
   ├─ dLat = (lat2 - lat1) × π/180
   ├─ dLon = (lon2 - lon1) × π/180
   ├─ a = sin²(dLat/2) + cos(lat1) × cos(lat2) × sin²(dLon/2)
   ├─ c = 2 × atan2(√a, √(1-a))
   └─ distance = R × c (in meters)

   Output:
   └─ Distance in meters between two GPS points

   Logic:
   ├─ For each geofence:
   │  ├─ Calculate distance to user location
   │  └─ If distance <= radius → User is INSIDE
   │
   └─ If inside ANY geofence → User is within geofence
      Else → User is outside all geofences

EXAMPLE:
   User at: (28.6140, 77.2091)
   Geofence: (28.6139, 77.2090) with radius 500m
   Calculated distance: ~123m
   Status: INSIDE (123m < 500m)
   Result: Show green box, no reason needed

┌─────────────────────────────────────────────────────────────────────────────┐
│                           WORD COUNT VALIDATION                             │
└─────────────────────────────────────────────────────────────────────────────┘

CLIENT-SIDE (Real-time feedback):
   │
   ├─ On input event → Count words immediately
   ├─ Word counting: text.split(/\s+/).length
   └─ Update word counter display:
      ├─ 0-9 words → "0-9 / 10 words" (red border, show warning)
      └─ 10+ words → "10+ / 10 words" (green border, hide warning)

SERVER-SIDE (Final validation):
   │
   ├─ Count words: count(array_filter(explode(' ', text)))
   ├─ Validation rules:
   │  ├─ Work report: >= 20 words (punch-out only)
   │  └─ Geofence reason: >= 10 words (if outside geofence)
   │
   └─ Error responses:
      ├─ If work report < 20 words → 400 Bad Request
      └─ If geofence reason < 10 words → 400 Bad Request

┌─────────────────────────────────────────────────────────────────────────────┐
│                         DATABASE SCHEMA SUMMARY                             │
└─────────────────────────────────────────────────────────────────────────────┘

ATTENDANCE TABLE (key columns for geofencing):
   ├─ id (INT)
   ├─ user_id (INT)
   ├─ date (DATE)
   ├─ punch_in (TIME)
   ├─ punch_out (TIME)
   ├─ punch_in_photo (VARCHAR)
   ├─ punch_out_photo (VARCHAR)
   ├─ punch_in_latitude (DECIMAL)
   ├─ punch_in_longitude (DECIMAL)
   ├─ punch_in_accuracy (DECIMAL)
   ├─ punch_out_latitude (DECIMAL)
   ├─ punch_out_longitude (DECIMAL)
   ├─ punch_out_accuracy (DECIMAL)
   ├─ work_report (TEXT)
   ├─ geofence_outside_reason (TEXT) ← NEW COLUMN
   └─ ... other columns

GEOFENCE_LOCATIONS TABLE (reference):
   ├─ id (INT)
   ├─ name (VARCHAR) - e.g., "Main Office"
   ├─ address (VARCHAR)
   ├─ latitude (DECIMAL) - GPS coordinate
   ├─ longitude (DECIMAL) - GPS coordinate
   ├─ radius (INT) - meters
   └─ is_active (BOOLEAN)

┌─────────────────────────────────────────────────────────────────────────────┐
│                        FEATURE DECISION TREE                                │
└─────────────────────────────────────────────────────────────────────────────┘

START: User tries to punch in/out
   │
   └─ Get user GPS location
      │
      ├─ Calculate distance to each geofence
      │
      └─ Check if user is within ANY geofence radius
         │
         ├─ YES → Within Geofence
         │  │
         │  ├─ If PUNCH-IN:
         │  │  └─ Allow without reason
         │  │
         │  └─ If PUNCH-OUT:
         │     ├─ Require work report (20+ words)
         │     └─ Allow without geofence reason
         │
         └─ NO → Outside All Geofences
            │
            ├─ If PUNCH-IN:
            │  ├─ Show reason textarea
            │  └─ Require reason (10+ words)
            │
            └─ If PUNCH-OUT:
               ├─ Require work report (20+ words)
               └─ Require reason (10+ words)

┌─────────────────────────────────────────────────────────────────────────────┐
│                     ERROR HANDLING & EDGE CASES                             │
└─────────────────────────────────────────────────────────────────────────────┘

GEOLOCATION ERRORS:
   ├─ User denies permission → Continue with null coordinates
   ├─ Timeout → Continue with last known location
   └─ Unsupported browser → Show warning, allow manual entry (future)

GEOFENCE FETCH ERRORS:
   ├─ API unreachable → Empty geofence array, treat as outside
   ├─ No active geofences → No geofence section shown
   └─ Network timeout → Retry silently, fallback to empty list

VALIDATION ERRORS:
   ├─ Insufficient words → Show error, prevent submission
   ├─ Invalid word count on server → Return 400, show error
   └─ Network error during submission → Show timeout message, enable retry

DATABASE ERRORS:
   ├─ Duplicate punch-in → Return 409 Conflict
   ├─ No open record for punch-out → Return 409 Conflict
   └─ Insert/update failure → Return 500 with error details

┌─────────────────────────────────────────────────────────────────────────────┐
│                          AUDIT TRAIL                                        │
└─────────────────────────────────────────────────────────────────────────────┘

All punch records include:
   ├─ GPS coordinates (latitude, longitude, accuracy)
   ├─ Timestamp (punch_in/punch_out time)
   ├─ Photo evidence (file paths)
   ├─ Work report (for punch-out)
   ├─ Geofence status
   │  ├─ NULL if within geofence
   │  └─ Reason text if outside geofence
   └─ Modified timestamps (created_at, modified_at)

This allows:
   ├─ Verification of work location
   ├─ Reason tracking for policy compliance
   ├─ Historical analysis of remote work
   └─ Audit reports for HR/compliance
```

## Summary

The geofencing system implements a complete flow:

1. **Pre-Submission**: Fetch geofences, calculate user distance
2. **Pre-Validation**: Display geofence status, show reason if needed
3. **Validation**: Check geofence reason (10 words) AND work report (20 words if punch-out)
4. **Submission**: Send data with optional reason to backend
5. **Processing**: Server validates, stores all data
6. **Storage**: Database records with geofence reason for audit trail

The system is designed to:
- Prevent unauthorized work location changes
- Provide accountability through reason tracking
- Maintain audit trail of all location-based activity
- Support policy enforcement for office presence
