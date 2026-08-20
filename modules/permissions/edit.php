<?php
$page_title = 'Edit Permission';
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

// Initialize form variables with current values
$name = $perm['name'];
$description = $perm['description'] ?? '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');

        // Validation
        if (empty($name)) {
            $errors['name'] = 'Permission name is required.';
        }

        // Check if permission name already exists (excluding current permission)
        if (empty($errors)) {
            try {
                $check_stmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ? AND id != ?");
                $check_stmt->execute([$name, $perm_id]);
                if ($check_stmt->fetch()) {
                    $errors['name'] = 'A permission with this name already exists.';
                }
            } catch (PDOException $e) {
                error_log("Permission name check error: " . $e->getMessage());
                $errors[] = 'An error occurred. Please try again.';
            }
        }

        // Update permission if no errors
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Update permission
                $stmt = $pdo->prepare("
                    UPDATE permissions 
                    SET name = ?, description = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description, $perm_id]);

                log_activity($_SESSION['user_id'], 'permission_edit', "Edited permission: $name");

                $pdo->commit();

                redirect_with_flash(
                    BASE_URL . '/modules/permissions/list.php',
                    'success',
                    'Permission updated successfully.'
                );
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Permission update error: " . $e->getMessage());
                $errors[] = 'An error occurred while updating the permission. Please try again.';
            }
        }
    }
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Edit Permission</h2>
        <p class="text-muted">Edit permission details</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">

            <div class="mb-3">
                <label for="name" class="form-label">Permission Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                       id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required
                       placeholder="e.g., employees.view, attendance.manage">
                <div class="form-text">Use dot notation: module.action (e.g., employees.view)</div>
                <?php if (isset($errors['name'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['name']); ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" 
                          id="description" name="description" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
                <div class="form-text">Optional description of what this permission allows</div>
                <?php if (isset($errors['description'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['description']); ?></div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Update Permission</button>
                <a href="<?php echo BASE_URL; ?>/modules/permissions/list.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
