<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_flash(BASE_URL . '/modules/departments/list.php', 'danger', 'Invalid request method.');
}

if (!verify_csrf_token()) {
    redirect_with_flash(BASE_URL . '/modules/departments/list.php', 'danger', 'Invalid form submission.');
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$current_status = $_POST['current_status'] ?? '';

if ($id <= 0) {
    redirect_with_flash(BASE_URL . '/modules/departments/list.php', 'danger', 'Invalid department ID.');
}

try {
    // Get department info
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$id]);
    $department = $stmt->fetch();
    
    if (!$department) {
        redirect_with_flash(BASE_URL . '/modules/departments/list.php', 'danger', 'Department not found.');
    }
    
    // If trying to deactivate, check for active designations
    if ($current_status === 'active') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM designations WHERE department_id = ? AND status = 'active'");
        $stmt->execute([$id]);
        $active_designations = $stmt->fetchColumn();
        
        if ($active_designations > 0) {
            redirect_with_flash(
                BASE_URL . '/modules/departments/list.php', 
                'danger', 
                "Cannot deactivate: $active_designations active designation(s) exist under this department. Deactivate them first."
            );
        }
        
        // Also check for active employees directly in this department
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM employees e 
            JOIN users u ON e.user_id = u.id 
            WHERE e.department_id = ? 
            AND e.deleted_at IS NULL 
            AND u.status = 'active'
        ");
        $stmt->execute([$id]);
        $active_employees = $stmt->fetchColumn();
        
        if ($active_employees > 0) {
            redirect_with_flash(
                BASE_URL . '/modules/departments/list.php', 
                'danger', 
                "Cannot deactivate: $active_employees active employee(s) are assigned to this department. Reassign them first."
            );
        }
    }
    
    // Toggle status
    $new_status = toggle_status('departments', $id, $current_status);
    
    if ($new_status) {
        $action = $new_status === 'active' ? 'reactivated' : 'deactivated';
        log_activity($_SESSION['user_id'], 'department_status_change', "$action department: {$department['name']}");
        redirect_with_flash(BASE_URL . '/modules/departments/list.php', 'success', "Department {$action} successfully!");
    } else {
        redirect_with_flash(BASE_URL . '/modules/departments/list.php', 'danger', 'An error occurred while updating the department status.');
    }
} catch (PDOException $e) {
    error_log("Department status toggle error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/departments/list.php', 'danger', 'An error occurred while updating the department status.');
}
