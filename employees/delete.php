<?php
/**
 * Delete Employee Controller (Employee Management Module)
 * Developed by Senior PHP Software Architect
 * 
 * Implements SECURE SOFT DELETION ONLY.
 * Never permanently deletes employee records from physical tables, keeping transaction logs intact.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/url_helper.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../includes/flash.php';

// Auth Guard: Admins and HR Managers only
AuthMiddleware::requireRoles(['Admin', 'HR Manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_set('error', 'Invalid request method. Soft-deletions must be sent via POST.');
    redirect('employees/index.php');
}

$db = Database::getConnection();

// 1. CSRF Token Verification
$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    flash_set('error', 'Security Violation: CSRF verification failed. Please try again.');
    redirect('employees/index.php');
}

// 2. Validate and retrieve ID
$id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$POST['id'] : 0;
if ($id <= 0) {
    flash_set('error', 'Soft-Deletion Error: Missing or invalid Employee ID parameter.');
    redirect('employees/index.php');
}

// Check if employee exists and is not already terminated
$stmt_check = $db->prepare("SELECT * FROM `employees` WHERE `id` = ?");
$stmt_check->execute([$id]);
$employee = $stmt_check->fetch();

if (!$employee) {
    flash_set('error', 'Soft-Deletion Error: Employee record not found.');
    redirect('employees/index.php');
}

if ($employee['employment_status'] === 'Terminated') {
    flash_set('error', 'Soft-Deletion Error: This employee is already terminated.');
    redirect('employees/index.php');
}

// 3. Perform Soft-Deletion & Log Activity in transaction
try {
    $db->beginTransaction();

    // Soft delete by updating employment status to 'Terminated'
    $stmt_del = $db->prepare("UPDATE `employees` SET `employment_status` = 'Terminated', `updated_at` = CURRENT_TIMESTAMP WHERE `id` = ?");
    $stmt_del->execute([$id]);

    // Automatically Create Activity Log Entry (Required)
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $activity_payload = json_encode([
        'employee_code' => $employee['employee_code'],
        'full_name' => $employee['first_name'] . ' ' . $employee['last_name'],
        'department_id' => $employee['department_id'],
        'designation_id' => $employee['designation_id'],
        'action_details' => 'Soft deleted / terminated employee record'
    ], JSON_UNESCAPED_SLASHES);

    $sql_log = "INSERT INTO `activity_logs` (`user_id`, `action`, `table_name`, `record_id`, `ip_address`, `user_agent`, `payload`) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_log = $db->prepare($sql_log);
    $stmt_log->execute([
        $user_id,
        'Employee Deleted',
        'employees',
        $id,
        $ip_address,
        $user_agent,
        $activity_payload
    ]);

    $db->commit();

    flash_set('success', "Personnel profile '" . $employee['first_name'] . " " . $employee['last_name'] . "' has been soft-deleted/terminated. biographical logs preserved.");
    redirect('employees/index.php');

} catch (Exception $e) {
    $db->rollBack();
    flash_set('error', 'Critical Transaction Error: Failed to commit soft-deletion. ' . $e->getMessage());
    redirect('employees/index.php');
}
