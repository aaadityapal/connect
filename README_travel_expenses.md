# Travel Expenses Tracker

This is a web-based travel expenses tracking system that allows users to record, manage, and track their travel expenses. The system includes features for adding, editing, and deleting expenses, as well as filtering and summarizing expense data.

## Features

- User authentication and session management
- Add, edit, and delete travel expenses
- Categorize expenses by transportation mode
- Filter expenses by status, date range, and month
- Summary statistics and visualizations
- Modal-based expense entry form
- Responsive design for desktop and mobile

## Installation

1. Clone or download the repository to your XAMPP htdocs directory
2. Import the database schema using the provided SQL script
3. Configure the database connection in `config/db_connect.php`
4. Access the application through your web browser

## Database Setup

1. Create a database named `crm` in your MySQL server
2. Import the `setup_travel_expenses_table.sql` file to create the necessary tables
3. Make sure the `users` table exists with appropriate user accounts

## Usage

1. Log in to the system with your user credentials
2. Navigate to the Travel Expenses page
3. Use the "Add Travel Expenses" button to add new expenses
4. Fill in the required details in the modal form
5. View your expenses in the list and use filters as needed
6. The summary cards at the top provide an overview of your expenses

## Technical Details

- PHP 7.4+ with PDO for database operations
- MySQL/MariaDB database
- Bootstrap 4.5 for UI components
- jQuery for DOM manipulation
- AJAX for asynchronous data operations
- Responsive design with CSS Grid and Flexbox

## File Structure

- `std_travel_expenses.php` - Main travel expenses page
- `config/db_connect.php` - Database connection configuration
- `css/supervisor/travel-expense-modal.css` - CSS styles for the expense modal
- `js/supervisor/travel-expense-modal.js` - JavaScript for the expense modal functionality

## Security Features

- Session-based authentication
- PDO prepared statements to prevent SQL injection
- Input validation and sanitization
- User-specific data access controls

## Troubleshooting

If you encounter issues with the travel expenses tracker:

1. Check that the database connection is properly configured
2. Ensure all required tables exist in the database
3. Verify that the user has appropriate permissions
4. Check browser console for JavaScript errors
5. Look for PHP errors in the server logs

## License

This project is licensed under the MIT License - see the LICENSE file for details. 