# Excel Import Feature Guide

## Overview
This feature allows users to import Excel files (.xls and .xlsx) and view their data in a tabular format directly in the browser. The import process is client-side, meaning the Excel file is processed in the browser without being uploaded to the server.

## Access
The Excel Import feature is available in the sidebar navigation under the "Excel Import" menu item. It is accessible to the following user roles:
- HR
- Admin
- Senior Manager (Studio)
- Senior Manager (Site)
- Site Manager

## How to Use

1. Navigate to the Excel Import page from the sidebar menu
2. Click the "Select Excel File" button
3. Choose an Excel file (.xls or .xlsx) from your computer
4. Click the "Upload and Process" button
5. The data from the Excel file will be displayed in a table format
6. (Optional) Click "Save to Database" to store the data in the system

A sample CSV file is available at `sample_data/sample_employees.csv` which can be opened in Excel and saved as an .xlsx file for testing purposes.

## Technical Details

### Database Setup
To enable the database saving functionality, you need to create the `imported_excel_data` table in your database.

#### Option 1: Manual Setup
Run the SQL script located at `sql/create_imported_data_table.sql` to create the required table.

#### Option 2: Automatic Setup (Admin Only)
Admin users can run the installation script at `install_excel_import.php` to automatically create the table.

### Client-Side Processing
The Excel import feature uses the SheetJS library (xlsx.full.min.js) to process Excel files directly in the browser. This approach:
- Ensures data privacy as files are not uploaded to the server
- Provides fast processing
- Works offline once the page is loaded

### Database Saving (Optional)
If the `imported_excel_data` table exists in the database, users can save the imported data to the database using the "Save to Database" button. This feature:
- Stores data with user tracking
- Preserves import timestamps
- Supports up to 10 columns of data

### Supported Formats
- Excel 97-2004 Workbook (.xls)
- Excel Workbook (.xlsx)

### Limitations
- Only the first worksheet of the Excel file is processed
- Very large files may cause browser performance issues
- Complex formatting is not preserved (only raw data)

## Troubleshooting

### File Not Processing
- Ensure the file is a valid Excel format (.xls or .xlsx)
- Check that the file is not corrupted
- Verify the file is not password protected

### Data Not Displaying Correctly
- Check that the first row contains headers
- Ensure data starts from the first column (A)
- Large files may take a moment to process

### Browser Compatibility
The feature works with modern browsers that support:
- FileReader API
- JavaScript ES6 features
- Fetch API

## Future Enhancements
Possible improvements for future versions:
- Support for multiple worksheet selection
- Server-side processing for very large files
- Data validation and error reporting
- Export options for the imported data
- Integration with existing database systems