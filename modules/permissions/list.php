<?php
$page_title = 'Permission Management';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');
has_permission_or_die('roles.manage');

// Get all permissions
try {
    $stmt = $pdo->prepare("SELECT * FROM permissions ORDER BY name ASC");
    $stmt->execute();
    $permissions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Permissions fetch error: " . $e->getMessage());
    $permissions = [];
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Permission Management</h2>
        <p class="text-muted">Manage system permissions</p>
    </div>
    <div class="col-auto">
        <a href="<?php echo BASE_URL; ?>/modules/permissions/create.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>
            Create Permission
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Permission Name</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($permissions)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-4">No permissions found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($permissions as $perm): ?>
                            <tr>
                                <td>
                                    <code><?php echo htmlspecialchars($perm['name']); ?></code>
                                </td>
                                <td>
                                    <?php if ($perm['description']): ?>
                                        <?php echo htmlspecialchars($perm['description']); ?>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">No description</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo BASE_URL; ?>/modules/permissions/edit.php?id=<?php echo $perm['id']; ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>/modules/permissions/delete.php?id=<?php echo $perm['id']; ?>" class="btn btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
