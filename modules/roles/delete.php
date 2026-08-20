<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');
has_permission_or_die('roles.manage');

$role_id = $_GET['id'] ?? 0;

if (!$role_id) {
    redirect_with_flash(BASE_URL . '/modules/roles/list.php', 'danger', 'Invalid role ID.');
}

// Get role info
try {
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch();

    if (!$role) {
        redirect_with_flash(BASE_URL . '/modules/roles/list.php', 'danger', 'Role not found.');
    }
} catch (PDOException $e) {
    error_log("Role fetch error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/roles/list.php', 'danger', 'An error occurred.');
}

// Prevent deletion of system roles
if ($role['is_system_role']) {
    redirect_with_flash(BASE_URL . '/modules/roles/list.php', 'danger', 'Cannot delete system roles.');
}

// Check if any users have this role
try {
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
    $check_stmt->execute([$role_id]);
    $user_count = $check_stmt->fetchColumn();

    if ($user_count > 0) {
        redirect_with_flash(BASE_URL . '/modules/roles/list.php', 'danger', "Cannot delete: {$user_count} user(s) have this role.");
    }
} catch (PDOException $e) {
    error_log("User count check error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/roles/list.php', 'danger', 'An error occurred.');
}

// Handle delete confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        redirect_with_flash(BASE_URL . '/modules/roles/list.php', 'danger', 'Invalid form submission. Please try again.');
    }

    try {
        $pdo->beginTransaction();

        // Delete role (this will cascade to role_permissions via FK)
        $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$role_id]);

        log_activity($_SESSION['user_id'], 'role_delete', "Deleted role: {$role['name']}");

        $pdo->commit();

        redirect_with_flash(
            BASE_URL . '/modules/roles/list.php',
            'success',
            'Role deleted successfully.'
        );
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Role deletion error: " . $e->getMessage());
        redirect_with_flash(BASE_URL . '/modules/roles/list.php', 'danger', 'An error occurred while deleting the role.');
    }
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Delete Role</h2>
        <p class="text-muted">Confirm role deletion</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Warning:</strong> You are about to delete the role <strong><?php echo htmlspecialchars($role['name']); ?></strong>.
            This action cannot be undone.
        </div>

        <p><strong>Role Name:</strong> <?php echo htmlspecialchars($role['name']); ?></p>
        <?php if ($role['description']): ?>
            <p><strong>Description:</strong> <?php echo htmlspecialchars($role['description']); ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger">Delete Role</button>
                <a href="<?php echo BASE_URL; ?>/modules/roles/list.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
