# WhatsApp Punch Notification Testing Guide

## 📋 Overview

This guide will help you test and debug the WhatsApp notification system for punch in/out functionality.

## 🔧 Test Files Created

### 1. **test_punch_notification.php** (Comprehensive Test Suite)
   - **URL**: `http://localhost/connect/test_punch_notification.php`
   - **Purpose**: Complete diagnostic tool with all checks
   - **Features**:
     - ✅ File inclusion verification
     - ✅ Database connection checks (MySQLi & PDO)
     - ✅ Function availability verification
     - ✅ User data validation
     - ✅ WhatsApp service configuration check
     - ✅ Recent attendance records
     - ✅ Error log analysis
     - ✅ Manual test interface

### 2. **test_simple_notification.php** (Quick Test)
   - **URL**: `http://localhost/connect/test_simple_notification.php?user_id=USER_ID`
   - **Purpose**: Quick, direct notification test
   - **Features**:
     - ✅ Simple user selection
     - ✅ Direct notification sending
     - ✅ Real-time log viewing
     - ✅ Clear success/failure indicators

## 🚀 How to Use

### Step 1: Run Comprehensive Test
1. Open your browser
2. Navigate to: `http://localhost/connect/test_punch_notification.php`
3. Review all diagnostic checks
4. Look for any ❌ (red X) indicators
5. Select a user from the table and click "Test Notification"

### Step 2: Run Simple Test
1. Navigate to: `http://localhost/connect/test_simple_notification.php`
2. Select a user from the list
3. Click on the user to send a test notification
4. Check the results and logs

### Step 3: Check WhatsApp Logs
The WhatsApp service logs are stored at:
```
/Applications/XAMPP/xamppfiles/htdocs/connect/whatsapp/whatsapp.log
```

You can view this file directly or through the test pages.

## 🔍 Common Issues & Solutions

### Issue 1: "PDO connection not available"
**Solution**: 
- Check `config/db_connect.php` is properly configured
- Verify database credentials are correct
- Ensure MySQL service is running

### Issue 2: "sendPunchNotification function not found"
**Solution**:
- Verify `whatsapp/send_punch_notification.php` exists
- Check the file is properly included in `punch.php`
- Look for PHP syntax errors in the notification file

### Issue 3: "No users found with phone numbers"
**Solution**:
- Add phone numbers to users in the database
- Format: International format with country code (e.g., +919876543210)
- Update users table: `UPDATE users SET phone = '+919876543210' WHERE id = 1;`

### Issue 4: "WhatsAppService error"
**Solution**:
- Check `whatsapp/WhatsAppService.php` exists
- Verify API credentials are correct (lines 8-9)
- Ensure the API URL is accessible
- Check if the access token is valid

### Issue 5: "Template not found"
**Solution**:
- Verify template `employee_punchin_attendance_update` is approved in Meta Business Suite
- Check template name spelling is exact
- Ensure template has 7 parameters configured

### Issue 6: API returns error
**Solution**:
- Check the WhatsApp log file for API response
- Verify phone number format (no + sign for Meta API)
- Ensure template parameters match the template structure
- Check API token hasn't expired

## 📊 What to Check in Logs

### WhatsApp Service Log (`whatsapp/whatsapp.log`)
Look for:
```
[2026-01-27 17:00:00] Sending template 'employee_punchin_attendance_update' to 919876543210
[2026-01-27 17:00:01] API Response: {"messaging_product":"whatsapp","contacts":[...],"messages":[...]}
```

**Success indicators**:
- HTTP 200 response
- `"messages"` array in response
- No error messages

**Error indicators**:
- HTTP 4xx or 5xx codes
- `"error"` object in response
- Missing or invalid parameters

### PHP Error Log
Look for:
```
WhatsApp Notification Error: ...
WhatsApp notification sent successfully for user ID: ...
```

## 🧪 Manual Testing Steps

### Test 1: Check Database Connection
```sql
-- Run in phpMyAdmin or MySQL console
SELECT id, username, phone FROM users WHERE phone IS NOT NULL LIMIT 5;
```

### Test 2: Check Attendance Records
```sql
SELECT * FROM attendance ORDER BY date DESC LIMIT 10;
```

### Test 3: Check User Shifts
```sql
SELECT us.*, s.shift_name, s.start_time, s.end_time 
FROM user_shifts us
JOIN shifts s ON us.shift_id = s.id
WHERE us.user_id = YOUR_USER_ID;
```

### Test 4: Check Monthly Statistics
```sql
SELECT 
    COUNT(*) as present_count 
FROM attendance 
WHERE user_id = YOUR_USER_ID 
AND MONTH(date) = MONTH(CURDATE()) 
AND YEAR(date) = YEAR(CURDATE()) 
AND punch_in IS NOT NULL;
```

## 📱 Phone Number Format

### Correct Formats:
- **For Meta API**: `919876543210` (no + sign)
- **In Database**: `+919876543210` or `919876543210`

### The code handles conversion automatically in `sendPunchNotification()`

## 🔐 WhatsApp API Configuration

### Required Settings in `WhatsAppService.php`:
1. **API URL** (Line 8): 
   ```php
   private $apiUrl = 'https://graph.facebook.com/v17.0/YOUR_PHONE_NUMBER_ID/messages';
   ```

2. **Access Token** (Line 9):
   ```php
   private $token = 'YOUR_ACCESS_TOKEN';
   ```

### How to Get These:
1. Go to [Meta Business Suite](https://business.facebook.com/)
2. Navigate to WhatsApp > API Setup
3. Copy the Phone Number ID
4. Generate a permanent access token
5. Update the values in `WhatsAppService.php`

## 📋 Template Structure

The template `employee_punchin_attendance_update` should have:

**Parameters (in order):**
1. {{1}} - Employee Name
2. {{2}} - Punch In Time
3. {{3}} - Date
4. {{4}} - Day
5. {{5}} - Present Days
6. {{6}} - Absent Days
7. {{7}} - Working Days

**Example Message:**
```
Hello {{1}},

Your punch in has been recorded at {{2}} on {{3}} ({{4}}).

Monthly Attendance Summary:
✅ Present: {{5}} days
❌ Absent: {{6}} days
📅 Working Days: {{7}} days

Thank you!
```

## 🎯 Expected Behavior

### When Punch In is Successful:
1. ✅ Attendance record created in database
2. ✅ WhatsApp notification function called
3. ✅ API request sent to Meta
4. ✅ User receives WhatsApp message
5. ✅ Success logged in `whatsapp.log`
6. ✅ Success logged in PHP error log

### When There's an Issue:
1. ⚠️ Attendance still recorded (fail-safe)
2. ⚠️ Error logged in `whatsapp.log`
3. ⚠️ Error logged in PHP error log
4. ⚠️ User doesn't receive WhatsApp message
5. ⚠️ Punch in still shows as successful to user

## 📞 Support Checklist

Before reporting an issue, verify:
- [ ] Database connection is working
- [ ] PDO connection is available
- [ ] User has a valid phone number
- [ ] WhatsApp API credentials are correct
- [ ] Template is approved in Meta Business Suite
- [ ] Access token is valid and not expired
- [ ] Phone number format is correct
- [ ] Test files show specific error messages
- [ ] Logs have been checked for errors

## 🔄 Next Steps

1. **Run the comprehensive test** to identify issues
2. **Check the logs** for specific error messages
3. **Verify API credentials** in WhatsAppService.php
4. **Test with a single user** using the simple test
5. **Review this guide** for solutions to common issues

## 📝 Notes

- The notification system is **fail-safe** - punch in will succeed even if WhatsApp fails
- All errors are logged for debugging
- Test files are safe to use in production (read-only operations)
- Remove test files after debugging is complete for security

---

**Created**: 2026-01-27
**Version**: 1.0
**Author**: Antigravity AI Assistant
