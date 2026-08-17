<?php
$page_title = 'Add Department';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

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
            $errors['name'] = 'Department name is required.';
        } elseif (strlen($name) < 2) {
            $errors['name'] = 'Department name must be at least 2 characters.';
        } elseif (strlen($name) > 100) {
            $errors['name'] = 'Department name must not exceed 100 characters.';
        } else {
            // Check uniqueness (case-insensitive)
            try {
                $stmt = $pdo->prepare("SELECT id FROM departments WHERE LOWER(name) = LOWER(?)");
                $stmt->execute([$name]);
                if ($stmt->fetch()) {
                    $errors['name'] = 'A department with this name already exists.';
                }
            } catch (PDOException $e) {
                error_log("Department uniqueness check error: " . $e->getMessage());
                $errors[] = 'An error occurred while validating the department name.';
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO departments (name, description, status, created_by, created_at) 
                    VALUES (?, ?, 'active', ?, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$name, $description, $_SESSION['user_id']]);
                
                // Log activity
                log_activity($_SESSION['user_id'], 'department_create', "Created department: $name");
                
                redirect_with_flash(BASE_URL . '/modules/departments/list.php', 'success', 'Department created successfully!');
            } catch (PDOException $e) {
                error_log("Department creation error: " . $e->getMessage());
                $errors[] = 'An error occurred while creating the department.';
            }
        }
    }
}


// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Add Department</h2>
        <p class="text-muted">Create a new department</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                               id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?php echo htmlspecialchars($errors['name']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($description); ?></textarea>
                        <div class="form-text">Optional description of the department's function.</div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Create Department</button>
                        <a href="<?php echo BASE_URL; ?>/modules/departments/list.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
