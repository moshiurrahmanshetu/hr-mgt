# HR Management System - Phase 3

This is Phase 3 of the HR Management System, building upon Phase 1 (foundation + authentication) and Phase 2 (Department & Designation management) to add full Employee Management.

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
   - First: `database/01_auth_schema.sql` (creates the authentication table structure)
   - Second: `database/seed_admin.sql` (inserts default admin user and roles)
   - Third: `database/02_department_designation_schema.sql` (creates department and designation tables with sample data)
   - Fourth: `database/03_employee_schema.sql` (creates employee table linked to users)

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

## Features Implemented

### Phase 1: Foundation & Authentication
- Secure login with email and password
- Password hashing using bcrypt
- Session management with session fixation prevention
- CSRF token protection on all forms
- Brute-force protection (5 failed attempts = 5-minute lockout)
- Activity logging for all user actions
- Role-based access control (admin/employee)
- Profile editing (name, email display)
- Password change with current password verification
- Avatar upload (JPG, PNG, WebP, max 2MB)
- Avatar deletion with default avatar fallback
- Collapsible sidebar with smooth transitions
- Role-based menu highlighting
- Responsive design for mobile devices

### Phase 2: Department & Designation Management
- Department management (CRUD operations)
- Designation management (CRUD operations)
- Soft-delete functionality (status toggle active/inactive)
- Department uniqueness validation
- Designation uniqueness within department
- Search and filter functionality
- Pagination (10 items per page)
- Dependency checking (cannot deactivate department with active designations)
- Role-based access (admin only for department/designation modules)
- Activity logging for all department/designation actions
- Sample seed data (5 departments, 5 designations)

### Phase 3: Employee Management
- Full employee management (CRUD operations)
- One-to-one linkage between users table and employees table
- PDO transactions for atomic employee creation (user + employee record)
- Auto-generated employee codes (EMP-000001 format) with race-condition protection
- Soft-delete functionality (deleted_at timestamp + user account deactivation)
- Employee reactivation capability
- Multi-section create/edit forms (Account, Job, Personal info)
- AJAX-powered designation dropdown filtering by department
- Separate password reset action in edit form
- Avatar upload/replacement with reusable helper function
- Real-time statistics on admin dashboard (total/active employees, departments)
- Real-time profile summary on employee dashboard (employee code, department, designation)
- Employee search, filter (by department/status), and pagination
- Dependency checking (cannot deactivate designation/department with active employees)
- Role-based access (admin only for employee module)
- Activity logging for all employee actions
- Placeholder sections for future Attendance/Leave/Payroll modules

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
    database.php                     -> PDO connection
    config.php                        -> App-wide constants
  /includes
    auth.php                          -> Authentication functions
    functions.php                     -> Helper functions
    csrf.php                          -> CSRF token functions
  /database
    01_auth_schema.sql                -> Authentication schema
    seed_admin.sql                     -> Default admin user
    02_department_designation_schema.sql -> Department/designation schema
    03_employee_schema.sql            -> Employee schema
  /assets
    /css/style.css                     -> Main stylesheet
    /js/main.js                        -> Main JavaScript
    /img (placeholder for avatars)
  /uploads
    /avatars                          -> Uploaded profile photos
  /modules
    /auth
      login.php
      logout.php
    /dashboard
      admin_dashboard.php
      employee_dashboard.php
    /profile
      profile.php
    /departments
      list.php                         -> Department list with search/filter
      create.php                       -> Add new department
      edit.php                         -> Edit department
      delete.php                       -> Toggle department status
    /designations
      list.php                         -> Designation list with search/filter
      create.php                       -> Add new designation
      edit.php                         -> Edit designation
      delete.php                       -> Toggle designation status
    /employees
      list.php                         -> Employee list with search/filter
      create.php                       -> Add new employee
      edit.php                         -> Edit employee
      view.php                         -> View employee profile
      delete.php                       -> Soft-delete/reactivate employee
      get_designations.php             -> AJAX endpoint for designation filtering
  /templates
    header.php
    sidebar.php
    footer.php
  index.php                             -> Entry point
  .htaccess                             -> Security rules
  README.md                             -> This file
```

## Phase 3 Testing Instructions

After importing the Phase 3 SQL file, test the following:

1. **Login as admin** and verify the sidebar shows "Employees", "Departments", and "Designations" links
2. **Create an employee**:
   - Navigate to Employees → Add Employee
   - Fill in Account Info (name, email, temporary password, optional photo)
   - Select Department and Designation (verify AJAX filtering works)
   - Fill in Job Info (joining date, employment status, basic salary)
   - Fill in optional Personal Info
   - Verify creation success, auto-generated employee code (EMP-000001 format), and activity log
3. **Login as the new employee**:
   - Use the temporary password
   - Verify login works
   - Verify employee dashboard shows real profile data (employee code, department, designation)
   - Verify employee sidebar does NOT show Employees/Departments/Designations links
4. **Test employee editing**:
   - Edit the employee's information
   - Replace the photo
   - Reset the password using the separate password reset action
   - Verify all changes persist
5. **Test employee soft-delete**:
   - Delete an employee (soft-delete)
   - Verify they disappear from the active list
   - Verify they cannot log in (account deactivated)
   - Reactivate the employee using the "Show Deleted" filter
   - Verify they can log in again
6. **Test dependency checks**:
   - Try to deactivate a designation with active employees (should fail with error)
   - Try to deactivate a department with active employees (should fail with error)
7. **Test admin dashboard**:
   - Verify real counts for Total Employees, Active Employees, and Departments
8. **Test search and filters**:
   - Search employees by name or employee code
   - Filter by department
   - Filter by employment status
   - Show/Hide deleted employees

## Next Steps (Future Phases)

Phase 4 will include:
- Attendance tracking module
- Leave management system
- Enhanced employee reporting

Phase 5 will include:
- Payroll management
- Advanced analytics and reporting

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
