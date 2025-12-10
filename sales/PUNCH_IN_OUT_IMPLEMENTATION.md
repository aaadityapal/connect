# Punch-In/Out Implementation - Testing Guide

## Implementation Summary

The punch-in/out feature has been successfully implemented with the following functionality:

### ✅ Features Implemented

1. **Punch-In State Management**
   - User can punch in with camera photo capture
   - Button changes to "Punch Out" after successful punch-in
   - Button is disabled during punch-in process

2. **Punch-Out Functionality**
   - After punch-in, button shows "Punch Out" instead of "Punch In"
   - User can only punch out after successful punch-in
   - Punch-out requires new photo capture (same camera modal)
   - Punch-out records end time in database

3. **State Persistence**
   - LocalStorage stores punch-in data with `punchOutTime` tracking
   - Button state reflects current attendance status
   - Data persists across page refreshes

4. **Button State Management**
   - **Punch In**: Shows "Punch In" (enabled) when no punch-in record exists
   - **Punched In**: Shows "Punch Out" (enabled) when punch-in exists but punch-out is null
   - **Punched In & Out**: Shows "Punched In & Out" (disabled) when both punch-in and punch-out are recorded
   - Resets to "Punch In" the next day

5. **Modal Title Updates**
   - Shows "Punch In - Capture Photo" when user is not punched in
   - Shows "Punch Out - Capture Photo" when user has active punch-in
   - Displays current punch-in time during punch-out

### 📁 Files Modified

#### 1. **punch-modal.js**
- Added `isPunchedIn` and `currentPunchData` properties to track punch status
- Added `checkPunchStatus()` method to verify today's punch status on initialization
- Updated `createModal()` to include punch status display section
- Enhanced `openModal()` to detect punch-in status and show appropriate UI
- Updated `showPreview()` to handle both punch-in and punch-out scenarios
- Added `confirmPunchOut()` method for punch-out processing
- Added `dispatchPunchOutEvent()` for punch-out notifications
- Updated `setupEventListeners()` to handle punch-out button click
- Updated `resetModal()` to handle punch-out UI cleanup

#### 2. **greeting.js**
- Enhanced `setupPunchButton()` to listen for both punch-in and punch-out events
- Completely rewrote `updatePunchButtonState()` with proper logic:
  - Checks if punch-in exists for today
  - Checks if punch-out has been recorded
  - Updates button text and state accordingly
- Added `resetPunchButton()` helper method for button initialization

#### 3. **api_punch_out.php** (NEW)
- Created new endpoint to handle punch-out requests
- Validates user authentication
- Updates attendance record with punch-out time
- Stores punch-out photo (naming convention: `userId_date_time_out_ms.jpeg`)
- Stores punch-out geolocation data
- Returns success/error responses

### 🔄 Workflow

#### Punch-In Workflow:
1. User clicks "Punch In" button
2. Modal opens with camera feed and shift info
3. User captures photo
4. Photo is sent to `api_punch_in.php`
5. Attendance record created with punch-in time
6. LocalStorage updated with punch-in data
7. Button changes to "Punch Out"
8. Success toast notification shown
9. Modal closes

#### Punch-Out Workflow:
1. User clicks "Punch Out" button
2. Modal opens with camera feed + punch-in time display
3. User captures punch-out photo
4. Photo is sent to `api_punch_out.php`
5. Attendance record updated with punch-out time
6. LocalStorage updated with punch-out time
7. Button changes to "Punched In & Out" (disabled)
8. Success toast notification shown
9. Modal closes
10. Button resets to "Punch In" on next day

### 💾 LocalStorage Structure

```json
{
  "date": "12/9/2025",
  "time": "09:30:45 AM",
  "timestamp": 1733730645000,
  "camera": "front",
  "attendance_id": 123,
  "status": "success",
  "punchOutTime": "06:15:30 PM",  // Only set after punch-out
  "punchOutTimestamp": 1733758530000
}
```

### 🗄️ Database Fields Used

**Attendance Table - Punch-In Fields:**
- `punch_in` - Time of punch-in
- `punch_in_photo` - Path to punch-in photo
- `punch_in_latitude` - Punch-in location latitude
- `punch_in_longitude` - Punch-in location longitude
- `punch_in_accuracy` - GPS accuracy at punch-in

**Attendance Table - Punch-Out Fields:**
- `punch_out` - Time of punch-out
- `punch_out_photo` - Path to punch-out photo
- `punch_out_latitude` - Punch-out location latitude
- `punch_out_longitude` - Punch-out location longitude
- `punch_out_accuracy` - GPS accuracy at punch-out

### 🧪 Testing Steps

1. **Initial Load**
   - Page loads, button shows "Punch In"
   - Button is enabled and clickable

2. **First Punch-In**
   - Click "Punch In" button
   - Modal opens with title "Punch In - Capture Photo"
   - Capture photo
   - Click "Confirm & Punch In"
   - Wait for success message
   - Button now shows "Punch Out"
   - Button is still clickable

3. **Punch-Out**
   - Click "Punch Out" button
   - Modal opens with title "Punch Out - Capture Photo"
   - Modal shows punch-in time display
   - Capture photo
   - Click "Confirm & Punch Out"
   - Wait for success message
   - Button now shows "Punched In & Out"
   - Button is disabled with reduced opacity

4. **Refresh Page**
   - Refresh browser (Ctrl+R or Cmd+R)
   - Button state persists correctly based on punch data

5. **Next Day**
   - Manually change system date OR
   - Clear localStorage and test again
   - Button should reset to "Punch In"

### 🐛 Error Handling

- Network errors: Shows error toast with "Unable to connect to server"
- Timeout errors: Shows "Request timeout. Please check your connection"
- Server errors: Shows specific error message from backend
- Camera access denied: Shows alert and closes modal
- JSON parsing errors: Gracefully handles invalid data

### 🔐 Security Features

- Session validation on both endpoints
- Only owner's attendance can be updated
- POST method required for both endpoints
- Photo compression to 0.8 quality to reduce payload
- 30-second timeout on requests to prevent hanging
- Proper error logging without exposing sensitive data

### 📱 Mobile Compatibility

- Responsive camera modal
- Works with front and back camera
- Touch-friendly buttons
- Dynamic viewport height (100dvh)
- Geolocation works on mobile devices
- Photo data properly encoded for transmission

### 🎯 Key Features

✅ Once punched in, button CANNOT be changed back to "Punch In" without successful punch-out
✅ Punch-out ONLY works if punch-in was successful
✅ Different button states clearly indicate attendance status
✅ User cannot punch-in twice on same day
✅ Geolocation tracking for both punch-in and punch-out
✅ Photo capture with timestamp for audit trail
✅ Proper error handling and user feedback
✅ LocalStorage persistence across sessions
✅ Clean modal UI with status displays
