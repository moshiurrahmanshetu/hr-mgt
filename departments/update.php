<?php
/**
 * Update Department Action (Organization Module)
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
    flash_set('error', 'Invalid request method. Operations must be submitted via secure POST.');
    redirect('index.php?route=departments');
}

$db = Database::getConnection();

// 1. CSRF Verification
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    flash_set('error', 'Security Violation: CSRF verification failed.');
    redirect('index.php?route=departments');
}

// 2. Extract & Validate ID
$id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    flash_set('error', 'Operation Error: Missing or invalid Department ID parameter.');
    redirect('index.php?route=departments');
}

// Check if Department exists
$stmt_check = $db->prepare("SELECT * FROM `departments` WHERE `id` = ? AND `deleted_at` IS NULL");
$stmt_check->execute([$id]);
$dept = $stmt_check->fetch();

if (!$dept) {
    flash_set('error', 'Operation Error: Department record not found or has been deleted.');
    redirect('index.php?route=departments');
}

// 3. Extract & Trim Fields
$name = trim($_POST['name'] ?? '');
$code = trim($_POST['code'] ?? '');
$description = trim($_POST['description'] ?? '');
$branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int)$_POST['branch_id'] : 0;
$manager_id = isset($_POST['manager_id']) && $_POST['manager_id'] !== '' ? (int)$_POST['manager_id'] : null;
$status = $_POST['status'] ?? 'Active';

// 4. Input Validations
if (empty($name)) {
    flash_set('error', 'Validation Error: Department name is required.');
    redirect('index.php?route=departments-edit&id=' . $id);
}

if (empty($code)) {
    flash_set('error', 'Validation Error: Department code is required.');
    redirect('index.php?route=departments-edit&id=' . $id);
}

if ($branch_id <= 0) {
    flash_set('error', 'Validation Error: Please select an associated corporate branch.');
    redirect('index.php?route=departments-edit&id=' . $id);
}

// 5. Duplicate Validation
$dup_name_stmt = $db->prepare("SELECT COUNT(*) FROM `departments` WHERE `name` = ? AND `id` != ? AND `deleted_at` IS NULL");
$dup_name_stmt->execute([$name, $id]);
if ((int)$dup_name_stmt->fetchColumn() > 0) {
    flash_set('error', "Validation Error: A department named '$name' already exists.");
    redirect('index.php?route=departments-edit&id=' . $id);
}

$dup_code_stmt = $db->prepare("SELECT COUNT(*) FROM `departments` WHERE `code` = ? AND `id` != ? AND `deleted_at` IS NULL");
$dup_code_stmt->execute([$code, $id]);
if ((int)$dup_code_stmt->fetchColumn() > 0) {
    flash_set('error', "Validation Error: A department with code '$code' already exists.");
    redirect('index.php?route=departments-edit&id=' . $id);
}

// 6. Foreign Key Validation
// A. Branch check
$branch_stmt = $db->prepare("SELECT COUNT(*) FROM `branches` WHERE `id` = ? AND `deleted_at` IS NULL");
$branch_stmt->execute([$branch_id]);
if ((int)$branch_stmt->fetchColumn() === 0) {
    flash_set('error', 'Validation Error: The selected corporate branch does not exist or has been deleted.');
    redirect('index.php?route=departments-edit&id=' . $id);
}

// B. Manager check
if ($manager_id !== null) {
    $mgr_stmt = $db->prepare("SELECT COUNT(*) FROM `employees` WHERE `id` = ? AND `employment_status` != 'Terminated'");
    $mgr_stmt->execute([$manager_id]);
    if ((int)$mgr_stmt->fetchColumn() === 0) {
        flash_set('error', 'Validation Error: The selected Department Head does not exist or is terminated.');
        redirect('index.php?route=departments-edit&id=' . $id);
    }
}

// 7. Commit changes & Log Activity
try {
    $db->beginTransaction();

    $stmt = $db->prepare("UPDATE `departments` SET `branch_id` = ?, `name` = ?, `code` = ?, `description` = ?, `manager_id` = ?, `status` = ?, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = ?");
    $stmt->execute([$branch_id, $name, $code, $description ?: null, $manager_id, $status, $id]);

    // Create Log
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $activity_payload = json_encode([
        'id' => $id,
        'branch_id' => $branch_id,
        'code' => $code,
        'name' => $name,
        'status' => $status,
        'manager_id' => $manager_id,
        'old_name' => $dept['name'],
        'old_code' => $dept['code']
    ], JSON_UNESCAPED_SLASHES);

    $sql_log = "INSERT INTO `activity_logs` (`user_id`, `action`, `table_name`, `record_id`, `ip_address`, `user_agent`, `payload`) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = $db->prepare($sql_log);
    $stmt_log->execute([
        $user_id,
        'Department Updated',
        'departments',
        $id,
        $ip_address,
        $user_agent,
        $activity_payload
    ]);

    $db->commit();
    flash_set('success', "Department details for '$name' updated successfully.");
    redirect('index.php?route=departments');

} catch (Exception $e) {
    $db->rollBack();
    flash_set('error', 'Database Transaction Error: Failed to commit updates. ' . $e->getMessage());
    redirect('index.php?route=departments-edit&id=' . $id);
}
