<?php
// CSRF Token generation and validation

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Validate CSRF token
 */
function validate_csrf_token($token) {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }
    
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Get CSRF token for form
 */
function get_csrf_token() {
    return generate_csrf_token();
}

/**
 * Verify CSRF token from POST request
 */
function verify_csrf_token() {
    if (!isset($_POST[CSRF_TOKEN_NAME])) {
        return false;
    }
    
    return validate_csrf_token($_POST[CSRF_TOKEN_NAME]);
}
