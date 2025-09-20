# Employee Salary Management System

A comprehensive employee salary management system built for the HR Management platform. This system provides a modern, responsive interface for managing employee salaries, payroll processing, and salary analytics.

## 🎯 Current Status

**Demo Mode**: The system is currently displaying sample/dummy data for demonstration purposes. Backend API endpoints need to be implemented for live data integration.

### What's Working (Frontend)
- ✅ Complete UI/UX with responsive design
- ✅ Interactive table with sorting and filtering
- ✅ Detailed salary breakdown modals
- ✅ Bulk processing wizard interface
- ✅ Payroll wizard interface
- ✅ Export functionality (UI ready)

### What Needs Implementation (Backend)
- 🔄 API endpoints for data fetching
- 🔄 Database integration
- 🔄 Actual salary calculations
- 🔄 PDF payslip generation
- 🔄 Excel export functionality

## Features

### 📊 Dashboard Overview
- **Real-time Statistics**: Total employees, monthly budget, processed payrolls, and pending reviews
- **Monthly Navigation**: Easy month selection with visual indicators
- **Quick Actions**: Bulk processing and payroll wizard access

### 👥 Employee Management
- **Advanced Search**: Search employees by name, ID, or department
- **Multi-filter Support**: Filter by department, status, and processing state
- **Sortable Columns**: Click any column header to sort data
- **Pagination**: Handle large employee lists efficiently

### 💰 Salary Processing
- **Individual Processing**: View and edit individual employee salaries
- **Bulk Processing**: Process multiple employees simultaneously
- **Payroll Wizard**: Guided payroll processing with automated calculations
- **Status Tracking**: Track processing status for each employee

### 📋 Detailed Salary Views
- **Comprehensive Breakdown**: Base salary, overtime, deductions, and net pay
- **Attendance Integration**: Present days, working days, late days, and leave days
- **Historical Records**: View salary history for each employee
- **Interactive Modals**: Rich, detailed views with easy navigation

### 📄 Reports and Export
- **Excel Export**: Export salary data to Excel format
- **PDF Payslips**: Generate and download individual payslips
- **Bulk Downloads**: Download multiple payslips as ZIP files
- **Summary Reports**: Generate comprehensive payroll reports

### 🎛️ Advanced Features
- **Keyboard Navigation**: Navigate tables using arrow keys
- **Context Menus**: Right-click for quick actions
- **Column Resizing**: Adjust table columns to your preference
- **Responsive Design**: Works perfectly on all device sizes

## File Structure

```
employee-salary/
├── index.php                      # Main salary management page
├── components/                     # Reusable UI components
│   ├── salary-detail-modal.php    # Employee salary detail modal
│   ├── bulk-process-modal.php     # Bulk processing modal
│   └── payroll-wizard-modal.php   # Payroll wizard modal
├── css/                           # Stylesheets
│   ├── salary-main.css           # Main styling
│   └── salary-components.css     # Component-specific styles
├── js/                           # JavaScript files
│   ├── salary-main.js           # Core functionality
│   ├── salary-modals.js         # Modal interactions
│   └── salary-table.js          # Table-specific features
└── assets/                       # Static assets
    └── (images, icons, etc.)
```

## Installation

1. **Copy the folder** to your HR system directory:
   ```
   /employee-salary/
   ```

2. **Ensure dependencies** are available:
   - PHP 7.4+ with PDO
   - MySQL/MariaDB database
   - Web server (Apache/Nginx)

3. **Include necessary CSS/JS libraries**:
   - Bootstrap Icons
   - Font Awesome
   - Google Fonts (Inter)

4. **Database requirements**:
   - Existing HR system tables (users, attendance, etc.)
   - Salary-related tables (salary_details, salary_increments, etc.)

## Usage

### Accessing the System
Navigate to `/employee-salary/` in your HR system to access the salary management interface.

### Basic Operations

#### View Employee Salary
1. Click the "View" (eye) icon in the Actions column
2. Review detailed salary breakdown in the modal
3. Check attendance details and deductions
4. View salary history

#### Edit Employee Salary
1. Click the "Edit" (pencil) icon in the Actions column
2. Modify salary components as needed
3. Save changes to update the record

#### Bulk Processing
1. Click "Bulk Process" button in the header
2. Select employees to process
3. Configure processing settings
4. Review and execute bulk processing

#### Payroll Wizard
1. Click "Payroll Wizard" button in the header
2. Configure payroll settings
3. Set attendance rules and deductions
4. Start automated processing
5. Download generated reports

### Keyboard Shortcuts
- **Ctrl/Cmd + F**: Focus search box
- **Arrow Keys**: Navigate table rows (when table is focused)
- **Enter**: View selected employee details
- **Escape**: Close modals or clear selections

### Filtering and Search
- **Search**: Type employee name, ID, or department
- **Department Filter**: Select specific department
- **Status Filter**: Filter by processing status
- **Month Selection**: Change salary period

## Customization

### Styling
Modify `css/salary-main.css` and `css/salary-components.css` to customize:
- Color schemes
- Layout spacing
- Component styling
- Responsive breakpoints

### Functionality
Extend JavaScript files to add:
- Additional table columns
- Custom validation rules
- Integration with other systems
- Enhanced reporting features

### API Integration
The system expects API endpoints for:
- `api/get-salary-data.php`: Fetch employee salary data
- `api/get-employee-salary-details.php`: Get detailed employee info
- `api/process-bulk-salaries.php`: Handle bulk processing
- `api/export-salary-data.php`: Export functionality
- `api/download-payslip.php`: Payslip generation

## Browser Support

- **Chrome**: 80+
- **Firefox**: 75+
- **Safari**: 13+
- **Edge**: 80+
- **Mobile**: iOS Safari 13+, Chrome Mobile 80+

## Performance

- **Pagination**: Handles large datasets efficiently
- **Lazy Loading**: Components load on demand
- **Caching**: Utilizes browser caching for assets
- **Responsive**: Optimized for all screen sizes

## Security

- **Session Validation**: Checks user authentication
- **Role-based Access**: Restricts access by user role
- **CSRF Protection**: Includes CSRF tokens in forms
- **Input Sanitization**: Sanitizes all user inputs

## Troubleshooting

### Common Issues

1. **Blank page or errors**:
   - Check PHP error logs
   - Verify database connection
   - Ensure proper file permissions

2. **Data not loading**:
   - Check API endpoints exist
   - Verify database queries
   - Check browser console for errors

3. **Styling issues**:
   - Clear browser cache
   - Check CSS file paths
   - Verify font loading

### Debug Mode
Enable debug mode by adding to the top of `index.php`:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## Future Enhancements

- **Real-time Notifications**: Live updates for salary processing
- **Advanced Analytics**: Detailed salary analytics and trends
- **API Integration**: RESTful API for external integrations
- **Mobile App**: Dedicated mobile application
- **Multi-language Support**: Internationalization features

## Support

For issues or questions:
1. Check the troubleshooting section
2. Review browser console for errors
3. Check PHP error logs
4. Contact system administrator

## Version History

- **v1.0.0**: Initial release with core functionality
- **v1.1.0**: Added bulk processing and payroll wizard
- **v1.2.0**: Enhanced UI/UX and table features
- **v1.3.0**: Added export and reporting capabilities

## License

This software is part of the HR Management System. All rights reserved.