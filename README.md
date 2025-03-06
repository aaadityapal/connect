# PHP CRM Web Application

A comprehensive Customer Relationship Management (CRM) System built with PHP, designed to help businesses manage their customer interactions, sales processes, and administrative tasks efficiently.

## 🌟 Features

- **User Authentication & Authorization**
  - Secure login system
  - Role-based access control (Admin, Staff, & encryption.)
  - Password encryption

- **Customer Management**
  - Add, edit, and delete customer profiles
  - Customer contact information tracking
  - Customer interaction history
  - Document management for customer files

- **Sales Management**
  - Lead tracking
  - Deal pipeline management
  - Sales reporting and analytics
  - Quote and invoice generation

- **Task Management**
  - Create and assign tasks
  - Task status tracking
  - Due date notifications
  - Task priority levels

- **Reporting System**
  - Generate detailed reports
  - Export data in various formats
  - Sales analytics dashboard
  - Performance metrics

## 🔧 Technologies Used

- PHP 7.4+
- MySQL/MariaDB
- HTML5
- CSS3
- JavaScript
- Bootstrap 5
- jQuery

## ⚙️ Installation

1. **Clone the repository**

```bash
git clone https://github.com/aaadityapal/connect.git
cd php-crm-app
```

2. **Database Setup**
- Create a new MySQL database
- Import the database schema from `database/crm_db.sql`
- Configure database connection in `config/database.php`

3. **Configuration**
- Copy `.env.example` to `.env`
- Update the following in your `.env` file:
  ```
  DB_HOST=your_database_host
  DB_USER=your_database_user
  DB_PASS=your_database_password
  DB_NAME=your_database_name
  ```

4. **Web Server Configuration**
- Configure your web server (Apache/Nginx) to point to the `public` directory
- Ensure mod_rewrite is enabled for Apache

5. **Install Dependencies**

```bash
composer install
npm install
```

## 🚀 Usage

1. Access the application through your web browser
2. Default admin credentials:
   - Username: admin@admin.com
   - Password: admin123
   (Remember to change these credentials after first login)

3. Start by:
   - Setting up user roles and permissions
   - Adding your team members
   - Creating customer profiles
   - Setting up your sales pipeline

## 🔐 Security Features

- Password Hashing
- SQL Injection Prevention
- XSS Protection
- CSRF Protection
- Session Management
- Input Validation

## 📁 Project Structure

- `public/` - Web root directory
- `src/` - PHP source code
- `templates/` - HTML templates
- `config/` - Configuration files
- `assets/` - Static assets


## 🔄 Database Schema

The application uses the following main tables:
- users
- customers
- sales
- tasks
- documents
- roles
- permissions

## 👥 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🤝 Support

For support, email aaadityapal69@gmail.com or create an issue in the repository.

## 🙏 Acknowledgments

- Bootstrap for the UI components
- PHP community for excellent documentation
- All contributors who helped in building this CRM



## 🔮 Future Enhancements

- Email integration
- Calendar management
- Mobile application
- API development
- Advanced reporting features
- Integration with third-party services

---

Made with ❤️ by [Aditya Pal](https://github.com/aaadityapal)
