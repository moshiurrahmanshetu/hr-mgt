<?php
/**
 * Store Designation Action (Organization Module)
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
    redirect('index.php?route=designations-create');
}

$db = Database::getConnection();

// 1. CSRF Verification
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    flash_set('error', 'Security Violation: CSRF verification failed.');
    redirect('index.php?route=designations-create');
}

// 2. Extract, Trim & Sanitize Inputs
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$department_id = isset($_POST['department_id']) && $_POST['department_id'] !== '' ? (int)$_POST['department_id'] : 0;
$salary_grade = trim($_POST['salary_grade'] ?? '');
$status = $_POST['status'] ?? 'Active';

// 3. Validation Rules
if (empty($title)) {
    flash_set('error', 'Validation Error: Designation title is required.');
    redirect('index.php?route=designations-create');
}

if ($department_id <= 0) {
    flash_set('error', 'Validation Error: Please select an associated corporate department.');
    redirect('index.php?route=designations-create');
}

// 4. Duplicate Validation (Amongst active, non-deleted records)
$dup_title_stmt = $db->prepare("SELECT COUNT(*) FROM `designations` WHERE `title` = ? AND `department_id` = ? AND `deleted_at` IS NULL");
$dup_title_stmt->execute([$title, $department_id]);
if ((int)$dup_title_stmt->fetchColumn() > 0) {
    flash_set('error', "Validation Error: A job designation with title '$title' already exists within the selected department.");
    redirect('index.php?route=designations-create');
}

// 5. Foreign Key Validation
// Department Validation
$dept_stmt = $db->prepare("SELECT COUNT(*) FROM `departments` WHERE `id` = ? AND `deleted_at` IS NULL");
$dept_stmt->execute([$department_id]);
if ((int)$dept_stmt->fetchColumn() === 0) {
    flash_set('error', 'Validation Error: The selected corporate department does not exist or has been deleted.');
    redirect('index.php?route=designations-create');
}

// 6. DB Persistence & Logging
try {
    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO `designations` (`department_id`, `title`, `description`, `salary_grade`, `status`) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$department_id, $title, $description ?: null, $salary_grade ?: null, $status]);
    $desg_id = $db->lastInsertId();

    // Log Activity Entry
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $activity_payload = json_encode([
        'id' => $desg_id,
        'department_id' => $department_id,
        'title' => $title,
        'salary_grade' => $salary_grade,
        'status' => $status
    ], JSON_UNESCAPED_SLASHES);

    $sql_log = "INSERT INTO `activity_logs` (`user_id`, `action`, `table_name`, `record_id`, `ip_address`, `user_agent`, `payload`) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = $db->prepare($sql_log);
    $stmt_log->execute([
        $user_id,
        'Designation Created',
        'designations',
        $desg_id,
        $ip_address,
        $user_agent,
        $activity_payload
    ]);

    $db->commit();
    flash_set('success', "Designation '$title' has been registered successfully.");
    redirect('index.php?route=designations');

} catch (Exception $e) {
    $db->rollBack();
    flash_set('error', 'Database Transaction Error: Failed to save designation details. ' . $e->getMessage());
    redirect('index.php?route=designations-create');
}
