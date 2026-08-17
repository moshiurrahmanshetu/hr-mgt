<?php
/**
 * Standalone Reset Password Bootstrapper
 * Developed by Senior PHP Software Architect
 * 
 * Safe entrance wrapper that boots the configuration, validates guest status, 
 * and loads the reset password form page.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/url_helper.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

// Guest access check
AuthMiddleware::guestOnly();

// Include the secure reset password view
require_once __DIR__ . '/views/reset-password.php';
