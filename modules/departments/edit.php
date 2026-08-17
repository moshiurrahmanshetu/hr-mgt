<?php
$page_title = 'Edit Department';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get department data
try {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->execute([$id]);
    $department = $stmt->fetch();
    
    if (!$department) {
        redirect_with_flash(BASE_URL . '/modules/departments/list.php', 'danger', 'Department not found.');
    }
} catch (PDOException $e) {
    error_log("Department fetch error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/departments/list.php', 'danger', 'An error occurred while fetching the department.');
}

$name = $department['name'];
$description = $department['description'] ?? '';
$status = $department['status'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        // Validation
        if (empty($name)) {
            $errors['name'] = 'Department name is required.';
        } elseif (strlen($name) < 2) {
            $errors['name'] = 'Department name must be at least 2 characters.';
        } elseif (strlen($name) > 100) {
            $errors['name'] = 'Department name must not exceed 100 characters.';
        } else {
            // Check uniqueness (case-insensitive, excluding current record)
            try {
                $stmt = $pdo->prepare("SELECT id FROM departments WHERE LOWER(name) = LOWER(?) AND id != ?");
                $stmt->execute([$name, $id]);
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
                    UPDATE departments 
                    SET name = ?, description = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description, $status, $id]);
                
                // Log activity
                log_activity($_SESSION['user_id'], 'department_update', "Updated department: $name");
                
                redirect_with_flash(BASE_URL . '/modules/departments/list.php', 'success', 'Department updated successfully!');
            } catch (PDOException $e) {
                error_log("Department update error: " . $e->getMessage());
                $errors[] = 'An error occurred while updating the department.';
            }
        }
    }
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Edit Department</h2>
        <p class="text-muted">Update department information</p>
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
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Department</button>
                        <a href="<?php echo BASE_URL; ?>/modules/departments/list.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
