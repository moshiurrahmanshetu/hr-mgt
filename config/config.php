<?php
/**
 * Core PHP HRM System Configuration File
 * Developed by Senior PHP Software Architect
 * 
 * This file handles global configurations, secure sessions, database credentials,
 * and security middleware settings. It automatically detects the system URL to
 * ensure seamless operation across XAMPP, WAMP, Laragon, or Shared Hosting.
 */

// 1. Error Reporting Configuration
// Set to 1 for Development, 0 for Production
define('APP_ENV', 'development'); 

if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// 2. Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'hrm_database');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// 3. Application Base URL Auto-Detection
// Resolves hostnames, subdirectory paths (e.g. XAMPP htdocs/hrm-system/)
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Extract subdirectory folder if not in root
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script_name));
    $base_dir = ($dir === '/') ? '' : $dir;
    
    define('BASE_URL', $protocol . $host . $base_dir);
}

// 4. Secure Session Lifecycle Configuration
// Prevents Session Hijacking and Session Fixation attacks
if (session_status() === PHP_SESSION_NONE) {
    // Session Cookie Settings for maximum security
    $cookieParams = [
        'lifetime' => 86400, // 24 Hours
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), // Secure only on HTTPS
        'httponly' => true, // Prevents JavaScript XSS access to Session ID
        'samesite' => 'Lax' // Protection against CSRF attacks
    ];
    
    // PHP 7.3+ compatibility check
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(
            $cookieParams['lifetime'],
            $cookieParams['path'] . '; samesite=' . $cookieParams['samesite'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }
    
    session_start();
}

// 5. Session Timeout Enforcement (30 Minutes of Inactivity)
define('SESSION_TIMEOUT_SECONDS', 1800); // 30 mins
if (isset($_SESSION['LAST_ACTIVITY'])) {
    if ((time() - $_SESSION['LAST_ACTIVITY']) > SESSION_TIMEOUT_SECONDS) {
        // Session expired, destroy and redirect to login
        session_unset();
        session_destroy();
        
        // Re-initialize for flash messages
        session_start();
        $_SESSION['flash_error'] = "Your session has expired due to inactivity. Please log in again.";
        header("Location: " . BASE_URL . "/index.php?route=login");
        exit();
    }
}
$_SESSION['LAST_ACTIVITY'] = time();

// 6. CSRF (Cross-Site Request Forgery) Token Helpers
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Returns the current CSRF Token for HTML Forms.
 * @return string
 */
function get_csrf_token(): string {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Verifies a submitted token against the stored Session Token.
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token): bool {
    if (!$token) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
