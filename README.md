# HR System - Company Statistics Dashboard

This module provides a comprehensive company statistics dashboard for the HR system, featuring income tracking and manager payout management.

## Features

- **Company Income Table**: View and filter income data by month and year
- **Manager Payout Management**: Track manager commissions and payments
- **Payment Processing**: Process payments to managers with detailed tracking
- **Real-time Data**: All data is fetched from the database in real-time

## Setup Instructions

### Database Setup

1. Import the database schema and sample data:
   ```
   mysql -u root -p < hr_database.sql
   ```

2. Database connection is handled by the `config/db_connect.php` file, which is already configured with:
   - Database: `crm`
   - Username: `root`
   - Password: `` (empty)
   - Host: `localhost`

### File Structure

- `company_stats.php` - Main dashboard page
- `get_income_data.php` - API endpoint to fetch income data
- `get_manager_data.php` - API endpoint to fetch manager data
- `process_payment.php` - API endpoint to process payments
- `hr_database.sql` - Database schema and sample data
- `config/db_connect.php` - Database connection configuration

### Usage

1. Navigate to `http://localhost/hr/company_stats.php` in your browser
2. Use the filters to view data for different months, years, and departments
3. Process payments using the "Pay Amount" button on manager cards

## Data Structure

### Project Payouts Table

- `id` - Unique identifier
- `project_name` - Name of the project
- `project_type` - Type of project (architecture, interior, construction)
- `client_name` - Name of the client
- `project_date` - Date of the payment
- `amount` - Payment amount
- `payment_mode` - Mode of payment (bank transfer, check, cash, UPI)
- `project_stage` - Stage of the project (1-10)
- `manager_id` - ID of the manager
- `notes` - Additional notes
- `remaining_amount` - Remaining amount to be paid
- `created_at` - Record creation timestamp
- `updated_at` - Record update timestamp

### Managers Table

- `id` - Unique identifier
- `name` - Manager's name
- `initials` - Manager's initials (for display)
- `color` - Color code for UI elements
- `department` - Department (architecture, interior, construction)
- `status` - Manager status (active, inactive)
- `fixed_remuneration` - Fixed monthly remuneration
- `created_at` - Record creation timestamp
- `updated_at` - Record update timestamp

## Development

To extend this system:

1. Add new features to the company_stats.php file
2. Create new API endpoints as needed
3. Update the database schema in hr_database.sql

## Notes

- The system uses Bootstrap 5 for UI components
- All monetary values are in Indian Rupees (₹)
- The system is designed to work with XAMPP/Apache server 