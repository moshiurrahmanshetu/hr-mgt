<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');
has_permission_or_die('users.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_flash(BASE_URL . '/modules/users/list.php', 'danger', 'Invalid request method.');
}

if (!verify_csrf_token()) {
    redirect_with_flash(BASE_URL . '/modules/users/list.php', 'danger', 'Invalid form submission. Please try again.');
}

$user_id = $_POST['user_id'] ?? 0;

if (!$user_id) {
    redirect_with_flash(BASE_URL . '/modules/users/list.php', 'danger', 'Invalid user ID.');
}

try {
    $pdo->beginTransaction();

    // Get user info and check if employee-linked
    $stmt = $pdo->prepare("
        SELECT u.*, e.id as employee_id 
        FROM users u 
        LEFT JOIN employees e ON e.user_id = u.id AND e.deleted_at IS NULL
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        $pdo->rollBack();
        redirect_with_flash(BASE_URL . '/modules/users/list.php', 'danger', 'User not found.');
    }

    // Prevent deactivating yourself
    if ($user_id == $_SESSION['user_id']) {
        $pdo->rollBack();
        redirect_with_flash(BASE_URL . '/modules/users/list.php', 'danger', 'You cannot deactivate your own account.');
    }

    // Toggle user status
    $new_status = $user['status'] === 'active' ? 'inactive' : 'active';
    $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$new_status, $user_id]);

    // If user is employee-linked, sync with employee soft-delete status
    if ($user['employee_id']) {
        if ($new_status === 'inactive') {
            // Soft-delete the employee
            $emp_stmt = $pdo->prepare("UPDATE employees SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $emp_stmt->execute([$user['employee_id']]);
        } else {
            // Restore the employee
            $emp_stmt = $pdo->prepare("UPDATE employees SET deleted_at = NULL WHERE id = ?");
            $emp_stmt->execute([$user['employee_id']]);
        }
    }

    log_activity($_SESSION['user_id'], 'user_toggle_status', "User status changed to {$new_status} for: {$user['name']} ({$user['email']})");

    $pdo->commit();

    redirect_with_flash(
        BASE_URL . '/modules/users/list.php',
        'success',
        "User status changed to {$new_status} successfully."
    );
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("User status toggle error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/users/list.php', 'danger', 'An error occurred while updating user status.');
}
