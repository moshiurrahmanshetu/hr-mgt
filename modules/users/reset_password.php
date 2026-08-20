<?php
$page_title = 'Reset User Password';
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

// Get user info
try {
    $stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        redirect_with_flash(BASE_URL . '/modules/users/list.php', 'danger', 'User not found.');
    }
} catch (PDOException $e) {
    error_log("User fetch error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/users/list.php', 'danger', 'An error occurred.');
}

// Initialize form variables
$password = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';

        // Validation
        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        // Update password if no errors
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Update user password
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);

                log_activity($_SESSION['user_id'], 'password_reset', "Reset password for user: {$user['name']} ({$user['email']})");

                $pdo->commit();

                redirect_with_flash(
                    BASE_URL . '/modules/users/list.php',
                    'success',
                    'Password reset successfully.'
                );
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Password reset error: " . $e->getMessage());
                $errors[] = 'An error occurred while resetting the password. Please try again.';
            }
        }
    }
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Reset User Password</h2>
        <p class="text-muted">Reset password for: <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">

            <div class="mb-3">
                <label for="password" class="form-label">New Password <span class="text-danger">*</span></label>
                <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                       id="password" name="password" required minlength="8">
                <div class="form-text">Minimum 8 characters</div>
                <?php if (isset($errors['password'])): ?>
                    <div class="invalid-feedback"><?php echo htmlspecialchars($errors['password']); ?></div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Reset Password</button>
                <a href="<?php echo BASE_URL; ?>/modules/users/list.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
