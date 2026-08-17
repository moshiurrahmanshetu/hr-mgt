<?php
/**
 * Global Utility and Security Helper Functions
 * Developed by Senior PHP Software Architect
 * 
 * Provides robust URL resolvers, navigation helpers, sanitization,
 * and security escaping utilities. Supports subdirectories without path breaking.
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Returns the absolute URL for any relative route inside the application.
 * Solves absolute/relative path confusion.
 * 
 * @param string $path The relative path to append, e.g., '/views/dashboard.php'
 * @return string
 */
function base_url(string $path = ''): string {
    $path = ltrim($path, '/');
    return BASE_URL . ($path ? '/' . $path : '');
}

/**
 * Returns the absolute URL for asset files.
 * 
 * @param string $path The path to the asset, e.g., 'css/styles.css'
 * @return string
 */
function asset_url(string $path = ''): string {
    return base_url('assets/' . ltrim($path, '/'));
}

/**
 * Performs clean, safe redirects. Prevents header injection.
 * Terminate script execution immediately.
 * 
 * @param string $path The route or path to redirect to, e.g., 'index.php?route=dashboard'
 */
function redirect(string $path): void {
    // If it starts with http/https, it's an external absolute redirect, else internal relative
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        header("Location: " . $path);
    } else {
        header("Location: " . base_url($path));
    }
    exit();
}

/**
 * Escapes values for HTML output. Anti-XSS (Cross-Site Scripting) standard.
 * 
 * @param mixed $value Raw string or value
 * @return string Safe, escaped string
 */
function sanitize($value): string {
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Returns a secure hidden CSRF input field for inclusion inside HTML Forms.
 * 
 * @return string
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . sanitize(get_csrf_token()) . '">';
}

/**
 * Safely format monetary decimal values.
 * 
 * @param float|string $amount
 * @return string
 */
function format_money($amount): string {
    return '$' . number_format((float)$amount, 2);
}

/**
 * Safely format date values to standard human-readable format.
 * 
 * @param string|null $date
 * @return string
 */
function format_date(?string $date): string {
    if (!$date) return 'N/A';
    return date('M d, Y', strtotime($date));
}
