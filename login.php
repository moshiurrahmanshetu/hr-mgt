<?php
/**
 * Standalone Login Route Bootstrapper
 * Developed by Senior PHP Software Architect
 * 
 * Safe entry point that boots the config and loads the login portal view.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/url_helper.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

// Guest access check
AuthMiddleware::guestOnly();

// Include core login view
require_once __DIR__ . '/views/login.php';
