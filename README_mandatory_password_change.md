# Mandatory Password Change Feature

This feature forces users to change their password when they first log in or when their password has expired.

## Files Created/Modified

1. `mandatory_password_change.php` - Contains the modal HTML and CSS
2. `js/mandatory_password_change.js` - JavaScript for password validation and UI interactions
3. `password_change_handler.php` - Server-side logic for checking and updating passwords
4. `include_password_change.php` - Include file for adding the modal to dashboard pages
5. `password_change_db_update.sql` - SQL script to update the database schema
6. Modified dashboard files to include the password change modal

## Database Changes

The following columns have been added to the `users` table:

- `password_change_required` - TINYINT(1) flag to indicate if password change is required (1=yes, 0=no)
- `last_password_change` - DATETIME timestamp of the last password change

## How It Works

1. When a user logs in and is redirected to their dashboard, the system checks if they need to change their password
2. If a password change is required, a modal appears that cannot be dismissed
3. The user must enter their current password and a new password that meets security requirements
4. After successfully changing their password, the modal closes and they can use the system

## Password Requirements

The new password must:
- Be at least 8 characters long
- Contain at least one uppercase letter
- Contain at least one lowercase letter
- Contain at least one number
- Contain at least one special character

## Implementation Details

### When Password Change is Required

A password change is required in the following scenarios:
- For new users who have never changed their password
- When an admin has reset a user's password
- When a password has expired (90 days since the last change)
- When the `password_change_required` flag is manually set to 1

### Security Features

- The modal cannot be dismissed until a valid password is set
- Password strength is visually indicated to the user
- Current password verification prevents unauthorized changes
- All password requirements must be met before submission is allowed

## Installation

1. Run the `password_change_db_update.sql` script to update your database schema
2. Add the include file to all dashboard pages:
   ```php
   <?php include 'include_password_change.php'; ?>
   ```
3. Make sure jQuery is included in your dashboard pages

## Customization

You can customize the password policy by modifying:
- The validation rules in `js/mandatory_password_change.js`
- The server-side validation in `password_change_handler.php`
- The password expiration period (default is 90 days)

## Troubleshooting

If users are not being prompted to change their password:
1. Check that the include file is properly added to the dashboard page
2. Verify that the `password_change_required` flag is set to 1 for the user
3. Check for JavaScript errors in the browser console
4. Ensure jQuery is loaded before the password change script
