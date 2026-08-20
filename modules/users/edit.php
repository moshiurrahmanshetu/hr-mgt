<?php
$page_title = 'Edit User';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');
has_permission_or_die('users.manage');

$user_id = $_GET['id'] ?? 0;

if (!$user_id) {
    redirect_with_flash(BASE_URL . '/modules/users/list.php', 'danger', 'Invalid user ID.');
}

// Get user with linked employee info
try {
    $stmt = $pdo->prepare("
        SELECT u.*, r.name as role_name, 
               e.id as employee_id, e.employee_code as employee_code, e.first_name as emp_first_name, e.last_name as emp_last_name
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        LEFT JOIN employees e ON e.user_id = u.id AND e.deleted_at IS NULL
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        redirect_with_flash(BASE_URL . '/modules/users/list.php', 'danger', 'User not found.');
    }
} catch (PDOException $e) {
    error_log("User fetch error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/users/list.php', 'danger', 'An error occurred.');
}

// Initialize form variables with current values
$name = $user['name'];
$email = $user['email'];
$role_id = $user['role_id'];
$status = $user['status'];
$errors = [];

// Get all active roles
try {
    $roles_stmt = $pdo->prepare("SELECT * FROM roles ORDER BY name ASC");
    $roles_stmt->execute();
    $roles = $roles_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Roles fetch error: " . $e->getMessage());
    $roles = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $role_id = $_POST['role_id'] ?? '';
        $status = $_POST['status'] ?? 'active';

        // Validation
        if (empty($name)) {
            $errors['name'] = 'Name is required.';
        }

        if (empty($email)) {
            $errors['email'] = 'Email is required.';
        } elseif (!is_valid_email($email)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if (empty($role_id)) {
            $errors['role_id'] = 'Role is required.';
        }

        if (!in_array($status, ['active', 'inactive'])) {
            $errors['status'] = 'Invalid status.';
        }

        // Check if email already exists (excluding current user)
        if (empty($errors)) {
            try {
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check_stmt->execute([$email, $user_id]);
                if ($check_stmt->fetch()) {
                    $errors['email'] = 'This email is already registered.';
                }
            } catch (PDOException $e) {
                error_log("Email check error: " . $e->getMessage());
                $errors[] = 'An error occurred. Please try again.';
            }
        }

        // Update user if no errors
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $old_role_name = $user['role_name'];

                // Update user
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET role_id = ?, name = ?, email = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$role_id, $name, $email, $status, $user_id]);

                $role_changed = ($old_role_name !== $role_id);

                log_activity($_SESSION['user_id'], 'user_edit', "Edited user: $name ($email)" . ($role_changed ? ' - Role changed' : ''));

                $pdo->commit();

                $flash_message = 'User updated successfully.';
                if ($role_changed) {
                    $flash_message .= ' Note: The user may need to log out and back in for role changes to take effect.';
                }

                redirect_with_flash(
                    BASE_URL . '/modules/users/list.php',
                    'success',
                    $flash_message
                );
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("User update error: " . $e->getMessage());
                $errors[] = 'An error occurred while updating the user. Please try again.';
            }
        }
    }
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Edit User</h2>
        <p class="text-muted">Edit user account details</p>
    </div>
</div>

<?php if ($user['employee_id']): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Employee-Linked Account:</strong> This user is linked to an employee record. 
    To edit HR-specific fields (department, designation, salary, etc.), 
    use <a href="<?php echo BASE_URL; ?>/modules/employees/edit.php?id=<?php echo $user['employee_id']; ?>" class="alert-link">Employee Management</a>.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">

            <div class="mb-3">
                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                       id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                <?php if (isset($errors['name'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['name']); ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                       id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                <?php if (isset($errors['email'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="role_id" class="form-label">Role <span class="text-danger">*</span></label>
                <select class="form-select <?php echo isset($errors['role_id']) ? 'is-invalid' : ''; ?>" 
                        id="role_id" name="role_id" required>
                    <option value="">Select a role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>" <?php echo $role_id == $role['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role['name']); ?>
                            <?php if ($role['description']): ?>
                                - <?php echo htmlspecialchars($role['description']); ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Role changes take effect on next login</div>
                <?php if (isset($errors['role_id'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['role_id']); ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select <?php echo isset($errors['status']) ? 'is-invalid' : ''; ?>" 
                        id="status" name="status">
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                <?php if (isset($errors['status'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['status']); ?></div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Update User</button>
                <a href="<?php echo BASE_URL; ?>/modules/users/list.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
