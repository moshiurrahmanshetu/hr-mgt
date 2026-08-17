<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Log the logout activity before destroying session
if (isset($_SESSION['user_id'])) {
    log_activity($_SESSION['user_id'], 'logout', 'User logged out');
}

// Destroy session completely
$_SESSION = [];
session_unset();
session_destroy();

// Redirect to login with success message via query parameter
header('Location: ' . BASE_URL . '/modules/auth/login.php?logout=success');
exit;
