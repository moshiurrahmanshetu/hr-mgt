# HR Management System - Phase 1

This is Phase 1 of the HR Management System, focusing on project foundation and authentication.

## System Requirements

- PHP 8.0 or higher
- MySQL/MariaDB
- XAMPP/WAMP/MAMP or similar PHP development environment
- Web server (Apache/Nginx)

## Installation Instructions

### 1. Database Setup

1. Open phpMyAdmin (usually at `http://localhost/phpmyadmin`)
2. Create a new database named `hr_system` with character set `utf8mb4` and collation `utf8mb4_unicode_ci`
3. Import the SQL files in this order:
   - First: `database/01_auth_schema.sql` (creates the table structure)
   - Second: `database/seed_admin.sql` (inserts default admin user and roles)

### 2. Configuration

The system is pre-configured for XAMPP with these default settings in `config/config.php`:
- Database Host: `localhost`
- Database Name: `hr_system`
- Database User: `root`
- Database Password: `` (empty)

If your MySQL credentials differ, update them in `config/config.php`.

### 3. File Permissions

Ensure the following directories are writable:
- `uploads/avatars/` (for profile picture uploads)

### 4. Access the Application

Open your web browser and navigate to:
```
http://localhost/hr-mgt/
```

This will automatically redirect you to the login page.

**Note:** If your web server is configured differently, you may need to update the `BASE_URL` in `config/config.php` to match your setup.

## Default Login Credentials

- **Email:** admin@hrsystem.com
- **Password:** Admin@123

## Features Implemented in Phase 1

### Authentication System
- Secure login with email and password
- Password hashing using bcrypt
- Session management with session fixation prevention
- CSRF token protection on all forms
- Brute-force protection (5 failed attempts = 5-minute lockout)
- Activity logging for all user actions
- Role-based access control (admin/employee)

### User Management
- Profile editing (name, email display)
- Password change with current password verification
- Avatar upload (JPG, PNG, WebP, max 2MB)
- Avatar deletion
- Default avatar fallback

### Dashboard
- Admin dashboard with placeholder statistics
- Employee dashboard with placeholder information
- Collapsible sidebar with smooth transitions
- Role-based menu highlighting
- Responsive design for mobile devices

### Security Features
- PDO prepared statements for all database queries
- Input validation and sanitization
- CSRF protection on all forms
- Session-based authentication
- File upload validation
- .htaccess protection for sensitive directories
- SQL injection prevention

### UI/UX
- Clean, professional "SaaS admin panel" design
- Neutral color palette with deep teal accent
- Bootstrap 5 for responsive layout
- Inter font for modern typography
- Smooth animations and transitions
- Mobile-responsive sidebar
- Clear success/error feedback messages

## File Structure

```
/hr-mgt
  /config
    database.php          -> PDO connection
    config.php             -> App-wide constants
  /includes
    auth.php                -> Authentication functions
    functions.php           -> Helper functions
    csrf.php                -> CSRF token functions
  /database
    01_auth_schema.sql      -> Database schema
    seed_admin.sql           -> Default admin user
  /assets
    /css/style.css          -> Main stylesheet
    /js/main.js             -> Main JavaScript
    /img (placeholder for avatars)
  /uploads
    /avatars                -> Uploaded profile photos
  /modules
    /auth
      login.php
      logout.php
    /dashboard
      admin_dashboard.php
      employee_dashboard.php
    /profile
      profile.php
  /templates
    header.php
    sidebar.php
    footer.php
  index.php                  -> Entry point
  .htaccess                  -> Security rules
  README.md                  -> This file
```

## Next Steps (Future Phases)

Phase 2 will include:
- Employee Management module
- Department Management module
- Enhanced permissions system

Phase 3 will include:
- Attendance tracking
- Leave management system

Phase 4 will include:
- Payroll management
- Reporting and analytics

## Troubleshooting

### Database Connection Error
- Verify MySQL credentials in `config/config.php`
- Ensure MySQL service is running in XAMPP
- Check that the `hr_system` database exists

### File Upload Issues
- Ensure `uploads/avatars/` directory exists and is writable
- Check PHP upload limits in php.ini (upload_max_filesize, post_max_size)

### Session Issues
- Verify session save path is writable
- Check cookie settings in your browser

### White Screen/500 Error
- Enable PHP error reporting in `config/config.php`:
  ```php
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  ```
- Check Apache error logs

## Development Notes

- All database queries use PDO prepared statements
- Passwords are never stored in plain text
- Sessions are regenerated on login to prevent fixation
- All form submissions validate CSRF tokens
- File uploads are validated for type and size
- The system follows security best practices

## License

This project is for educational/development purposes.
