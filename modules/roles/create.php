<?php
$page_title = 'Create Role';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');
has_permission_or_die('roles.manage');

// Initialize form variables
$name = '';
$description = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');

        // Validation
        if (empty($name)) {
            $errors['name'] = 'Role name is required.';
        }

        // Check if role name already exists
        if (empty($errors)) {
            try {
                $check_stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
                $check_stmt->execute([$name]);
                if ($check_stmt->fetch()) {
                    $errors['name'] = 'A role with this name already exists.';
                }
            } catch (PDOException $e) {
                error_log("Role name check error: " . $e->getMessage());
                $errors[] = 'An error occurred. Please try again.';
            }
        }

        // Create role if no errors
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Insert role (is_system_role defaults to FALSE for new roles)
                $stmt = $pdo->prepare("
                    INSERT INTO roles (name, description, is_system_role, created_at) 
                    VALUES (?, ?, FALSE, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$name, $description]);
                $role_id = $pdo->lastInsertId();

                log_activity($_SESSION['user_id'], 'role_create', "Created role: $name");

                $pdo->commit();

                redirect_with_flash(
                    BASE_URL . '/modules/roles/list.php',
                    'success',
                    'Role created successfully. You can now assign permissions to it.'
                );
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Role creation error: " . $e->getMessage());
                $errors[] = 'An error occurred while creating the role. Please try again.';
            }
        }
    }
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Create Role</h2>
        <p class="text-muted">Create a new custom role</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">

            <div class="mb-3">
                <label for="name" class="form-label">Role Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                       id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                <div class="form-text">Use a descriptive name like "HR Assistant" or "Manager"</div>
                <?php if (isset($errors['name'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['name']); ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" 
                          id="description" name="description" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
                <div class="form-text">Optional description of this role's purpose</div>
                <?php if (isset($errors['description'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['description']); ?></div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Create Role</button>
                <a href="<?php echo BASE_URL; ?>/modules/roles/list.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
