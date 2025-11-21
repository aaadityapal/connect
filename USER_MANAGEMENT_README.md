# User Management Admin Page

## Overview
A comprehensive admin page for managing user roles, positions, and designations.

## Features

### 1. **User List Display**
- Display all active users in a clean, organized table
- Shows: Username, Email, Employee ID, Position, Designation, Department, Role, Status
- Real-time search functionality
- Responsive design for mobile and desktop

### 2. **Search & Filter**
- Search by username, email, or employee ID
- Instant filtering as you type
- Easy access to find any user quickly

### 3. **Edit User Modal**
- Beautiful modal popup for editing user details
- Edit fields:
  - **Position**: User's position in the organization
  - **Designation**: Job title/designation
  - **Department**: Department name
  - **Role**: Select from predefined roles:
    - Admin
    - Manager
    - Purchase Manager
    - Employee
    - HR
    - Finance
    - Supervisor
  - **Status**: Active or Inactive

### 4. **User Information Display**
- View-only information in the modal:
  - User ID
  - Username
  - Email

### 5. **Data Management**
- Real-time table updates after saving changes
- Input validation on both client and server side
- XSS protection with HTML escaping
- Comprehensive error handling

## Files Created

### 1. `/admin_user_management.php`
Main admin page with:
- User listing in table format
- Search functionality
- Modal for editing users
- Real-time data binding

### 2. `/handlers/update_user_handler.php`
Backend handler with:
- User authentication check
- Input validation
- Database update logic
- Audit logging support
- Error handling

## Database Columns Updated
The following columns in the `users` table are managed through this interface:
- `position` - User's position
- `designation` - User's job designation
- `department` - User's department
- `role` - User's system role
- `status` - User's account status (active/inactive)
- `modified_at` - Timestamp of modification
- `updated_at` - Timestamp of update

## Security Features
✅ Session-based authentication
✅ Role-based access control (Admin only)
✅ XSS protection with HTML escaping
✅ SQL injection prevention with prepared statements
✅ CSRF protection (implement token if needed)
✅ Input validation on server side
✅ Audit logging for changes

## Usage

### Accessing the Page
1. Log in as an Admin user
2. Navigate to the User Management page
3. Use search to find specific users
4. Click "Edit" button to modify user details
5. Make changes in the modal
6. Click "Save Changes" to update

### Making Changes
1. Search for the user
2. Click the "Edit" button in the Actions column
3. Update Position, Designation, Department, Role, or Status
4. Click "Save Changes"
5. Changes are immediately reflected in the table

## Customization

### Adding More Roles
Edit `admin_user_management.php` line ~384 and `update_user_handler.php` line ~64:

```php
<option value="Your New Role">Your New Role</option>
```

And update the validation array:
```php
$validRoles = ['Admin', 'Manager', 'Your New Role', ...];
```

### Changing Table Columns
Modify the table structure in the HTML section to display different columns

### Custom Styling
All styles are embedded in the `<style>` tag for easy customization

## Error Handling
- User not found: Returns 404 error
- Missing required fields: Returns 400 error
- Unauthorized access: Returns 403 error
- Database errors: Returns 500 error with message

## Responsive Design
- Mobile: Stacked layout, optimized for small screens
- Tablet: Adjusted font sizes and padding
- Desktop: Full featured layout with all columns visible

## Performance
- Uses client-side filtering for instant search results
- Efficient data binding with minimal DOM manipulation
- Modal animations for smooth user experience

## Future Enhancements (Optional)
- Bulk user updates
- Export user list to CSV
- User creation/deletion
- Custom role management
- Advanced filtering with multiple criteria
- User activity history
- Permission management per role
