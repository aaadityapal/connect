# Fix Summary: User Session Handling

## Issue
The overtime approval page was defaulting to user ID 21 instead of using the currently logged-in user's session data.

## Root Cause
The page was using a hardcoded default user ID for testing purposes, which bypassed the actual user authentication system.

## Changes Made

### 1. Updated `new_page.php`
- Modified the user ID retrieval logic to properly use `$_SESSION['user_id']` when available
- Removed the hardcoded default to user ID 21
- Added proper handling for cases when no user is logged in
- Updated the shift data display to show appropriate messages when no user/shift data is available
- Added HTML escaping for security

### 2. Verified `shift_functions.php`
- Confirmed that the shift functions properly handle cases where a user might not have a shift assigned
- Functions return default shift values when no specific shift is found

## Result
The page now correctly displays shift end time based on the logged-in user's session data, rather than defaulting to a hardcoded test user. When no user is logged in, appropriate messages are displayed instead of showing incorrect data.