<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');
has_permission_or_die('roles.manage');

$perm_id = $_GET['id'] ?? 0;

if (!$perm_id) {
    redirect_with_flash(BASE_URL . '/modules/permissions/list.php', 'danger', 'Invalid permission ID.');
}

// Get permission info
try {
    $stmt = $pdo->prepare("SELECT * FROM permissions WHERE id = ?");
    $stmt->execute([$perm_id]);
    $perm = $stmt->fetch();

    if (!$perm) {
        redirect_with_flash(BASE_URL . '/modules/permissions/list.php', 'danger', 'Permission not found.');
    }
} catch (PDOException $e) {
    error_log("Permission fetch error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/permissions/list.php', 'danger', 'An error occurred.');
}

// Check if any roles have this permission
try {
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE permission_id = ?");
    $check_stmt->execute([$perm_id]);
    $role_count = $check_stmt->fetchColumn();

    if ($role_count > 0) {
        redirect_with_flash(BASE_URL . '/modules/permissions/list.php', 'danger', "Cannot delete: {$role_count} role(s) have this permission assigned.");
    }
} catch (PDOException $e) {
    error_log("Role count check error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/permissions/list.php', 'danger', 'An error occurred.');
}

// Handle delete confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        redirect_with_flash(BASE_URL . '/modules/permissions/list.php', 'danger', 'Invalid form submission. Please try again.');
    }

    try {
        $pdo->beginTransaction();

        // Delete permission
        $stmt = $pdo->prepare("DELETE FROM permissions WHERE id = ?");
        $stmt->execute([$perm_id]);

        log_activity($_SESSION['user_id'], 'permission_delete', "Deleted permission: {$perm['name']}");

        $pdo->commit();

        redirect_with_flash(
            BASE_URL . '/modules/permissions/list.php',
            'success',
            'Permission deleted successfully.'
        );
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Permission deletion error: " . $e->getMessage());
        redirect_with_flash(BASE_URL . '/modules/permissions/list.php', 'danger', 'An error occurred while deleting the permission.');
    }
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Delete Permission</h2>
        <p class="text-muted">Confirm permission deletion</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Warning:</strong> You are about to delete the permission <strong><?php echo htmlspecialchars($perm['name']); ?></strong>.
            This action cannot be undone.
        </div>

        <p><strong>Permission Name:</strong> <code><?php echo htmlspecialchars($perm['name']); ?></code></p>
        <?php if ($perm['description']): ?>
            <p><strong>Description:</strong> <?php echo htmlspecialchars($perm['description']); ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger">Delete Permission</button>
                <a href="<?php echo BASE_URL; ?>/modules/permissions/list.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
