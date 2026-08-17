<?php
/**
 * Authentication & Authorization Guard Middleware
 * Developed by Senior PHP Software Architect
 * 
 * Intercepts requests to enforce session states and user permissions,
 * blocking unauthenticated or unauthorized users from reaching sensitive files.
 */

require_once __DIR__ . '/../helpers/url_helper.php';

class AuthMiddleware {
    
    /**
     * Asserts that a user has a valid active session.
     * Redirects to the login page with an error if unauthorized.
     */
    public static function requireLogin(): void {
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            // Set error message
            $_SESSION['flash_error'] = "Authentication required. Please access the portal using valid credentials.";
            redirect('index.php?route=login');
        }
        
        // Check if user status is suspended or blocked
        if (isset($_SESSION['user_status']) && $_SESSION['user_status'] !== 'Active') {
            self::logoutAndRedirect("Your account has been deactivated. Please contact support.");
        }
    }

    /**
     * Asserts that the visitor has NOT logged in (guest only, e.g., login page).
     * Redirects to the dashboard if they are already logged in.
     */
    public static function guestOnly(): void {
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            redirect('index.php?route=dashboard');
        }
    }

    /**
     * Restricts access to specific role hierarchies.
     * 
     * @param array $allowedRoles Array of roles permitted to view, e.g., ['Admin', 'HR Manager']
     */
    public static function requireRoles(array $allowedRoles): void {
        // First, check basic authentication
        self::requireLogin();
        
        $userRole = $_SESSION['user_role'] ?? '';
        
        if (!in_array($userRole, $allowedRoles, true)) {
            $_SESSION['flash_error'] = "Access Denied: Your security clearance is insufficient for this action.";
            redirect('index.php?route=dashboard');
        }
    }

    /**
     * Helper to log out and redirect with a custom message.
     * 
     * @param string $message Flash message to display on login
     */
    private static function logoutAndRedirect(string $message): void {
        session_unset();
        session_destroy();
        
        session_start();
        $_SESSION['flash_error'] = $message;
        redirect('index.php?route=login');
    }
}
