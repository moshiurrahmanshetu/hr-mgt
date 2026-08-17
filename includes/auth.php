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
