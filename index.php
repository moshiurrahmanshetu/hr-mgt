<?php
require_once __DIR__ . '/config/config.php';

// Redirect based on session status
if (isset($_SESSION['user_id'])) {
    // User is logged in, redirect to appropriate dashboard
    $role = $_SESSION['role'] ?? 'employee';
    $dashboard_url = BASE_URL . '/modules/dashboard/' . $role . '_dashboard.php';
    header('Location: ' . $dashboard_url);
} else {
    // User is not logged in, redirect to login
    header('Location: ' . BASE_URL . '/modules/auth/login.php');
}
exit;
