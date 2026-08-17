<?php
$page_title = 'Add Designation';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

$department_id = '';
$title = '';
$description = '';
$errors = [];

// Get active departments for dropdown
try {
    $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name ASC");
    $stmt->execute();
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Departments fetch error: " . $e->getMessage());
    $departments = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $department_id = intval($_POST['department_id'] ?? 0);
        $title = sanitize_input($_POST['title'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        
        // Validation
        if ($department_id <= 0) {
            $errors['department_id'] = 'Please select a department.';
        }
        
        if (empty($title)) {
            $errors['title'] = 'Designation title is required.';
        } elseif (strlen($title) < 2) {
            $errors['title'] = 'Designation title must be at least 2 characters.';
        } elseif (strlen($title) > 100) {
            $errors['title'] = 'Designation title must not exceed 100 characters.';
        } else {
            // Check uniqueness within department (case-insensitive)
            try {
                $stmt = $pdo->prepare("SELECT id FROM designations WHERE department_id = ? AND LOWER(title) = LOWER(?)");
                $stmt->execute([$department_id, $title]);
                if ($stmt->fetch()) {
                    $errors['title'] = 'A designation with this title already exists in this department.';
                }
            } catch (PDOException $e) {
                error_log("Designation uniqueness check error: " . $e->getMessage());
                $errors[] = 'An error occurred while validating the designation title.';
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO designations (department_id, title, description, status, created_by, created_at) 
                    VALUES (?, ?, ?, 'active', ?, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$department_id, $title, $description, $_SESSION['user_id']]);
                
                // Get department name for logging
                $dept_stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
                $dept_stmt->execute([$department_id]);
                $dept_name = $dept_stmt->fetchColumn();
                
                // Log activity
                log_activity($_SESSION['user_id'], 'designation_create', "Created designation: $title in $dept_name");
                
                redirect_with_flash(BASE_URL . '/modules/designations/list.php', 'success', 'Designation created successfully!');
            } catch (PDOException $e) {
                error_log("Designation creation error: " . $e->getMessage());
                $errors[] = 'An error occurred while creating the designation.';
            }
        }
    }
}


// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Add Designation</h2>
        <p class="text-muted">Create a new job designation</p>
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
                
                <?php if (empty($departments)): ?>
                    <div class="alert alert-warning">
                        No active departments available. Please create a department first.
                        <a href="<?php echo BASE_URL; ?>/modules/departments/create.php" class="alert-link">Create Department</a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo get_csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                            <select class="form-select <?php echo isset($errors['department_id']) ? 'is-invalid' : ''; ?>" 
                                    id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $department_id == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['department_id'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['department_id']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Designation Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" 
                                   id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                            <?php if (isset($errors['title'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['title']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($description); ?></textarea>
                            <div class="form-text">Optional description of the designation's responsibilities.</div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Create Designation</button>
                            <a href="<?php echo BASE_URL; ?>/modules/designations/list.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
