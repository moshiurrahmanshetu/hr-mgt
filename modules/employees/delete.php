<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_flash(BASE_URL . '/modules/employees/list.php', 'danger', 'Invalid request method.');
}

if (!verify_csrf_token()) {
    redirect_with_flash(BASE_URL . '/modules/employees/list.php', 'danger', 'Invalid form submission.');
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$action = $_POST['action'] ?? '';

if ($id <= 0) {
    redirect_with_flash(BASE_URL . '/modules/employees/list.php', 'danger', 'Invalid employee ID.');
}

try {
    // Get employee info
    $stmt = $pdo->prepare("
        SELECT e.*, u.name as user_name, u.email as user_email 
        FROM employees e 
        JOIN users u ON e.user_id = u.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $employee = $stmt->fetch();
    
    if (!$employee) {
        redirect_with_flash(BASE_URL . '/modules/employees/list.php', 'danger', 'Employee not found.');
    }
    
    if ($action === 'delete') {
        // Soft delete: set deleted_at and deactivate user account
        $pdo->beginTransaction();
        
        // Update employee record
        $emp_stmt = $pdo->prepare("UPDATE employees SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
        $emp_stmt->execute([$id]);
        
        // Deactivate user account
        $user_stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        $user_stmt->execute([$employee['user_id']]);
        
        $pdo->commit();
        
        log_activity($_SESSION['user_id'], 'employee_delete', "Soft-deleted employee: {$employee['employee_code']} - {$employee['user_name']}");
        redirect_with_flash(BASE_URL . '/modules/employees/list.php', 'success', "Employee deleted successfully!");
        
    } elseif ($action === 'reactivate') {
        // Reactivate: clear deleted_at and activate user account
        $pdo->beginTransaction();
        
        // Update employee record
        $emp_stmt = $pdo->prepare("UPDATE employees SET deleted_at = NULL WHERE id = ?");
        $emp_stmt->execute([$id]);
        
        // Activate user account
        $user_stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $user_stmt->execute([$employee['user_id']]);
        
        $pdo->commit();
        
        log_activity($_SESSION['user_id'], 'employee_reactivate', "Reactivated employee: {$employee['employee_code']} - {$employee['user_name']}");
        redirect_with_flash(BASE_URL . '/modules/employees/list.php', 'success', "Employee reactivated successfully!");
        
    } else {
        redirect_with_flash(BASE_URL . '/modules/employees/list.php', 'danger', 'Invalid action.');
    }
    
} catch (PDOException $e) {
    // Rollback transaction on any error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Employee delete/reactivate error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/employees/list.php', 'danger', 'An error occurred while updating the employee status.');
}
