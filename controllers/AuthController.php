<?php
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
     * Initializes the login attempts table if not present.
     */
    private function initializeLoginAttemptsTable($db): void {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS `login_attempts` (
                `id` INT UNSIGNED AUTO_INCREMENT,
                `ip_address` VARCHAR(45) NOT NULL,
                `username` VARCHAR(100) NOT NULL,
                `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_attempts_ip_time` (`ip_address`, `attempted_at`),
                INDEX `idx_attempts_user_time` (`username`, `attempted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $db->exec($sql);
        } catch (Exception $e) {
            // Fail-safe, ignore if there is any error
        }
    }

    /**
     * Checks if the user or IP is currently locked out.
     */
    private function isLockedOut($db, string $username, string $ip): bool {
        $this->initializeLoginAttemptsTable($db);
        
        // Count failed attempts in the last 15 minutes (900 seconds)
        $timeLimit = date('Y-m-d H:i:s', time() - 900);
        
        $sql = "SELECT COUNT(*) FROM `login_attempts` 
                WHERE (`ip_address` = :ip OR `username` = :user) 
                AND `attempted_at` > :time_limit";
        
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'ip' => $ip,
                'user' => trim($username),
                'time_limit' => $timeLimit
            ]);
            $attempts = (int)$stmt->fetchColumn();
            return $attempts >= 5;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Registers a failed login attempt.
     */
    private function registerFailedAttempt($db, string $username, string $ip): void {
        try {
            $sql = "INSERT INTO `login_attempts` (`ip_address`, `username`) VALUES (:ip, :user)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'ip' => $ip,
                'user' => trim($username)
            ]);
        } catch (Exception $e) {
            // Fail-safe
        }
    }

    /**
     * Clears failed login attempts for an authenticated user.
     */
    private function clearFailedAttempts($db, string $username, string $ip): void {
        try {
            $sql = "DELETE FROM `login_attempts` WHERE `ip_address` = :ip OR `username` = :user";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                'ip' => $ip,
                'user' => trim($username)
            ]);
        } catch (Exception $e) {
            // Fail-safe
        }
    }

    /**
     * Executes the secure login process for users.
     * Validates inputs, sanitizes data, prevents brute-force timing leakage,
     * regenerates secure session tokens, and maps variables.
     * 
     * @param string $username Or Email
     * @param string $password Raw input password
     * @param bool $rememberMe Whether to persist sessions (placeholder for cookies)
     * @return bool True if successful, redirects on failure
     */
    public function handleLogin(string $username, string $password, bool $rememberMe = false): bool {
        try {
            // Retrieve DB connection
            $db = Database::getConnection();

            // 1. Brute-force & Rate limiting check
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            if ($this->isLockedOut($db, $username, $ip)) {
                flash_set('error', "Security Lockdown: Too many failed login attempts. Access is locked out for 15 minutes.");
                return false;
            }

            // SQL prepared statement to query by username or email
            $sql = "SELECT * FROM `users` WHERE `username` = :username OR `email` = :email LIMIT 1";

            $stmt = $db->prepare($sql);
            $loginIdentifier = trim($username);
            $stmt->execute([
                'username' => $loginIdentifier,
                'email'     => $loginIdentifier
            ]);

            $user = $stmt->fetch();


            // Validate user exists and verify hash (timing attack safe)
            if ($user && password_verify($password, $user['password_hash'])) {
                
                // Assert account is active
                if ($user['status'] !== 'Active') {
                    flash_set('error', "Your portal account is currently " . $user['status'] . ". Please contact your HR department.");
                    return false;
                }

                // Clear any stored failed login attempts on success
                $this->clearFailedAttempts($db, $username, $ip);

                // 1. Session Fixation Mitigation: Regenerate session ID and delete old session files
                session_regenerate_id(true);

                // 2. Hydrate session storage
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_status'] = $user['status'];
                $_SESSION['LAST_ACTIVITY'] = time();

                // 3. Query linked Employee data for customized dashboard greet
                $empSql = "SELECT `id`, `first_name`, `last_name`, `employee_code` FROM `employees` WHERE `user_id` = :uid LIMIT 1";
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

                // 4. Set remember me cookie if checked (Simulated for Sprint 01 using highly secure flags)
                if ($rememberMe) {
                    $token = bin2hex(random_bytes(32));
                    
                    // Save to DB
                    $updateSql = "UPDATE `users` SET `remember_token` = :token WHERE `id` = :uid";
                    $updateStmt = $db->prepare($updateSql);
                    $updateStmt->execute(['token' => $token, 'uid' => $user['id']]);

                    // Store cookie (valid for 30 days, HttpOnly, Secure if HTTPS, SameSite Lax)
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
                // Register a failed attempt
                $this->registerFailedAttempt($db, $username, $ip);

                // Generic error to prevent user enumeration / username harvesting
                flash_set('error', "Invalid authentication credentials provided. Please double-check and retry.");
                return false;
            }

        } catch (Exception $e) {
            // Graceful exception capture
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
     * Clears session arrays, invalidates session cookie, deletes remember token,
     * and deletes local cookie credentials.
     */
    public function handleLogout(): void {
        try {
            // Delete remember me tokens from database
            if (isset($_SESSION['user_id'])) {
                $db = Database::getConnection();
                $updateSql = "UPDATE `users` SET `remember_token` = NULL WHERE `id` = :uid";
                $updateStmt = $db->prepare($updateSql);
                $updateStmt->execute(['uid' => $_SESSION['user_id']]);
            }
        } catch (Exception $e) {
            // Fail silently on logout DB cleanup to prevent denial of service logout failures
        }

        // Unset all session variables
        $_SESSION = [];

        // Terminate the session cookie
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

        // Terminate the remember me cookie
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

        // Destroy the session context on server
        session_destroy();

        // Start a temporary session purely to pass the logout flash confirmation
        session_start();
        $_SESSION['flash_notifications'][] = [
            'type' => 'success',
            'message' => 'You have been safely disconnected. All active portal sessions are terminated.'
        ];
        
        redirect('index.php?route=login');
    }
}
