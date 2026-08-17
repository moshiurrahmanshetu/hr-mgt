<?php
/**
 * Soft Delete Branch Action (Organization Module)
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
    redirect('index.php?route=branches');
}

$db = Database::getConnection();

// 1. CSRF Verification
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    flash_set('error', 'Security Violation: CSRF verification failed.');
    redirect('index.php?route=branches');
}

// 2. Extract & Validate ID
$id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$POST['id'] : 0;
if ($id <= 0) {
    flash_set('error', 'Operation Error: Missing or invalid Branch ID parameter.');
    redirect('index.php?route=branches');
}

// Check if Branch exists and is not already deleted
$stmt_check = $db->prepare("SELECT * FROM `branches` WHERE `id` = ? AND `deleted_at` IS NULL");
$stmt_check->execute([$id]);
$branch = $stmt_check->fetch();

if (!$branch) {
    flash_set('error', 'Operation Error: Branch record not found or already deleted.');
    redirect('index.php?route=branches');
}

// 3. FOREIGN KEY VALIDATION (Guarantees database integrity)
// A. Check if any active departments exist in this branch
$dept_check = $db->prepare("SELECT COUNT(*) FROM `departments` WHERE `branch_id` = ? AND `deleted_at` IS NULL");
$dept_check->execute([$id]);
if ((int)$dept_check->fetchColumn() > 0) {
    flash_set('error', "Integrity Constraint: Cannot delete branch '{$branch['name']}' because there are active departments associated with it. Please reassign or delete departments first.");
    redirect('index.php?route=branches');
}

// B. Check if any active employees are assigned to this branch
$emp_check = $db->prepare("SELECT COUNT(*) FROM `employees` WHERE `branch_id` = ? AND `employment_status` != 'Terminated'");
$emp_check->execute([$id]);
if ((int)$emp_check->fetchColumn() > 0) {
    flash_set('error', "Integrity Constraint: Cannot delete branch '{$branch['name']}' because there are active employees currently assigned to it.");
    redirect('index.php?route=branches');
}

// 4. Perform Soft-Deletion & Log Activity
try {
    $db->beginTransaction();

    // Soft delete: set deleted_at and update status to Inactive
    $stmt = $db->prepare("UPDATE `branches` SET `deleted_at` = CURRENT_TIMESTAMP, `status` = 'Inactive', `updated_at` = CURRENT_TIMESTAMP WHERE `id` = ?");
    $stmt->execute([$id]);

    // Automatically Create Activity Log Entry
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $activity_payload = json_encode([
        'id' => $id,
        'code' => $branch['code'],
        'name' => $branch['name'],
        'action_details' => 'Soft deleted branch record'
    ], JSON_UNESCAPED_SLASHES);

    $sql_log = "INSERT INTO `activity_logs` (`user_id`, `action`, `table_name`, `record_id`, `ip_address`, `user_agent`, `payload`) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = $db->prepare($sql_log);
    $stmt_log->execute([
        $user_id,
        'Branch Deleted',
        'branches',
        $id,
        $ip_address,
        $user_agent,
        $activity_payload
    ]);

    $db->commit();
    flash_set('success', "Branch '{$branch['name']}' has been soft-deleted successfully.");
    redirect('index.php?route=branches');

} catch (Exception $e) {
    $db->rollBack();
    flash_set('error', 'Database Transaction Error: Failed to commit soft-deletion. ' . $e->getMessage());
    redirect('index.php?route=branches');
}
