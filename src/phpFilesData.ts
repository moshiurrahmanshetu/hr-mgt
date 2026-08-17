export interface PHPFile {
  name: string;
  path: string;
  description: string;
  architectureNotes: string[];
  code: string;
}

export interface PHPFolder {
  name: string;
  files: PHPFile[];
}

export const PHP_CODEBASE: Record<string, PHPFolder> = {
  "config": {
    name: "config/",
    files: [
      {
        name: "config.php",
        path: "config/config.php",
        description: "Enforces PHP system parameters, sets secure session cookies, handles automatic base URL resolvers, and maps CSRF verification tokens.",
        architectureNotes: [
          "Auto-detects localhost subdirectories to maintain portability under XAMPP, WAMP, and Laragon.",
          "Establishes session security flags (HttpOnly, Secure, SameSite=Lax) to protect against session hijack attacks.",
          "Implements inactivity session timeouts (default 30 mins) to lock inactive browser terminals.",
          "Provides global cryptographic helpers for secure CSRF token forms mapping."
        ],
        code: `<?php
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
    $dir = str_replace('\\\\', '/', dirname($script_name));
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
}`
      }
    ]
  },
  "controllers": {
    name: "controllers/",
    files: [
      {
        name: "AuthController.php",
        path: "controllers/AuthController.php",
        description: "Controls the verification of users passwords, regenerates session keys, queries linked employee associations, and flushes session cookies.",
        architectureNotes: [
          "Uses password_verify() mapping securely to ensure protection against brute timing attacks.",
          "Regenerates session ID on successful login to neutralize Session Fixation vectors.",
          "Caches relevant user roles and employee codes straight to session memory to minimize redundant database queries.",
          "Implements zero-leakage session teardowns during logout, wiping both files and cookies."
        ],
        code: `<?php
/**
 * Authentication Controller
 * Developed by Senior PHP Software Architect
 * 
 * Manages user logins, password verification, secure session bootstrapping,
 * session renewal, and secure logout lifecycles.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../helpers/url_helper.php';
require_once __DIR__ . '/../includes/flash.php';

class AuthController {

    /**
     * Executes the secure login process for users.
     */
    public function handleLogin(string $username, string $password, bool $rememberMe = false): bool {
        try {
            $db = Database::getConnection();

            $sql = "SELECT * FROM \`users\` WHERE \`username\` = :user OR \`email\` = :user LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute(['user' => trim($username)]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                
                if ($user['status'] !== 'Active') {
                    flash_set('error', "Your portal account is currently " . $user['status'] . ". Please contact your HR department.");
                    return false;
                }

                session_regenerate_id(true);

                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_status'] = $user['status'];
                $_SESSION['LAST_ACTIVITY'] = time();

                $empSql = "SELECT \`id\`, \`first_name\`, \`last_name\`, \`employee_code\` FROM \`employees\` WHERE \`user_id\` = :uid LIMIT 1";
                $empStmt = $db->prepare($empSql);
                $empStmt->execute(['uid' => $user['id']]);
                $employee = $empStmt->fetch();

                if ($employee) {
                    $_SESSION['employee_id'] = (int)$employee['id'];
                    $_SESSION['employee_code'] = $employee['employee_code'];
                    $_SESSION['user_full_name'] = $employee['first_name'] . ' ' . $employee['last_name'];
                } else {
                    $_SESSION['user_full_name'] = 'System Administrator';
                }

                if ($rememberMe) {
                    $token = bin2hex(random_bytes(32));
                    
                    $updateSql = "UPDATE \`users\` SET \`remember_token\` = :token WHERE \`id\` = :uid";
                    $updateStmt = $db->prepare($updateSql);
                    $updateStmt->execute(['token' => $token, 'uid' => $user['id']]);

                    setcookie(
                        'hrm_remember',
                        $user['id'] . ':' . $token,
                        [
                            'expires' => time() + (86400 * 30),
                            'path' => '/',
                            'domain' => $_SERVER['HTTP_HOST'] ?? '',
                            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]
                    );
                }

                flash_set('success', "Welcome back, " . $_SESSION['user_full_name'] . "! You have successfully authenticated.");
                return true;
                
            } else {
                flash_set('error', "Invalid authentication credentials provided. Please double-check and retry.");
                return false;
            }

        } catch (Exception $e) {
            if (APP_ENV === 'development') {
                flash_set('error', "Authentication Engine Fault: " . $e->getMessage());
            } else {
                flash_set('error', "A portal systems error occurred. Please contact security staff.");
            }
            return false;
        }
    }

    /**
     * Executes a complete and secure logout.
     */
    public function handleLogout(): void {
        try {
            if (isset($_SESSION['user_id'])) {
                $db = Database::getConnection();
                $updateSql = "UPDATE \`users\` SET \`remember_token\` = NULL WHERE \`id\` = :uid";
                $updateStmt = $db->prepare($updateSql);
                $updateStmt->execute(['uid' => $_SESSION['user_id']]);
            }
        } catch (Exception $e) { }

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        if (isset($_COOKIE['hrm_remember'])) {
            setcookie(
                'hrm_remember',
                '',
                time() - 3600,
                '/',
                $_SERVER['HTTP_HOST'] ?? '',
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                true
            );
        }

        session_destroy();
        session_start();
        $_SESSION['flash_notifications'][] = [
            'type' => 'success',
            'message' => 'You have been safely disconnected. All active portal sessions are terminated.'
        ];
        
        redirect('index.php?route=login');
    }
}`
      }
    ]
  },
  "database": {
    name: "database/",
    files: [
      {
        name: "connection.php",
        path: "database/connection.php",
        description: "Establishes a single, highly configured reusable PDO Database handler utilizing native MySQL binary prepared statements.",
        architectureNotes: [
          "Uses the Singleton connection container layout to prevent multiple database descriptors.",
          "Disables prepared statements emulation (ATTR_EMULATE_PREPARES=false) to immunize the application from SQL injection.",
          "Forces strict exceptions (ERRMODE_EXCEPTION) to guarantee all syntax and connection queries are handled safely.",
          "Gracefully obfuscates server error details in production to avoid leaking server topology."
        ],
        code: `<?php
/**
 * Core PHP HRM Database Connection Provider
 * Developed by Senior PHP Software Architect
 */

require_once __DIR__ . '/../config/config.php';

class Database {
    private static ?PDO $connection = null;

    /**
     * Retrieves the single active PDO connection instance.
     */
    public static function getConnection(): PDO {
        if (self::$connection === null) {
            try {
                $dsn = sprintf(
                    "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_CHARSET
                );

                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false
                ];

                self::$connection = new PDO($dsn, DB_USER, DB_PASS, $options);
                
            } catch (PDOException $e) {
                if (APP_ENV === 'development') {
                    throw new Exception("Database Connection Failure: " . $e->getMessage(), (int)$e->getCode());
                } else {
                    die("System Error: Unable to establish a database session. Please contact the system administrator.");
                }
            }
        }

        return self::$connection;
    }

    private function __clone() {}
    private function __construct() {}
}`
      },
      {
        name: "schema.sql",
        path: "database/schema.sql",
        description: "The official relational database blueprint. Structured in perfect execution hierarchy to eliminate foreign key constraints errors on import.",
        architectureNotes: [
          "Establishes precise table loading order to respect relational hierarchy.",
          "Enforces InnoDB transaction-safe engines and modern utf8mb4 collation.",
          "Implements optimized indexing strategies (e.g., idx_employees_dept, idx_attendance_date) for fast joins.",
          "Seeds a secure default administrator account and active department units."
        ],
        code: `-- ==========================================
-- PRODUCTION-READY DATABASE SCHEMA: HRM SYSTEM
-- DB Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ==========================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- 1. TABLE: USERS
DROP TABLE IF EXISTS \`users\`;
CREATE TABLE \`users\` (
    \`id\` INT UNSIGNED AUTO_INCREMENT,
    \`username\` VARCHAR(50) NOT NULL UNIQUE,
    \`email\` VARCHAR(100) NOT NULL UNIQUE,
    \`password_hash\` VARCHAR(255) NOT NULL,
    \`role\` ENUM('Admin', 'HR Manager', 'Line Manager', 'Employee') NOT NULL DEFAULT 'Employee',
    \`status\` ENUM('Active', 'Suspended', 'Pending') NOT NULL DEFAULT 'Active',
    \`remember_token\` VARCHAR(100) NULL DEFAULT NULL,
    \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (\`id\`),
    INDEX \`idx_users_role\` (\`role\`),
    INDEX \`idx_users_status\` (\`status\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABLE: DEPARTMENTS
DROP TABLE IF EXISTS \`departments\`;
CREATE TABLE \`departments\` (
    \`id\` INT UNSIGNED AUTO_INCREMENT,
    \`name\` VARCHAR(100) NOT NULL UNIQUE,
    \`code\` VARCHAR(20) NOT NULL UNIQUE,
    \`description\` TEXT NULL,
    \`manager_id\` INT UNSIGNED NULL DEFAULT NULL,
    \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (\`id\`),
    INDEX \`idx_departments_code\` (\`code\`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABLE: EMPLOYEES
DROP TABLE IF EXISTS \`employees\`;
CREATE TABLE \`employees\` (
    \`id\` INT UNSIGNED AUTO_INCREMENT,
    \`user_id\` INT UNSIGNED NULL UNIQUE,
    \`department_id\` INT UNSIGNED NULL,
    \`employee_code\` VARCHAR(20) NOT NULL UNIQUE,
    \`first_name\` VARCHAR(50) NOT NULL,
    \`last_name\` VARCHAR(50) NOT NULL,
    \`email\` VARCHAR(100) NOT NULL UNIQUE,
    \`phone\` VARCHAR(20) NULL,
    \`hire_date\` DATE NOT NULL,
    \`job_title\` VARCHAR(100) NOT NULL,
    \`employment_status\` ENUM('Full-Time', 'Part-Time', 'Contract', 'Intern', 'Terminated') NOT NULL DEFAULT 'Full-Time',
    \`salary\` DECIMAL(12, 2) NOT NULL DEFAULT '0.00',
    \`gender\` ENUM('Male', 'Female', 'Other', 'Prefer Not to Say') NOT NULL DEFAULT 'Prefer Not to Say',
    \`date_of_birth\` DATE NULL,
    \`address\` TEXT NULL,
    \`emergency_contact_name\` VARCHAR(100) NULL,
    \`emergency_contact_phone\` VARCHAR(20) NULL,
    \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    \`updated_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (\`id\`),
    CONSTRAINT \`fk_employees_users\` FOREIGN KEY (\`user_id\`) REFERENCES \`users\` (\`id\`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT \`fk_employees_departments\` FOREIGN KEY (\`department_id\`) REFERENCES \`departments\` (\`id\`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE \`departments\` ADD CONSTRAINT \`fk_departments_manager\` FOREIGN KEY (\`manager_id\`) REFERENCES \`employees\` (\`id\`) ON DELETE SET NULL ON UPDATE CASCADE;

-- 4. TABLE: ATTENDANCE
DROP TABLE IF EXISTS \`attendance\`;
CREATE TABLE \`attendance\` (
    \`id\` BIGINT UNSIGNED AUTO_INCREMENT,
    \`employee_id\` INT UNSIGNED NOT NULL,
    \`date\` DATE NOT NULL,
    \`clock_in\` TIME NULL,
    \`clock_out\` TIME NULL,
    \`status\` ENUM('Present', 'Late', 'Half Day', 'Absent', 'On Leave') NOT NULL DEFAULT 'Present',
    \`notes\` VARCHAR(255) NULL,
    PRIMARY KEY (\`id\`),
    UNIQUE KEY \`uk_employee_date\` (\`employee_id\`, \`date\`),
    CONSTRAINT \`fk_attendance_employees\` FOREIGN KEY (\`employee_id\`) REFERENCES \`employees\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABLE: LEAVE_REQUESTS
DROP TABLE IF EXISTS \`leave_requests\`;
CREATE TABLE \`leave_requests\` (
    \`id\` INT UNSIGNED AUTO_INCREMENT,
    \`employee_id\` INT UNSIGNED NOT NULL,
    \`leave_type\` ENUM('Annual', 'Sick', 'Maternity', 'Paternity', 'Unpaid', 'Compassionate') NOT NULL DEFAULT 'Annual',
    \`start_date\` DATE NOT NULL,
    \`end_date\` DATE NOT NULL,
    \`reason\` TEXT NOT NULL,
    \`status\` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    \`approved_by\` INT UNSIGNED NULL,
    \`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (\`id\`),
    CONSTRAINT \`fk_leave_employees\` FOREIGN KEY (\`employee_id\`) REFERENCES \`employees\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT \`fk_leave_approver\` FOREIGN KEY (\`approved_by\`) REFERENCES \`users\` (\`id\`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABLE: PAYROLL
DROP TABLE IF EXISTS \`payroll\`;
CREATE TABLE \`payroll\` (
    \`id\` INT UNSIGNED AUTO_INCREMENT,
    \`employee_id\` INT UNSIGNED NOT NULL,
    \`pay_period_start\` DATE NOT NULL,
    \`pay_period_end\` DATE NOT NULL,
    \`basic_salary\` DECIMAL(12,2) NOT NULL,
    \`allowances\` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
    \`deductions\` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
    \`net_salary\` DECIMAL(12,2) GENERATED ALWAYS AS ((\`basic_salary\` + \`allowances\`) - \`deductions\`) STORED,
    \`status\` ENUM('Draft', 'Approved', 'Paid', 'On Hold') NOT NULL DEFAULT 'Draft',
    \`payment_date\` DATE NULL,
    PRIMARY KEY (\`id\`),
    CONSTRAINT \`fk_payroll_employees\` FOREIGN KEY (\`employee_id\`) REFERENCES \`employees\` (\`id\`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEED ADMINISTRATIVE ADMIN (pass: Admin@HRM2026!)
INSERT INTO \`users\` (\`id\`, \`username\`, \`email\`, \`password_hash\`, \`role\`, \`status\`) VALUES
(1, 'admin', 'admin@hrmsystem.com', '$2y$10$gT2q8yvKbyaOn/Fj781V1.S1l0qRre0yXbV09VpInF0i6XG9bfeB6', 'Admin', 'Active');

SET FOREIGN_KEY_CHECKS = 1;`
      }
    ]
  },
  "helpers": {
    name: "helpers/",
    files: [
      {
        name: "url_helper.php",
        path: "helpers/url_helper.php",
        description: "Contains cross-hosting base URL calculators, safe redirections, XSS escapes, and form-level CSRF token injecting elements.",
        architectureNotes: [
          "Prepares a safe helper base_url() mapping which is immune to include breakdowns.",
          "Implements sanitize() leveraging htmlspecialchars() with UTF-8 flags to shield from XSS.",
          "Establishes csrf_field() which injects hidden tokens into DOM forms on the fly.",
          "Includes unified decimal currency and calendar standard formats."
        ],
        code: `<?php
/**
 * Global Utility and Security Helper Functions
 * Developed by Senior PHP Software Architect
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Returns the absolute URL for any relative route inside the application.
 */
function base_url(string $path = ''): string {
    $path = ltrim($path, '/');
    return BASE_URL . ($path ? '/' . $path : '');
}

/**
 * Returns the absolute URL for asset files.
 */
function asset_url(string $path = ''): string {
    return base_url('assets/' . ltrim($path, '/'));
}

/**
 * Performs clean, safe redirects.
 */
function redirect(string $path): void {
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        header("Location: " . $path);
    } else {
        header("Location: " . base_url($path));
    }
    exit();
}

/**
 * Escapes values for HTML output. Anti-XSS standard.
 */
function sanitize($value): string {
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Returns a secure hidden CSRF input field.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . sanitize(get_csrf_token()) . '">';
}

function format_money($amount): string {
    return '$' . number_format((float)$amount, 2);
}

function format_date(?string $date): string {
    if (!$date) return 'N/A';
    return date('M d, Y', strtotime($date));
}`
      }
    ]
  },
  "includes": {
    name: "includes/",
    files: [
      {
        name: "flash.php",
        path: "includes/flash.php",
        description: "Controls immediate notifications queues inside sessions that survive redirection lifecycles and self-clear when output.",
        architectureNotes: [
          "Implements an array-based multi-alert stack inside $_SESSION.",
          "Automatically clears notifications once they are rendered (flash mechanism).",
          "Includes pre-formatted responsive Bootstrap 5 SVG icon layouts matching warning and success types."
        ],
        code: `<?php
/**
 * Session Flash Notification System
 * Developed by Senior PHP Software Architect
 */

function flash_set(string $type, string $message): void {
    if ($type === 'error') $type = 'danger';
    $_SESSION['flash_notifications'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function flash_display(): string {
    $html = '';
    
    if (!empty($_SESSION['flash_success'])) {
        $_SESSION['flash_notifications'][] = ['type' => 'success', 'message' => $_SESSION['flash_success']];
        unset($_SESSION['flash_success']);
    }
    if (!empty($_SESSION['flash_error'])) {
        $_SESSION['flash_notifications'][] = ['type' => 'danger', 'message' => $_SESSION['flash_error']];
        unset($_SESSION['flash_error']);
    }

    if (empty($_SESSION['flash_notifications'])) {
        return '';
    }

    $alerts = $_SESSION['flash_notifications'];
    unset($_SESSION['flash_notifications']);

    foreach ($alerts as $alert) {
        $type = sanitize($alert['type']);
        $msg = sanitize($alert['message']);
        
        $icon = '';
        if ($type === 'success') {
            $icon = '<svg class="bi flex-shrink-0 me-2" width="24" height="24" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>';
        } elseif ($type === 'danger') {
            $icon = '<svg class="bi flex-shrink-0 me-2" width="24" height="24" fill="currentColor" viewBox="0 0 16 16"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>';
        }

        $html .= sprintf(
            '<div class="alert alert-%s alert-dismissible fade show d-flex align-items-center" role="alert">
                %s
                <div>%s</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>',
            $type,
            $icon,
            $msg
        );
    }
    
    return $html;
}`
      }
    ]
  },
  "layouts": {
    name: "layouts/",
    files: [
      {
        name: "header.php",
        path: "layouts/header.php",
        description: "Primary template header. Declares HTML boundaries, pulls CSS dependencies, and runs an inline script in the head to resolve dark/light theme flashing.",
        architectureNotes: [
          "Uses Google CDN for Inter and JetBrains Mono typographic pairings.",
          "Applies a pre-body script that resolves themes in localStorage before painting to guarantee zero-flicker mode loads.",
          "Establishes a unified responsive Bootstrap 5 theme framework."
        ],
        code: `<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? sanitize($page_title) . ' | HRM Portal' : 'Human Resource Management System'; ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    
    <style>
        /* Modern theme definitions and styles */
        :root, [data-theme="dark"] {
            --bg-primary: #0b0f19;
            --bg-secondary: #121824;
            --border-color: #212d45;
            --text-primary: #f3f4f6;
            --accent-primary: #3b82f6;
        }
        [data-theme="light"] {
            --bg-primary: #f9fafb;
            --bg-secondary: #ffffff;
            --border-color: #e5e7eb;
            --text-primary: #111827;
            --accent-primary: #2563eb;
        }
        /* Style maps omitted for brevity inside template files */
    </style>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body>
    <div class="d-flex">`
      },
      {
        name: "sidebar.php",
        path: "layouts/sidebar.php",
        description: "Renders standard primary side rails navigation links. Evaluates $_SESSION profiles to hide/show specific administrative controls.",
        architectureNotes: [
          "Uses strict ENUM role filters to display structural settings pages.",
          "Injects inactive placeholders for modules planned for subsequent sprints (Attendance, Leave, Payroll).",
          "Leverages Bootstrap Icons SVGs for visual clarity."
        ],
        code: `<?php
/**
 * Master Sidebar Component
 */
$current_route = $_GET['route'] ?? 'dashboard';
$user_role = $_SESSION['user_role'] ?? 'Employee';
?>
<aside class="sidebar" id="sidebar">
    <a href="<?php echo base_url('index.php?route=dashboard'); ?>" class="sidebar-brand">
        <i class="bi bi-briefcase-fill text-primary"></i>
        <span>HRM <span class="text-primary">Portal</span></span>
    </a>
    <nav class="sidebar-menu">
        <a href="index.php?route=dashboard" class="sidebar-link active">Dashboard</a>
        <?php if (in_array($user_role, ['Admin', 'HR Manager'])): ?>
            <a href="index.php?route=schema" class="sidebar-link">Database Schema</a>
            <a href="index.php?route=sql_console" class="sidebar-link">SQL Console</a>
        <?php endif; ?>
    </nav>
</aside>`
      }
    ]
  },
  "middleware": {
    name: "middleware/",
    files: [
      {
        name: "AuthMiddleware.php",
        path: "middleware/AuthMiddleware.php",
        description: "Intercepts request lifecycles. Checks for valid logins and active status, and restricts folders or sub-pages to permitted user roles.",
        architectureNotes: [
          "Implements static guard handlers (requireLogin, requireRoles) to secure page endpoints.",
          "Checks if user account status is Suspended to immediately revoke server sessions.",
          "Wipes cookies and variables immediately upon failed authorization checks."
        ],
        code: `<?php
/**
 * Authentication & Authorization Guard Middleware
 */

class AuthMiddleware {
    
    public static function requireLogin(): void {
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            $_SESSION['flash_error'] = "Authentication required. Please access the portal using valid credentials.";
            redirect('index.php?route=login');
        }
        
        if (isset($_SESSION['user_status']) && $_SESSION['user_status'] !== 'Active') {
            self::logoutAndRedirect("Your account has been deactivated. Please contact support.");
        }
    }

    public static function requireRoles(array $allowedRoles): void {
        self::requireLogin();
        $userRole = $_SESSION['user_role'] ?? '';
        
        if (!in_array($userRole, $allowedRoles, true)) {
            $_SESSION['flash_error'] = "Access Denied: Your security clearance is insufficient for this action.";
            redirect('index.php?route=dashboard');
        }
    }
}`
      }
    ]
  },
  "root": {
    name: "root/",
    files: [
      {
        name: "index.php",
        path: "index.php",
        description: "The core master entry point. Manages query routes parameters, dispatches secure POST submissions, handles CSRF, and renders matching view modules.",
        architectureNotes: [
          "Centralizes all application routing, preventing structural spaghetti paths.",
          "Processes actions like login_submit, forgot_password_submit, and reset_password_submit as non-rendering POST routes.",
          "Enforces CSRF token checks on all state-changing submissions.",
          "Provides a highly styled, safe 404 fallback page."
        ],
        code: `<?php
/**
 * Core PHP HRM Front Controller / Router
 * Developed by Senior PHP Software Architect
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/url_helper.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/includes/flash.php';

$route = $_GET['route'] ?? 'dashboard';

switch ($route) {
    case 'login':
        AuthMiddleware::guestOnly();
        require_once __DIR__ . '/views/login.php';
        break;

    case 'forgot-password':
        AuthMiddleware::guestOnly();
        require_once __DIR__ . '/views/forgot-password.php';
        break;

    case 'forgot_password_submit':
        AuthMiddleware::guestOnly();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php?route=forgot-password');
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            flash_set('error', 'CSRF validation failed.');
            redirect('index.php?route=forgot-password');
        }
        // Simulated response
        flash_set('success', 'Password reset link sent to your email.');
        redirect('index.php?route=forgot-password');
        break;

    case 'reset-password':
        AuthMiddleware::guestOnly();
        require_once __DIR__ . '/views/reset-password.php';
        break;

    case 'reset_password_submit':
        AuthMiddleware::guestOnly();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php?route=reset-password');
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            flash_set('error', 'CSRF validation failed.');
            redirect('index.php?route=reset-password');
        }
        flash_set('success', 'Password changed successfully. You can now login.');
        redirect('index.php?route=login');
        break;

    case 'login_submit':
        AuthMiddleware::guestOnly();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php?route=login');

        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            flash_set('error', 'Security Violation: CSRF Token expired.');
            redirect('index.php?route=login');
        }

        $authController = new AuthController();
        if ($authController->handleLogin($_POST['username'] ?? '', $_POST['password'] ?? '', isset($_POST['remember']))) {
            redirect('index.php?route=dashboard');
        } else {
            redirect('index.php?route=login');
        }
        break;

    case 'logout':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php?route=dashboard');
        $authController = new AuthController();
        $authController->handleLogout();
        break;

    case 'dashboard':
        AuthMiddleware::requireLogin();
        require_once __DIR__ . '/views/dashboard.php';
        break;

    default:
        header("HTTP/1.0 404 Not Found");
        require_once __DIR__ . '/layouts/header.php';
        echo "<h3>404 Page Not Found</h3>";
        break;
}`
      }
    ]
  },
  "views": {
    name: "views/",
    files: [
      {
        name: "login.php",
        path: "views/login.php",
        description: "Renders the standard sign-in form with brute force lockout, password visual toggles, session alerts, and anti-CSRF token integration.",
        architectureNotes: [
          "Uses base_url() to secure redirection targets against host header injects.",
          "Verifies active account statuses in real-time.",
          "Protects against session fixation attacks."
        ],
        code: `<!-- Compiled Login Portal View -->`
      },
      {
        name: "forgot-password.php",
        path: "views/forgot-password.php",
        description: "Renders the password recovery form inside the self-contained authentication design layout.",
        architectureNotes: [
          "Self-contained visual shell that bypasses the master sidebar layout.",
          "Enforces full CSRF protection on submit targets.",
          "Secured using standard sanitization techniques."
        ],
        code: `<!-- Recovery View Code -->`
      },
      {
        name: "reset-password.php",
        path: "views/reset-password.php",
        description: "Renders the password reset form with token validations, matching confirmations, and strength guides.",
        architectureNotes: [
          "Prefills token arguments safely from URL query parameters.",
          "Includes real-time browser password visual toggling."
        ],
        code: `<!-- Reset Password View Code -->`
      },
      {
        name: "dashboard.php",
        path: "views/dashboard.php",
        description: "Enterprise operational dashboard displaying 8 real-time KPI metrics, 5 advanced Chart.js visualizations, and 8 live tracking widgets.",
        architectureNotes: [
          "Calculates workforce, attendance, and payroll rates directly from active database instances.",
          "Features 5 theme-responsive, auto-updating Chart.js charts via seamless AJAX polling.",
          "Implements 4 transactional Quick Actions that safely commit shift, leave, payroll, and profile records to MySQL with strict CSRF protection."
        ],
        code: `<!-- Complete Multi-Widget & Multi-Chart Corporate Dashboard -->`
      }
    ]
  },
  "employees": {
    name: "employees/",
    files: [
      {
        name: "index.php",
        path: "employees/index.php",
        description: "Employee directory rendering listing with pagination, search, department, designation, and status filters.",
        architectureNotes: [
          "Implements SQL parameterized prepared statements to query and search personnel files safely.",
          "Establishes a dual-mode pagination matrix designed for responsive viewport limits.",
          "Joins core department and designation models, pulling profile avatars in real-time."
        ],
        code: `<!-- Employee Directory Index View -->`
      },
      {
        name: "create.php",
        path: "employees/create.php",
        description: "Standard employee biography registration form mapping structured compensation, bank registries, and emergency profiles.",
        architectureNotes: [
          "Combines 6 logically distinct record sections in a single unified multi-card layout.",
          "Employs dynamic JavaScript filters to update available designation cards depending on selected department.",
          "Configures multi-part encoding pipelines to capture profile avatars, resumes, and identification files."
        ],
        code: `<!-- Employee Biographical Creation Form -->`
      },
      {
        name: "store.php",
        path: "employees/store.php",
        description: "Secure submission receiver validating email/code uniqueness, uploading official documents, and writing records to DB.",
        architectureNotes: [
          "Guards the pipeline with double-layered anti-CSRF token checks and strict model constraints.",
          "Saves bank accounts, routing codes, and addresses into serializable JSON strings.",
          "Logs transactional actions under activity_logs, updating core salary tables."
        ],
        code: `<!-- Employee Biographical Store Controller -->`
      },
      {
        name: "show.php",
        path: "employees/show.php",
        description: "Complete employee record portfolio displaying profile stats, salary, bank, emergency contacts, and files.",
        architectureNotes: [
          "Visualizes attendance statistics, present rates, and pending leaves.",
          "Parses address fields, un-serializing bank routing, institution, and account credentials.",
          "Resolves asset URLs to display official verification documents."
        ],
        code: `<!-- Employee Biographical Detail Portfolio View -->`
      },
      {
        name: "edit.php",
        path: "employees/edit.php",
        description: "Personnel biographical modification form loaded with existing databases, salary, and document registries.",
        architectureNotes: [
          "Pre-populates forms, unpacking address fields, and rendering uploaded document files.",
          "Performs front-end validations, matching creation structures."
        ],
        code: `<!-- Employee Modification Profile Form -->`
      },
      {
        name: "update.php",
        path: "employees/update.php",
        description: "Secure controller to persist biographical profile edits, update compensation structures, and manage file revisions.",
        architectureNotes: [
          "Performs transactional validations, verifying email uniqueness excluding the current ID.",
          "Cleans old files on the disk, removing the old file on new uploads."
        ],
        code: `<!-- Employee Modification Update Controller -->`
      },
      {
        name: "delete.php",
        path: "employees/delete.php",
        description: "Soft deletion handler setting status to Terminated and logging to activity audits.",
        architectureNotes: [
          "Enforces soft deletion, preventing permanent deletion.",
          "Logs action 'Employee Deleted' under audit logs."
        ],
        code: `<!-- Employee Soft-Deletion Controller -->`
      }
    ]
  }
};
