<?php
/**
 * Update Role Controller (System/Organization Module)
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
    flash_set('error', 'Invalid request method. Record modifications must be sent via POST.');
    redirect('index.php?route=roles');
}

$id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    flash_set('error', 'Operation Error: Missing or invalid Role ID parameter.');
    redirect('index.php?route=roles');
}

$db = Database::getConnection();

// 1. Verify CSRF Token
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    flash_set('error', 'Security Violation: CSRF verification failed. Please try again.');
    redirect('index.php?route=roles-edit&id=' . $id);
}

// 2. Lookup existing non-deleted role
$stmt = $db->prepare("SELECT * FROM `roles` WHERE `id` = ? AND `deleted_at` IS NULL");
$stmt->execute([$id]);
$role = $stmt->fetch();

if (!$role) {
    flash_set('error', 'Operation Error: Role record not found or has been deleted.');
    redirect('index.php?route=roles');
}

// 3. Extract and Sanitize Inputs
$name = trim($_POST['name'] ?? '');
$status = trim($_POST['status'] ?? 'Active');
$description = trim($_POST['description'] ?? '');

// If it's a system protected role (Admin, HR Manager, Employee), do not allow renaming the role
if (in_array($role['name'], ['Admin', 'HR Manager', 'Employee'], true)) {
    $name = $role['name']; // retain protected name
}

// 4. Input Validation Check
$errors = [];

if (empty($name)) {
    $errors[] = "Role name is a required field.";
} elseif (strlen($name) > 50) {
    $errors[] = "Role name must not exceed 50 characters.";
}

if (!in_array($status, ['Active', 'Inactive'], true)) {
    $errors[] = "Invalid status selected. Must be Active or Inactive.";
}

// Check for Duplicate Role Name (excluding currently edited role, and deleted roles)
if (empty($errors)) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM `roles` WHERE `name` = ? AND `id` != ? AND `deleted_at` IS NULL");
    $stmt->execute([$name, $id]);
    if ((int)$stmt->fetchColumn() > 0) {
        $errors[] = "A role named '$name' already exists in the system directory.";
    }
}

// Redirect back on validation checks failures
if (!empty($errors)) {
    flash_set('error', implode('<br>', $errors));
    redirect('index.php?route=roles-edit&id=' . $id);
}

// 5. Update Database Record via SQL Transaction
try {
    $db->beginTransaction();

    $stmt = $db->prepare("UPDATE `roles` SET `name` = :name, `description` = :description, `status` = :status, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id");
    $stmt->execute([
        'name' => $name,
        'description' => !empty($description) ? $description : null,
        'status' => $status,
        'id' => $id
    ]);

    // 6. Log Activity Entry
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $activity_payload = json_encode([
        'previous' => [
            'name' => $role['name'],
            'status' => $role['status'],
            'description' => $role['description']
        ],
        'current' => [
            'name' => $name,
            'status' => $status,
            'description' => $description
        ]
    ], JSON_UNESCAPED_SLASHES);

    $sql_log = "INSERT INTO `activity_logs` (`user_id`, `action`, `table_name`, `record_id`, `ip_address`, `user_agent`, `payload`) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = $db->prepare($sql_log);
    $stmt_log->execute([
        $user_id,
        'Role Updated',
        'roles',
        $id,
        $ip_address,
        $user_agent,
        $activity_payload
    ]);

    $db->commit();

    flash_set('success', "Security role '$name' details were successfully updated.");
    redirect('index.php?route=roles');

} catch (Exception $e) {
    $db->rollBack();
    flash_set('error', 'Transaction Error: Failed to update role profile. ' . $e->getMessage());
    redirect('index.php?route=roles-edit&id=' . $id);
}
