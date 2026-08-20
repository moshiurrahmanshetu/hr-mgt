<?php
$page_title = 'Role Management';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');
has_permission_or_die('roles.manage');

// Get all roles with user counts
try {
    $stmt = $pdo->prepare("
        SELECT r.*, COUNT(u.id) as user_count 
        FROM roles r 
        LEFT JOIN users u ON u.role_id = r.id 
        GROUP BY r.id 
        ORDER BY r.is_system_role DESC, r.name ASC
    ");
    $stmt->execute();
    $roles = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Roles fetch error: " . $e->getMessage());
    $roles = [];
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Role Management</h2>
        <p class="text-muted">Manage system roles and their permissions</p>
    </div>
    <div class="col-auto">
        <a href="<?php echo BASE_URL; ?>/modules/roles/create.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>
            Create Role
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>Users</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($roles)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">No roles found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($roles as $role): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($role['name']); ?></div>
                                </td>
                                <td>
                                    <?php if ($role['description']): ?>
                                        <?php echo htmlspecialchars($role['description']); ?>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">No description</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $role['user_count']; ?></span>
                                </td>
                                <td>
                                    <?php if ($role['is_system_role']): ?>
                                        <span class="badge bg-primary">System</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark">Custom</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo BASE_URL; ?>/modules/roles/edit.php?id=<?php echo $role['id']; ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if (!$role['is_system_role']): ?>
                                            <a href="<?php echo BASE_URL; ?>/modules/roles/delete.php?id=<?php echo $role['id']; ?>" class="btn btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
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
