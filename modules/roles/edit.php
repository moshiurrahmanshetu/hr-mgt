<?php
$page_title = 'Edit Role';
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

// Initialize form variables with current values
$name = $role['name'];
$description = $role['description'] ?? '';
$errors = [];

// Get all permissions grouped by module
try {
    $perm_stmt = $pdo->prepare("SELECT * FROM permissions ORDER BY name ASC");
    $perm_stmt->execute();
    $all_permissions = $perm_stmt->fetchAll();
    
    // Group permissions by module prefix
    $grouped_permissions = [];
    foreach ($all_permissions as $perm) {
        $parts = explode('.', $perm['name']);
        $module = $parts[0];
        if (!isset($grouped_permissions[$module])) {
            $grouped_permissions[$module] = [];
        }
        $grouped_permissions[$module][] = $perm;
    }
} catch (PDOException $e) {
    error_log("Permissions fetch error: " . $e->getMessage());
    $grouped_permissions = [];
}

// Get current role permissions
try {
    $role_perm_stmt = $pdo->prepare("
        SELECT permission_id 
        FROM role_permissions 
        WHERE role_id = ?
    ");
    $role_perm_stmt->execute([$role_id]);
    $current_permission_ids = array_column($role_perm_stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_id');
} catch (PDOException $e) {
    error_log("Role permissions fetch error: " . $e->getMessage());
    $current_permission_ids = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $permission_ids = $_POST['permissions'] ?? [];

        // Validation
        if (empty($name)) {
            $errors['name'] = 'Role name is required.';
        }

        // Check if role name already exists (excluding current role)
        if (empty($errors)) {
            try {
                $check_stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ? AND id != ?");
                $check_stmt->execute([$name, $role_id]);
                if ($check_stmt->fetch()) {
                    $errors['name'] = 'A role with this name already exists.';
                }
            } catch (PDOException $e) {
                error_log("Role name check error: " . $e->getMessage());
                $errors[] = 'An error occurred. Please try again.';
            }
        }

        // Update role and permissions if no errors
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Update role (only if not a system role)
                if (!$role['is_system_role']) {
                    $stmt = $pdo->prepare("
                        UPDATE roles 
                        SET name = ?, description = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $description, $role_id]);
                }

                // Update role permissions (delete existing, insert new)
                $delete_stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $delete_stmt->execute([$role_id]);

                if (!empty($permission_ids)) {
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO role_permissions (role_id, permission_id, created_at) 
                        VALUES (?, ?, CURRENT_TIMESTAMP)
                    ");
                    foreach ($permission_ids as $perm_id) {
                        $insert_stmt->execute([$role_id, $perm_id]);
                    }
                }

                log_activity($_SESSION['user_id'], 'role_edit', "Updated role: $name" . ($role['is_system_role'] ? ' (permissions only)' : ''));

                $pdo->commit();

                redirect_with_flash(
                    BASE_URL . '/modules/roles/list.php',
                    'success',
                    'Role updated successfully. Note: Users with this role may need to log out and back in for changes to take effect.'
                );
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Role update error: " . $e->getMessage());
                $errors[] = 'An error occurred while updating the role. Please try again.';
            }
        }
    }
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Edit Role</h2>
        <p class="text-muted">Edit role details and assign permissions</p>
    </div>
</div>

<?php if ($role['is_system_role']): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>System Role:</strong> This is a core system role (<?php echo htmlspecialchars($role['name']); ?>). 
    Its name cannot be changed, but you can modify its permissions. System roles cannot be deleted.
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">

            <div class="mb-3">
                <label for="name" class="form-label">Role Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                       id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" 
                       <?php echo $role['is_system_role'] ? 'disabled' : 'required'; ?>>
                <?php if (isset($errors['name'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['name']); ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" 
                          id="description" name="description" rows="3" 
                          <?php echo $role['is_system_role'] ? 'disabled' : ''; ?>><?php echo htmlspecialchars($description); ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['description']); ?></div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title mb-3">Permissions</h5>
        <p class="text-muted mb-4">Select the permissions this role should have</p>

        <?php foreach ($grouped_permissions as $module => $permissions): ?>
            <div class="mb-4">
                <h6 class="text-uppercase text-muted small mb-3"><?php echo htmlspecialchars($module); ?></h6>
                <div class="row">
                    <?php foreach ($permissions as $perm): ?>
                        <div class="col-md-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       name="permissions[]" 
                                       value="<?php echo $perm['id']; ?>"
                                       id="perm_<?php echo $perm['id']; ?>"
                                       <?php echo in_array($perm['id'], $current_permission_ids) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="perm_<?php echo $perm['id']; ?>">
                                    <?php echo htmlspecialchars($perm['name']); ?>
                                    <?php if ($perm['description']): ?>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($perm['description']); ?></small>
                                    <?php endif; ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" form="roleForm" class="btn btn-primary">Save Changes</button>
            <a href="<?php echo BASE_URL; ?>/modules/roles/list.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</div>

<form method="POST" action="" id="roleForm">
    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
    <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
    <input type="hidden" name="description" value="<?php echo htmlspecialchars($description); ?>">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sync permission checkboxes to the main form
    document.querySelectorAll('input[name="permissions[]"]').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const mainForm = document.getElementById('roleForm');
            const existingInput = mainForm.querySelector('input[name="permissions[]"][value="' + this.value + '"]');
            
            if (this.checked) {
                if (!existingInput) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'permissions[]';
                    input.value = this.value;
                    mainForm.appendChild(input);
                }
            } else {
                if (existingInput) {
                    existingInput.remove();
                }
            }
        });
        
        // Initialize checked state
        if (checkbox.checked) {
            const mainForm = document.getElementById('roleForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'permissions[]';
            input.value = checkbox.value;
            mainForm.appendChild(input);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
