<?php
$page_title = 'Create Standalone User';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');
has_permission_or_die('users.manage');

// Initialize form variables
$name = '';
$email = '';
$password = '';
$role_id = '';
$status = 'active';
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
        $password = $_POST['password'] ?? '';
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

        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if (empty($role_id)) {
            $errors['role_id'] = 'Role is required.';
        }

        if (!in_array($status, ['active', 'inactive'])) {
            $errors['status'] = 'Invalid status.';
        }

        // Check if email already exists
        if (empty($errors)) {
            try {
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $check_stmt->execute([$email]);
                if ($check_stmt->fetch()) {
                    $errors['email'] = 'This email is already registered.';
                }
            } catch (PDOException $e) {
                error_log("Email check error: " . $e->getMessage());
                $errors[] = 'An error occurred. Please try again.';
            }
        }

        // Create user if no errors
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert user
                $stmt = $pdo->prepare("
                    INSERT INTO users (role_id, name, email, password, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$role_id, $name, $email, $hashed_password, $status]);
                $user_id = $pdo->lastInsertId();

                log_activity($_SESSION['user_id'], 'user_create', "Created standalone user: $name ($email)");

                $pdo->commit();

                redirect_with_flash(
                    BASE_URL . '/modules/users/list.php',
                    'success',
                    'User created successfully.'
                );
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("User creation error: " . $e->getMessage());
                $errors[] = 'An error occurred while creating the user. Please try again.';
            }
        }
    }
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Create Standalone User</h2>
        <p class="text-muted">Create a user account without an employee record</p>
    </div>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Note:</strong> To create an employee with full HR details (department, designation, etc.), 
    use <a href="<?php echo BASE_URL; ?>/modules/employees/create.php" class="alert-link">Employee Management</a> instead.
</div>

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
                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                       id="password" name="password" required>
                <div class="form-text">Minimum 8 characters</div>
                <?php if (isset($errors['password'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['password']); ?></div>
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
                <button type="submit" class="btn btn-primary">Create User</button>
                <a href="<?php echo BASE_URL; ?>/modules/users/list.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
