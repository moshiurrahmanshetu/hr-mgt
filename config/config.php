<?php
// Output buffering as safety net for header() calls
ob_start();

// Enable full error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// App-wide configuration constants

// Base URL - adjust this to match your server setup
// If accessing via http://localhost/hr-mgt/, use: '/hr-mgt'
// If accessing via http://localhost/ (with hr-mgt in htdocs), use: '/hr-mgt'
define('BASE_URL', '/hr-mgt');

// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'hr_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Session settings
define('SESSION_LIFETIME', 3600); // 1 hour in seconds
define('SESSION_NAME', 'HR_SYSTEM_SESSION');

// Security settings
define('CSRF_TOKEN_NAME', 'csrf_token');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 300); // 5 minutes in seconds

// File upload settings
define('MAX_AVATAR_SIZE', 2 * 1024 * 1024); // 2MB in bytes
define('ALLOWED_AVATAR_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// Timezone
date_default_timezone_set('UTC');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
