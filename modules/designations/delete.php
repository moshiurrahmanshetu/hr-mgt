<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_flash(BASE_URL . '/modules/designations/list.php', 'danger', 'Invalid request method.');
}

if (!verify_csrf_token()) {
    redirect_with_flash(BASE_URL . '/modules/designations/list.php', 'danger', 'Invalid form submission.');
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$current_status = $_POST['current_status'] ?? '';

if ($id <= 0) {
    redirect_with_flash(BASE_URL . '/modules/designations/list.php', 'danger', 'Invalid designation ID.');
}

try {
    // Get designation info
    $stmt = $pdo->prepare("
        SELECT d.*, dept.name as department_name 
        FROM designations d 
        JOIN departments dept ON d.department_id = dept.id 
        WHERE d.id = ?
    ");
    $stmt->execute([$id]);
    $designation = $stmt->fetch();
    
    if (!$designation) {
        redirect_with_flash(BASE_URL . '/modules/designations/list.php', 'danger', 'Designation not found.');
    }
    
    // Check for active employees using this designation
    if ($current_status === 'active') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM employees e 
            JOIN users u ON e.user_id = u.id 
            WHERE e.designation_id = ? 
            AND e.deleted_at IS NULL 
            AND u.status = 'active'
        ");
        $stmt->execute([$id]);
        $active_employees = $stmt->fetchColumn();
        
        if ($active_employees > 0) {
            redirect_with_flash(
                BASE_URL . '/modules/designations/list.php', 
                'danger', 
                "Cannot deactivate: $active_employees active employee(s) are assigned to this designation. Reassign them first."
            );
        }
    }
    
    // Toggle status
    $new_status = toggle_status('designations', $id, $current_status);
    
    if ($new_status) {
        $action = $new_status === 'active' ? 'reactivated' : 'deactivated';
        log_activity($_SESSION['user_id'], 'designation_status_change', "$action designation: {$designation['title']} in {$designation['department_name']}");
        redirect_with_flash(BASE_URL . '/modules/designations/list.php', 'success', "Designation {$action} successfully!");
    } else {
        redirect_with_flash(BASE_URL . '/modules/designations/list.php', 'danger', 'An error occurred while updating the designation status.');
    }
} catch (PDOException $e) {
    error_log("Designation status toggle error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/designations/list.php', 'danger', 'An error occurred while updating the designation status.');
}
