<?php
/**
 * Standalone Logout Processing Script
 * Developed by Senior PHP Software Architect
 * 
 * Intercepts secure POST logouts, verifies CSRF token sanity, and terminates the session context.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/url_helper.php';
require_once __DIR__ . '/controllers/AuthController.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token to prevent malicious logout requests
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($token)) {
        $authController = new AuthController();
        $authController->handleLogout();
        exit();
    } else {
        flash_set('error', 'Security warning: Failed logout CSRF validation.');
    }
}

// Fallback redirect to dashboard
redirect('index.php?route=dashboard');
