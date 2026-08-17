<?php
/**
 * Soft Delete Role Action (System/Organization Module)
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
    flash_set('error', 'Invalid request method. Deletions must be submitted via secure POST.');
    redirect('index.php?route=roles');
}

$db = Database::getConnection();

// 1. CSRF Verification
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    flash_set('error', 'Security Violation: CSRF verification failed.');
    redirect('index.php?route=roles');
}

// 2. Extract & Validate ID
$id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$POST['id'] : 0;
if ($id <= 0) {
    flash_set('error', 'Operation Error: Missing or invalid Role ID parameter.');
    redirect('index.php?route=roles');
}

// Check if Role exists and is not already deleted
$stmt_check = $db->prepare("SELECT * FROM `roles` WHERE `id` = ? AND `deleted_at` IS NULL");
$stmt_check->execute([$id]);
$role = $stmt_check->fetch();

if (!$role) {
    flash_set('error', 'Operation Error: Role record not found or already deleted.');
    redirect('index.php?route=roles');
}

// 3. SYSTEM PROTECTION CHECK (Prevent destroying foundational role entities)
if (in_array($role['name'], ['Admin', 'HR Manager', 'Employee'], true)) {
    flash_set('error', "Security Violation: System protected role '{$role['name']}' cannot be deleted under any circumstances.");
    redirect('index.php?route=roles');
}

// 4. FOREIGN KEY VALIDATION (Guarantees database integrity)
// Check if any active user accounts are assigned to this role
$user_check = $db->prepare("SELECT COUNT(*) FROM `users` WHERE `role_id` = ?");
$user_check->execute([$id]);
if ((int)$user_check->fetchColumn() > 0) {
    flash_set('error', "Integrity Constraint: Cannot delete role '{$role['name']}' because it is currently assigned to active user accounts. Reassign or delete those user accounts first.");
    redirect('index.php?route=roles');
}

// 5. Perform Soft-Deletion & Log Activity
try {
    $db->beginTransaction();

    // Soft delete: set deleted_at and update status to Inactive
    $stmt = $db->prepare("UPDATE `roles` SET `deleted_at` = CURRENT_TIMESTAMP, `status` = 'Inactive', `updated_at` = CURRENT_TIMESTAMP WHERE `id` = ?");
    $stmt->execute([$id]);

    // Automatically Create Activity Log Entry
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $activity_payload = json_encode([
        'id' => $id,
        'name' => $role['name'],
        'action_details' => 'Soft deleted security role record'
    ], JSON_UNESCAPED_SLASHES);

    $sql_log = "INSERT INTO `activity_logs` (`user_id`, `action`, `table_name`, `record_id`, `ip_address`, `user_agent`, `payload`) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = $db->prepare($sql_log);
    $stmt_log->execute([
        $user_id,
        'Role Deleted',
        'roles',
        $id,
        $ip_address,
        $user_agent,
        $activity_payload
    ]);

    $db->commit();
    flash_set('success', "Security role '{$role['name']}' has been soft-deleted successfully.");
    redirect('index.php?route=roles');

} catch (Exception $e) {
    $db->rollBack();
    flash_set('error', 'Transaction Error: Failed to soft-delete role. ' . $e->getMessage());
    redirect('index.php?route=roles');
}
