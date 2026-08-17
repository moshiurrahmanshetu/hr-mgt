<?php
// Common helper functions

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Set flash message
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Redirect with flash message
 */
function redirect_with_flash($url, $type, $message) {
    set_flash_message($type, $message);
    header('Location: ' . $url);
    exit;
}

/**
 * Get avatar URL or default
 */
function get_avatar_url($avatar_filename) {
    if ($avatar_filename && file_exists(__DIR__ . '/../uploads/avatars/' . $avatar_filename)) {
        return BASE_URL . '/uploads/avatars/' . $avatar_filename;
    }
    // Return a simple SVG data URI as default avatar
    return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSI1MCIgY3k9IjUwIiByPSI1MCIgZmlsbD0iI2UyZThmMCIvPjxjaXJjbGUgY3g9IjUwIiBjeT0iNDAiIHI9IjIwIiBmaWxsPSIjNjQ3NDhiIi8+PHBhdGggZD0iTTUwIDcwYzE1IDAgMzAgMTAgMzAgMjB2MTBIMjB2LTEwYzAtMTAgMTUtMjAgMzAtMjB6IiBmaWxsPSIjNjQ3NDhiIi8+PC9zdmc+';
}

/**
 * Generate unique filename for upload
 */
function generate_unique_filename($original_filename) {
    $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    return uniqid('avatar_', true) . '.' . $extension;
}

/**
 * Check login attempts (brute force protection)
 */
function check_login_attempts($email) {
    if (!isset($_SESSION['login_attempts'][$email])) {
        return true;
    }
    
    $attempts = $_SESSION['login_attempts'][$email];
    
    // Reset if lockout time has passed
    if (time() - $attempts['first_attempt'] > LOGIN_LOCKOUT_TIME) {
        unset($_SESSION['login_attempts'][$email]);
        return true;
    }
    
    // Block if max attempts reached
    if ($attempts['count'] >= MAX_LOGIN_ATTEMPTS) {
        return false;
    }
    
    return true;
}

/**
 * Record failed login attempt
 */
function record_failed_attempt($email) {
    if (!isset($_SESSION['login_attempts'][$email])) {
        $_SESSION['login_attempts'][$email] = [
            'count' => 1,
            'first_attempt' => time()
        ];
    } else {
        $_SESSION['login_attempts'][$email]['count']++;
    }
}

/**
 * Clear login attempts on successful login
 */
function clear_login_attempts($email) {
    if (isset($_SESSION['login_attempts'][$email])) {
        unset($_SESSION['login_attempts'][$email]);
    }
}

/**
 * Get remaining lockout time in seconds
 */
function get_lockout_time($email) {
    if (!isset($_SESSION['login_attempts'][$email])) {
        return 0;
    }
    
    $attempts = $_SESSION['login_attempts'][$email];
    if ($attempts['count'] < MAX_LOGIN_ATTEMPTS) {
        return 0;
    }
    
    $elapsed = time() - $attempts['first_attempt'];
    $remaining = LOGIN_LOCKOUT_TIME - $elapsed;
    
    return max(0, $remaining);
}
