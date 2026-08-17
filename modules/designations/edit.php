<?php
$page_title = 'Edit Designation';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_role('admin');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get designation data
try {
    $stmt = $pdo->prepare("
        SELECT d.*, dept.name as department_name 
        FROM designations d 
        JOIN departments dept ON d.department_id = dept.id 
        WHERE d.id = ?
    ");
    $stmt->execute([$id]);
    $designation = $stmt->fetch();
    
    if (!$designation) {
        redirect_with_flash(BASE_URL . '/modules/designations/list.php', 'danger', 'Designation not found.');
    }
} catch (PDOException $e) {
    error_log("Designation fetch error: " . $e->getMessage());
    redirect_with_flash(BASE_URL . '/modules/designations/list.php', 'danger', 'An error occurred while fetching the designation.');
}

$department_id = $designation['department_id'];
$title = $designation['title'];
$description = $designation['description'] ?? '';
$status = $designation['status'];
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
        $status = $_POST['status'] ?? 'active';
        
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
            // Check uniqueness within department (case-insensitive, excluding current record)
            try {
                $stmt = $pdo->prepare("SELECT id FROM designations WHERE department_id = ? AND LOWER(title) = LOWER(?) AND id != ?");
                $stmt->execute([$department_id, $title, $id]);
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
                    UPDATE designations 
                    SET department_id = ?, title = ?, description = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$department_id, $title, $description, $status, $id]);
                
                // Get department name for logging
                $dept_stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
                $dept_stmt->execute([$department_id]);
                $dept_name = $dept_stmt->fetchColumn();
                
                // Log activity
                log_activity($_SESSION['user_id'], 'designation_update', "Updated designation: $title in $dept_name");
                
                redirect_with_flash(BASE_URL . '/modules/designations/list.php', 'success', 'Designation updated successfully!');
            } catch (PDOException $e) {
                error_log("Designation update error: " . $e->getMessage());
                $errors[] = 'An error occurred while updating the designation.';
            }
        }
    }
}

// Only include templates after all redirect logic is done
require_once __DIR__ . '/../../templates/header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h2 class="fw-bold">Edit Designation</h2>
        <p class="text-muted">Update designation information</p>
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
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update Designation</button>
                        <a href="<?php echo BASE_URL; ?>/modules/designations/list.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/footer.php'; ?>
