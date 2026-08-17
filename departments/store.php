<?php
/**
 * Store Department Action (Organization Module)
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
    redirect('index.php?route=departments-create');
}

$db = Database::getConnection();

// 1. CSRF Verification
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    flash_set('error', 'Security Violation: CSRF verification failed.');
    redirect('index.php?route=departments-create');
}

// 2. Extract, Trim & Sanitize Inputs
$name = trim($_POST['name'] ?? '');
$code = trim($_POST['code'] ?? '');
$description = trim($_POST['description'] ?? '');
$branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int)$_POST['branch_id'] : 0;
$manager_id = isset($_POST['manager_id']) && $_POST['manager_id'] !== '' ? (int)$_POST['manager_id'] : null;
$status = $_POST['status'] ?? 'Active';

// 3. Validation Rules
if (empty($name)) {
    flash_set('error', 'Validation Error: Department name is required.');
    redirect('index.php?route=departments-create');
}

if (empty($code)) {
    flash_set('error', 'Validation Error: Department code is required.');
    redirect('index.php?route=departments-create');
}

if ($branch_id <= 0) {
    flash_set('error', 'Validation Error: Please select an associated corporate branch.');
    redirect('index.php?route=departments-create');
}

// 4. Duplicate Validation (Amongst active, non-deleted records)
$dup_name_stmt = $db->prepare("SELECT COUNT(*) FROM `departments` WHERE `name` = ? AND `deleted_at` IS NULL");
$dup_name_stmt->execute([$name]);
if ((int)$dup_name_stmt->fetchColumn() > 0) {
    flash_set('error', "Validation Error: A department named '$name' already exists.");
    redirect('index.php?route=departments-create');
}

$dup_code_stmt = $db->prepare("SELECT COUNT(*) FROM `departments` WHERE `code` = ? AND `deleted_at` IS NULL");
$dup_code_stmt->execute([$code]);
if ((int)$dup_code_stmt->fetchColumn() > 0) {
    flash_set('error', "Validation Error: A department with code '$code' already exists.");
    redirect('index.php?route=departments-create');
}

// 5. Foreign Key Validation
// A. Branch Validation
$branch_stmt = $db->prepare("SELECT COUNT(*) FROM `branches` WHERE `id` = ? AND `deleted_at` IS NULL");
$branch_stmt->execute([$branch_id]);
if ((int)$branch_stmt->fetchColumn() === 0) {
    flash_set('error', 'Validation Error: The selected corporate branch does not exist or has been deleted.');
    redirect('index.php?route=departments-create');
}

// B. Department Head (Manager) Validation
if ($manager_id !== null) {
    $mgr_stmt = $db->prepare("SELECT COUNT(*) FROM `employees` WHERE `id` = ? AND `employment_status` != 'Terminated'");
    $mgr_stmt->execute([$manager_id]);
    if ((int)$mgr_stmt->fetchColumn() === 0) {
        flash_set('error', 'Validation Error: The selected Department Head does not exist or is terminated.');
        redirect('index.php?route=departments-create');
    }
}

// 6. DB Persistence & Logging
try {
    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO `departments` (`branch_id`, `name`, `code`, `description`, `manager_id`, `status`) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$branch_id, $name, $code, $description ?: null, $manager_id, $status]);
    $dept_id = $db->lastInsertId();

    // Log Activity Entry
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $activity_payload = json_encode([
        'id' => $dept_id,
        'branch_id' => $branch_id,
        'code' => $code,
        'name' => $name,
        'status' => $status,
        'manager_id' => $manager_id
    ], JSON_UNESCAPED_SLASHES);

    $sql_log = "INSERT INTO `activity_logs` (`user_id`, `action`, `table_name`, `record_id`, `ip_address`, `user_agent`, `payload`) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = $db->prepare($sql_log);
    $stmt_log->execute([
        $user_id,
        'Department Created',
        'departments',
        $dept_id,
        $ip_address,
        $user_agent,
        $activity_payload
    ]);

    $db->commit();
    flash_set('success', "Department '$name' has been registered successfully.");
    redirect('index.php?route=departments');

} catch (Exception $e) {
    $db->rollBack();
    flash_set('error', 'Database Transaction Error: Failed to save department details. ' . $e->getMessage());
    redirect('index.php?route=departments-create');
}
