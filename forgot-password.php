<?php
/**
 * Standalone Forgot Password Bootstrapper
 * Developed by Senior PHP Software Architect
 * 
 * Safely initializes configuration settings and renders the password recovery portal.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/url_helper.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

// Guest access verification
AuthMiddleware::guestOnly();

// Include the secure forgot password view
require_once __DIR__ . '/views/forgot-password.php';
