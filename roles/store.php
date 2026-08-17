<?php
/**
 * Store Role Controller (System/Organization Module)
 * Developed by Senior PHP Software Architect
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/url_helper.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../includes/flash.php';

// Auth Guard: Admins and HR Managers only
AuthMiddleware::requireRoles(['Admin', 'HR Manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_set('error', 'Invalid request method. Record creation must be sent via POST.');
    redirect('index.php?route=roles');
}

$db = Database::getConnection();

// 1. CSRF Token Verification
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    flash_set('error', 'Security Violation: CSRF verification failed. Please try again.');
    redirect('index.php?route=roles-create');
}

// 2. Extract and Sanitize Inputs
$name = trim($_POST['name'] ?? '');
$status = trim($_POST['status'] ?? 'Active');
$description = trim($_POST['description'] ?? '');

// 3. Input Validation Check
$errors = [];

if (empty($name)) {
    $errors[] = "Role name is a required field.";
} elseif (strlen($name) > 50) {
    $errors[] = "Role name must not exceed 50 characters.";
}

if (!in_array($status, ['Active', 'Inactive'], true)) {
    $errors[] = "Invalid status selected. Must be Active or Inactive.";
}

// Check for Duplicate Role Name (excluding deleted records)
if (empty($errors)) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM `roles` WHERE `name` = ? AND `deleted_at` IS NULL");
    $stmt->execute([$name]);
    if ((int)$stmt->fetchColumn() > 0) {
        $errors[] = "A role named '$name' already exists in the system directory.";
    }
}

// Redirect back with validation error payload if any checks fail
if (!empty($errors)) {
    flash_set('error', implode('<br>', $errors));
    redirect('index.php?route=roles-create');
}

// 4. Secure Database Insertion via Transaction
try {
    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO `roles` (`name`, `description`, `status`) VALUES (:name, :description, :status)");
    $stmt->execute([
        'name' => $name,
        'description' => !empty($description) ? $description : null,
        'status' => $status
    ]);

    $role_id = (int)$db->lastInsertId();

    // 5. Register Activity Log Entry
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $activity_payload = json_encode([
        'role_name' => $name,
        'status' => $status,
        'description' => $description
    ], JSON_UNESCAPED_SLASHES);

    $sql_log = "INSERT INTO `activity_logs` (`user_id`, `action`, `table_name`, `record_id`, `ip_address`, `user_agent`, `payload`) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = $db->prepare($sql_log);
    $stmt_log->execute([
        $user_id,
        'Role Created',
        'roles',
        $role_id,
        $ip_address,
        $user_agent,
        $activity_payload
    ]);

    $db->commit();

    flash_set('success', "Security role '$name' was successfully created and configured.");
    redirect('index.php?route=roles');

} catch (Exception $e) {
    $db->rollBack();
    flash_set('error', 'Transaction Error: Failed to save role profile. ' . $e->getMessage());
    redirect('index.php?route=roles-create');
}
