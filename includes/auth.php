<?php
// Authentication and authorization functions

require_once __DIR__ . '/../config/database.php';

/**
 * Check if user is logged in, redirect to login if not
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/modules/auth/login.php');
        exit;
    }
}

/**
 * Check if user has specific role, show 403 if not
 */
function require_role($required_role) {
    require_login();
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        http_response_code(403);
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="text-center">
        <h1 class="display-1 fw-bold text-danger">403</h1>
        <h2 class="h4 mb-3">Access Denied</h2>
        <p class="text-muted">You do not have permission to access this page.</p>
        <a href="' . BASE_URL . '/modules/dashboard/' . $_SESSION['role'] . '_dashboard.php" class="btn btn-primary">Back to Dashboard</a>
    </div>
</body>
</html>';
        exit;
    }
}

/**
 * Get current user data from database
 */
function get_logged_in_user() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return null;
    }
}

/**
 * Log user activity
 */
function log_activity($user_id, $action, $description = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description, created_at) 
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$user_id, $action, $description]);
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

/**
 * Load user permissions into session cache
 * This should be called during login to populate $_SESSION['permissions']
 */
function load_user_permissions($user_id, $role_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.name 
            FROM role_permissions rp 
            JOIN permissions p ON rp.permission_id = p.id 
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$role_id]);
        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $_SESSION['permissions'] = $permissions;
        
        return $permissions;
    } catch (PDOException $e) {
        error_log("Error loading permissions: " . $e->getMessage());
        $_SESSION['permissions'] = [];
        return [];
    }
}

/**
 * Check if current user has a specific permission
 * This checks against the session-cached permissions array loaded during login
 * Permission changes take effect on next login (simple, safe approach)
 */
function has_permission($permission_name) {
    if (!isset($_SESSION['permissions'])) {
        return false;
    }
    
    return in_array($permission_name, $_SESSION['permissions']);
}

/**
 * Check if current user has a specific permission, show 403 if not
 * This is a convenience function that combines has_permission() with a 403 response
 */
function has_permission_or_die($permission_name) {
    if (!has_permission($permission_name)) {
        http_response_code(403);
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="text-center">
        <h1 class="display-1 fw-bold text-danger">403</h1>
        <h2 class="h4 mb-3">Access Denied</h2>
        <p class="text-muted">You do not have permission to access this page.</p>
        <a href="' . BASE_URL . '/modules/dashboard/' . $_SESSION['role'] . '_dashboard.php" class="btn btn-primary">Back to Dashboard</a>
    </div>
</body>
</html>';
        exit;
    }
}
