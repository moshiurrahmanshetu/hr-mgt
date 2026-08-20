<?php
$page_title = 'Leave Type Status';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_flash(BASE_URL . '/modules/leave-types/list.php', 'danger', 'Invalid request method.');
}

if (!verify_csrf_token()) {
    redirect_with_flash(BASE_URL . '/modules/leave-types/list.php', 'danger', 'Invalid form submission.');
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$action = $_POST['action'] ?? '';

if (!in_array($action, ['deactivate', 'reactivate'])) {
    redirect_with_flash(BASE_URL . '/modules/leave-types/list.php', 'danger', 'Invalid action.');
}

// Get leave type
try {
    $stmt = $pdo->prepare("SELECT * FROM leave_types WHERE id = ?");
    $stmt->execute([$id]);
    $leave_type = $stmt->fetch();
    
    if (!$leave_type) {
        redirect_with_flash(BASE_URL . '/modules/leave-types/list.php', 'danger', 'Leave type not found.');
    }
} catch (PDOException $e) {
    error_log("Leave type fetch error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/leave-types/list.php', 'danger', 'An error occurred.');
}

// Handle deactivation - check for pending requests
if ($action === 'deactivate') {
    try {
        // Check for pending leave requests using this type
        $check_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM leave_requests 
            WHERE leave_type_id = ? AND status = 'pending'
        ");
        $check_stmt->execute([$id]);
        $pending_count = $check_stmt->fetchColumn();
        
        if ($pending_count > 0) {
            redirect_with_flash(
                BASE_URL . '/modules/leave-types/list.php', 
                'danger', 
                "Cannot deactivate this leave type. There are $pending_count pending leave request(s) using it. Process or cancel them first."
            );
        }
        
        // Safe to deactivate
        $update_stmt = $pdo->prepare("
            UPDATE leave_types 
            SET status = 'inactive', updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $update_stmt->execute([$id]);
        
        log_activity($_SESSION['user_id'], 'leave_type_deactivate', "Deactivated leave type: {$leave_type['name']}");
        
        redirect_with_flash(BASE_URL . '/modules/leave-types/list.php', 'success', 'Leave type deactivated successfully.');
        
    } catch (PDOException $e) {
        error_log("Leave type deactivation error: " . $e->getMessage());
        redirect_with_flash(BASE_URL . '/modules/leave-types/list.php', 'danger', 'An error occurred while deactivating the leave type.');
    }
}

// Handle reactivation
if ($action === 'reactivate') {
    try {
        $update_stmt = $pdo->prepare("
            UPDATE leave_types 
            SET status = 'active', updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $update_stmt->execute([$id]);
        
        log_activity($_SESSION['user_id'], 'leave_type_reactivate', "Reactivated leave type: {$leave_type['name']}");
        
        redirect_with_flash(BASE_URL . '/modules/leave-types/list.php', 'success', 'Leave type reactivated successfully.');
        
    } catch (PDOException $e) {
        error_log("Leave type reactivation error: " . $e->getMessage());
        redirect_with_flash(BASE_URL . '/modules/leave-types/list.php', 'danger', 'An error occurred while reactivating the leave type.');
    }
}
